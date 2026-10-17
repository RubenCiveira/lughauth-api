# EP-16 — Cierre: E2E, conformidad y documentación

- **Tareas:** [042](../042-suite-e2e-oidc.md) (suite end-to-end y OpenID Conformance Suite) y [066](../066-actualizar-documentacion-oauth.md) (documentación OAuth).
- **Prioridad:** alta (042), baja (066). **Tamaño:** L. **Depende de:** fase 0 de nada; fases 1–2 de EP-01 a EP-09.
- **Esquema:** ninguno; necesita una BD MySQL de prueba con las migraciones aplicadas.

## Por qué juntas

Ambas certifican el estado final: 042 demuestra que los contratos se cumplen y 066 documenta solo lo que 042 demuestra. 066 exige además no documentar como operativo lo que no tenga test, lo que la ata al resultado de 042.

## Fase 0 — arnés de pruebas (al inicio, en paralelo con la ola 0)

Hoy toda la integración OAuth usa stubs en memoria (`Stub/InMemory*`), y solo [SessionStoreSqlAdapterTest](../../../test/integration/Features/OAuth/SessionStoreSqlAdapterTest.php) y [TokenRevocationSqlAdapterTest](../../../test/integration/Features/OAuth/TokenRevocationSqlAdapterTest.php) prueban MySQL, y se saltan si no lo encuentran. Adelantar el arnés permite que **cada épica entregue sus escenarios** contra la BD real.

- [OidcIntegrationTestCase](../../../test/integration/Features/OAuth/OidcIntegrationTestCase.php): fixtures de cliente público y confidencial, dos tenants, sesión y claves reales.
- Arnés MySQL común con la tarea 007 (`review/`); conservar los stubs solo donde no oculten carreras SQL.
- `.ci/phpunit.xml.dist` y `Makefile`: testsuites `unit`, `integration` y `conformance`, con objetivos `make`.
- **No hay pipeline de CI versionado**: decidir dónde se ejecuta (p. ej. `.github/workflows/` o el sistema externo) y gestionar los secretos de prueba fuera del repositorio.

## Fase 1 — escenarios encadenados (tras EP-03 a EP-09)

Flujos HTTP completos: code+PKCE, redirects negativos, code concurrente, refresh/replay, aislamiento de tenant, logout (GET, POST, back- y front-channel), discovery/JWKS frente a la implementación y DCR. Cada épica añade los suyos; esta fase cubre los que cruzan épicas.

## Fase 2 — conformidad y documentación

1. OpenID Conformance Suite: perfiles Basic, Config, Dynamic y Logout. Resultados conectados a CI; un perfil solo se declara conforme cuando pasa.
2. 066: [docs/OAuth/README.md](../../OAuth/README.md), [00-overview.md](../../OAuth/00-overview.md) y las guías 03, 04, 05 y 08. Usar las rutas reales `src/Features/OAuth`, reconocer las dependencias con `Access`, contrastar cada flujo con [OAuthPlugin](../../../src/Bootstrap/Plugin/OAuthPlugin.php) y con las «Decisiones tomadas» de las tareas cerradas, y no documentar opcionales no implementados.

## Aceptación

Unión de 042 y 066: escenarios de éxito y de ataque reproducibles, perfiles anunciados en verde y documentación alineada con el código y las rutas actuales.
