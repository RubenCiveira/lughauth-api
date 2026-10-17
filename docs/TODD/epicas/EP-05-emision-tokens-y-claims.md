# EP-05 — Emisión de tokens y claims

- **Tareas:** [038](../038-extraer-emision-tokens.md) (extraer emisión del controller), [032](../032-claims-id-token.md) (claims del ID token), [049](../049-perfil-at-jwt.md) (perfil access RFC 9068) y [064](../064-respuestas-token-y-scopes.md) (errores, no-store y scopes exactos). Incluye el **`sid` público** (hallazgo 2 de [MODELO_Y_ESQUEMA](../MODELO_Y_ESQUEMA_018-071.md)), que no tiene tarea propia.
- **Prioridad:** alta. **Tamaño:** L. **Depende de:** EP-04. **Desbloquea:** EP-08 (`at_hash`/`c_hash`), EP-09, EP-12, EP-13, EP-15.
- **Esquema:** B.1 en `_oauth_session`: `sid` (público, `UNIQUE`), `tenant_id`, `amr`. `auth_time` ya existe pero `loadSession` no lo lee.

## Por qué juntas

Las cuatro cambian cómo [TokenController::handlePost](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php) construye claims y respuestas. 038 crea el lugar donde viven esas reglas, y 032, 049 y 064 las corrigen. Hacer 032/049/064 antes que 038 obliga a corregir dos veces (controller y [OidcResponseBuilder](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Services/OidcResponseBuilder.php)); hacerlas después es aplicarlas en un solo sitio.

## El `sid` publicado es la cookie

Hoy el `sid` de refresh, logout token y front-channel ([LogoutController.php:129](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/LogoutController.php#L129)) es `_oauth_session.session`, que es el valor de la cookie de sesión. Cualquier RP participante recibe la credencial de sesión del usuario. 032 exige `sid` en el ID token, lo que lo empeoraría si se hace antes de separarlo.

## Componentes comunes

- **`TokenFactory`** (Application): emite access, ID y refresh por grant con expiraciones centralizadas (`tenant-config.refresh-token-ttl-seconds` en lugar de `PT10H`), usando `TokenSigner` con `TokenType` (EP-04).
- **`ClaimsMapper`** (Application): claims por tipo de token; único lugar para scopes → claims, audiencias, `auth_time`/`amr`/`acr`/`sid`, `at_hash` y `c_hash`. Lo reutilizan el token endpoint, `OidcResponseBuilder`, y más adelante UserInfo (EP-06) y las extensiones (EP-15).
- **`SessionInfo`** expone `publicSid`, `authTime`, `amr`, `acr` y `tenantId`.

## Plan

1. **`sid` público.**
   - Migración B.1: `sid VARCHAR(64) NULL UNIQUE`, `tenant_id`, `amr`; rellenar `sid` en sesiones vivas y pasar a `NOT NULL` cuando el código lo escriba.
   - [SessionStoreSqlAdapter](../../../src/Features/OAuth/Session/Infrastructure/Driven/SessionStoreSqlAdapter.php): escribir los nuevos campos, leer `auth_time` y buscar por `sid`.
   - Sustituir el identificador de sesión por el `sid` público en refresh, logout token, front-channel y `BackChannelLogoutDispatcher`. [ResolverForRefresh](../../../src/Features/OAuth/Authentication/Application/TokenGranter/ResolverForRefresh.php) resuelve la sesión por `sid`.
   - Los refresh emitidos con el `sid` antiguo dejan de valer: anunciarlo o aceptar ambos durante `fexp`.
2. **Extracción (038), sin cambiar comportamiento.** Tests de caracterización de los grants code, refresh, device, password y client_credentials; mover a `TokenFactory`/`ClaimsMapper`; `TokenController` y `OidcResponseBuilder` delegan. Registrar dependencias en [OAuthPlugin](../../../src/Bootstrap/Plugin/OAuthPlugin.php).
3. **Respuestas y scopes (064).** Tokenizar scopes exactos en [AuthenticationRequest](../../../src/Features/OAuth/Authentication/Domain/AuthenticationRequest.php) (`myprofile` ≠ `profile`); `Cache-Control: no-store` y `Pragma: no-cache` en éxito y error; `invalid_grant` frente a `invalid_client` en [OAuthTokenException](../../../src/Features/OAuth/Authentication/Domain/Exception/OAuthTokenException.php).
4. **ID token (032).** `aud` = solo el RP; `auth_time` de la sesión (nunca del instante de emisión); `amr`/`acr` solo con evidencia real; `sid` público; `at_hash` cuando se emite access. Funciones de `at_hash`/`c_hash` en el mapper para que EP-08 las use en el front-channel. `nonce` del code, nunca del `/token`.
5. **Access token (049).** Perfil RFC 9068: `iss`, `sub`, `aud`, `client_id`, `iat`, `exp`, `jti`, `scope`; la cabecera `typ: at+jwt` ya la pone EP-04.

## Pruebas

- Caracterización antes de refactorizar y comparación después, grant a grant ([TokenEndpointTest](../../../test/integration/Features/OAuth/TokenEndpointTest.php)).
- Claims por flujo (code, refresh, device, hybrid); `myprofile` no da `name`; cabeceras anticaché; errores OAuth.
- Ningún token ni URL contiene el valor de la cookie de sesión.

## Aceptación

Unión de 032, 038, 049 y 064, más: el `sid` publicado no permite reconstruir la cookie de sesión.

## Coordinación

- El `amr` real lo escriben el login y el MFA (EP-10: `pwd`, `otp`, federado).
- La validación de presencia de `nonce` por `response_type` (070) está en EP-08.
