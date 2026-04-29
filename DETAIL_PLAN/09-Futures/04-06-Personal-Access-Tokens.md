# 04-06 — Personal Access Tokens (PAT)

**Prioridad:** P2  
**Esfuerzo estimado:** Medio (2-3 días)  
**Dependencias previas:** ninguna

---

## Contexto

Los PAT (Personal Access Tokens) son tokens de larga duración asociados a un usuario
que pueden usarse en lugar de Bearer tokens en APIs. Son el estándar de GitHub, GitLab,
Notion, etc. para integraciones de scripts y herramientas CLI.

**Diferencia con access tokens OIDC:** Los PAT no expiran automáticamente (o tienen TTL
muy largo), son revocables manualmente, y se pueden nombrar para identificar su uso.

---

## Qué implementar

```
GET    /api/me/api-keys              # Listar PATs propios
POST   /api/me/api-keys              # Crear nuevo PAT (devuelve valor una sola vez)
DELETE /api/me/api-keys/{uid}        # Revocar PAT
GET    /api/me/api-keys/{uid}        # Detalle (sin valor, solo metadata)

# Admin
GET    /api/access/users/{uid}/api-keys   # PATs de cualquier usuario
DELETE /api/access/users/{uid}/api-keys   # Revocar todos los PATs de un usuario
```

---

## Dónde y cómo hacer los cambios

### A. Migración

```sql
CREATE TABLE IF NOT EXISTS access_user_api_key (
  uid          VARCHAR(36)   NOT NULL,
  tenant_id    VARCHAR(36)   NOT NULL,
  user_uid     VARCHAR(36)   NOT NULL,
  name         VARCHAR(200)  NOT NULL,
  key_prefix   VARCHAR(10)   NOT NULL,    -- primeros 8 chars del token (para display)
  key_hash     VARCHAR(64)   NOT NULL,    -- SHA-256 del token completo
  scopes_json  TEXT          NULL,        -- JSON array de scopes
  expires_at   DATETIME      NULL,        -- NULL = no expira
  last_used_at DATETIME      NULL,
  last_used_ip VARCHAR(45)   NULL,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at   DATETIME      NULL,
  PRIMARY KEY (uid),
  UNIQUE KEY uk_key_hash (key_hash),
  INDEX idx_pat_user   (user_uid, tenant_id),
  INDEX idx_pat_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### B. Nuevo sub-feature: src/Features/Access/UserApiKey/

```
src/Features/Access/UserApiKey/
├── Domain/
│   ├── UserApiKey.php
│   ├── UserApiKeyCreated.php          # DTO de creación con token visible
│   └── Gateway/
│       ├── UserApiKeyReadGateway.php
│       └── UserApiKeyWriteGateway.php
├── Application/
│   └── Usecase/
│       ├── CreateUserApiKey/
│       │   ├── CreateUserApiKeyUsecase.php
│       │   └── CreateUserApiKeyResult.php  # incluye raw token (solo aquí)
│       ├── ListUserApiKeys/
│       │   └── ListUserApiKeysUsecase.php
│       └── RevokeUserApiKey/
│           └── RevokeUserApiKeyUsecase.php
└── Infrastructure/
    ├── Driven/
    │   ├── UserApiKeyReadAdapter.php
    │   └── UserApiKeyWriteAdapter.php
    └── Driver/
        └── Rest/
            └── UserApiKeyController.php
```

### C. Domain — UserApiKey

```php
final class UserApiKey
{
    public function __construct(
        public readonly string $uid,
        public readonly string $tenant,
        public readonly string $userUid,
        public readonly string $name,
        public readonly string $keyPrefix,  // "lugh_aBc1" — visible, no sensible
        public readonly array  $scopes,
        public readonly ?\DateTimeImmutable $expiresAt,
        public readonly ?\DateTimeImmutable $lastUsedAt,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?\DateTimeImmutable $revokedAt,
    ) {}

