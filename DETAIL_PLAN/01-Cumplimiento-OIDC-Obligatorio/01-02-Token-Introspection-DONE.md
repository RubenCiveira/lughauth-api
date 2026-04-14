# 01-02 — Token Introspection — RFC 7662

[x] DONE

**Prioridad:** P1  
**Spec:** RFC 7662  
**Esfuerzo estimado:** Bajo-Medio (2-3 días)  
**Dependencias previas:** añadir `jti` a tokens emitidos (ver 07-03 Refactoring)  
**Habilita:** validación de tokens en microservicios sin verificación local de firma

---

## Contexto

Token Introspection permite a un resource server (microservicio) preguntar al
authorization server si un token concreto está activo y obtener sus metadatos,
sin necesidad de verificar la firma JWT localmente ni mantener una copia de las
claves públicas. Es especialmente útil para tokens revocados.

**Estado actual:** No existe ningún endpoint de introspección. Los resource servers
dependen exclusivamente de la verificación JWT local mediante JWKS.

---

## Qué implementar

### Endpoint

```
POST /openid/{tenant}/introspect
Authorization: Basic base64(client_id:client_secret)
Content-Type: application/x-www-form-urlencoded

token=<token_value>&token_type_hint=access_token
```

### Respuesta token activo

```json
{
  "active": true,
  "sub": "550e8400-e29b-41d4-a716-446655440000",
  "client_id": "client-uuid",
  "scope": "openid email profile",
  "exp": 1893456000,
  "iat": 1893452400,
  "nbf": 1893452400,
  "iss": "https://auth.example.com/openid/my-tenant",
  "jti": "unique-token-id",
  "token_type": "Bearer",
  "username": "user@example.com"
}
```

### Respuesta token inactivo (revocado, expirado, desconocido)

```json
{ "active": false }
```

### Seguridad

- Solo clientes registrados (`access_trusted_client`) pueden llamar a introspect
- Autenticación: `Authorization: Basic` con `client_id:client_secret`
- Rate limiting específico para este endpoint (abuso potencial)
- Un cliente **no puede** introspectar tokens emitidos para otro `client_id`
  (salvo que tenga scope especial `introspect:any`)

---

## Dónde y cómo hacer los cambios

### A. Nuevo sub-feature Introspection

Estructura hexagonal bajo `src/Features/Oidc/Introspection/`:

```
src/Features/Oidc/Introspection/
├── Domain/
│   ├── IntrospectionResult.php          # VO resultado: active + claims
│   ├── Gateway/
│   │   └── TokenIntrospectionGateway.php  # Puerto: buscar token por valor
├── Application/
│   └── Usecase/
│       └── IntrospectToken/
│           ├── IntrospectTokenUsecase.php
│           ├── IntrospectTokenParams.php  # token, token_type_hint, requesting_client
│           └── IntrospectTokenResult.php
└── Infrastructure/
    ├── Driven/
    │   └── TokenIntrospectionSqlAdapter.php  # Consulta _oauth_session + jti revocados
    └── Driver/
        └── Rest/
            └── IntrospectController.php
```

### B. Domain — IntrospectionResult

**Archivo:** `src/Features/Oidc/Introspection/Domain/IntrospectionResult.php`

```php
final class IntrospectionResult
{
    private function __construct(
        public readonly bool $active,
        public readonly ?string $sub = null,
        public readonly ?string $clientId = null,
        public readonly ?string $scope = null,
        public readonly ?int $exp = null,
        public readonly ?int $iat = null,
        public readonly ?string $iss = null,
        public readonly ?string $jti = null,
        public readonly ?string $username = null,
    ) {}

    public static function inactive(): self
    {
        return new self(active: false);
    }

    public static function active(array $claims): self
    {
        return new self(active: true, ...$claims);
    }

    public function toArray(): array
    {
        if (!$this->active) return ['active' => false];
        return array_filter([
            'active'    => true,
            'sub'       => $this->sub,
            'client_id' => $this->clientId,
            'scope'     => $this->scope,
            'exp'       => $this->exp,
            'iat'       => $this->iat,
            'iss'       => $this->iss,
            'jti'       => $this->jti,
            'username'  => $this->username,
            'token_type'=> 'Bearer',
        ], fn($v) => $v !== null);
    }
}
```

### C. Application — IntrospectTokenUsecase

**Archivo:** `src/Features/Oidc/Introspection/Application/Usecase/IntrospectToken/IntrospectTokenUsecase.php`

Lógica:
1. Validar autenticación del cliente solicitante (Basic Auth → buscar en `access_trusted_client`)
2. Intentar parsear el token como JWT:
   - Si es válido: extraer claims `sub`, `client_id`, `scope`, `exp`, `iat`, `iss`, `jti`
   - Si ha expirado: devolver `inactive`
   - Si la firma no es válida: devolver `inactive`
