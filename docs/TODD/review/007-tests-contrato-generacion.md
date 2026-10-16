# 007 — Tests de contrato e integración de maestros

- **Estado:** pendiente. **Prioridad:** alta.
- **Evidencia:** los tests unitarios generados comprueban sobre todo VO y DTO. El [plugin de Role](../../src/Features/Access/Role/Infrastructure/Driver/RolePlugin.php) y su [conector PDO](../../src/Features/Access/Role/Infrastructure/Connector/Pdo/RolePdoConnector.php) tienen lógica que esos tests no cubren. `test/integration/` usa solo stubs en memoria ([OidcIntegrationTestCase](../../test/integration/Features/OAuth/OidcIntegrationTestCase.php)); **no hay arnés de BD** para integración. Algunos tests unitarios de `Shared` usan PDO sobre SQLite. Una sola testsuite en [phpunit.xml.dist](../../.ci/phpunit.xml.dist) cubre todo `test/`.
- **Impacto:** una plantilla rota puede pasar cientos de tests unitarios sin ejercitar SQL, permisos ni HTTP.
- **Por hacer:** decidir el arnés de BD (ver decisión); separar testsuites `unit` / `integration`; crear fixtures y contratos reutilizables para un maestro sencillo (Role) y uno complejo (User) que cubran CRUD, autorización denegada, SQL real, paginación (004) y visibilidad (005). Generar estos tests desde la plantilla.
- **Aceptación:** una regresión en listeners, en el SQL de cursor o en una dependencia de ruta rompe algún test.
- **Origen:** ambos análisis de maestros, §6/§7.6 y hallazgo priorizado 4.

## Decisión de diseño

- **Motor de BD para integración:**
  - (a) MySQL real en contenedor. Es el motor de las migraciones en `migrations/mysql`.
  - (b) SQLite en memoria. Es rápido, pero no reproduce `FOR UPDATE`, los bloqueos ni las carreras de 017/020, y obliga a aplicar el esquema de otra forma.
- **Recomendación:** (a) para `integration` y SQLite solo donde ya se usa en `unit`. Las tareas OIDC 017, 020, 039 y 042 necesitan concurrencia real, y el mismo arnés les sirve.
- **¿Los tests los genera el generador o se escriben a mano?** **Recomendación:** generar el contrato CRUD/paginación por entidad y escribir a mano solo los casos de políticas específicas.
