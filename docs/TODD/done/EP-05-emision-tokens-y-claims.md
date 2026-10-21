# EP-05 — Emisión de tokens y claims

- **Tareas:** [038](038-extraer-emision-tokens.md) (extraer emisión del controller), [032](032-claims-id-token.md) (claims del ID token), [049](049-perfil-at-jwt.md) (perfil access RFC 9068) y [064](064-respuestas-token-y-scopes.md) (errores, no-store y scopes exactos). Incluye el **`sid` público** (hallazgo 2 de [MODELO_Y_ESQUEMA](../MODELO_Y_ESQUEMA_018-071.md)), que no tiene tarea propia.
- **Estado:** hecho. **Prioridad:** alta. **Tamaño:** L. **Depende de:** EP-04. **Desbloquea:** EP-08 (`at_hash`/`c_hash`), EP-09, EP-12, EP-13, EP-15.
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

## Resultado

### Decisiones tomadas

- **`sid` público aleatorio:** `SessionStoreSqlAdapter::saveSession` genera `sid` con 16 bytes aleatorios (32 hex), sin relación con la cookie (`session`). La migración `20261021120000` lo añade (`NOT NULL` + `UNIQUE` desde el principio, porque el código ya lo escribe), rellena las sesiones vivas con un valor aleatorio, añade `tenant_id` (rellenado desde `issuer`) y `amr`, y el índice `(user_uid, tenant_id)`. El puerto gana `findActiveSessionBySid` y `deleteSessionBySid`; `findActiveSession` queda para la cookie (authorize, logout).
- **Dónde se publica:** refresh (`sid`), ID token (`sid`), logout token y front-channel (`sid` en la URL) llevan solo `SessionInfo::publicSid`. `PublicLoginAuthResponse::sessionId` y `TemporalAuthCode::sessionId` siguen siendo la cookie, pero no salen del servidor.
- **Sin compatibilidad para refresh antiguos:** un refresh con el `sid` antiguo (la cookie) ya no encuentra sesión y se rechaza con `invalid_grant`. Como EP-04 ya obligaba a volver a iniciar sesión al desplegar, no se añade ventana.
- **`SessionInfo`** expone `publicSid`, `authTime` (columna `auth_time`, que ahora se lee), `amr`, `acr` y `tenantId`. `acr` es siempre `null` hasta que 067 mapee la columna `TINYINT` a un valor configurado: un número sin mapear no es evidencia.
- **`amr` solo con evidencia:** `AuthenticateUser` guarda `pwd` cuando la sesión nace de usuario y contraseña comprobados en ese paso, y `mfa` cuando se completó el segundo factor; en cualquier otro caso (magic link, invitación, WebAuthn, delegado) no se escribe nada y el ID token no lleva `amr`. Los valores finos (`otp`, `hwk`, federado) son de EP-10.
- **Componentes:** [TokenGrant](../../../src/Features/OAuth/Authentication/Domain/TokenGrant.php) (lo que autoriza un grant o una respuesta de autorización), [ClaimsMapper](../../../src/Features/OAuth/Authentication/Application/ClaimsMapper.php) (claims por tipo, `hash()` para `at_hash`/`c_hash`/`s_hash`) y [TokenFactory](../../../src/Features/OAuth/Authentication/Application/TokenFactory.php) (`tokenResponse` para `/token`, `authorizationResponse` para el front-channel). `TokenController` queda en parseo HTTP, autenticación de cliente y resolución del grant; `OidcResponseBuilder` en la construcción de la URL. `AuthenticationResult::toJwtUserClaims`, `PublicLoginAuthResponse::{idData, authExpiration, idExpiration, accessTokenClaims}` y `AuthenticateUser::generateHash` desaparecen.
- **Expiraciones centralizadas** ([TokenLifetimes](../../../src/Features/OAuth/Authentication/Domain/TokenLifetimes.php) por el puerto `TokenLifetimesGateway`): refresh = `tenant-config.refresh-token-ttl-seconds` y familia = `refresh-token-max-lifetime-seconds` (antes `PT10H` y `P30D` fijos, que quedan como valores por defecto si el tenant no tiene configuración o el valor no es positivo). Access e ID siguen en 10 minutos; `client_credentials` usa el TTL m2m del cliente.
- **ID token:** `aud` = `azp` = cliente; `auth_time` de la sesión (nunca el instante de emisión); `amr`/`acr`/`sid` solo si la sesión los tiene; `nonce` solo del code o de la petición de autorización (nunca en `/token` por refresh); `at_hash` siempre que se emite con un access; `c_hash` y `s_hash` en el front-channel. Ya no lleva `scope`, `typ` ni `session_state` (este último era la cookie). `name`/`email` solo con los scopes exactos `profile`/`email`, también en implícito (antes siempre).
- **Access token (RFC 9068):** `iss`, `sub`, `aud` (cliente y recursos concedidos), `client_id` (antes solo en `client_credentials`), `scope`, más `iat`/`exp`/`jti` del firmante y `typ: at+jwt` de EP-04. Se conservan `azp` (el middleware lo usa), `tenant`, `roles`, `groups` y `tenancy`. No lleva `auth_time`, `amr` ni `sid`.
- **Front-channel firmado con la clave del tenant:** `OidcResponseBuilder` firmaba con `PublicLoginAuthResponse::tenant`, que es el **uid** del tenant, no su nombre, así que esos tokens no se podían verificar con el JWKS publicado. Ahora firma con el nombre, como `/token`.
- **Scopes (064):** [ScopeSet](../../../src/Features/OAuth/Authentication/Domain/ValueObject/ScopeSet.php) tokeniza por espacios y elimina duplicados; `AuthenticationRequest` normaliza su `scope` para todos los grants. El grant `password` pasa ahora el `scope` pedido (antes vacío), así que también puede pedir consentimiento de scopes y fallar con `invalid_grant`.
- **Scope en refresh (RFC 6749 §6):** el refresh guarda el scope concedido en `scope` (antes `["refresh"]`). Sin `scope` en la petición se conserva; con `scope` solo puede estrecharse (si no, `invalid_scope`), y el refresh rotado conserva el original. La revocación distingue el refresh por `token_use` en vez de por `scope`.
- **Respuestas (064):** todas las respuestas de `/token`, éxito o error (también las del device grant), llevan `Cache-Control: no-store` y `Pragma: no-cache`. Los `UnauthorizedException` genéricos (401 sin cuerpo OAuth) pasan a `invalid_grant` (refresh inválido, credenciales del usuario, replay); un `grant_type` ausente es `invalid_request` y uno sin estrategia `unsupported_grant_type` (nuevo constructor). La respuesta incluye `scope`.
- **Code ligado a sesión:** un code emitido desde una sesión solo se canjea si la sesión sigue activa en el issuer y es del mismo usuario; si no, `invalid_grant` (antes se emitía un refresh que ya no servía).
- **Discovery:** `claims_supported` añade `aud`, `auth_time`, `amr` y `sid`.
- **API de sesiones del perfil:** `GET /api/me/sessions` devolvía la cookie como `session_id`. Ahora devuelve el `sid` público (mismo nombre de campo), y `ActiveSession::sid` sustituye a `sessionId`. Como los RPs conocen el `sid`, `SessionsGateway::revoke(userUid, sid)` solo borra sesiones del usuario autenticado. Antes `DELETE /api/me/sessions/{id}` no comprobaba el dueño; bastaba con que nadie más conociera la cookie. La página de perfil marca la sesión actual por su `sid`.
- **Respuesta híbrida en el fragmento:** con `code id_token`, `code token` o `code id_token token`, `state`, `code` y los tokens iban en la query del redirect, así que llegaban al servidor del RP. Ahora toda respuesta con tokens va en el fragmento y solo `code` usa la query. El error ya existía antes de EP-05. La codificación de parámetros y `response_mode` siguen en EP-08.

