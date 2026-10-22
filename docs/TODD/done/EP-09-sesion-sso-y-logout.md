# EP-09 — Sesión SSO y logout

- **Tareas:** [031](031-prompt-max-age-sso.md) (`prompt`, `max_age` y vigencia SSO) y [036](036-logout-validado-y-post.md) (logout validado y POST). Opcionales: [067](../067-hints-y-acr-step-up.md) (hints y step-up ACR), [041](../041-admin-sesiones-usuario.md) (API administrativa de sesiones) y [062](../062-session-management-iframe.md) (iframe de Session Management).
- **Estado:** hecho (031 y 036). Los opcionales 067, 041 y 062 siguen pendientes de aprobación.
- **Prioridad:** alta (036), media (031). **Tamaño:** M (+ opcionales). **Depende de:** EP-04 (verificar `id_token_hint`) y EP-05 (`sid` público y `auth_time`/`amr`/`tenant_id` en `SessionInfo`).
- **Esquema:** `_oauth_session.auth_time` existe; `tenant_id` lo añade EP-05. Para 067, revisar `acr` (`TINYINT`) y `tenant-config.acr-values-supported`.

## Por qué juntas

Todas dependen del mismo modelo de sesión ([SessionInfo](../../../src/Features/OAuth/Session/Domain/SessionInfo.php) y [SessionStoreSqlAdapter](../../../src/Features/OAuth/Session/Infrastructure/Driven/SessionStoreSqlAdapter.php)) y de cuándo una sesión «sirve» para una petición:

- 031 y 067 deciden en `AuthorizeHtml`/[OidcStepRouter](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/OidcStepRouter.php) si la sesión vale (edad, `prompt`, ACR).
- 036 y 067 verifican un `id_token_hint` con la misma regla: firma propia, tipo ID, tenant correcto y caducidad tolerada.
- 041 y 062 consultan sesiones por usuario y tenant o exponen su estado.

## Componentes comunes

- **`SessionSufficiencyPolicy`**: dada la sesión y la petición (`prompt`, `max_age`, `acr_values`, TTL SSO del tenant), decide entre reutilizar, reautenticar, step-up, consentimiento o error con `prompt=none`.
- **`IdTokenHintVerifier`**: `TokenSigner::verify(TokenType::Id)` admitiendo `exp` vencido, con tenant y `aud` comprobados; devuelve `sub`, `sid` y `client_id`.

## Plan

1. **Política de sesión (031).**
   - `OidcFlowContext` extrae la lista de `prompt` (rechaza `none` combinado) y `max_age`.
   - `SessionSufficiencyPolicy` usa `auth_time` real y `tenant-config.session-sso-ttl-seconds`.
   - `login` fuerza login, `consent` fuerza `ScopeConsentForm`, `none` devuelve error sin UI y `select_account` se define o se rechaza.
2. **Logout (036).** La 021 ya valida `client_id` y `post_logout_redirect_uri` antes de cerrar. Falta:
   - `IdTokenHintVerifier` sobre el hint.
   - Página de confirmación con CSRF cuando no hay hint fiable.
   - POST `/logout` en [OAuthPlugin](../../../src/Bootstrap/Plugin/OAuthPlugin.php) con la misma lógica que GET.
   - Coincidencia exacta de `post_logout_redirect_uri`, sin el atajo por URL base de `ClientStoreAdapter`.
3. **Opcional (067).** `login_hint` precarga la cuenta sin autenticar; `id_token_hint` con el verificador del paso 2; `acr_values` mapeados por `tenant-config.acr-values-supported` y step-up a MFA cuando la sesión no alcanza. Volver a publicar ACR en discovery.
4. **Opcional (041).** `AdminUserSessionController` con listado y revocación por `userUid` restringidos a `tenant_id`, guard de admin y comprobación en el caso de uso.
5. **Opcional (062).** Iframe de Session Management con `session_state` (derivado del `sid` público, nunca de la cookie), en ruta separada de `/check-session`.

## Pruebas

- Sesión recién autenticada o caducada, `max_age=0`, `prompt=none/login/consent` y TTL por tenant.
- Logout: petición inválida mantiene la sesión; POST o confirmación válidos la cierran y redirigen solo a la URI registrada; hint de otro tenant o falsificado rechazado.
- Opcionales: step-up, hint erróneo, admin frente a usuario ordinario y tenant cruzado.

## Aceptación

