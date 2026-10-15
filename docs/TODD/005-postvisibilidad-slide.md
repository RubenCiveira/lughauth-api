# 005 — Verificar filtrado posterior y rellenado de páginas

- **Estado:** parcialmente corregido; integración pendiente. **Prioridad:** alta.
- **Evidencia:** [RoleSlide::slide](../../src/Features/Access/Role/Domain/Gateway/RoleSlide.php) delega ahora en [Slider](../../src/Shared/Value/Pagination/Slider.php), que tiene pruebas unitarias propias ([SliderUnitTest](../../test/unit/Shared/Value/Pagination/SliderUnitTest.php)). El callback de [RoleQueryRepositoryAdapter](../../src/Features/Access/Role/Infrastructure/Driven/RoleQueryRepositoryAdapter.php) recibe `RoleSlide`, no `RoleFilter`. Ya no aparece el `return $this` que señalaba el análisis.
- **Impacto:** el algoritmo está probado de forma aislada, pero no su conexión con el cursor SQL y con los listeners `*VisibilityCheck`. Si se activa un listener de visibilidad por fila sin pruebas, un error de cursor o de rellenado puede exponer elementos o repetir páginas.
- **Por hacer:** prueba de integración con un listener de visibilidad que descarte filas al principio, entre medias y al final. Comprobar `hasNext`, el lookahead y el cursor en Role y en otra entidad generada. Depende del arnés de BD de 007.
- **Aceptación:** ninguna fila descartada aparece y las páginas se rellenan hasta el límite cuando hay suficientes filas visibles.
- **Origen:** ambos análisis de maestros, §4.5 / hallazgo 3.
