# 035 — Completar UserInfo GET/POST y errores bearer

- **Estado:** hecho (EP-06). **Prioridad:** media.
- **Evidencia:** [OAuthPlugin](../../../src/Bootstrap/Plugin/OAuthPlugin.php) registra solo GET; [UserInfoController](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/UserInfoController.php) filtra perfil/phone por scope y mapea `address` internamente, pero no lo devuelve; tampoco `email_verified`.
- **Impacto:** RP que usa POST o solicita claims habituales no interoperará; hay que confirmar identidad y tipo de token.
- **Por hacer:** añadir POST con bearer, `WWW-Authenticate` en 401, claims disponibles según scope, comprobar `openid`, `sub` y uso de access token del tenant correcto.
- **Aceptación:** GET/POST producen mismos claims autorizados y rechazan ID/refresh tokens y bearer inválido.
- **Origen:** análisis OAuth §5.1/1.6 y `_2.md` línea base.

## Detalle de implementación

- `src/Bootstrap/Plugin/OAuthPlugin.php`: registrar POST `/userinfo` además del GET ya existente.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/UserInfoController.php`: reutilizar mapeo para ambos métodos, incluir `email_verified`/`address` solo si se conocen y el scope lo permite; responder 401 con `WWW-Authenticate` cuando el bearer sea inválido.
- `src/Bootstrap/Middleware/JwtVerifierMiddleware.php`: validar access token con `openid`, tenant/issuer/audience y revocación antes de poblar la identidad; no equiparar JWT ID/refresh a bearer.
- `test/integration/Features/OAuth/`: comprobar GET/POST equivalentes, claims por scope y errores 401.
