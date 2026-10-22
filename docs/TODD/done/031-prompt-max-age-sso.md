# 031 — Implementar prompt, max_age y vigencia SSO

- **Estado:** hecho (EP-09). **Prioridad:** media.
- **Evidencia:** [OidcFlowContext](../../../src/Features/OAuth/Authentication/Domain/OidcFlowContext.php) extrae `prompt`, pero [AuthorizeHtml](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php) trata solo `none`; no extrae `max_age`; sesión persiste `auth_time` en [SessionStoreSqlAdapter](../../../src/Features/OAuth/Session/Infrastructure/Driven/SessionStoreSqlAdapter.php).
- **Impacto:** RP no puede exigir reautenticación ni controlar antigüedad de sesión.
- **Por hacer:** semántica de `login`, `select_account`, `consent`, `none`/combinaciones y `max_age`, TTL SSO por tenant usando tiempo real de autenticación.
- **Aceptación:** sesión vieja no satisface `max_age`; `prompt=login` fuerza login y `none` falla sin interacción.
- **Origen:** análisis OAuth §5.1/1.2 y `_2.md` comparación.

## Detalle de implementación

- `src/Features/OAuth/Authentication/Domain/OidcFlowContext.php`: extraer y validar `max_age` y la lista `prompt`, incluidas combinaciones incompatibles con `none`.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php` y `OidcStepRouter.php`: decidir reautenticación, selección de cuenta o forzar `ScopeConsentForm` según prompt y edad de sesión; `none` debe devolver error sin UI.
- `src/Features/OAuth/Session/Domain/SessionInfo.php` y `Infrastructure/Driven/SessionStoreSqlAdapter.php`: leer `auth_time` ya persistido y TTL SSO del tenant; no usar `iat` de un token reciente como sustituto.
- `test/integration/Features/OAuth/`: cubrir sesión recién autenticada/caducada, `max_age=0`, `prompt=none/login/consent` y expiración por tenant.
