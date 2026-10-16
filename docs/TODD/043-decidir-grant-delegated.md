# 043 — Decidir si hace falta un grant `delegated`

- **Estado:** no implementado; decisión de producto. **Prioridad:** baja.
- **Evidencia:** [TokenController](../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php) no tiene grant `delegated`; [DelegateLogin](../../src/Features/OAuth/DelegateLogin/Application/DelegateLogin.php) implementa federación interactiva, que es otro caso de uso.
- **Impacto:** la paridad con Phylax solo aporta valor si hay delegación entre clientes autorizados.
- **Por hacer:** concretar actor, alcance, consentimiento y audiencia; elegir grant estándar (p. ej. token exchange de 052) o descartar el propietario; no confundirlo con login federado.
- **Aceptación:** decisión y modelo de autorización documentados antes de añadir ruta/metadata.
- **Origen:** análisis OAuth §5.2/2.5.

## Detalle de implementación (solo si se aprueba)

- `src/Features/OAuth/Authentication/Application/TokenGranter/` (**nuevo** `ResolverForDelegated.php`): describir actor, sujeto, autorización y auditoría del grant; si se elige RFC 8693, reutilizar la tarea 052 en lugar de duplicar estrategia.
- `src/Features/OAuth/Authentication/Application/TokenGranter/TokenGranterMediator.php` y `Infrastructure/Driver/Rest/TokenController.php`: enrutar y validar grant únicamente para clientes permitidos; `src/Features/OAuth/Client/Domain/ClientData.php` expone permisos.
- `src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php` y `test/integration/Features/OAuth/TokenEndpointTest.php`: anunciar solo grant real y comprobar límites de tenant, actor y audiencia.
