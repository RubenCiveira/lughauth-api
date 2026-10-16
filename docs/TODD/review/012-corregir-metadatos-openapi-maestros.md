# 012 — Corregir OpenAPI y textos generados de listados

- **Estado:** pendiente. **Prioridad:** baja.
- **Evidencia:** [RoleListController](../../src/Features/Access/Role/Infrastructure/Driver/Rest/RoleListController.php) anuncia `description:"Create"`/201 y devuelve 200; `nextLink` sigue trazando «Check generic allow».
- **Impacto:** clientes generados y trazas describen una operación distinta de la real.
- **Por hacer:** arreglar plantilla de OpenAPI y etiquetas de observabilidad de listados; regenerar y comprobar códigos/descriciones para las demás entidades.
- **Aceptación:** el contrato documenta GET/200 y logs reflejan la operación ejecutada.
- **Origen:** `ANALISIS_CALIDAD_CODIGO_MAESTROS.md` §4.6.
