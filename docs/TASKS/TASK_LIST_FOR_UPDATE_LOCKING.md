# TASK (contingencia) — Bloqueos en `listForUpdate` con post-visibilidad

> Estado: **riesgo asumido**, sin acción inmediata. Activar este plan solo si aparecen los síntomas descritos.
> Origen: rediseño de `*Slide::slide()` (relleno de página con lookahead y estimación por tasa de descartes).

## Decisión

`*CommandRepositoryAdapter::listForUpdate()` sigue devolviendo un `*Slide` normal, y `*VisibilityService::listVisiblesForUpdate()` aplica `slide()` sobre él. Se acepta porque:

- hoy **nadie llama** a `listVisiblesForUpdate()` (está definido en 25 servicios sin consumidores);
- el único uso directo de `listForUpdate()` es `MfaAdapter::generateRecoveryCodes()`, sin cursor ni post-visibilidad (lee los códigos de un usuario y los borra);
- su uso futuro queda limitado a operaciones concretas y delicadas.

## Riesgo

En `SELECT … FOR UPDATE`, **todas las filas leídas quedan bloqueadas hasta el final de la transacción**, también las que `slide()` descarta por visibilidad y las del lookahead. Con descartes altos, `slide()` amplía la lectura (hasta `max(1000, 2 × limit)` filas por consulta, en varias consultas), así que una sola operación puede bloquear muchas más filas de las que devuelve. Con dos operaciones concurrentes que recorran rangos en distinto orden, pueden aparecer esperas largas o interbloqueos.

## Síntomas que activan el plan

- Errores `SQLSTATE[40001]` (deadlock) o `SQLSTATE[HY000]: 1205 Lock wait timeout exceeded` en MySQL, en trazas o logs, con un span `List query for update of …` en la pila.
- Latencia anómala en operaciones que usen `listVisiblesForUpdate()` o `listForUpdate()` con cursor.
- `SHOW ENGINE INNODB STATUS` → `LATEST DETECTED DEADLOCK` con consultas sobre tablas `access_*` / `document_*` / `notification_*` que lleven `FOR UPDATE`.

## Plan de acción (de menor a mayor esfuerzo)

1. **Identificar la operación**: localizar el caso de uso en el span y medir cuántas filas lee frente a cuántas devuelve (tasa de descartes).
2. **Llevar la regla a pre-visibilidad**: si la regla que descarta se puede expresar como filtro (`*RestrictFilterToVisibility`), moverla al `WHERE`. Así la consulta bloquea solo filas visibles y desaparece la ampliación.
3. **Separar localizar de bloquear** en esa operación: `listVisibles()` (sin bloqueo) para obtener los uids, y bloquear solo lo que se modifica con `resolveForUpdate()` / `retrieveForUpdate()`, por uid y **en orden de uid** (orden de bloqueo consistente, que evita interbloqueos entre transacciones).
4. **Reintento acotado** del caso de uso ante `40001` (p. ej. 3 intentos con espera aleatoria), siempre que la operación sea idempotente dentro de su transacción.
5. **Cambio estructural** (si se repite en varias operaciones): que `listForUpdate()` devuelva una clase propia sin `slide()` (p. ej. `*LockedPage` con `values()`, `nextCursor()`, `hasNext()` y un `filter()` que solo descarta dentro de la página leída, sin volver a consultar), con fetch de `limit + 1` y sin ampliación. Aplicarlo en la plantilla del generador.

## Prevención

- Revisar en code review cualquier nuevo uso de `listVisiblesForUpdate()` o de `listForUpdate()` con cursor: justificar por qué no basta con el paso 3.
- Mantener las transacciones que usan `ForUpdate` lo más cortas posible (sin llamadas HTTP ni envíos de correo dentro).
