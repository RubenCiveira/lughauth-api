# 016 — Vincular canje de authorization code a cliente y redirect

- **Estado:** pendiente. **Prioridad:** crítica. **Dependencia:** 015.
- **Evidencia:** [TokenController](../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php) valida PKCE pero utiliza `$code->client` sin autenticar cliente confidencial ni comparar `client_id` y `redirect_uri` solicitados con [TemporalAuthCode](../../src/Features/OAuth/Session/Domain/TemporalAuthCode.php).
- **Impacto:** codes pueden canjearse fuera del vínculo de solicitud, especialmente junto al redirect inseguro.
- **Por hacer:** exigir identificación del cliente público o autenticación del confidencial según método registrado, comparar cliente y redirect exactos y validar pertenencia del code al tenant antes de emitir.
- **Aceptación:** code ajeno, redirect distinto, cliente confidencial sin secreto y PKCE incorrecto devuelven error OAuth sin tokens.
- **Origen:** análisis OAuth §3.2/0.2 y `_2.md` brecha 2.

## Detalle de implementación

- `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php`: antes de emitir tokens, identificar el cliente de la petición, autenticar el confidencial según su método, comparar `client_id`, `redirect_uri` y tenant con los datos del code; responder `invalid_client` o `invalid_grant` según corresponda.
- `src/Features/OAuth/Session/Domain/TemporalAuthCode.php` y `src/Features/OAuth/Session/Infrastructure/Driven/TemporalKeysSqlAdapter.php`: conservar y deserializar sin pérdida el cliente, redirect, PKCE y tenant originales; añadir un campo únicamente si la solicitud almacenada no aporta ese dato.
- `src/Features/OAuth/Client/Domain/ClientData.php` y `src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php`: exponer el método de autenticación/tipo público-confidencial necesario para el canje; no equiparar `publicAllow` con permiso de omitir secreto para un confidencial.
- `test/integration/Features/OAuth/TokenEndpointTest.php`: añadir canje válido y rechazos de cliente ajeno, secreto ausente, redirect alterado, otro tenant y PKCE inválido.