Unión de 031 y 036 (y de los opcionales aprobados).

## Implementación

### 031: `prompt`, `max_age` y vigencia SSO

- **Petición:** [OidcFlowContext](../../../src/Features/OAuth/Authentication/Domain/OidcFlowContext.php) lee `max_age` (`requestedMaxAge`, `maxAge()`) y expone `prompt` como [Prompt](../../../src/Features/OAuth/Authentication/Domain/ValueObject/Prompt.php), una lista de valores. `AuthorizeHtml::rejectInvalidRequest` devuelve `invalid_request` si `none` va combinado con otro valor o si `max_age` no es un entero no negativo. Los valores desconocidos se ignoran. `max_age` viaja en `continuationParams()`, así que `/check-session` también lo recibe.
- **Política:** [SessionSufficiencyPolicy](../../../src/Features/OAuth/Authentication/Application/SessionSufficiencyPolicy.php) decide entre `Reuse`, `Authenticate` y `LoginRequired` ([SessionDecision](../../../src/Features/OAuth/Authentication/Domain/SessionDecision.php)). La sesión se reutiliza si no hay `prompt=login` ni `select_account` y si el `auth_time` real de la sesión está dentro de la vida SSO del tenant y, si se envió, de `max_age`. Una sesión sin `auth_time` nunca está vigente. Si no se reutiliza, se pide login, o `login_required` con `prompt=none`.
- **Vida SSO por tenant:** puerto [SessionSsoTtlGateway](../../../src/Features/OAuth/Authentication/Domain/Gateway/SessionSsoTtlGateway.php) y adaptador [TenantConfigSessionSsoTtlAdapter](../../../src/Features/OAuth/Authentication/Infrastructure/Driven/TenantConfigSessionSsoTtlAdapter.php) sobre `tenant-config.session-sso-ttl-seconds`. Sin configuración, o con un valor no positivo, se usan 3600 s, el mismo valor por defecto que `tenant-config`. **Cambio de comportamiento:** antes la sesión servía para SSO durante toda su vida en `_oauth_session` (15 días).
- **Dónde se aplica:** en `GET /authorize` y otra vez en `/check-session`, para que un POST directo a `/check-session` no se salte `max_age` ni `prompt=login`.
- **`select_account`:** se trata como `login`. Un navegador tiene una sola sesión por tenant, así que elegir otra cuenta es iniciar sesión con ella.
- **`prompt=consent`:** vuelve a mostrar `ScopeConsentForm` con **todos** los scopes pedidos (`ScopesConsentUsecase::pendingScopes(..., reconsent: true)`). Con sesión, `/check-session` muestra el consentimiento antes de autorizar. Tanto en `/check-session` como en `formAuthorize`, cualquier paso que termine bien, salvo el propio consentimiento de scopes, lleva al consentimiento: un `step` enviado en el POST no puede saltárselo. Un scope opcional que se deja sin marcar se retira de la aprobación anterior (`ScopesConsentGateway::storeAcceptedScopes(..., $declinedScopes)`). Antes se unía a lo ya aceptado, así que una solicitud posterior lo daba por consentido sin preguntar. `ScopePermission::requested()` concentra la regla de que `openid` es obligatorio.
- **`prompt=none`:** sin sesión válida, `login_required`. Si la sesión necesita un paso del usuario, `consent_required` (términos o scopes) o `interaction_required` (MFA, contraseña nueva…). Ninguno de estos errores toca la cookie de sesión, ni siquiera `login_required` por `max_age` o por la vida SSO, porque la sesión puede servir a otra petición.
- **`auth_time` al reautenticar:** [AuthenticateUser](../../../src/Features/OAuth/Authentication/Application/AuthenticateUser.php) reutilizaba la sesión del mismo navegador (`csid`) sin tocar `auth_time`, así que con `max_age` el usuario volvía al login una y otra vez. Ahora `SessionStoreGateway::renewAuthentication` mueve `auth_time` a ahora y, si se indican, cambia los `amr`. Además, la sesión del `csid` solo se reutiliza si es **del mismo usuario**. Antes, otra cuenta que iniciara sesión en ese navegador heredaba la sesión del usuario anterior.
- **Bucle evitado:** la redirección de `runSessionStep` tras guardar un consentimiento (instantánea `REPEATABLE READ`) ya no lleva `prompt` ni `max_age`.

### 036: logout validado, confirmación y POST

