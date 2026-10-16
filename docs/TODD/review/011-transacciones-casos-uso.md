# 011 — Ubicar transacciones en límites de caso de uso

- **Estado:** pendiente, decisión arquitectónica. **Prioridad:** media.
- **Evidencia:** [RoleDeleteController](../../src/Features/Access/Role/Infrastructure/Driver/Rest/RoleDeleteController.php) y [UserGroupMembershipCreateController](../../src/Features/Access/UserGroupMembership/Infrastructure/Driver/Rest/UserGroupMembershipCreateController.php) gestionan `SqlTemplate::begin/commit/close` alrededor de los casos de uso. En `src/Features/Access` hay 57 ficheros que llaman a `->begin()`, y fuera de Access ocurre lo mismo en [ProfileHtml](../../src/Features/OAuth/Profile/Infrastructure/Driver/Html/ProfileHtml.php). Las tareas batch (`Infrastructure/Driver/Batch`) de Role no abren transacción.
- **Impacto:** las llamadas desde batch u otros transportes pueden ejecutarse sin atomicidad, y las compensaciones de visibilidad (`create` que borra si no es visible) dependen de una transacción externa.
- **Por hacer:** elegir el mecanismo (ver decisión), introducir un puerto de aplicación, migrar las plantillas y cubrir el rollback de una operación fallida.
- **Aceptación:** un mismo caso de uso es atómico en REST y en batch sin que los controladores inicien transacciones.
- **Origen:** `ANALISIS_CALIDAD_CODIGO_MAESTROS.md` §5.2/§5.5/§7.10.

## Decisión de diseño

| Opción | Descripción | Pros | Contras |
|---|---|---|---|
| A. Puerto `TransactionManager` inyectado en el caso de uso (`$tx->run(fn() => …)`) | Explícito en Application | Atomicidad visible y testeable | Toca todos los casos de uso generados |
| B. Decorador transaccional del caso de uso, configurado en el plugin | El caso de uso no sabe de transacciones | Sin cambios en el cuerpo del caso de uso | Requiere interfaz por caso de uso o un decorador genérico |
| C. Middleware HTTP «una transacción por petición» | Infraestructura | Mínimo cambio | No cubre batch; mantiene transacciones largas en peticiones con E/S externa |

**Recomendación:** A, generada por la plantilla. Es la opción más explícita y la que mejor encaja con la arquitectura hexagonal, y las tareas OIDC 017, 020 y 039 necesitan el mismo puerto para sus consumos atómicos. C no sirve porque no cubre batch.

**Duda abierta:** ¿un caso de uso que despacha eventos con efectos externos (notificaciones, `NotifyCreateListener` de `docs/TASKS`) los emite dentro o después del commit? Conviene decidirlo aquí: lo recomendable es emitirlos *after commit*.
