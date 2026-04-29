# 04-01 — API de Sesiones Activas del Usuario

**Prioridad:** P1  
**Esfuerzo estimado:** Bajo-Medio (2-3 días)  
**Dependencias previas:** 01-04 Token Revocation (columna `revoked_at` en `_oauth_session`)

---

## Contexto

Los usuarios no tienen forma de ver desde qué dispositivos están conectados ni
de cerrar sesiones remotas. Esta funcionalidad es estándar en cualquier BaaS
(Google, Auth0, Supabase la ofrecen). Usa la tabla `_oauth_session` existente.

---

## Qué implementar

```
GET    /api/me/sessions              # Listar sesiones activas del usuario autenticado
DELETE /api/me/sessions/{session_id} # Revocar una sesión concreta
DELETE /api/me/sessions              # Revocar TODAS las sesiones (excepto la actual)
```

**Respuesta de listado:**
```json
[
  {
    "session_id": "uuid",
    "client_name": "Mi App Web",
    "created_at": "2026-04-10T10:00:00Z",
    "last_used_at": "2026-04-12T08:30:00Z",
    "ip_address": "192.168.1.1",
    "user_agent": "Mozilla/5.0...",
    "is_current": true
  }
]
```

---

## Dónde y cómo hacer los cambios

### A. Migración — campos adicionales en _oauth_session

```sql
ALTER TABLE _oauth_session
  ADD COLUMN IF NOT EXISTS ip_address   VARCHAR(45)  NULL,
  ADD COLUMN IF NOT EXISTS user_agent   VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS last_used_at DATETIME     NULL,
  ADD COLUMN IF NOT EXISTS client_name  VARCHAR(200) NULL;

-- Poblar client_name desde access_trusted_client al crear sesión (JOIN en INSERT)
```

Al crear una sesión en `SessionStoreSqlAdapter.php`, capturar:
- `$_SERVER['REMOTE_ADDR']` via PSR-7 request `getServerParams()['REMOTE_ADDR']`
- `$request->getHeaderLine('User-Agent')`

### B. Nuevo sub-feature: src/Features/Access/UserSession/

```
src/Features/Access/UserSession/
├── Domain/
│   ├── UserSession.php
│   └── Gateway/
│       └── UserSessionGateway.php
├── Application/
│   └── Usecase/
│       ├── ListUserSessions/
│       │   ├── ListUserSessionsUsecase.php
│       │   └── ListUserSessionsResult.php
│       └── RevokeUserSession/
│           └── RevokeUserSessionUsecase.php
└── Infrastructure/
    ├── Driven/
    │   └── UserSessionSqlAdapter.php       # lee _oauth_session
    └── Driver/
        └── Rest/
            └── UserSessionController.php
```

### C. Domain — UserSession

```php
final class UserSession
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $userUid,
        public readonly string $tenant,
        public readonly ?string $clientName,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?\DateTimeImmutable $lastUsedAt,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
        public readonly bool $isCurrent,
    ) {}
}
```

### D. Domain — UserSessionGateway

```php
interface UserSessionGateway
{
    /** @return UserSession[] */
    public function findActiveByUser(string $userUid, string $tenant): array;

    public function findById(string $sessionId, string $userUid, string $tenant): ?UserSession;

    public function revoke(string $sessionId, string $tenant): void;

    public function revokeAllExcept(string $userUid, string $tenant, string $currentSessionId): int;
}
```

### E. Infrastructure — UserSessionSqlAdapter

**Archivo:** `src/Features/Access/UserSession/Infrastructure/Driven/UserSessionSqlAdapter.php`

```php
public function findActiveByUser(string $userUid, string $tenant): array
{
    $rows = $this->pdo->fetchAll(
        'SELECT s.*, tc.code as client_name
         FROM _oauth_session s
         LEFT JOIN access_trusted_client tc ON tc.uid = s.client_uid
         WHERE s.user_uid = ? AND s.tenant_id = ?
           AND s.revoked_at IS NULL
           AND s.expires_at > NOW()
         ORDER BY s.last_used_at DESC',
        [$userUid, $tenant]
    );
    return array_map(fn($r) => $this->mapRow($r), $rows);
}

public function revokeAllExcept(string $userUid, string $tenant, string $currentSessionId): int
{
    return $this->pdo->execute(
        'UPDATE _oauth_session SET revoked_at = NOW()
         WHERE user_uid = ? AND tenant_id = ? AND session_id != ? AND revoked_at IS NULL',
        [$userUid, $tenant, $currentSessionId]
    );
}
```

### F. Application — RevokeUserSessionUsecase

```php
public function revoke(string $sessionId, string $currentUserUid, string $tenant): void
{
    $session = $this->gateway->findById($sessionId, $currentUserUid, $tenant);

    if ($session === null) {
        throw new \DomainException('Session not found');
    }

    // Autorización: el usuario solo puede revocar sus propias sesiones
    if ($session->userUid !== $currentUserUid) {
        throw new \DomainException('Forbidden');
    }

    $this->gateway->revoke($sessionId, $tenant);

    // También revocar los JTIs de tokens asociados a esa sesión
    $this->tokenRevocationGateway->revokeBySessionId($sessionId, $tenant);
}
```

### G. REST Controller

**Archivo:** `src/Features/Access/UserSession/Infrastructure/Driver/Rest/UserSessionController.php`

```php
#[OA\Get(path: '/api/me/sessions', summary: 'List active sessions', tags: ['Session'], security: [['bearerAuth' => []]])]
public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface

#[OA\Delete(path: '/api/me/sessions/{session_id}', summary: 'Revoke a session', tags: ['Session'])]
public function revoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface

#[OA\Delete(path: '/api/me/sessions', summary: 'Revoke all other sessions', tags: ['Session'])]
public function revokeAll(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
```

El `currentUserUid` se extrae del JWT Bearer del request.
El `currentSessionId` se extrae del claim `sid` del JWT.

---

## Tests a incluir

### Test unitario — RevokeUserSessionUsecase

Con mocks:
- Revocar sesión propia → gateway llamado, JTIs revocados
- Revocar sesión de otro usuario → excepción Forbidden
- Sesión inexistente → excepción Not Found
- `revokeAll` → gateway llamado con `currentSessionId` excluido

### Test unitario — ListUserSessionsUsecase

Con mock:
- 2 sesiones activas → devuelve 2 `UserSession`
- Sin sesiones → devuelve `[]`
- `isCurrent = true` para la sesión con `sid` del JWT actual

### Test integración — UserSessionController

- `GET /api/me/sessions` sin Bearer → `401`
- `GET /api/me/sessions` con Bearer válido → `200` con lista
- `DELETE /api/me/sessions/{id}` sesión propia → `204`
- `DELETE /api/me/sessions/{id}` sesión de otro usuario → `403`
- `DELETE /api/me/sessions` → `204`, solo queda sesión actual activa
- Refresh token de sesión revocada → `invalid_grant`
