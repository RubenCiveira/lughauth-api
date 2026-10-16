# 016 — Vincular canje de authorization code a cliente y redirect

- **Estado:** corregida en código y cubierta por pruebas; confirmación manual de Angular pendiente. **Prioridad:** crítica. **Dependencia:** 015 corregida.
- **Evidencia:** [TokenController](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php) autentica al cliente antes de cargar el code y comprueba `redirect_uri`, tenant y PKCE del [TemporalAuthCode](../../../src/Features/OAuth/Session/Domain/TemporalAuthCode.php) bajo bloqueo, antes de consumirlo; hay tests de canje y reintento tras errores, y de rechazo por cliente ajeno, redirect alterado, otro tenant, confidencial sin secreto o con método distinto del registrado, y público sin PKCE. Una validación fallida no consume el code (el cliente legítimo puede reintentar).
- **Impacto corregido:** el canje exige el cliente, método de autenticación, redirect y tenant de la solicitud original; SPA pública requiere PKCE.
- **Operación:** verificar el Angular real iniciando un login nuevo; el consumo atómico de códigos pertenece a la tarea 017.
- **Aceptación:** code ajeno, redirect distinto, cliente confidencial sin secreto y PKCE incorrecto devuelven error OAuth sin tokens.
- **Origen:** análisis OAuth §3.2/0.2 y `_2.md` brecha 2.

## Detalle de implementación

- `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php`: antes de emitir tokens, identificar el cliente de la petición, autenticar el confidencial según su método, comparar `client_id`, `redirect_uri` y tenant con los datos del code; responder `invalid_client` o `invalid_grant` según corresponda.
- `src/Features/OAuth/Session/Domain/TemporalAuthCode.php` y `src/Features/OAuth/Session/Infrastructure/Driven/TemporalKeysSqlAdapter.php`: conservar y deserializar sin pérdida el cliente, redirect, PKCE y tenant originales; añadir un campo únicamente si la solicitud almacenada no aporta ese dato.
- `src/Features/OAuth/Client/Domain/ClientData.php` y `src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php`: exponer el método de autenticación/tipo público-confidencial necesario para el canje; no equiparar `publicAllow` con permiso de omitir secreto para un confidencial.
- `test/integration/Features/OAuth/TokenEndpointTest.php`: añadir canje válido y rechazos de cliente ajeno, secreto ausente, redirect alterado, otro tenant y PKCE inválido.

## Aplicación al cliente Angular existente

- `src/Features/OAuth/Common/Application/Usecase/InstallUsecase.php` y `migrations/mysql/schema/1.0.0/20261016000000_PublicOidcUiClient.php` (que ejecuta su SQL homónimo): configurar `lughauth-ui` como cliente público (`token_endpoint_auth_method=none`) con grant `authorization_code` en nuevas y antiguas instalaciones. Aplicar la migración Phinx antes de probar el login; otros clientes SPA configurados como `client_secret_basic` deben revisarse individualmente.
- El Angular debe enviar `client_id`, la misma `redirect_uri` que usó en `/authorize` y `code_verifier` S256, sin secreto. Las pruebas de integración cubren el canje público, confidencial y los rechazos; la comprobación de un solo uso concurrente pertenece a la tarea 017.
