# 05-04 — Audit Log API Pública

**Prioridad:** P3  
**Esfuerzo estimado:** Bajo-Medio (2 días)  
**Dependencias previas:** `_audit_action` y `_audit_change` ya existen

---

## Contexto

El sistema de auditoría interno (`AuditMiddleware`, `_audit_action`, `_audit_change`)
registra todas las operaciones pero no hay API para que los administradores del tenant
consulten el log de auditoría de su tenant.

---

## Qué implementar

```
GET /api/access/audit-log
  ?actor_uid=xxx
  &resource_type=user
  &resource_uid=xxx
  &event_type=user.login.success
  &from=2026-01-01T00:00:00Z
  &to=2026-12-31T23:59:59Z
  &limit=50
  &cursor=<opaque_cursor>
```

Respuesta:
```json
{
  "data": [
    {
      "id": "uuid",
      "actor_uid": "uuid-usuario",
      "actor_type": "user",
      "action": "user.login.success",
      "resource_type": "user",
      "resource_uid": "uuid",
      "ip_address": "192.168.1.1",
      "user_agent": "...",
      "metadata": {},
      "created_at": "2026-04-12T10:00:00Z"
    }
  ],
  "next_cursor": "opaque_string",
  "has_more": true
}
```

---

## Dónde y cómo hacer los cambios

### A. Nuevo sub-feature: src/Features/Access/AuditLog/

```
src/Features/Access/AuditLog/
├── Domain/
│   ├── AuditLogEntry.php
│   └── Gateway/
│       └── AuditLogReadGateway.php
├── Application/
│   └── Usecase/
│       └── ListAuditLog/
│           ├── ListAuditLogUsecase.php
│           ├── ListAuditLogFilter.php     # actor_uid, resource_type, from, to, etc.
│           └── ListAuditLogResult.php     # data[], next_cursor, has_more
└── Infrastructure/
    ├── Driven/
    │   └── AuditLogSqlAdapter.php         # lee _audit_action + _audit_change
    └── Driver/
        └── Rest/
            └── AuditLogController.php
```

### B. AuditLogReadGateway

```php
interface AuditLogReadGateway
{
    public function findFiltered(ListAuditLogFilter $filter, string $tenant): ListAuditLogResult;
}
```

SQL con cursor-based pagination (más eficiente que OFFSET para logs grandes):
```sql
SELECT a.uid, a.actor_uid, a.actor_type, a.action, a.resource_type,
       a.resource_uid, a.ip_address, a.user_agent, a.created_at
FROM _audit_action a
WHERE a.tenant_id = ?
  AND (? IS NULL OR a.actor_uid = ?)
  AND (? IS NULL OR a.resource_type = ?)
  AND (? IS NULL OR a.created_at >= ?)
  AND (? IS NULL OR a.created_at <= ?)
  AND (? IS NULL OR a.uid > ?)   -- cursor
ORDER BY a.created_at DESC, a.uid DESC
LIMIT ?
```

### C. Seguridad — scoping por tenant

- Solo se devuelven registros del tenant del usuario autenticado
- Roles requeridos: `ROOT` o `AUDIT_VIEWER` (nuevo role)
- Los usuarios normales solo pueden ver sus propios registros vía `/api/me/audit-log`

### D. Retención configurable

```sql
ALTER TABLE access_tenant_config
  ADD COLUMN audit_log_retention_days INT NOT NULL DEFAULT 90;
```

Job de limpieza:
```sql
DELETE FROM _audit_action WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY) AND tenant_id = ?
```

---

## Tests a incluir

### Test integración — AuditLogController

- `GET /api/access/audit-log` admin → `200` con entries
- `GET /api/access/audit-log` usuario sin permisos → `403`
- Filtro `actor_uid` → solo entries de ese usuario
- Filtro `from/to` → rango correcto
- Cursor pagination: `next_cursor` funciona en siguiente request
- `has_more: false` en última página
