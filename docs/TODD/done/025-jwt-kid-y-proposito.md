# 025 — Emitir y verificar `kid` y propósito de cada JWT

- **Estado:** hecho (EP-04). **Prioridad:** crítica.
- **Evidencia:** [JoseTokenSigner::sign](../../src/Features/OAuth/TokenSecurity/Infrastructure/Driven/JoseTokenSigner.php) firma con cabecera solo `alg` pese a publicar `kid`; payload por defecto `typ=Bearer`; [TokenController](../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php) firma access/id/refresh con el mismo mecanismo y apenas un grant fija `token_use`.
- **Impacto:** RP no selecciona la clave durante rotación y tokens de diferente uso pueden confundirse.
- **Por hacer:** cabecera `kid` real y `typ` adecuado, propósito obligatorio validado en cada consumidor (incluidos pre-sesión y logout), compatibilidad acotada para tokens viejos.
- **Aceptación:** cada verificador rechaza un token válido de otro propósito; JWT emitido referencia clave pública existente.
- **Origen:** análisis OAuth §3.6/0.8 y `_2.md` brecha 4.

## Detalle de implementación

- `src/Features/OAuth/TokenSecurity/Domain/Gateway/TokenSigner.php` y `Infrastructure/Driven/JoseTokenSigner.php`: introducir tipo de token en el contrato de firma/verificación, escribir `kid` y `typ` en **cabecera** JWS usando la clave pública identificada; no insertar datos privados en la cabecera.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php`, `Infrastructure/Driver/Html/Services/OidcResponseBuilder.php` y `src/Features/OAuth/Authentication/Domain/LogoutToken.php`: emitir tipos distintos para access, ID, refresh y logout; `signKeypass` debe seguir siendo exclusivo de pre-sesión.
- `src/Features/OAuth/Authentication/Application/TokenGranter/ResolverForRefresh.php`, `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/UserInfoController.php` y `src/Bootstrap/Middleware/JwtVerifierMiddleware.php`: validar propósito esperado antes de usar claims; contemplar ventana de compatibilidad para JWT emitidos antes del cambio.
- `test/integration/Features/OAuth/`: decodificar cabeceras y comprobar rechazo cruzado entre tipos y `kid` presente en JWKS.
