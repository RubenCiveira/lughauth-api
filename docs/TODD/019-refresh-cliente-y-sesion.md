# 019 — Vincular refresh a cliente, tenant y sesión activa

- **Estado:** pendiente. **Prioridad:** crítica.
- **Evidencia:** [TokenController](../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php) resuelve `client_id` por `preValidatedClient` sin contrastar `azp`; [ResolverForRefresh](../../src/Features/OAuth/Authentication/Application/TokenGranter/ResolverForRefresh.php) fuerza `withSession(true)` sin consultar sesión.
- **Impacto:** refresh robado puede reutilizarse bajo otro cliente; logout no corta su uso.
- **Por hacer:** autenticar al cliente confidencial, comprobar `azp`/tenant/token_use, incluir `sid` en refresh y validar que siga vigente; ajustar logout y pruebas negativas.
- **Aceptación:** otro cliente, otro tenant y sesión cerrada no pueden refrescar.
- **Origen:** análisis OAuth §3.4/0.4 y `_2.md` brecha 3.

## Detalle de implementación

- `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php`: comparar el `azp` del refresh con el cliente autenticado, comprobar tenant/uso y emitir `sid` vinculado a la sesión en los refresh nuevos.
- `src/Features/OAuth/Authentication/Application/TokenGranter/ResolverForRefresh.php`: eliminar la aceptación por `withSession(true)` sin verificación; cargar la sesión mediante `SessionStoreGateway` y exigir usuario, cliente e issuer coincidentes.
- `src/Features/OAuth/Session/Domain/Gateway/SessionStoreGateway.php` y `src/Features/OAuth/Session/Infrastructure/Driven/SessionStoreSqlAdapter.php`: exponer búsqueda de sesión activa por `sid` y tenant; considerar el `issuer` ya persistido al contrastar tenant.
- `test/integration/Features/OAuth/TokenEndpointTest.php`: cubrir refresh de otro cliente/tenant y posterior a logout o revocación de sesión.
