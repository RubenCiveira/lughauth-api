# 064 — Corregir respuestas OAuth y coincidencia exacta de scopes

- **Estado:** pendiente. **Prioridad:** media.
- **Evidencia:** [TokenController](../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php) devuelve algunos `UnauthorizedException` genéricos, no añade `Cache-Control: no-store` y busca `profile`/`email` con `str_contains` (también acepta `myprofile`).
- **Impacto:** respuestas de error poco interoperables, caché de secretos y claims no solicitados.
- **Por hacer:** convertir errores de grant a `invalid_grant`/`invalid_client` según caso, añadir cabeceras no-store/no-cache a respuestas de token y analizar scopes como conjunto de valores exactos.
- **Aceptación:** `myprofile` no entrega `name`; éxito/error tienen cabeceras anticaché y formato OAuth esperado.
- **Origen:** análisis OAuth §3.6.

## Detalle de implementación

- `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php`: añadir `Cache-Control: no-store` y `Pragma: no-cache` a éxito/error; mapear fallo code/refresh a errores OAuth y sustituir `str_contains` por tokenización exacta de scopes.
- `src/Features/OAuth/Authentication/Domain/Exception/OAuthTokenException.php`: ampliar constructores de error RFC 6749 necesarios sin confundir cliente inválido con grant inválido.
- `src/Features/OAuth/Authentication/Domain/AuthenticationRequest.php`: normalizar scopes cuando se construye la solicitud para todos los grants.
- `test/integration/Features/OAuth/TokenEndpointTest.php`: validar cabeceras de éxito/error, `invalid_grant` y que `myprofile` no genera claim `name`.
