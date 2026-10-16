# 017 — Hacer el authorization code de un solo uso atómico

- **Estado:** implementada y verificada con MySQL/MariaDB. **Prioridad:** alta. **Dependencia:** 016.
- **Evidencia:** [TemporalKeysSqlAdapter::consumeTemporalAuthCode](../../../src/Features/OAuth/Session/Infrastructure/Driven/TemporalKeysSqlAdapter.php) bloquea, hidrata y valida el code antes del `DELETE`, en la misma transacción; solo lo devuelve si borró una fila. Existe un índice único sobre `code`.
- **Impacto corregido:** dos canjes concurrentes no pueden recibir ambos el mismo code y un intento con cliente, tenant, redirect o PKCE incorrectos ya no agota el code legítimo.
- **Seguimiento:** si se exige revocar tokens de la primera petición ante un segundo canje, hacen falta persistencia de codes ya utilizados/vínculo con JTI y validación efectiva de revocación de bearer (tarea 028).
- **Aceptación:** dos peticiones válidas simultáneas no obtienen ambas tokens; una inválida no consume el code, y el reintento legítimo aún puede usarlo una sola vez.
- **Origen:** análisis OAuth §3.2/0.2; `_2.md` brecha 2.

## Detalle de implementación

- `src/Features/OAuth/Session/Infrastructure/Driven/TemporalKeysSqlAdapter.php`: transacción propia cuando no existe otra, bloqueo de fila, validación bajo bloqueo antes de borrar y comprobación de `rowCount() === 1`; una validación rechazada hace rollback. MariaDB 1020 al competir con una instantánea antigua se interpreta como code ya consumido.
- `migrations/mysql/schema/1.0.0/20261016000001_UniqueTemporalAuthCode.php`: añade índice único al code; aplicado en la base local.
- `src/Features/OAuth/Session/Domain/Gateway/TemporalKeysGateway.php` y `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php`: el puerto recibe un validador que rechaza por excepción bajo bloqueo; el controlador verifica cliente, redirect, tenant y PKCE allí, y responde `invalid_grant` si no hay code disponible.
- `test/integration/Features/OAuth/TemporalAuthCodeSqlAdapterTest.php` y `TokenEndpointTest.php`: prueban instantánea antigua, dos consumos solapados, rechazo sin borrado, reintento válido, uso único y errores OAuth.
