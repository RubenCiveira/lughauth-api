# 07-02 — Admin Dashboard API

**Prioridad:** P4  
**Esfuerzo estimado:** Medio-Alto (4-5 días)  
**Dependencias previas:** 04-01 Sesiones Activas, 05-04 Audit Log API

---

## Contexto

Los administradores de cada tenant necesitan una API consolidada para gestionar
usuarios, monitorizar actividad y configurar el tenant. Actualmente solo existe
la Management API de sistema (`/management/*`) que es de administración global,
no por tenant.

---

## Qué implementar

```
# Estadísticas del tenant
GET /api/admin/stats
GET /api/admin/stats/logins?from=2026-01-01&to=2026-12-31&interval=day

# Búsqueda avanzada de usuarios (más potente que el CRUD básico)
GET /api/admin/users/search?q=john&status=active&has_mfa=true&last_login_after=2026-01-01

# Impersonación de usuario (para soporte/debug)
POST /api/admin/users/{uid}/impersonate

# Configuración del tenant
GET /api/admin/tenant/config
PUT /api/admin/tenant/config

# Estado de integraciones
GET /api/admin/integrations/status
```

---

## Dónde y cómo hacer los cambios

### A. Nuevo sub-feature: src/Features/Access/AdminDashboard/

```
src/Features/Access/AdminDashboard/
├── Domain/
│   ├── TenantStats.php
│   ├── UserSearchFilter.php
│   └── Gateway/
│       └── TenantStatsGateway.php
├── Application/
│   └── Usecase/
│       ├── GetTenantStats/
│       │   └── GetTenantStatsUsecase.php
│       ├── SearchUsers/
│       │   └── SearchUsersUsecase.php
│       └── ImpersonateUser/
│           └── ImpersonateUserUsecase.php
└── Infrastructure/
    ├── Driven/
    │   └── TenantStatsSqlAdapter.php
    └── Driver/
        └── Rest/
            ├── TenantStatsController.php
            ├── AdminUserSearchController.php
            └── ImpersonateController.php
```

### B. Application — GetTenantStatsUsecase

```php
public function getStats(string $tenant): TenantStats
{
    return new TenantStats(
        totalUsers:     $this->gateway->countUsers($tenant),
        activeUsers30d: $this->gateway->countActiveUsers($tenant, 30),
        activeSessions: $this->gateway->countActiveSessions($tenant),
        loginsToday:    $this->gateway->countLogins($tenant, new \DateTimeImmutable('today')),
        loginsFailed7d: $this->gateway->countFailedLogins($tenant, 7),
        mfaAdoption:    $this->gateway->getMfaAdoptionRate($tenant),  // porcentaje
        newUsers7d:     $this->gateway->countNewUsers($tenant, 7),
    );
}
```

### C. Application — ImpersonateUserUsecase

La impersonación crea un token especial con claim `impersonated_by` para auditabilidad:

```php
public function impersonate(
    string $targetUserUid,
    string $impersonatorUid,
    string $clientId,
    string $tenant,
): string
{
    // 1. Verificar que el impersonator tiene role ROOT o IMPERSONATOR
    $this->checkPermission($impersonatorUid, 'impersonate', $tenant);

    // 2. El target no puede ser ROOT
    if ($this->hasRole($targetUserUid, 'ROOT', $tenant)) {
        throw new \DomainException('Cannot impersonate ROOT users');
    }

    // 3. Crear un access token especial
    $token = $this->signer->sign([
        'sub'              => $targetUserUid,
        'impersonated_by'  => $impersonatorUid,
        'client_id'        => $clientId,
        'tenant'           => $tenant,
        'iat'              => time(),
        'exp'              => time() + 3600,  // máximo 1 hora
        'jti'              => Uuid::uuid4()->toString(),
        'scope'            => 'openid email profile',
    ]);

    // 4. Registrar en audit log
    $this->audit->log(
        actor:    $impersonatorUid,
        action:   'user.impersonation',
        resource: $targetUserUid,
        tenant:   $tenant,
    );

    return $token;
}
```

### D. AdminUserSearchController — búsqueda avanzada

