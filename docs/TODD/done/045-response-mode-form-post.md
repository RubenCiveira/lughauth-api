# 045 — Implementar response_mode=form_post si se anuncia

- **Estado:** hecho (EP-08, aprobada por producto). **Prioridad:** baja.
- **Evidencia:** [OpenIdConfiguration](../../../src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php) anuncia `form_post`; [OidcResponseBuilder](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Services/OidcResponseBuilder.php) solo crea `Location` 302.
- **Impacto:** RP que elija form_post recibe modo incorrecto.
- **Por hacer:** tras resolver 030/033, generar formulario HTML autoenviado con parámetros escapados y política CSP apropiada, o mantener metadatos sin form_post hasta disponer de ello.
- **Aceptación:** respuesta `form_post` llega por POST al redirect registrado sin tokens en URL.
- **Origen:** análisis OAuth §4/3.2.

## Detalle de implementación (solo si se aprueba)

- `src/Features/OAuth/Authentication/Domain/OidcFlowContext.php`: leer/validar `response_mode=form_post` junto con `response_type` y política del RP.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Services/OidcResponseBuilder.php`: producir HTML con formulario POST autoenviado, campos escapados y redirección exclusivamente a URI validada; tratar éxitos y errores de modo uniforme.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php`: usar mismo modo en errores redirigibles y aplicar CSP segura al documento HTML.
- `src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php` y `test/integration/Features/OAuth/`: anunciar el modo después de tests de navegador/RP.
