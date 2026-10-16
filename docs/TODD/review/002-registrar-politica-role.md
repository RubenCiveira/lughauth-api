# 002 — Declarar la política de autorización de Role

- **Estado:** pendiente. **Prioridad:** crítica.
- **Evidencia:**
  - [RolePlugin::registerEvents](../../src/Features/Access/Role/Infrastructure/Driver/RolePlugin.php), regenerado el 15-10, está vacío. En la copia antigua del modelo ([multitenant-oidc.xml](../../multitenant-oidc.xml), ver 001), la entidad `role` tiene `<facade type="crud" path="access/roles">` **sin `<allow>`**, mientras que `relying-party`, `trusted-client`, `api-key-client` y `tenant` declaran `#only-for-root`. Todo apunta a que el modelo vigente tampoco lo declara; hay que confirmarlo.
  - [OnlyForRootAllow](../../src/Features/Access/Role/Application/Policy/Allow/OnlyForRootAllow.php) (Create/Retrieve/List/Update/Delete, último cambio en `8434e105`, mayo) existe pero nadie lo registra: es un **resto** probable o un fallo del generador. Ya no existe ningún filtro `RoleAccesible` ni otro filtro de visibilidad para Role.
  - [guard.yaml](../../config/guard.yaml) no tiene reglas para `/api/access/*` y [AccessControlMiddleware](../../src/Bootstrap/Middleware/AccessControlMiddleware.php) deja pasar las rutas sin regla (`return $handler->handle($request)`). Sin RBAC externo, [Handler::allow](../../src/Shared/Security/Rbac/Handler.php) devuelve `true`.
- **Impacto:** si no se configura `security.rbac.lugh.location`, una petición **anónima** puede llegar al caso de uso y crear, modificar o borrar roles. Hay que confirmarlo con un test HTTP.
- **Por hacer:** añadir a la facade de `role` en el modelo vigente el mismo `<allow for="all" name="#only-for-root" match="hasScope('platform:global_access') and hasRole('admin')" />` que usa `relying-party`, y regenerar. **No** registrar los listeners a mano en `RolePlugin`, porque la siguiente regeneración los borraría. Si se elige otra política (ver decisión), borrar `OnlyForRootAllow`.
- **Aceptación:** un anónimo y un usuario sin privilegio no listan ni mutan roles; el administrador root conserva el acceso; hay test de ruta y de caso de uso.
- **Origen:** ambos análisis de maestros, §4.2 y hallazgo 2.

## Decisión de diseño

- **¿Quién gestiona roles?** Un rol pertenece a un `relying-party`, que hoy es global y solo lo gestiona root.
  - (a) Solo root, igual que `relying-party`. Es coherente y es el mínimo cambio.
  - (b) También el admin de tenant, para roles de RP que su tenant usa. Esto exige modelar la relación RP↔tenant, que hoy no existe.
- **Recomendación:** (a) ahora. Revisar (b) junto con 022 (pertenencia de clientes al tenant), ya que ambas comparten la misma pregunta: «¿qué es global y qué es del tenant?».
