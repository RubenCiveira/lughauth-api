# 021 — Notificar logout solo a RP participantes

- **Estado:** pendiente. **Prioridad:** alta.
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
