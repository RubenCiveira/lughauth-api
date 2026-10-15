# 001 — Vigilar clases PHP generadas vacías

- **Estado:** corregido en el árbol actual; prevención pendiente. **Prioridad:** alta.
- **Evidencia:** los cuatro ficheros citados de `UserGroupMembership` (create controller, retrieve usecase, update check y task reader) contienen ahora clases y métodos: [ejemplo](../../src/Features/Access/UserGroupMembership/Application/Usecase/Retrieve/UserGroupMembershipRetrieveUsecase.php). Hoy `find src test -name '*.php' -size 0` no devuelve nada. El análisis describía una versión anterior.
- **Contexto del generador:** el generador y el modelo vigente están fuera del repositorio. [multitenant-oidc.xml](../../multitenant-oidc.xml), en la raíz (sin versionar, de junio, esquema `civi-model-v4.xsd`), es una copia **anterior** del modelo: aún contiene `user-role-assignament`, `user-accepted-termns-of-use` y `wellcome-*`, que la regeneración de octubre ya corrigió. No hay pipeline de CI en el repositorio: solo objetivos de [Makefile](../../Makefile) y [.ci/new_Makefile](../../.ci/new_Makefile).
- **Impacto:** una regeneración incompleta vuelve a romper la resolución de dependencias y las rutas de membresías.
- **Por hacer:** versionar el modelo XML **vigente** junto al código que genera, sustituyendo la copia antigua de la raíz; añadir un objetivo de `make` (p. ej. `make check-generated`) que falle con PHP vacío y que cargue todas las clases referenciadas por plugins/rutas (instanciar el contenedor DI y resolver cada controlador registrado).
- **Aceptación:** una generación con fichero truncado hace fallar `make check-generated`; existe un smoke test de rutas CRUD y de borrado en lote.
- **Origen:** `ANALISIS_CALIDAD_CODIGO_MAESTROS.md` §4.1/§7.1; `_1.md` hallazgo 1.

## Decisión de diseño

- **¿Dónde vive la comprobación?** (a) en el generador (verifica su salida antes de escribir) o (b) en este repo (verifica lo que hay en el árbol). **Recomendación:** (b), porque protege también frente a ediciones manuales y no depende de una herramienta externa; (a) es complementaria.
- **¿Se versiona el modelo?** **Recomendación:** sí, en este repo y en su versión vigente. Sin él no se puede revisar en un PR qué cambió en el modelo ni reproducir una regeneración, y copias desfasadas como la actual inducen a error (afecta también a 002, 003, 007, 014 y 022).
