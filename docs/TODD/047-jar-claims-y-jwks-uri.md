# 047 — Completar validación JAR y proteger JWKS remoto

- **Estado:** parcial. **Prioridad:** alta.
- **Evidencia:** [RequestObjectValidator](../../src/Features/OAuth/Authentication/Domain/RequestObjectValidator.php) valida firma y `client_id`, pero no `aud`, tiempos ni JTI; hace HTTP a `jwks_uri` directamente desde Domain y sin restricciones/caché.
- **Impacto:** request objects reutilizables y SSRF/coste de red en `/authorize`.
- **Por hacer:** validar issuer/audience/exp/nbf/jti con replay store; mover carga de JWKS a adaptador con origen permitido, timeout, tamaño máximo y caché; comprobar conflictos entre JAR/PAR/query.
- **Aceptación:** objeto vencido/repetido u origen JWKS privado se rechaza; firmado válido funciona.
- **Origen:** análisis OAuth §5.4/3.4 y `_2.md` JAR.

## Detalle de implementación

- `src/Features/OAuth/Authentication/Domain/RequestObjectValidator.php`: validar `iss`, `aud` del issuer canónico, `exp/nbf/iat`, `jti` y cliente; extraer de Domain el uso directo de `ClientInterface`.
- `src/Features/OAuth/Authentication/Domain/Gateway/` (**nuevo puerto JWKS/replay**) y `Infrastructure/Driven/` (**adaptadores nuevos**): descarga de `jwks_uri` con HTTPS, bloqueo SSRF, tiempo/tamaño máximos y caché por cliente; guardar JTI con expiración y consumo atómico.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php`: usar únicamente claims JAR verificados, resolver conflictos de query/PAR sin aceptar parámetros que degraden la firma.
- `test/integration/Features/OAuth/`: probar objeto válido, vencido, repetido, `aud` de otro tenant y JWKS en red privada.
