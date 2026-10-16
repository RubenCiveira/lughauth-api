# 049 — Aplicar perfil de access token JWT

- **Estado:** pendiente, extensión de interoperabilidad. **Prioridad:** media. **Dependencia:** 025.
- **Evidencia:** [JoseTokenSigner](../../src/Features/OAuth/TokenSecurity/Infrastructure/Driven/JoseTokenSigner.php) pone `typ=Bearer` en payload y ninguna cabecera `typ`; [TokenController](../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php) emite access como JWT sin perfil explícito.
- **Impacto:** resource servers no pueden distinguir de forma estándar access e ID token.
- **Por hacer:** para RFC 9068, cabecera `typ: at+jwt`, `iss/sub/aud/client_id/iat/exp/jti` correctos, scopes y validación estricta en middleware sin romper compatibilidad planificada.
- **Aceptación:** access cumple perfil elegido y un ID token no autentica como access.
- **Origen:** análisis OAuth §5.2/3.6.

## Detalle de implementación

- `src/Features/OAuth/TokenSecurity/Domain/Gateway/TokenSigner.php` y `Infrastructure/Driven/JoseTokenSigner.php`: añadir firma por tipo con cabecera `typ=at+jwt`, `kid` y algoritmo acordado; no confundir la claim `typ` del payload con la cabecera.
- `src/Features/OAuth/Authentication/Application/` (**nuevo mapper de claims de access**, 038) y `Infrastructure/Driver/Rest/TokenController.php`: generar `iss/sub/aud/exp/iat/jti/client_id` y scopes correctos según RFC 9068.
- `src/Bootstrap/Middleware/JwtVerifierMiddleware.php`: exigir perfil access al autenticar APIs; ventana explícita para tokens antiguos.
- `test/integration/Features/OAuth/TokenEndpointTest.php`: inspeccionar cabecera/payload y rechazar ID token usado como access.
