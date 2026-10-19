# 065 — Aplicar metadatos de seguridad de TrustedClient

- **Estado:** hecho en lo que toca a EP-03 (metadatos en `ClientData`, PKCE en el canje); `responseTypes` y `requirePar` los aplican EP-08 y EP-07. **Prioridad:** media.
- **Evidencia:** [ClientStoreAdapter::mapClientData](../../src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php) transporta grants/M2M/JAR, pero no `tokenEndpointAuthMethod`, `responseTypes`, `requirePkce` ni flags de logout presentes en TrustedClient.
- **Impacto:** configuración administrativa no impone los límites esperados por cliente.
- **Por hacer:** incorporar metadatos necesarios a [ClientData](../../src/Features/OAuth/Client/Domain/ClientData.php), aplicar en authorize/token/logout/DCR y probar defaults seguros; coordinar con 016, 033, 034 y 021.
- **Aceptación:** cambiar cada flag relevante produce comportamiento verificable en el endpoint correspondiente.
- **Origen:** análisis OAuth §5.3.

## Detalle de implementación

- `src/Features/OAuth/Client/Domain/ClientData.php`: añadir metadatos tipados para `requirePkce`, `tokenEndpointAuthMethod`, `responseTypes`, `allowAllScopes`, `isResourceServer` y flags `*LogoutSessionRequired`.
- `src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php` y `DynamicClientAdapter.php`: mapear/validar atributos de TrustedClient existentes en lugar de devolver defaults fijos; preservar compatibilidad de clientes ya registrados.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php`, `Infrastructure/Driver/Rest/TokenController.php` y `LogoutController.php`: aplicar flags en authorize, canje y notificaciones respectivamente; coordinar con 016/021/033/034.
- `test/integration/Features/OAuth/`: activar/desactivar cada metadato en fixtures y demostrar que cambia el resultado del endpoint previsto.
