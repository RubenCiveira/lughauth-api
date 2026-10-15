# 004 — Completar la paginación keyset de maestros

- **Estado:** parcialmente corregido; falta prueba de extremo a extremo. **Prioridad:** alta.
- **Evidencia:** [RolePdoConnector](../../src/Features/Access/Role/Infrastructure/Connector/Pdo/RolePdoConnector.php) ya usa `"access_role"` en el keyset (`sinceName` + desempate por `sinceUid`). [RoleCursor](../../src/Features/Access/Role/Domain/Gateway/RoleCursor.php) conserva `sinceName` y `sinceUid`, y [RoleListController](../../src/Features/Access/Role/Infrastructure/Driver/Rest/RoleListController.php) los emite sin comparar mayúsculas. El [adaptador](../../src/Features/Access/Role/Infrastructure/Driven/RoleQueryRepositoryAdapter.php) ha sustituido a `ReadRepositoryAdapter`.
- **Impacto:** los tres defectos históricos ya no se reproducen tal cual. Quedan sin demostrar los límites, los desempates, el orden descendente y las listas con nombres repetidos.
- **Por hacer:** probar y, si hace falta, corregir `hasNext` y los cursores del listado completo, con órdenes asc/desc y nombres duplicados, en Role y en otro maestro. Cualquier ajuste se hace en la plantilla del generador. Depende del arnés de BD que se decida en 007.
- **Aceptación:** recorrer todas las páginas termina sin duplicados ni pérdidas, y el enlace `next` usa el último elemento devuelto.
- **Origen:** ambos análisis de maestros, §4.4 / hallazgo 4.