    public function isActive(): bool
    {
        if ($this->revokedAt !== null) return false;
        if ($this->expiresAt !== null && $this->expiresAt < new \DateTimeImmutable()) return false;
        return true;
    }
}
```

### D. Application — CreateUserApiKeyUsecase

```php
public function create(CreateUserApiKeyParams $params): CreateUserApiKeyResult
{
    // 1. Generar token: prefix_<random64chars>
    $randomPart = bin2hex(random_bytes(32));
    $rawToken   = "lugh_{$randomPart}";
    $prefix     = substr($rawToken, 0, 10);
    $hash       = hash('sha256', $rawToken);

    $key = new UserApiKey(
        uid:       Uuid::uuid4()->toString(),
        tenant:    $params->tenant,
        userUid:   $params->userUid,
        name:      $params->name,
        keyPrefix: $prefix,
        scopes:    $params->scopes ?? ['read'],
        expiresAt: $params->expiresAt,
        lastUsedAt: null,
        createdAt: new \DateTimeImmutable(),
        revokedAt: null,
    );

    $this->writeGateway->store($key, $hash);

    // rawToken solo se devuelve aquí — NUNCA se vuelve a mostrar
    return new CreateUserApiKeyResult(
        uid:      $key->uid,
        token:    $rawToken,   // ← visible solo en creación
        prefix:   $prefix,
        name:     $params->name,
        expiresAt: $params->expiresAt,
    );
}
```

### E. Middleware — autenticación con PAT

**Archivo nuevo o ampliar:** `src/Bootstrap/Middleware/` (middleware de autenticación)

Añadir soporte para header `Authorization: Bearer lugh_<token>`:
- Si el token empieza con `lugh_` → buscar en `access_user_api_key` por hash
- Si encontrado y activo → autenticar como el `user_uid` asociado con los scopes del PAT
- Actualizar `last_used_at` y `last_used_ip` (async si es posible)

```php
private function resolveBearer(string $bearerValue, string $tenant): ?AuthenticatedSubject
{
    if (str_starts_with($bearerValue, 'lugh_')) {
        $hash = hash('sha256', $bearerValue);
        $pat  = $this->patGateway->findActiveByHash($hash, $tenant);
        if ($pat === null) return null;
        $this->patGateway->updateLastUsed($pat->uid, $clientIp);
        return new AuthenticatedSubject(
            type:   'user',
            uid:    $pat->userUid,
            scopes: $pat->scopes,
            tenant: $tenant,
        );
    }
    // Fallback: JWT verification existente
    return $this->verifyJwt($bearerValue, $tenant);
}
```

### F. REST Controller — respuesta de creación

**Importante:** la respuesta del `POST /api/me/api-keys` debe incluir el token completo,
con una advertencia clara:

```json
{
  "uid": "uuid",
  "name": "CI Pipeline",
  "token": "lugh_a1b2c3...",  // ← SOLO en la respuesta de creación
  "prefix": "lugh_a1b2c3",
  "scopes": ["read", "write"],
  "expires_at": null,
  "created_at": "2026-04-12T00:00:00Z",
  "warning": "Store this token securely. It will not be shown again."
}
```

Los endpoints de listado **nunca** devuelven el campo `token`, solo `prefix`.

---

## Tests a incluir

### Test unitario — UserApiKey.isActive()

- `revokedAt` no null → `false`
- `expiresAt` pasado → `false`
- `expiresAt` futuro, no revocado → `true`
- Sin `expiresAt` ni `revokedAt` → `true`

### Test unitario — CreateUserApiKeyUsecase

Con mocks:
- Token generado empieza con `lugh_`
- Hash almacenado es SHA-256 del token
- Token rawValue devuelto correctamente
- Scope vacío → scope por defecto asignado

### Test integración — UserApiKeyController

- `POST /api/me/api-keys` → `201` con `token` en respuesta
- `GET /api/me/api-keys` → lista sin campo `token`
- `GET /api/me/api-keys/{uid}` → detalle sin campo `token`, con `prefix`
- `DELETE /api/me/api-keys/{uid}` → `204`
- PAT revocado → `DELETE` segundo intento → `404`

### Test integración — Autenticación con PAT

- Request con `Authorization: Bearer lugh_<valid_token>` → autenticado
- Request con PAT revocado → `401`
- Request con PAT expirado → `401`
- Request con PAT válido pero scope insuficiente para el endpoint → `403`
- `GET /userinfo` con PAT con scope `openid email` → claims correctos
