# 067 — Evaluar hints y step-up ACR en autorización

- **Estado:** pospuesta (opcional de [EP-09](../done/EP-09-sesion-sso-y-logout.md), cerrada sin aprobarla); no implementado, opcional. **Prioridad:** baja.
- **Evidencia:** [OidcFlowContext](../../../src/Features/OAuth/Authentication/Domain/OidcFlowContext.php) captura `prompt` pero no `login_hint`, `id_token_hint` ni `acr_values`; [OpenIdConfiguration](../../../src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php) no publica actualmente `acr_values_supported`.
- **Impacto:** RP no pueden orientar la cuenta ni solicitar un nivel de autenticación verificable.
- **Por hacer:** decidir semántica y privacidad de hints, comprobar firma/tenant del ID hint, definir ACR reales por método/MFA y forzar step-up cuando se solicite; anunciar solo ACR demostrables en discovery.
- **Aceptación:** ACR solicitado no se afirma sin autenticación suficiente y los hints no autentican por sí mismos.
- **Origen:** análisis OAuth §5.1, capacidades `login_hint`/`id_token_hint`/`acr_values`.

## Por qué se plantea

Una petición de autorización puede necesitar orientar al usuario hacia una cuenta concreta (`login_hint`), aportar contexto sobre una sesión anterior (`id_token_hint`) o pedir una garantía de autenticación mayor (`acr_values`), por ejemplo MFA antes de una acción sensible. Son tres necesidades relacionadas, pero distintas: un hint es una sugerencia, mientras que un ACR representa un nivel que el proveedor debe poder comprobar. El flujo actual ya contempla `prompt`, `max_age` y vigencia de la sesión SSO; no interpreta estos parámetros adicionales ni vincula un ACR solicitado a la suficiencia de la sesión. Sin una definición de niveles y una demanda concreta de clientes, afirmar soporte sería engañoso.

## Qué conseguiría implementarla

- Los RP podrían facilitar la selección de cuenta sin que un `login_hint` o un `id_token_hint` otorguen acceso automáticamente; el token sugerido se comprobaría contra firma, emisor, audiencia y tenant antes de usarlo como pista.
- Un RP podría solicitar un nivel ACR concreto y el servidor decidiría si la sesión actual basta, si debe pedir reautenticación o MFA, o si no puede cumplirlo. Con `prompt=none` y necesidad de interacción devolvería el error apropiado en lugar de afirmar un nivel no alcanzado.
- El nivel realmente obtenido podría persistirse y reflejarse coherentemente en los tokens y en discovery, con pruebas de sesiones normales, step-up y peticiones imposibles de satisfacer.

## Qué ocurre mientras siga pospuesta

El login y las reglas actuales de sesión (`prompt`/`max_age`) siguen funcionando, pero los RP no pueden confiar en que `login_hint` seleccione una cuenta, que `id_token_hint` oriente la autorización o que `acr_values` fuerce MFA. El MFA exigido por la política general del tenant es independiente de que un cliente solicite step-up. Discovery no ofrece una lista de ACR compatibles; un cliente que necesite una garantía concreta no puede obtenerla solo enviando el parámetro ni deducirla de la presencia de MFA en otros flujos.

## Detalle de implementación (solo si se aprueba)

- `src/Features/OAuth/Authentication/Domain/OidcFlowContext.php`: analizar `login_hint`, `id_token_hint` y `acr_values` con límites y normalización de tipos.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php` y `OidcStepRouter.php`: precargar cuenta sin autenticar por el hint; forzar MFA/reautenticación cuando ACR pedido exceda la sesión actual.
- `src/Features/OAuth/Session/Domain/SessionInfo.php` y `Infrastructure/Driven/SessionStoreSqlAdapter.php`: leer/persistir ACR y método reales (existe la columna `_oauth_session.acr`, pero es `TINYINT`: hace falta mapear el nivel numérico a valores ACR publicables, o cambiar el tipo con una nueva migración); `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php` emite claims demostrables.
- `src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php` y `test/integration/Features/OAuth/`: mantener sin anunciar ACR no implementados y publicar únicamente los verificados tras probar login normal, step-up e ID hint incorrecto.