- **`id_token_hint`:** [IdTokenHintVerifier](../../../src/Features/OAuth/Authentication/Application/IdTokenHintVerifier.php) exige firma propia, `TokenType::Id`, `iss` igual al issuer canónico del tenant y un cliente (`azp`, o su única `aud`) contenido en `aud`. Si llega `client_id`, debe ser ese cliente. Acepta tokens caducados. Devuelve [IdTokenHint](../../../src/Features/OAuth/Authentication/Domain/IdTokenHint.php) (`sub`, `sid`, cliente). Un hint inválido da 400 sin tocar la sesión.
- **Confirmación:** si hay sesión y el hint no se emitió desde ella (mismo `sid` público, o el mismo `sub` si no trae `sid`), o si no hay hint, [LogoutController](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/LogoutController.php) muestra una página de confirmación (`Cache-Control: no-store`, traducida en `forms*.yaml` > `logout.confirm`). La página hace POST a `/logout` con `logout_confirmation` = HMAC-SHA256 con la cookie de sesión como clave. Un tercero no conoce la cookie, así que no puede generarlo. El token solo cuenta por POST. Sin sesión no hay nada que confirmar y se redirige directamente.
- **Cookies:** se borran con `OidcCookieManager::clearSession`, con la misma ruta con la que se crean. Antes `LogoutController` usaba la URL completa como `Path`, así que el navegador conservaba la cookie aunque la sesión ya no existiera en el servidor. Además, [Cookie::remove](../../../src/Shared/Infrastructure/Http/Cookie.php) envía ahora `Expires` en 1970 y `Max-Age=0` en vez de `Expires` igual a la hora actual, que dependía de que los relojes del servidor y del navegador coincidieran.
- **POST `/logout`:** registrado en [OAuthPlugin](../../../src/Bootstrap/Plugin/OAuthPlugin.php) con el mismo manejador. Lee los parámetros del formulario en vez de la query.
- **URI post-logout exacta:** `ClientStoreGateway::allowsPostLogoutRedirect` (en [ClientStoreAdapter](../../../src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php)) solo acepta un cliente habilitado con la URI registrada exactamente, sin el comodín de `localhost`. Ya no exige `publicAllow`, porque un cliente confidencial también puede cerrar sesión. Sigue sin haber un campo propio de `post_logout_redirect_uris`: se usan las URIs de redirección registradas.

### Pruebas

- `AuthorizeHtmlIntegrationUnitTest`: sesión reciente y vencida por TTL, `max_age=0`, `max_age` cumplido y superado, `prompt=login/select_account/consent/none`, `none` combinado y `max_age` inválido. También `/check-session` con la política otra vez, consentimiento forzado con y sin sesión, `consent_required`/`interaction_required` sin borrar la cookie, y finalización tras el consentimiento.
- `SessionSufficiencyPolicyUnitTest`: TTL distinto por tenant, sesión sin `auth_time` y límite exacto de `max_age`.
- `LogoutEndpointTest`: confirmación sin hint y POST confirmado; token de otra sesión, vacío o por GET rechazado; POST con hint; hint caducado aceptado; hint de otra sesión pide confirmación; hint de otro tenant, de otro cliente, con `client_id` distinto o falsificado da 400 y conserva la sesión; URI post-logout no exacta; sin sesión. Además, reautenticación que renueva `auth_time` y sesión `csid` de otro usuario no reutilizada.
- `SessionStoreSqlAdapterTest` (MySQL): `renewAuthentication` limitado al issuer y conservación de `amr`. `ClientStoreAdapterUnitTest`: coincidencia exacta y sin comodín.

### Pendiente fuera de esta épica

- **PAR/JAR con `prompt=consent` o `max_age`:** la redirección de `runSessionStep` no puede quitar `prompt` ni `max_age` de una solicitud firmada (`request_ref`). Si la instantánea `REPEATABLE READ` no ve el consentimiento recién guardado, se vuelve a pedir.
- **Consentimiento forzado fuera de `/authorize`:** el enlace mágico y otros accesos que terminan en su propio endpoint no pasan por `formAuthorize`, así que `prompt=consent` no les afecta.
- **`post_logout_redirect_uris` propias:** el logout reutiliza las URIs de redirección del cliente. Un campo separado en `TrustedClient`/DCR permitiría registrarlas por separado.
- **`prompt_values_supported`** (OIDC Prompt Create) no se anuncia en discovery.
