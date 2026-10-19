# 022 — Definir y aplicar pertenencia de TrustedClient al tenant

- **Estado:** hecho (EP-03). **Prioridad:** alta.
- **Evidencia:** [ClientStoreAdapter::publicClientData](../../src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php) recibe `$tenant` pero no lo usa; [DynamicClientAdapter](../../src/Features/OAuth/Client/Infrastructure/Driven/DynamicClientAdapter.php) guarda clientes DCR como `TrustedClient` globales.
- **Impacto:** clientes DCR de un tenant pueden reutilizarse o recibir eventos de otro.
- **Por hacer:** acordar si se permiten clientes globales; si no, agregar pertenencia al modelo/esquema, migrar datos, filtrar todas las lecturas/autenticaciones/DCR/logout por tenant y definir el comportamiento de clientes compartidos.
- **Aceptación:** cliente de tenant A no obtiene codes/tokens ni notificaciones de B salvo permiso explícito de alcance global.
- **Origen:** análisis OAuth §3.5/0.6 y `_2.md` brecha 1.

## Detalle de implementación

- `src/Features/Access/TrustedClient/Domain/TrustedClientAttributes.php`, sus VO/accessors y `Infrastructure/Connector/Pdo/TrustedClientPdoConnector.php`: añadir relación tenant (o pertenencia múltiple/global explícita) en la **fuente de generación** (entidad `trusted-client` del modelo vigente; la copia de `multitenant-oidc.xml` en la raíz está desfasada, ver 001) y regenerar la entidad, plugin y DTO afectados.
- `migrations/mysql/schema/1.0.0/`: crear migración nueva para columna/relación e índice y migrar clientes actuales con una regla de pertenencia revisada; no alterar migraciones históricas.
- `src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php` y `DynamicClientAdapter.php`: filtrar lecturas y altas DCR por tenant; adaptar `src/Features/OAuth/Client/Domain/Gateway/ClientStoreGateway.php` para que cada consulta tenga contexto tenant, incluido `clientData`.
- `src/Features/OAuth/Authentication/Application/BackChannelLogoutDispatcher.php` y `LogoutController.php`: excluir clientes de otros tenants; probar el aislamiento en `test/integration/Features/OAuth/`.
