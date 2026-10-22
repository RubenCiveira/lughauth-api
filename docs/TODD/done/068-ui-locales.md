# 068 — Evaluar `ui_locales` por petición

- **Estado:** hecho (EP-07, aprobado por producto). **Prioridad:** baja.
- **Evidencia:** [OidcFlowContext::fromRequest](../../../src/Features/OAuth/Authentication/Domain/OidcFlowContext.php) toma locale de `Accept-Language` y no usa `ui_locales`.
- **Impacto:** RP no pueden seleccionar idioma de login de forma interoperable.
- **Por hacer:** negociar el primer locale compatible de `ui_locales` con fallback a cabecera/tenant, propagar a formularios y errores y probar prioridades.
- **Aceptación:** un locale soportado solicitado afecta a la interfaz sin alterar tenant ni preferencias permanentes.
- **Origen:** análisis OAuth §5.1.

## Detalle de implementación

- `src/Features/OAuth/Authentication/Domain/OidcFlowContext.php`: capturar lista `ui_locales` por prioridad y escoger primer idioma soportado, con fallback a `Accept-Language`.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php` y `Infrastructure/Driver/Html/Forms/UseMfaForm.php` (más formularios que fuerzan locale vacío): propagar el locale resuelto a `MessageProvider`/plantilla durante todos los pasos.
- `test/integration/Features/OAuth/`: probar idioma admitido, no admitido y prioridad entre preferencias sin persistirlo en el perfil.
