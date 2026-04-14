# 01-04 — Token Revocation Completa — RFC 7009

[x] DONE

**Prioridad:** P0  
**Spec:** RFC 7009  
**Esfuerzo estimado:** Bajo-Medio (2-3 días)  
**Dependencias previas:** columna `jti` en `_oauth_session` (ver 01-02)  
**Habilita:** 01-05 Logout Distribuido, 04-01 Sesiones Activas

---

## Contexto

El endpoint `POST /openid/{tenant}/revoke` existe en
`src/Features/Oidc/Authentication/Infrastructure/Driver/Rest/` pero la implementación
es incompleta: no invalida en cascada los tokens asociados al refresh token revocado,
y no existe una tabla de JTIs revocados para bloquear tokens aún dentro del período
de validez (el JWT no ha expirado pero fue revocado).

---

## Qué implementar

### Endpoint (ya registrado, completar lógica)

```
POST /openid/{tenant}/revoke
Authorization: Basic base64(client_id:client_secret)
Content-Type: application/x-www-form-urlencoded

token=<token_value>&token_type_hint=refresh_token|access_token
```

RFC 7009 requiere:
- Respuesta siempre `200 OK` (incluso si el token no existe)
- No revelar si el token existía o no
- Solo el cliente que emitió el token puede revocarlo

### Comportamiento completo

1. **Revocar access_token:** Insertar `jti` en `_oauth_revoked_jti`
2. **Revocar refresh_token:**
   - Revocar el propio refresh token
   - Revocar todos los access tokens emitidos con ese refresh (cascada de JTIs)
   - Marcar la sesión como cerrada en `_oauth_session`

---

## Dónde y cómo hacer los cambios

### A. Migración — tabla _oauth_revoked_jti

*(Compartida con 01-02 — crear solo si no existe)*

**Archivo nuevo:** `migrations/mysql/schema/YYYYMMDDHHMMSS_CreateRevokedJti.php`

```sql
CREATE TABLE IF NOT EXISTS _oauth_revoked_jti (
  jti        VARCHAR(36)  NOT NULL,
  tenant_id  VARCHAR(36)  NOT NULL,
  token_type ENUM('access','refresh') NOT NULL DEFAULT 'access',
  revoked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,   -- para cleanup job
  PRIMARY KEY (jti),
  INDEX idx_revoked_tenant  (tenant_id),
  INDEX idx_revoked_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Añadir jti y refresh_jti a la tabla de sesión si no existen
ALTER TABLE _oauth_session
  ADD COLUMN IF NOT EXISTS jti         VARCHAR(36) NULL,
  ADD COLUMN IF NOT EXISTS refresh_jti VARCHAR(36) NULL,
  ADD COLUMN IF NOT EXISTS revoked_at  DATETIME    NULL,
  ADD INDEX  IF NOT EXISTS idx_session_jti         (jti),
  ADD INDEX  IF NOT EXISTS idx_session_refresh_jti (refresh_jti);
```

### B. Dominio — Gateway de revocación

**Archivo nuevo:** `src/Features/Oidc/Authentication/Domain/Gateway/TokenRevocationGateway.php`

```php
interface TokenRevocationGateway
{
    public function revokeByJti(string $jti, string $tenant, string $type, \DateTimeImmutable $expiresAt): void;
    public function revokeSessionByRefreshJti(string $refreshJti, string $tenant): void;
    /** @return string[] lista de JTIs de access tokens asociados al refresh */
    public function findAccessJtisByRefreshJti(string $refreshJti, string $tenant): array;
    public function isRevoked(string $jti, string $tenant): bool;
}
```

### C. Infrastructure — TokenRevocationSqlAdapter

**Archivo nuevo:** `src/Features/Oidc/Authentication/Infrastructure/Driven/TokenRevocationSqlAdapter.php`

Implementa `TokenRevocationGateway`:

```php
public function revokeByJti(string $jti, string $tenant, string $type, \DateTimeImmutable $expiresAt): void
{
    $this->pdo->execute(
        'INSERT IGNORE INTO _oauth_revoked_jti (jti, tenant_id, token_type, expires_at) VALUES (?, ?, ?, ?)',
        [$jti, $tenant, $type, $expiresAt->format('Y-m-d H:i:s')]
    );
}

public function revokeSessionByRefreshJti(string $refreshJti, string $tenant): void
{
    $this->pdo->execute(
        'UPDATE _oauth_session SET revoked_at = NOW() WHERE refresh_jti = ? AND tenant_id = ?',
        [$refreshJti, $tenant]
    );
}

public function findAccessJtisByRefreshJti(string $refreshJti, string $tenant): array
{
    // SELECT jti FROM _oauth_session WHERE refresh_jti = ? AND tenant_id = ? AND jti IS NOT NULL
}
```

### D. Application — Usecase de revocación

**Archivo nuevo o actualizar:** `src/Features/Oidc/Authentication/Application/Usecase/RevokeToken/RevokeTokenUsecase.php`

