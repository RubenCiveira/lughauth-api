# 070 — Validar nonce en todos los flujos OIDC

- **Estado:** hecho (EP-08). **Prioridad:** media.
- **Evidencia:** [OidcFlowContext](../../../src/Features/OAuth/Authentication/Domain/OidcFlowContext.php) captura `nonce`; [OidcResponseBuilder](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Services/OidcResponseBuilder.php) lo transporta al code; falta una política explícita por flujo y tests que garanticen su emisión correcta en ID token.
- **Impacto:** RP pueden recibir ID tokens sin vínculo demostrable con su solicitud.
- **Por hacer:** exigir nonce según response_type, persistirlo ligado a la solicitud y devolverlo íntegro en ID token de code/hybrid; cubrir rechazo de valores omitidos o alterados cuando sean obligatorios.
- **Aceptación:** nonce ausente donde se exige da error; ID token contiene exactamente el solicitado, sin confundirlo con parámetros de redirección.
- **Origen:** análisis OAuth §5.1 y `_2.md` línea base.

## Detalle de implementación

- `src/Features/OAuth/Authentication/Domain/OidcFlowContext.php` y `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php`: validar presencia/formato del nonce según `response_type` y no continuar si falta donde es obligatorio.
- `src/Features/OAuth/Session/Domain/TemporalAuthCode.php` y `Infrastructure/Driven/TemporalKeysSqlAdapter.php`: persistir y recuperar el nonce sin modificarlo ni aceptar su sustitución durante el canje.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php` y `Infrastructure/Driver/Html/Services/OidcResponseBuilder.php`: incluir nonce en el ID token de code/hybrid, nunca confiar en el nonce enviado al `/token`.
- `test/integration/Features/OAuth/TokenEndpointTest.php`: comparar nonce solicitado/emitido y probar omisión o alteración durante code exchange.
