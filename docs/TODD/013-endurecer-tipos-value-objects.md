# 013 — Revisar tipos, igualdad y referencias generadas

- **Estado:** pendiente. **Prioridad:** baja.
- **Evidencia:** [RoleNameVO](../../src/Features/Access/Role/Domain/ValueObject/RoleNameVO.php) usa comparación laxa en `equals`. [RoleRef](../../src/Features/Access/Role/Domain/RoleRef.php) expone `_private_resolve` mutable y un uid anulable. Los holders usan `mixed` y asignaciones dentro de condiciones.
- **Impacto:** tipos nominales sin semántica estricta y acoplamiento entre la caché del dominio y la infraestructura.
- **Por hacer:** definir los contratos reales de nulabilidad e igualdad, encapsular la resolución sin romper la identidad, tipar los holders y eliminar en las plantillas el despacho dinámico que no esté justificado.
- **Aceptación:** pruebas de igualdad y de resolución equivalentes con firmas estrictas, y Psalm confirma los usos.
- **Origen:** ambos análisis de maestros, §4.6/§5.5 y apartados de tipos.

## Decisión de diseño

- **`_private_resolve`:**
  - (a) Mantener la caché en la referencia, pero privada, con un `resolved(): ?Role` y un `withResolved(Role)` que devuelve una copia.
  - (b) Sacarla a un `IdentityMap` o caché por petición en infraestructura, de modo que `RoleRef` sea solo `uid`.
- **Recomendación:** (b). La referencia pasa a ser un valor inmutable de verdad y la caché queda donde corresponde. (a) es un paso intermedio aceptable si (b) obliga a reescribir demasiados adaptadores.
- **`uid` anulable:** hacerlo `string` no anulable en `*Ref`. Un `Ref` sin uid no tiene sentido; la entidad en construcción puede seguir teniéndolo anulable.
