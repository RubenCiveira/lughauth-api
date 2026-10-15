# 069 — Evaluar JWT bearer grant

- **Estado:** ausente; decisión de producto. **Prioridad:** baja.
- **Evidencia:** [TokenController](../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php) no gestiona `urn:ietf:params:oauth:grant-type:jwt-bearer`; el soporte de JAR en [RequestObjectValidator](../../src/Features/OAuth/Authentication/Domain/RequestObjectValidator.php) verifica solicitudes de autorización, no assertions de grant.
- **Impacto:** no hay intercambio de assertion firmada por token según RFC 7523 §2.1.
- **Por hacer:** decidir confianza/issuer/audience/subject y prevención de replay si hay integración de servicio a servicio; no anunciar grant hasta implementarlo.
- **Aceptación:** decisión documentada; si se implementa, assertion ajena o repetida no emite tokens.
- **Origen:** análisis OAuth §5.2.

## Detalle de implementación (solo si se aprueba)

- `src/Features/OAuth/Authentication/Application/TokenGranter/` (**nuevo** `ResolverForJwtBearer.php`): verificar assertion firmada, issuer/subject/audience, expiración y JTI de un solo uso para el grant RFC 7523.
- `src/Features/OAuth/Authentication/Application/TokenGranter/TokenGranterMediator.php` y `Infrastructure/Driver/Rest/TokenController.php`: enrutar grant solo a clientes autorizados, separar assertion de autenticación de cliente de assertion grant.
- `src/Features/OAuth/Client/Domain/ClientData.php` y `src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php`: incluir política por cliente y anunciar grant únicamente cuando sea operativo.
- `test/integration/Features/OAuth/TokenEndpointTest.php`: comprobar assertion válida, replay, firma ajena y `aud` errónea.
