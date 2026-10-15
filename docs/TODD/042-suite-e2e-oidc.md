# 042 — Suite end-to-end OAuth/OIDC y conformidad

- **Estado:** pendiente. **Prioridad:** alta.
- **Evidencia:** [OAuthPlugin](../../src/Bootstrap/Plugin/OAuthPlugin.php) registra muchos flujos cuya presencia no acredita contrato; los dos análisis señalan cobertura escasa de escenarios.
- **Impacto:** defectos de canje, tenant, cursores de sesión y claims sobreviven a unitarios aislados.
- **Por hacer:** tests HTTP+persistencia de code+PKCE, redirects negativos, code concurrente, refresh/replay, logout, discovery/JWKS y DCR; después de 015–038 ejecutar perfiles Basic/Config/Dynamic/Logout de OpenID Conformance Suite y conectar resultados a CI.
- **Aceptación:** escenarios de éxito y ataque reproducibles; los perfiles anunciados pasan antes de declararse conformes.
- **Origen:** análisis OAuth §5.8/2.4/validación y `_2.md` P1/P2.

## Detalle de implementación

- `test/integration/Features/OAuth/OidcIntegrationTestCase.php`: ampliar fixtures de cliente público/confidencial, tenant, sesión y claves reales; conservar los stubs de `Stub/` únicamente donde no oculten carreras SQL.
- `test/integration/Features/OAuth/TokenEndpointTest.php`, `DiscoveryTest.php`, `ParTest.php`, `IntrospectionTest.php` y `DeviceEndpointTest.php`: añadir escenarios encadenados HTTP code+PKCE, refresh/replay, aislamiento, logout y metadatos frente a implementación.
- `test/integration/Features/OAuth/` (**nuevos tests de flujo/logout/SQL**): ejecutar escenarios que necesiten BD contra MySQL de prueba (arnés común con 007) y OpenID Conformance Suite después de cerrar seguridad/Core. Hoy toda la integración OAuth usa stubs en memoria (`Stub/InMemory*`).
- `.ci/phpunit.xml.dist`, `Makefile` / `.ci/new_Makefile`: separar testsuites `unit`/`integration`/`conformance` y añadir objetivos de `make`. **No hay pipeline de CI versionado en el repo**: decidir dónde se ejecuta (p. ej. nuevo `.github/workflows/` o el sistema externo existente) y registrar allí perfiles y secretos de prueba sin introducir credenciales en el repositorio.