```php
public function revoke(string $tokenValue, ?string $hint, string $clientId, string $tenant): void
{
    // 1. Intentar parsear como JWT (sin validar expiración)
    try {
        $claims = $this->jwtParser->parseUnchecked($tokenValue);
        $jti    = $claims['jti'] ?? null;
        $type   = $claims['token_type'] ?? ($hint ?? 'access');
        $exp    = $claims['exp'] ?? time() + 3600;
        $sub    = $claims['sub'] ?? null;
        $cid    = $claims['client_id'] ?? null;
    } catch (\Exception) {
        return; // RFC 7009: no revelar error, retornar 200
    }

    // 2. Validar que el cliente es el emisor
    if ($cid !== $clientId) return;

    // 3. Revocar según tipo
    if ($type === 'refresh') {
        // Obtener todos los access tokens vinculados
        $accessJtis = $this->gateway->findAccessJtisByRefreshJti($jti, $tenant);
        foreach ($accessJtis as $accessJti) {
            $this->gateway->revokeByJti($accessJti, $tenant, 'access', new \DateTimeImmutable("@$exp"));
        }
        $this->gateway->revokeSessionByRefreshJti($jti, $tenant);
    }

    $this->gateway->revokeByJti($jti, $tenant, $type, new \DateTimeImmutable("@$exp"));
}
```

### E. Token Granter — emitir jti en todos los tokens

**Archivo:** `src/Features/Oidc/Key/Infrastructure/Driven/JoseTokenSigner.php`
(o donde se construyen los payloads JWT)

Asegurar que todos los tokens emitidos incluyen:
```php
'jti' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
```

Y que al crear la sesión en `_oauth_session`, se persiste tanto el `jti` del
access token como el `refresh_jti` del refresh token.

### F. Token Granter — verificar revocación en cada uso

**Archivo:** `src/Features/Oidc/Authentication/Application/TokenGranter/ResolverForRefresh.php`

Al procesar `grant_type=refresh_token`:
```php
$refreshJti = $claims['jti'];
if ($this->revocationGateway->isRevoked($refreshJti, $tenant)) {
    throw new OAuthException('invalid_grant', 'Refresh token has been revoked');
}
```

### G. UserInfo y Middleware JWT

**Archivo:** `src/Features/Oidc/Authentication/Infrastructure/Driver/Rest/UserInfoController.php`
**Archivo:** `src/Bootstrap/Middleware/` (middleware de verificación JWT)

En la verificación de access tokens, añadir consulta a `_oauth_revoked_jti`:
```php
if ($this->revocationGateway->isRevoked($claims['jti'], $tenant)) {
    // 401 Unauthorized
}
```

> Considerar cache Redis de JTIs revocados (TTL = tiempo restante del token)
> para evitar consulta DB en cada request.

### H. Cleanup Job

**Archivo nuevo:** `src/Features/Oidc/Authentication/Infrastructure/Driver/Cli/CleanupRevokedTokensCommand.php`

```php
// DELETE FROM _oauth_revoked_jti WHERE expires_at < NOW()
```

Registrar como tarea programada o ejecutar desde cron/deployment.

---

## Tests a incluir

### Test unitario — RevokeTokenUsecase

**Archivo:** `test/Features/Oidc/Authentication/Application/Usecase/RevokeTokenUsecaseUnitTest.php`

Con mocks:

Casos:
- Revocar access token válido → `revokeByJti()` llamado una vez
- Revocar refresh token → `findAccessJtisByRefreshJti()` + `revokeByJti()` por cada access + `revokeSessionByRefreshJti()`
- Revocar token de otro `client_id` → no se llama a gateway (silencioso)
- Token no parseable → no se llama a gateway, retorna sin error
- Token sin `jti` → no se llama a gateway, retorna sin error

### Test unitario — TokenRevocationGateway (via adapter con SQLite/mock)

**Archivo:** `test/Features/Oidc/Authentication/Infrastructure/Driven/TokenRevocationSqlAdapterUnitTest.php`

Casos:
- `revokeByJti()` → `isRevoked()` devuelve `true`
- `isRevoked()` para jti no revocado → `false`
- `findAccessJtisByRefreshJti()` → devuelve lista correcta

### Test integración — endpoint /revoke

**Archivo:** `test/Features/Oidc/Authentication/Infrastructure/Driver/Rest/RevokeControllerIntegrationTest.php`

Casos:
- `POST /revoke` con refresh token válido → `200 OK` (body vacío)
- `POST /revoke` con token inexistente → `200 OK` (sin revelar)
- `POST /revoke` sin autenticación de cliente → `401`
- Refresh token revocado, intentar usar en `/token` → `400 invalid_grant`
- Access token revocado, intentar usar en `/userinfo` → `401`

### Test integración — cascada de revocación

Caso:
- Emitir access + refresh token
- Revocar refresh token
- Intentar usar access token en userinfo → `401` (revocado en cascada)
