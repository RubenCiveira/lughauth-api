# 021 — Notificar logout solo a RP participantes

- **Estado:** hecho. **Prioridad:** alta.
- **Evidencia:** [BackChannelLogoutDispatcher](../../src/Features/OAuth/Authentication/Application/BackChannelLogoutDispatcher.php) y [LogoutController](../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/LogoutController.php) recorren todos los TrustedClient habilitados; las llamadas back-channel son HTTP síncrono y el iframe frontal carece de `iss`/`sid`.
- **Impacto:** difunde identidad/sesión a RP no participantes, incluso de otros tenants, y aumenta latencia del logout.
- **Por hacer:** persistir RP participantes por `sid`/tenant, filtrar destinatarios, enviar back-channel en cola e incluir parámetros exigidos en front-channel según configuración del cliente.
- **Aceptación:** un RP ajeno no recibe `sub`/`sid`; un RP participante sí, sin bloquear la respuesta al usuario.
- **Origen:** análisis OAuth §3.5/0.5 y `_2.md` logout.

## Detalle de implementación

- `src/Features/OAuth/Session/Domain/Gateway/SessionStoreGateway.php` y `src/Features/OAuth/Session/Infrastructure/Driven/SessionStoreSqlAdapter.php`: guardar/consultar RP participantes por `sid` y tenant; estudiar `sso_clients_json` existente y no confiar solo en el cliente de la última petición.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Services/OidcResponseBuilder.php` y `src/Features/OAuth/Authentication/Application/AuthenticateUser.php`: registrar la participación al conceder autorización, preservando el `sid` real.
- `src/Features/OAuth/Authentication/Application/BackChannelLogoutDispatcher.php` y `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/LogoutController.php`: consumir solo participantes habilitados, aislar el envío HTTP en trabajo de cola y añadir `iss`/`sid` al front-channel según las flags del RP.
- `test/integration/Features/OAuth/`: cubrir dos clientes participantes y uno ajeno, incluidos tenants distintos y fallo de entrega sin bloquear logout.

## Decisiones tomadas

- **Participantes:** se reutiliza `sso_clients_json` (tarea 019). `SessionInfo::clients()` devuelve el cliente que abrió la sesión más los que se unieron por SSO; el registro ya lo hace `AuthenticateUser` al reutilizar sesión, así que no hay migración nueva. La sesión se busca con `findActiveSession(sid, issuer del tenant)`, de modo que una sesión de otro tenant no notifica a nadie ni se borra.
- **Resolución de clientes:** los participantes se resuelven con `findOneByTenantAndCode(null, code)` (clientes globales, como `ClientStoreAdapter`) hasta la tarea 022. Solo se notifica a los habilitados.
- **Back-channel en cola:** `BackChannelLogoutDispatcher` recibe los participantes y deja un trabajo con `register_shutdown_function` (el mismo patrón que el outbox urgente), que se ejecuta después de `fastcgi_finish_request`. La firma del `logout_token` y el POST van dentro del trabajo y cada fallo se registra como WARNING sin cortar el resto. No hay reintentos: si el proceso muere, la notificación se pierde. El logger pasa a `LoggerAwareTrait`; antes siempre era `NullLogger`.
- **Front-channel:** solo iframes de participantes habilitados; cuando `frontchannel_logout_session_required` está activo se añaden `iss` y `sid`.
- **Sin sesión:** si la cookie no lleva a una sesión activa (aunque llegue `id_token_hint`), no se notifica a ningún RP; ya no se usa el `sub` del hint para difundir el logout.
- **Validar antes de cerrar:** `client_id` y `post_logout_redirect_uri` se validan antes de tocar la sesión. Una petición inválida devuelve 400 y conserva la sesión sin notificar a nadie; una válida cierra la sesión y avisa a los participantes. Así nunca se cierra la sesión sin avisar a los RP. El POST y la confirmación/CSRF siguen pendientes en la 036.
- **Participación ligada al tenant:** `findActiveSessionIdByCsid(csid, issuer)` solo reutiliza sesiones del issuer del tenant, y `addSessionClient(state, issuer, clientId)` no hace nada si la sesión es de otro issuer. Esto cubre tanto la reutilización por `csid` como la rama de sesión activa de `AuthenticateUser`, para que un cliente no quede registrado como participante de una sesión ajena.
- **Sin autorizaciones desde sesiones ajenas:** `AuthorizeHtml` carga la sesión de la cookie con `SessionManager::findActiveSession(sid, issuer)`, así que una sesión de otro tenant cuenta como "sin sesión" (login, o error con `prompt=none`). Como defensa adicional, `AuthenticateUser::saveIt` lanza `UnauthorizedException` si se le pide reutilizar una sesión de otro issuer, de modo que nunca se emite un código ligado a un `sid` del que el RP no es participante.
- **Incorporaciones SSO concurrentes:** `SessionStoreSqlAdapter::addSessionClient` hace un único `UPDATE` con `JSON_ARRAY_APPEND` condicionado a `NOT JSON_CONTAINS` (y a issuer, caducidad y que no sea el cliente que abrió la sesión), en lugar de leer y reescribir la lista. El bloqueo de fila de InnoDB serializa las incorporaciones simultáneas, así que ninguna se pierde y un mismo cliente no se duplica.
- **Pruebas:** `test/integration/Features/OAuth/LogoutEndpointTest.php` y, contra MySQL, `test/integration/Features/OAuth/SessionStoreSqlAdapterTest.php` (8 procesos que se unen a la vez a la misma sesión, 5 rondas; se salta si no hay MySQL).