3. Comprobar si el `jti` está en la tabla de tokens revocados → si sí: devolver `inactive`
4. Comprobar que `client_id` del token == `client_id` del solicitante (o scope especial)
5. Devolver `IntrospectionResult::active(...)`

### D. Infrastructure — TokenIntrospectionSqlAdapter

**Archivo:** `src/Features/Oidc/Introspection/Infrastructure/Driven/TokenIntrospectionSqlAdapter.php`

Implementa `TokenIntrospectionGateway`:
```php
public function isRevoked(string $jti, string $tenant): bool;
public function findSessionByJti(string $jti, string $tenant): ?array;
```

Consultas:
- `SELECT revoked_at FROM _oauth_session WHERE jti = ? AND tenant_id = ?`
- Índice necesario: `INDEX idx_session_jti (jti)` en `_oauth_session`

### E. REST Controller

**Archivo:** `src/Features/Oidc/Introspection/Infrastructure/Driver/Rest/IntrospectController.php`

```php
#[OA\Post(
    path: '/openid/{tenant}/introspect',
    summary: 'Token Introspection (RFC 7662)',
    tags: ['OIDC'],
    responses: [
        new OA\Response(response: 200, description: 'Introspection result'),
        new OA\Response(response: 401, description: 'Client authentication failed'),
    ]
)]
public function post(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
```

Autenticación del cliente:
- Leer header `Authorization: Basic base64(client_id:client_secret)`
- Alternativamente: `client_id` y `client_secret` en el body form-encoded
- Si falla: `401 Unauthorized` con `WWW-Authenticate: Basic realm="introspect"`

### F. Migración — índice jti en _oauth_session

**Archivo nuevo:** `migrations/mysql/schema/YYYYMMDDHHMMSS_AddJtiIndexToSession.php`

```sql
-- Añadir columna jti si no existe (prerequisito para introspección y revocación)
ALTER TABLE _oauth_session
  ADD COLUMN IF NOT EXISTS jti VARCHAR(36) NULL AFTER session_id,
  ADD INDEX IF NOT EXISTS idx_session_jti (jti);

-- Tabla de JTIs revocados (para revocación sin borrar sesión)
CREATE TABLE IF NOT EXISTS _oauth_revoked_jti (
  jti         VARCHAR(36)  NOT NULL,
  tenant_id   VARCHAR(36)  NOT NULL,
  revoked_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at  DATETIME     NOT NULL,
  PRIMARY KEY (jti),
  INDEX idx_revoked_tenant (tenant_id),
  INDEX idx_revoked_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### G. Plugin DI — IntrospectionPlugin

**Archivo nuevo:** `src/Features/Oidc/Introspection/Infrastructure/Driver/IntrospectionPlugin.php`

Extiende `MicroPlugin`:
- Bind `TokenIntrospectionGateway::class` → `TokenIntrospectionSqlAdapter::class`
- Registrar ruta `POST /openid/{tenant}/introspect`

### H. Discovery

**Archivo:** `src/Features/Oidc/Common/Infrastructure/Driver/Rest/OpenIdConfigurationController.php`

Añadir:
```php
'introspection_endpoint' => $issuer . '/introspect',
'introspection_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post'],
```

---

## Tests a incluir

### Test unitario — IntrospectionResult

**Archivo:** `test/Features/Oidc/Introspection/Domain/IntrospectionResultUnitTest.php`

Casos:
- `inactive()` → `toArray()` devuelve solo `['active' => false]`
- `active([...])` → `toArray()` incluye todos los claims presentes
- `active([...])` con campos null → no aparecen en `toArray()`

### Test unitario — IntrospectTokenUsecase

**Archivo:** `test/Features/Oidc/Introspection/Application/Usecase/IntrospectTokenUsecaseUnitTest.php`

Casos (con mocks de gateway):
- Token JWT válido, no revocado, mismo `client_id` → `active: true` con claims
- Token JWT expirado → `active: false`
- Token JWT con firma inválida → `active: false`
- Token con jti en `_oauth_revoked_jti` → `active: false`
- Token de otro cliente intentado por cliente distinto → `active: false`
- Cliente solicitante con credenciales inválidas → excepción de autenticación

### Test integración — IntrospectController

**Archivo:** `test/Features/Oidc/Introspection/Infrastructure/Driver/Rest/IntrospectControllerIntegrationTest.php`

Casos:
- `POST /introspect` con token válido y Basic Auth correcto → `200 { active: true, ... }`
- `POST /introspect` con token expirado → `200 { active: false }`
- `POST /introspect` sin Authorization header → `401`
- `POST /introspect` con credenciales de cliente incorrectas → `401`
- `POST /introspect` con token que no es JWT → `200 { active: false }`
