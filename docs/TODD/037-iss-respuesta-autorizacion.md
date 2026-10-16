# 037 — Incluir `iss` en respuestas OAuth de autorización

- **Estado:** pendiente. **Prioridad:** media. **Dependencia:** 015, 027.
- **Evidencia:** [OpenIdConfiguration](../../src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php) anuncia `authorization_response_iss_parameter_supported: true`; [OidcResponseBuilder](../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Services/OidcResponseBuilder.php) no lo añade.
- **Impacto:** RP que confían en RFC 9207 no pueden validar origen de la respuesta.
- **Por hacer:** adjuntar issuer canónico correctamente codificado a éxitos y errores redirigibles; no anunciar soporte antes de implementarlo.
- **Aceptación:** code y hybrid incluyen `iss` idéntico al discovery del tenant.
- **Origen:** análisis OAuth §4/1.8 y `_2.md` brechas.

## Detalle de implementación

- `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Services/OidcResponseBuilder.php`: añadir `iss` codificado al query/fragment/form_post correcto en respuestas exitosas; usar el issuer canónico de la misma solicitud.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php`: añadirlo también a errores redirigibles con redirect ya validado.
- `src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php`: conservar el flag RFC 9207 solo cuando los flujos anunciados lo cumplan.
- `test/integration/Features/OAuth/DiscoveryTest.php` y `test/integration/Features/OAuth/`: comparar `iss` de code/hybrid/error con discovery del tenant.
