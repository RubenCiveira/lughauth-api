# 018 — Controlar audiencias de todos los grants

- **Estado:** pendiente. **Prioridad:** crítica.
- **Evidencia:** [TokenController](../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php) propaga `params['audience']` a refresh, device y demás grants; [OidcFlowContext](../../src/Features/OAuth/Authentication/Domain/OidcFlowContext.php) acepta audiencia de authorize.
- **Impacto:** el cliente puede obtener tokens aceptados por resource servers no concedidos.
- **Por hacer:** definir recursos permitidos por cliente/tenant o eliminar el parámetro libre, preservar únicamente audiencias aprobadas en code y refresh; distinguir audiencia del ID token (solo RP).
- **Aceptación:** audiencia ajena da error; refresh nunca amplía la audiencia original.
- **Origen:** análisis OAuth §3.3/0.3 y `_2.md` brecha 4.

## Detalle de implementación

- `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php`: eliminar la expansión directa con `$params['audience']` en cada grant; intersectar recursos solicitados con autorizados y no ampliar los del refresh/code.
- `src/Features/OAuth/Authentication/Domain/OidcFlowContext.php` y `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php`: validar audiencia al autorizar y persistir únicamente la aprobada en el `AuthenticationRequest` del code.
- `src/Features/OAuth/Client/Domain/ClientData.php` y `src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php`: mapear del cliente el catálogo de resource servers admitidos; si el modelo no lo contiene, añadir atributo y migración nueva (no editar una migración ya aplicada).
- `test/integration/Features/OAuth/TokenEndpointTest.php`: comprobar recurso autorizado, no autorizado y refresh que intenta añadir audiencia.
