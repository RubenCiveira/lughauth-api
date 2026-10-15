# 061 — Decidir soporte del parámetro OIDC `claims`

- **Estado:** anunciado sin implementar. **Prioridad:** baja.
- **Evidencia:** [OpenIdConfiguration](../../src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php) declara `claims_parameter_supported: true`; [OidcFlowContext](../../src/Features/OAuth/Authentication/Domain/OidcFlowContext.php) no analiza `claims`.
- **Impacto:** RP pueden pedir claims esenciales que se ignoran.
- **Por hacer:** desactivar flag en 030 o modelar claims solicitados en autorización, consentimiento y emisión/UserInfo, con errores para claims esenciales no soportados.
- **Aceptación:** flag false hasta que `claims` influya realmente en la respuesta.
- **Origen:** análisis OAuth §4/§5.1.

## Detalle de implementación (solo si se aprueba)

- `src/Features/OAuth/Authentication/Domain/OidcFlowContext.php` y `AuthenticationRequest.php`: parsear `claims` (`id_token`/`userinfo`), validar sintaxis, esenciales y valores permitidos.
- `src/Features/OAuth/Session/Domain/TemporalAuthCode.php` y `src/Features/OAuth/Authentication/Application/` (mapper de 038): preservar solicitud y construir solo claims consentidas/disponibles en ID token y UserInfo.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/UserInfoController.php`: respetar claims autorizadas sin ignorar scopes; `src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php` activa flag solo con soporte real.
- `test/integration/Features/OAuth/`: claim opcional, esencial ausente y parámetro malformado.
