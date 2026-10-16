# 018 — Controlar audiencias de todos los grants

- **Estado:** implementada y verificada con tests unitarios e integración. **Prioridad:** crítica.
- **Evidencia:** [ClientData::allowsAudiences](../../../src/Features/OAuth/Client/Domain/ClientData.php) acepta solo el propio cliente y las audiencias de `trusted-client-allowed-audience`, que [ClientStoreAdapter](../../../src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php) mapea a `allowedAudiences`. [TokenController](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php) ya no propaga `params['audience']` sin validar.
- **Impacto corregido:** una audiencia ajena responde `invalid_target` (RFC 8707) en authorize, device authorization y token; el refresh nunca amplía la audiencia original y el ID token solo lleva al RP como `aud`.
- **Seguimiento:** el techo de audiencias del refresh viaja firmado en el propio refresh token (claim `resources`); cuando 020 introduzca la familia de refresh en `_oauth_session_grant`, conviene leerlo de allí. Los refresh tokens emitidos antes de este cambio no llevan `resources` y quedan limitados al cliente. La comprobación de que el refresh pertenece al cliente que lo presenta es la tarea 019.
- **Aceptación:** audiencia ajena da error; refresh nunca amplía la audiencia original.
- **Origen:** análisis OAuth §3.3/0.3 y `_2.md` brecha 4.

## Detalle de implementación

- `src/Features/OAuth/Client/Domain/ClientData.php` y `src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php`: nuevo `allowedAudiences` desde la subentidad `allowed-audiences` del modelo (tabla `access_trusted_client_allowed_audience`, ya en la migración de esquema) y `allowsAudiences()`.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php`: `rejectInvalidRequest()` (antes `requirePkce()`) redirige con `invalid_target` si la audiencia no está autorizada, así que el `AuthenticationRequest` del code solo contiene audiencias aprobadas. `OidcFlowContext::normalizeAudiences()` pasa a ser público para reutilizarlo.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php`: `aud` sale de las audiencias resueltas por grant: code → `code->request->audiences`; device → las aprobadas con el device code; password/client_credentials → `grantedAudiences()` contra el cliente; refresh → `grantedAudiences()` con el techo `resources` del refresh token. El ID token fija `aud` al `client_id`.
- `src/Features/OAuth/Device/Infrastructure/Driver/Rest/DeviceAuthorizationController.php`: valida la audiencia al crear la autorización.
- `OAuthTokenException::invalidTarget()` y `DeviceAuthorizationException::invalidTarget()`.
- `test/integration/Features/OAuth/TokenEndpointTest.php`: recurso autorizado; recurso ajeno en client_credentials y password (`invalid_target`, sin tokens); code que ignora la audiencia del token request (también si es ajena) con ID token solo para el RP; refresh que intenta ampliar con un recurso del cliente no concedido, con uno ajeno al cliente y desde un code sin audiencias (todos `invalid_target`), además de mantener y reducir.
- `test/integration/Features/OAuth/DeviceEndpointTest.php`: device authorization con audiencia ajena da `invalid_target`; el polling no puede añadir audiencia.
- `test/unit/.../AuthorizeHtmlIntegrationUnitTest.php`: authorize con audiencia ajena redirige con `#error=invalid_target` sin cargar sesión. `ClientDomainUnitTest` cubre `allowsAudiences()`.
- Las pruebas negativas de authorize, device, client_credentials y password fallan si `allowsAudiences()` acepta todo (comprobado en rojo/verde).