### Qué cambió

- Migración `20261021120000` (B.1). En la BD de desarrollo sin `phinxlog` se aplicó el SQL directamente.
- Pruebas: `TokenEndpointTest` (claims del ID por code y refresh, `auth_time` de la sesión, `amr` con y sin evidencia, perfil RFC 9068, `myprofile`, ningún token contiene la cookie, estrechar y no ampliar el scope en refresh, cabeceras anticaché, `invalid_request`/`invalid_grant`, code de sesión ajena o cerrada), [AuthorizationResponseTokensTest](../../../test/integration/Features/OAuth/AuthorizationResponseTokensTest.php) (híbrido con `c_hash`/`at_hash`/`s_hash`/`sid`, firmado con la clave del tenant, sin cookie en la URL), `LogoutEndpointTest` (logout token y front-channel con `sid` público), `SessionStoreSqlAdapterTest` (MySQL: `sid` aleatorio, búsqueda por `sid` e issuer, `amr`, `auth_time`, `tenant_id`), `ResolversUnitTest` (refresh con la cookie como `sid` rechazado), `ClaimsMapperUnitTest` (incluido el vector de `at_hash` de OIDC Core §A.3), `ScopeSetUnitTest` y `TokenLifetimesUnitTest`.

### Pendiente fuera de esta épica

- `acr` real y su mapeo (067, EP-09) y `amr` fino por método (EP-10).
- El ID token se sigue emitiendo aunque falte el scope `openid`; exigirlo es correcto según OIDC pero rompería clientes que no lo piden.
- `SessionStoreSqlAdapter::updateSession` intercambia sus parámetros (escribe el estado nuevo en `expiration`); no tiene llamadas, así que no se ha tocado.
- Si un tenant configura una familia de refresh mayor que la retención de claves (`KeyConfig::retention`, 30 días), los refresh del final de la familia dejarán de verificarse cuando se purgue su clave.
