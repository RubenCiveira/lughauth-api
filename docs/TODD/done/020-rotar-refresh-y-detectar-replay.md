# 020 — Rotar refresh y detectar reutilización concurrente

- **Estado:** hecho. **Prioridad:** crítica. **Dependencia:** 019.
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

## Decisiones tomadas

- **Familia en el token:** cada refresh lleva `fid` (familia, UUID creado en el grant original) y `fexp` (caducidad absoluta, 30 días desde el grant original), ambos firmados. Al rotar se heredan, y el `exp` del nuevo refresh es `min(ahora + 10h, fexp)`, así que la familia nunca se alarga. El `exp` se fija explícitamente en los claims para que el reloj del firmador no lo desplace más allá de `fexp`.
- **Consumo atómico:** `TokenRevocationGateway::consumeJti` hace `INSERT IGNORE` en `_oauth_revoked_jti`; la PK sobre `jti` garantiza un único vencedor (`rowCount() === 1`). Se consume en último lugar, tras validar familia y sesión y recargar al usuario, de modo que un refresh rechazado por otros motivos no se gasta. Si falla algo después del consumo (firma, respuesta), ese refresh queda gastado.
- **Replay:** un JTI ya consumido (o revocado por `/revoke`) revoca la familia (`_oauth_revoked_refresh_family`, hasta `fexp`) y cierra la sesión `sid` (lo que corta también los refresh de otros clientes SSO de esa sesión). No se emiten tokens.
- **Peticiones simultáneas:** sin periodo de gracia; la segunda se trata como replay. Los clientes deben serializar sus refresh.
- **Tokens anteriores al cambio:** sin `jti`/`fid` se rechazan, así que hay que volver a iniciar sesión.
- **Tablas `_oauth_session_grant` / `_oauth_session_token`:** no se usan porque exigen sesión (`session NOT NULL`) y los grants `password`/`device_code` no la tienen. Migración nueva: `20261016200000_SynchSchemaFor20261016200000`.
- **Prueba de concurrencia:** `test/integration/Features/OAuth/TokenRevocationSqlAdapterTest.php` lanza 8 procesos PHP con conexiones propias contra MySQL y exige un único vencedor en 5 rondas. Se salta si no hay MySQL o falta la migración.
