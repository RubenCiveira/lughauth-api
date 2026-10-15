# 067 — Evaluar hints y step-up ACR en autorización

- **Estado:** no implementado; opcional. **Prioridad:** baja.
- **Evidencia:** [OidcFlowContext](../../src/Features/OAuth/Authentication/Domain/OidcFlowContext.php) captura `prompt` pero no `login_hint`, `id_token_hint` ni `acr_values`; [OpenIdConfiguration](../../src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php) anuncia un ACR fijo.
- **Impacto:** RP no pueden orientar la cuenta ni solicitar un nivel de autenticación verificable.
- **Por hacer:** decidir semántica y privacidad de hints, comprobar firma/tenant del ID hint, definir ACR reales por método/MFA y forzar step-up cuando se solicite; retirar ACR ficticio del discovery mientras tanto.
- **Aceptación:** ACR solicitado no se afirma sin autenticación suficiente y los hints no autentican por sí mismos.
- **Origen:** análisis OAuth §5.1, capacidades `login_hint`/`id_token_hint`/`acr_values`.

## Detalle de implementación (solo si se aprueba)

- `src/Features/OAuth/Authentication/Domain/OidcFlowContext.php`: analizar `login_hint`, `id_token_hint` y `acr_values` con límites y normalización de tipos.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php` y `OidcStepRouter.php`: precargar cuenta sin autenticar por el hint; forzar MFA/reautenticación cuando ACR pedido exceda la sesión actual.
- `src/Features/OAuth/Session/Domain/SessionInfo.php` y `Infrastructure/Driven/SessionStoreSqlAdapter.php`: leer/persistir ACR y método reales (existe la columna `_oauth_session.acr`, pero es `TINYINT`: hace falta mapear el nivel numérico a valores ACR publicables, o cambiar el tipo con una nueva migración); `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php` emite claims demostrables.
- `src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php` y `test/integration/Features/OAuth/`: retirar ACR no implementados hasta verificar login normal, step-up e ID hint incorrecto.
