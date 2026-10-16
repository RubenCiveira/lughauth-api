# 028 — Aplicar revocación al validar bearer y UserInfo

- **Estado:** pendiente. **Prioridad:** alta.
- **Evidencia:** [JwtVerifierMiddleware](../../src/Bootstrap/Middleware/JwtVerifierMiddleware.php) valida firma/tiempo y cachea identidad, pero no consulta `TokenRevocationGateway`; [ResolverForRefresh](../../src/Features/OAuth/Authentication/Application/TokenGranter/ResolverForRefresh.php) sí consulta JTI revocado.
- **Impacto:** `/revoke` no impide que un access token aún vivo se use hasta expirar.
- **Por hacer:** consultar revocación por JTI/tenant en validación bearer, incluso con caché positiva; invalidación TTL acotada o política explícita de vida corta, y pruebas de `/userinfo` y recurso protegido.
- **Aceptación:** token revocado deja de autenticar antes de su `exp`.
- **Origen:** análisis OAuth §3.6/0.10 y `_2.md` revocación.

## Detalle de implementación

- `src/Bootstrap/Middleware/JwtVerifierMiddleware.php`: inyectar `TokenRevocationGateway` (o verificador que lo use) y consultar `jti`/tenant también al recuperar identidad de caché; limitar TTL para no aceptar una revocación recién registrada.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/UserInfoController.php` e `IntrospectionController.php`: usar la misma política de validación/revocación para el bearer y respuestas `active`; no aceptar ID/refresh como access.
- `src/Features/OAuth/TokenSecurity/Infrastructure/Driven/TokenRevocationSqlAdapter.php`: mantener consulta/index por tenant+JTI eficiente y comprobar que la revocación se conserva hasta `exp`.
- `test/integration/Features/OAuth/IntrospectionTest.php` y `TokenEndpointTest.php`: revocar tras primera validación y repetir con caché caliente; debe rechazarse antes de caducar.
