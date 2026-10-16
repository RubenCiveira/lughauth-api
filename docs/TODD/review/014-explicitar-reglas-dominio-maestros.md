# 014 — Hacer descubribles las reglas de cada maestro

- **Estado:** pendiente, diseño opcional. **Prioridad:** baja.
- **Evidencia:** [Role](../../src/Features/Access/Role/Domain/Role.php) expresa pocos invariantes. Las reglas efectivas están en las políticas y en plugins como [RolePlugin](../../src/Features/Access/Role/Infrastructure/Driver/RolePlugin.php). Su fuente real es el modelo del generador (en el repo solo hay una copia antigua, [multitenant-oidc.xml](../../multitenant-oidc.xml); ver 001), que declara por entidad `<allow>`, `<visibility>`, `<fixed-field>`, `<formula>`, `<index unique>`, `required` y `default`.
- **Impacto:** para conocer las restricciones de negocio hay que seguir listeners y eventos implícitos en el código generado. Ejemplo: en el código parece que Role tiene política root, pero el modelo no la declara (002).
- **Por hacer:** tratar el modelo XML versionado como documento de reglas (001) y generar desde él un resumen legible por entidad (p. ej. un docblock en `*Plugin` o un `RULES.md` por módulo) que liste las políticas, la visibilidad y los invariantes registrados. Mover al dominio solo las reglas que de verdad pertenecen al agregado.
- **Aceptación:** las reglas de Role y User se localizan desde un único punto y tienen tests de comportamiento.
- **Origen:** ambos análisis de maestros, §5.1/§5.2.

## Decisión de diseño

- **¿Dominio rico o modelo declarativo?**
  - (a) Mover invariantes a métodos de la entidad (`Role::rename()` valida unicidad…).
  - (b) Aceptar que en los maestros CRUD la «fuente de verdad» es el modelo XML y hacerlo legible y revisable.
- **Recomendación:** (b) para los maestros, porque es coherente con generarlos. Reservar (a) para los agregados con comportamiento real, que hoy están en `src/Features/OAuth` y no en `Access`. Depende de versionar el modelo (001).
