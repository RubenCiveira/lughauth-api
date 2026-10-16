# 009 — Simplificar DTO y traits duplicados del CRUD

- **Estado:** pendiente, decisión de diseño. **Prioridad:** baja.
- **Evidencia:** [RoleAttributes](../../src/Features/Access/Role/Domain/RoleAttributes.php), `RoleCreateParams/Result`, `RoleUpdateParams/Result` y `RoleRetrieveResult` repiten la misma forma y los mismos holders. Hay que conservar las diferencias semánticas entre entrada, salida y restricciones.
- **Impacto:** mantenimiento costoso y varios puntos de cambio por atributo.
- **Por hacer:** medir la duplicación real y decidir cómo reutilizar (composición o tipos compartidos) en la fuente generadora, conservando los contratos HTTP y la validación contextual. Probar con Role y User antes de extenderlo al resto.
- **Aceptación:** menos código generado sin alterar la serialización, las reglas por operación ni la compatibilidad de los casos de uso.
- **Origen:** ambos análisis de maestros, §5.3/§7.8.

## Decisión de diseño

- **Opciones:**
  - (a) Un único `RoleAttributes` como entrada y salida de todos los casos de uso.
  - (b) Mantener los tipos por operación como subclases o alias finos de `RoleAttributes`, sin repetir traits.
  - (c) Dejarlo como está y aceptar el coste, porque es código generado.
- **Recomendación:** (b). Mantiene los nombres en las firmas, lo que sirve de documentación y permite divergir más adelante (p. ej. `UpdateParams` con campos no editables), y elimina la repetición. (a) pierde la distinción entre lo que entra y lo que sale. Prioridad baja: hacerlo solo cuando se toque la plantilla por otro motivo.
