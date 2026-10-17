# EP-07 — Autorización: origen y política de la solicitud

- **Tareas:** [046](../046-par-no-sobrescribible.md) (PAR no sobrescribible y obligatorio por cliente), [047](../047-jar-claims-y-jwks-uri.md) (JAR completo y JWKS remoto seguro), [034](../034-pkce-s256-y-politica.md) (PKCE S256 y política del cliente) y, opcional, [068](../068-ui-locales.md) (`ui_locales`).
- **Prioridad:** alta. **Tamaño:** L. **Depende de:** EP-03 (`requirePkce`, `requirePar` en `ClientData`). **Desbloquea:** EP-10 (051), EP-11, EP-13 (DPoP), EP-15.
- **Esquema:** B.2, nueva tabla `_oauth_replay_jti`. `_oauth_par_request` ya tiene `client_id`, `tenant_id` y `used_at`. A.1/A.2: `require-pushed-authorization-requests` en cliente y en tenant.

## Por qué juntas

Las tres deciden **de dónde salen y qué valen** los parámetros de `/authorize` en [AuthorizeHtml::buildContext](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php) y [OidcFlowContext](../../../src/Features/OAuth/Authentication/Domain/OidcFlowContext.php). Hoy hay dos fusiones inseguras en el mismo método: `array_merge($parRequest->params, $query)` ([línea 388](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php#L388)), donde la query sobrescribe PAR, y `array_merge($query, $verifiedParams)` ([línea 406](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php#L406)) para JAR. La precedencia PAR > JAR > query se resuelve una sola vez. El PKCE se valida justo después, sobre los parámetros ya resueltos.

## Componentes comunes

- **`AuthorizationRequestResolver`**: con `request_uri`, solo admite `client_id` exterior (que debe coincidir con el del PAR) y descarta el resto de la query. Con `request`, usa solo los claims JAR verificados. Rechaza conflictos que degraden lo firmado.
- **`ReplayJtiGateway`** sobre `_oauth_replay_jti` (PK `(tenant_id, client_id, purpose, jti)`; un `INSERT` duplicado = replay). Limpieza en `OAuthCleanupScheduler`. Lo reutilizan EP-11 y EP-13.
- **`RemoteJwksFetcher`** (puerto en Domain y adaptador en Infrastructure): solo HTTPS, bloqueo de rangos privados y loopback (SSRF), timeout, tamaño máximo y caché por cliente. Sale de [RequestObjectValidator](../../../src/Features/OAuth/Authentication/Domain/RequestObjectValidator.php), que hoy hace HTTP desde Domain. Lo reutilizan EP-10 (051), EP-11 (044) y EP-14 (057).

## Plan

1. **Resolver.** Extraer la resolución de parámetros de `buildContext` y cubrirla con los tests de sobrescritura de 046 antes de cambiar la precedencia.
2. **PAR (046).** Igualdad de `client_id`; consumo único atómico en `ParRequestSqlAdapter` (`UPDATE … WHERE used_at IS NULL`); pertenencia a cliente y tenant en [PushAuthorizationUsecase](../../../src/Features/OAuth/Par/Application/Usecase/PushAuthorizationRequest/PushAuthorizationUsecase.php) y `ResolveParRequestUsecase`; `requirePar` por cliente o tenant rechaza el authorize directo. Activar `require_pushed_authorization_requests` en discovery solo si aplica a todo el tenant.
3. **JAR (047).** Validar `iss`, `aud` (issuer canónico), `exp`/`nbf`/`iat` y `jti` (con `purpose=request_object`); sustituir la llamada HTTP por `RemoteJwksFetcher`.
4. **PKCE (034).** [PkceChallenge](../../../src/Features/OAuth/Authentication/Domain/ValueObject/PkceChallenge.php) rechaza `plain` y la ausencia de método, y valida longitud y alfabeto. La misma política al crear el code y al canjearlo en `TokenController`; obligatorio si `requirePkce` o si el cliente es público.
5. **Opcional (068).** `ui_locales` con fallback a `Accept-Language`, propagado a todos los formularios sin persistirlo en el perfil.

## Pruebas

- [ParTest](../../../test/integration/Features/OAuth/ParTest.php): sobrescribir redirect, scope, PKCE o `client_id` desde la query; repetir `request_uri`; cliente con PAR obligatorio usando authorize directo.
- JAR válido, vencido, repetido, con `aud` de otro tenant y `jwks_uri` en red privada.
- `plain` y downgrade rechazados en authorize y en el canje.

## Aceptación

Unión de 034, 046 y 047: la query no altera una solicitud PAR o JAR, los objetos se usan una vez y PKCE es S256 y coherente con el cliente.

## Conflictos

EP-08 toca el mismo `AuthorizeHtml` (respuesta y errores). Hacer EP-07 y EP-08 en serie o con el mismo responsable.
