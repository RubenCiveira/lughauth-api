# 048 — Evaluar DPoP para tokens ligados a clave

- **Estado:** ausente; extensión opcional. **Prioridad:** baja.
- **Evidencia:** [TokenController](../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php) emite bearer sin prueba DPoP; [JwtVerifierMiddleware](../../src/Bootstrap/Middleware/JwtVerifierMiddleware.php) acepta bearer sin vínculo criptográfico a la petición.
- **Impacto:** un access token robado puede reutilizarse hasta expiración/revocación.
- **Por hacer:** si se requiere RFC 9449, validar prueba `htu`/`htm`/`iat`/`jti`, ligar `cnf.jkt` a code/refresh/access y verificarla en recursos; actualizar discovery.
- **Aceptación:** prueba ausente, repetida o con otra clave no autoriza token DPoP.
- **Origen:** análisis OAuth §5.4/3.5.

## Detalle de implementación (solo si se aprueba)

- `src/Features/OAuth/Authentication/Application/` (**nuevo verificador de prueba DPoP**) y `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php`: validar cabecera DPoP (`htu`, `htm`, `iat`, `jti`, firma y clave pública) y emitir token con `cnf.jkt`.
- `src/Features/OAuth/Session/Domain/TemporalAuthCode.php` y `Infrastructure/Driven/TemporalKeysSqlAdapter.php`: preservar vínculo de clave desde autorización/code hacia canje y refresh.
- `src/Bootstrap/Middleware/JwtVerifierMiddleware.php` y `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/UserInfoController.php`: exigir prueba por petición al usar token DPoP; mantener política bearer separada.
- `src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php` y `test/integration/Features/OAuth/`: anunciar capacidad únicamente después de casos de replay, clave ajena y URI alterada.
