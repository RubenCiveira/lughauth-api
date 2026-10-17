# EP-09 — Sesión SSO y logout

- **Tareas:** [031](../031-prompt-max-age-sso.md) (`prompt`, `max_age` y vigencia SSO) y [036](../036-logout-validado-y-post.md) (logout validado y POST). Opcionales: [067](../067-hints-y-acr-step-up.md) (hints y step-up ACR), [041](../041-admin-sesiones-usuario.md) (API administrativa de sesiones) y [062](../062-session-management-iframe.md) (iframe de Session Management).
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
