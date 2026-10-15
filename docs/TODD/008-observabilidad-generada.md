# 008 — Reducir spans y logs de boilerplate generado

- **Estado:** pendiente. **Prioridad:** baja.
- **Evidencia:** [RolePdoConnector::mapper](../../src/Features/Access/Role/Infrastructure/Connector/Pdo/RolePdoConnector.php) abre un span por fila. [RoleListController](../../src/Features/Access/Role/Infrastructure/Driver/Rest/RoleListController.php) también abre spans en `extractOrder` y `nextLink`. Incluso [OnlyForRootAllow](../../src/Features/Access/Role/Application/Policy/Allow/OnlyForRootAllow.php) envuelve una comprobación de una línea en log + span + try/catch.
- **Impacto:** coste y ruido elevados en los listados, y la lógica queda enterrada entre trazas repetidas.
- **Por hacer:** en la plantilla, instrumentar la operación de negocio o el adaptador en lugar de cada método trivial; conservar los errores relevantes y medir los spans de listados amplios.
- **Aceptación:** el número de spans de un listado no crece linealmente con el mapper y los fallos siguen siendo trazables.
- **Origen:** ambos análisis de maestros, §5.4/§7.7.

## Decisión de diseño

- **Mecanismo:**
  - (a) Quitar el span de los métodos privados y de `mapper`, y dejar uno por método público de adaptador y de caso de uso.
  - (b) Un decorador o middleware genérico por caso de uso (un span por petición y caso de uso) y nada en los adaptadores.
  - (c) Los atributos PHP `#[Traced]` con un proxy. Requiere contenedor o AOP, y PHP-DI no lo da de serie.
- **Recomendación:** (a). Es un cambio de plantilla sin infraestructura nueva, y deja spans de BD útiles. (b) puede venir después si se adopta el decorador transaccional de 011, ya que ambos envuelven el mismo límite.
