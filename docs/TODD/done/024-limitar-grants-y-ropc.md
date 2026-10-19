# 024 — Restringir grants de DCR y eliminar ROPC por defecto

- **Estado:** hecho (EP-03). **Prioridad:** alta.
- **Evidencia:** [DynamicClientAdapter](../../src/Features/OAuth/Client/Infrastructure/Driven/DynamicClientAdapter.php) persiste `grantTypes` sin lista permitida; [ClientStoreAdapter::mapClientData](../../src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php) elige `password` si falta configuración.
- **Impacto:** DCR puede activar grants sensibles y un cliente sin metadatos obtiene ROPC.
- **Por hacer:** política allowlist por tenant/tipo de cliente en create y update; default seguro (`authorization_code` cuando aplique), migración de clientes existentes y tests de grants rechazados.
- **Aceptación:** `password` no se obtiene por omisión ni por DCR sin autorización específica.
- **Origen:** análisis OAuth §3.6/0.7 y `_2.md` priorización.

## Detalle de implementación

- `src/Features/OAuth/Client/Application/Usecase/RegisterClient/RegisterClientUsecase.php` y `UpdateDynamicClient/UpdateDynamicClientUsecase.php`: validar `grant_types`/`response_types` contra allowlist del tenant y método de autenticación; rechazar `password` sin permiso explícito.
- `src/Features/OAuth/Client/Infrastructure/Driven/DynamicClientAdapter.php`: no persistir arrays arbitrarios tras el alta o actualización; mapear solo metadatos validados.
- `src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php`: eliminar `['password']` como valor por defecto, establecer un default seguro y definir migración de metadatos de clientes existentes antes de cambiar su comportamiento.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php` y `test/integration/Features/OAuth/TokenEndpointTest.php`: aplicar grants autorizados al canje y comprobar rechazo de ROPC no concedido.
