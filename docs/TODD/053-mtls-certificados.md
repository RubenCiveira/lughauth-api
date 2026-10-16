# 053 — Implementar mTLS y tokens ligados a certificado si se requiere

- **Estado:** alias anunciado parcialmente; extensión opcional. **Prioridad:** baja.
- **Evidencia:** [OpenIdConfiguration](../../src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php) publica alias mTLS y `tls_client_certificate_bound_access_tokens: false`; no se valida certificado en [TokenController](../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php).
- **Impacto:** no hay autenticación ni token ligado al certificado según RFC 8705.
- **Por hacer:** decidir necesidad FAPI; si se implementa, cert validado por proxy de confianza, método registrado, `cnf.x5t#S256` y verificación en recursos; retirar alias engañosos mientras tanto.
- **Aceptación:** certificado ajeno/no presente no autentica ni usa el token ligado; discovery exacto.
- **Origen:** análisis OAuth §5.4/fase 4 y `_2.md` P3.

## Detalle de implementación (solo si se aprueba)

- `src/Features/OAuth/Oidc/Infrastructure/Driven/MtlsDiscoveryContributor.php` y `Domain/OpenIdConfiguration.php`: dejar de publicar alias por defecto sin soporte; publicar únicamente endpoints mTLS reales cuando la terminación TLS de confianza esté configurada.
- `src/Features/OAuth/Client/Domain/ClientData.php` y `Infrastructure/Driven/ClientStoreAdapter.php`: transportar método mTLS y certificado/subject registrado para el cliente.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php` y `src/Bootstrap/Middleware/JwtVerifierMiddleware.php`: verificar certificado del proxy confiable, ligar access token mediante `cnf.x5t#S256` y exigir mismo certificado al consumirlo.
- `test/integration/Features/OAuth/`: incluir certificados de prueba, certificado ajeno y cabeceras de proxy falsificadas.