Parámetros de búsqueda soportados:
- `q` — búsqueda full-text en email y nombre
- `status` — `active`, `disabled`, `blocked`
- `has_mfa` — boolean
- `has_social_login` — boolean
- `last_login_after` / `last_login_before` — fecha
- `created_after` / `created_before` — fecha
- `role` — filtrar por role asignado
- `order` — `email`, `created_at`, `last_login_at`

```sql
SELECT u.*, up.given_name, up.family_name
FROM access_user u
LEFT JOIN access_user_profile up ON up.user_uid = u.uid
LEFT JOIN _oauth_session s ON s.user_uid = u.uid AND s.tenant_id = u.tenant_id
WHERE u.tenant_id = ?
  AND (? IS NULL OR (u.email LIKE ? OR CONCAT(up.given_name, ' ', up.family_name) LIKE ?))
  AND (? IS NULL OR u.enabled = ?)
  AND (? IS NULL OR u.use_second_factors = ?)
  -- ... más filtros
ORDER BY u.created_at DESC
LIMIT ? OFFSET ?
```

### E. Seguridad — Role ADMIN_TENANT

Crear un nuevo role estándar `ADMIN_TENANT` que otorga acceso a `/api/admin/*`:
- Puede ver estadísticas, buscar usuarios, ver audit log
- NO puede impersonar (requiere `ROOT`)
- NO puede eliminar usuarios (requiere `ROOT`)

**Actualización en `guard.yaml`:**
```yaml
/api/admin/users/{uid}/impersonate:
  roles: ['ROOT']
  anonymous: false
/api/admin:
  roles: ['ROOT', 'ADMIN_TENANT']
  anonymous: false
```

### F. TenantStats — queries SQL de estadísticas

**Archivo:** `src/Features/Access/AdminDashboard/Infrastructure/Driven/TenantStatsSqlAdapter.php`

```php
public function countActiveUsers(string $tenant, int $days): int
{
    return (int) $this->pdo->fetchOne(
        'SELECT COUNT(DISTINCT user_uid) FROM _oauth_session
         WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)',
        [$tenant, $days]
    );
}

public function getLoginTimeSeries(string $tenant, string $from, string $to, string $interval): array
{
    // GROUP BY DATE(created_at) o DATE_FORMAT según interval
    $format = match($interval) {
        'hour'  => '%Y-%m-%d %H:00:00',
        'day'   => '%Y-%m-%d',
        'week'  => '%Y-%u',
        'month' => '%Y-%m',
    };
    return $this->pdo->fetchAll(
        "SELECT DATE_FORMAT(created_at, ?) as period, COUNT(*) as count
         FROM _audit_action
         WHERE tenant_id = ? AND action = 'user.login.success'
           AND created_at BETWEEN ? AND ?
         GROUP BY period ORDER BY period",
        [$format, $tenant, $from, $to]
    );
}
```

---

## Tests a incluir

### Test unitario — GetTenantStatsUsecase

Con mock de `TenantStatsGateway`:
- Devuelve `TenantStats` correctamente poblado desde los conteos del gateway

### Test unitario — ImpersonateUserUsecase

Con mocks:
- Impersonator con role ROOT + target no-ROOT → token emitido con `impersonated_by`
- Impersonator sin role → excepción Forbidden
- Intentar impersonar usuario ROOT → excepción
- Audit log registrado en ambos casos

### Test integración — TenantStatsController

- `GET /api/admin/stats` como ROOT → `200` con stats
- `GET /api/admin/stats` como usuario normal → `403`
- `GET /api/admin/stats/logins?interval=day` → array de puntos de datos

### Test integración — AdminUserSearchController

- `GET /api/admin/users/search?q=john` → lista de usuarios
- `GET /api/admin/users/search?has_mfa=true` → solo usuarios con MFA
- `GET /api/admin/users/search?status=disabled` → solo deshabilitados

### Test integración — ImpersonateController

- `POST /api/admin/users/{uid}/impersonate` ROOT → `200` con `{ access_token }`
- Token de impersonación tiene claim `impersonated_by`
- `POST /api/admin/users/root-uid/impersonate` → `403`
