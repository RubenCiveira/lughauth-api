# 006 — Definir autorización fail-closed para maestros protegidos

- **Estado:** pendiente. **Prioridad:** crítica.
- **Evidencia:** la protección tiene tres capas y las tres permiten por defecto:
  1. **Guard HTTP:** [AccessControlMiddleware](../../src/Bootstrap/Middleware/AccessControlMiddleware.php) no aplica restricciones si ninguna regla coincide, y [guard.yaml](../../config/guard.yaml) solo cubre `/management/*` y `/no-management*`.
  2. **RBAC:** [Handler::allow](../../src/Shared/Security/Rbac/Handler.php) devuelve `true` si `security.rbac.lugh.location` no está configurado.
  3. **Caso de uso:** [RoleRetrieveUsecase](../../src/Features/Access/Role/Application/Usecase/Retrieve/RoleRetrieveUsecase.php) y el resto parten de `Allow::allowed`. En la copia del modelo [multitenant-oidc.xml](../../multitenant-oidc.xml) (antigua, ver 001), solo seis entidades declaran `<allow>` (relying-party, trusted-client, api-key-client, tenant, user-consented-scopes y user-accepted-terms-of-use), además del embebido `self-user`. Las demás dependen de la visibilidad por tenant o de nada (`role`, ver 002). Hay que contrastarlo con el modelo vigente.
- **Impacto:** si se olvida un `<allow>` en el modelo o se desactiva RBAC, las operaciones quedan abiertas, incluso para anónimos si la ruta no tiene regla de guard.
- **Por hacer:** elegir la estrategia (ver decisión), implementarla en `Shared/Security` y en las plantillas, y cubrir con tests que la ausencia de política deniegue sin bloquear flujos legítimos (`/api/me/*`, invitaciones, registro).
- **Aceptación:** ninguna acción protegida se permite solo porque falte un listener, una regla de guard o el servicio RBAC.
- **Origen:** ambos análisis de maestros, §4.2/§5.2/§7.5.

## Decisión de diseño

Opciones, que se pueden combinar:

| Opción | Dónde | Coste | Protege de |
|---|---|---|---|
| A. Regla de guard `/api/access` con `anonymous: false` (y `/api/me` igual) | `config/guard.yaml` | S | Anónimos; no protege de usuarios autenticados sin permiso |
| B. RBAC desactivado deniega, salvo con un flag explícito de desarrollo (`security.rbac.permissive: true`) | `Shared/Security/Rbac/Handler.php` | S | Despliegues sin RBAC configurado |
| C. El caso de uso parte de `denied` y exige que al menos una política conceda | Plantillas + modelo | M–L | Olvidos de `<allow>` en el modelo |
| D. El generador falla si una facade CRUD no declara `<allow>` ni `<visibility>` | Generador | S–M | Olvidos de `<allow>`, en tiempo de generación |

**Recomendación:** A + B de inmediato (bajo coste, cierran el acceso anónimo y el despliegue mal configurado) y D como siguiente paso. C invierte la semántica de todos los listeners existentes (hoy solo **deniegan**), así que conviene hacerla después de D, cuando cada entidad ya declare su política.

**Duda abierta:** ¿la defensa principal es RBAC externo o los `<allow>` del modelo? Si es RBAC, B es lo esencial. Si son los `<allow>`, lo esencial es D.
