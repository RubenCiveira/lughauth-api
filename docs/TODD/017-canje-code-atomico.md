# 017 — Hacer el authorization code de un solo uso atómico

- **Estado:** pendiente. **Prioridad:** alta. **Dependencia:** 016.
- **Evidencia:** [TemporalKeysSqlAdapter::retrieveTemporalAuthCode](../../src/Features/OAuth/Session/Infrastructure/Driven/TemporalKeysSqlAdapter.php) hace `SELECT` seguido de `DELETE`, sin comprobar que la segunda operación consumió la fila.
- **Impacto:** dos canjes concurrentes pueden recibir el mismo code.
- **Por hacer:** consumir con bloqueo/operación atómica apropiada para la BD y comprobar filas afectadas; definir detección de replay y revocación de tokens asociados cuando sea posible.
- **Aceptación:** en dos peticiones simultáneas solo una obtiene tokens; la otra recibe `invalid_grant`.
- **Origen:** análisis OAuth §3.2/0.2; `_2.md` brecha 2.

## Detalle de implementación

- `src/Features/OAuth/Session/Infrastructure/Driven/TemporalKeysSqlAdapter.php`: sustituir `SELECT` + `DELETE` no protegidos por consumo transaccional con bloqueo/filas afectadas según MySQL; deserializar y devolver solo la fila consumida y no caducada.
- `src/Features/OAuth/Session/Domain/Gateway/TemporalKeysGateway.php` y `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php`: aclarar el contrato de consumo de un solo uso; convertir code inexistente, caducado o repetido en `invalid_grant` antes de firmar.
- `test/integration/Features/OAuth/TokenEndpointTest.php` y su stub `Stub/InMemoryTemporalKeys.php`: reflejar la semántica de consumo; añadir prueba de dos canjes simultáneos contra el adaptador SQL, pues un stub secuencial no detecta la carrera.
