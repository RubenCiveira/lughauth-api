# 020 — Rotar refresh y detectar reutilización concurrente

- **Estado:** pendiente. **Prioridad:** crítica. **Dependencia:** 019.
- **Evidencia:** [TokenController](../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php) emite refresh nuevo cada vez; [ResolverForRefresh](../../src/Features/OAuth/Authentication/Application/TokenGranter/ResolverForRefresh.php) solo consulta revocación, aunque [TokenRevocationGateway](../../src/Features/OAuth/TokenSecurity/Domain/Gateway/TokenRevocationGateway.php) ofrece `revokeJti`.
- **Impacto:** refresh anteriores siguen sirviendo y la vida de la familia se extiende indefinidamente.
- **Por hacer:** consumo atómico del JTI, familia de refresh con duración absoluta, revocación de familia/sesión al detectar replay; definir tratamiento de dos peticiones simultáneas.
- **Aceptación:** primer uso rota, segundo falla y dispara política de replay sin emitir tokens.
- **Origen:** análisis OAuth §3.4/0.4; `_2.md` comparación refresh.

## Detalle de implementación

- `src/Features/OAuth/Authentication/Application/TokenGranter/ResolverForRefresh.php` y `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php`: trasladar la decisión de rotación a una operación única antes de emitir; registrar JTI anterior, familia y expiración absoluta del refresh siguiente.
- `src/Features/OAuth/TokenSecurity/Domain/Gateway/TokenRevocationGateway.php` y `src/Features/OAuth/TokenSecurity/Infrastructure/Driven/TokenRevocationSqlAdapter.php`: añadir consumo condicional atómico y revocación de familia/sesión; `isRevoked` seguido de `revokeJti` sin bloqueo no evita dos canjes concurrentes.
- `src/Features/OAuth/Session/Infrastructure/Driven/SessionStoreSqlAdapter.php`: vincular la familia a sesión y considerar las tablas existentes `_oauth_session_grant` / `_oauth_session_token`; crear **nueva** migración en `migrations/mysql/schema/1.0.0/` solo para columnas/índices que falten.
- `test/integration/Features/OAuth/TokenEndpointTest.php`: demostrar un único vencedor en refresh simultáneos y denegación del resto de la familia tras replay.
