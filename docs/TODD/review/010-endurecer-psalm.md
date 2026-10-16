# 010 — Endurecer análisis estático de maestros

- **Estado:** pendiente. **Prioridad:** media.
- **Evidencia:** [.ci/psalm.xml](../../.ci/psalm.xml) usa `errorLevel="4"` y `findUnusedCode="true"`. No hay pipeline de CI en el repositorio: el [Makefile](../../Makefile) ejecuta Psalm y redirige la salida a un informe XML, y [.ci/new_Makefile](../../.ci/new_Makefile) lo ejecuta sin opciones. No se ha comprobado si alguno de los dos falla ante errores.
- **Impacto:** los tipos débiles y el código generado sin usar (p. ej. `OnlyForRootAllow` de Role, ver 002/003) pueden pasar inadvertidos.
- **Por hacer:** ejecutar Psalm y medir el número de incidencias; generar `psalm-baseline.xml`; subir gradualmente al nivel 3 y luego al 2; hacer que el objetivo de `make` devuelva error ante incidencias nuevas. Para el código sin uso, declarar los puntos de entrada de DI y reflexión (plugins, controladores, listeners) y evitar así falsos positivos.
- **Aceptación:** una clase generada obsoleta o un error de tipos representativo hace fallar la comprobación.
- **Origen:** `ANALISIS_CALIDAD_CODIGO_MAESTROS.md` §6/§7.9.

## Decisión de diseño

- **¿Psalm analiza el código generado?**
  - (a) Sí, con el mismo nivel que el resto.
  - (b) Sí, pero con un nivel propio para `src/Features/Access` (Psalm admite `<directory>` con un `errorLevel` distinto).
  - (c) No, se excluye.
- **Recomendación:** (b). El código generado se corrige en la plantilla, así que cada incidencia se multiplica por 23 entidades. Conviene un nivel propio que se pueda endurecer de forma independiente.
