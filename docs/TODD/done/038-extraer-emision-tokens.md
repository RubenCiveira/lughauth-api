# 038 — Extraer la emisión de tokens del controller

- **Estado:** hecho (EP-05). **Prioridad:** media.
- **Evidencia:** [TokenController](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php) construye audiences/claims y firma tres tipos de tokens dentro de `handlePost`; [OidcResponseBuilder](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Services/OidcResponseBuilder.php) emite otros JWT por separado.
- **Impacto:** duplicidad y errores de semántica entre grants/flujos.
- **Por hacer:** servicio de aplicación para emisión/verificación por propósito y mapper de claims, con gateway de firma; mantener controller limitado al protocolo HTTP y centralizar políticas de expiración.
- **Aceptación:** flujos code, refresh, device e hybrid comparten reglas por tipo y tests sin depender de controller.
- **Origen:** análisis OAuth §5.8/1.9 y `_2.md` paridad.

## Detalle de implementación

- `src/Features/OAuth/Authentication/Application/` (**nuevos** `TokenFactory`/`ClaimsMapper`): construir claims y expiraciones por grant/tipo, compartidos por token endpoint y autorización.
- `src/Features/OAuth/TokenSecurity/Domain/Gateway/TokenSigner.php` y `Infrastructure/Driven/JoseTokenSigner.php`: proporcionar firma/verificación explícita por tipo y tenant; la aplicación no debe manejar claves privadas.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php` y `Infrastructure/Driver/Html/Services/OidcResponseBuilder.php`: delegar emisión al servicio; dejar parseo HTTP y respuesta de protocolo en drivers.
- `src/Bootstrap/Plugin/OAuthPlugin.php` y `test/integration/Features/OAuth/TokenEndpointTest.php`: vincular dependencias nuevas y verificar que grants existentes conservan semántica tras la extracción.
