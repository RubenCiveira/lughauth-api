# 027 — Codificar parámetros y errores de autorización

- **Estado:** hecho (EP-08). **Prioridad:** alta.
- **Evidencia:** [OidcResponseBuilder](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Services/OidcResponseBuilder.php) concatena `state`/`nonce`; [AuthorizeHtml::redirectError](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php) devuelve siempre `#error` sin `state` ni `iss`.
- **Impacto:** parámetros inyectables o pérdida de correlación en errores; divergencia en query/fragment.
- **Por hacer:** construir URI con codificación de parámetros y preservación de query/fragment preexistente; elegir response_mode correcto y añadir `state`/`iss` cuando corresponda tras validar redirect.
- **Aceptación:** caracteres reservados no crean parámetros adicionales; éxito/error code usan query y flujos fragment usan fragment.
- **Origen:** análisis OAuth §3.6/0.9 y `_2.md` respuestas.

## Detalle de implementación

- `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Services/OidcResponseBuilder.php`: construir query/fragment con `http_build_query` RFC3986 respetando los componentes ya presentes en la URI registrada; incluir `state` una sola vez y no trasladar `nonce` como parámetro de respuesta no requerido.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php`: sustituir `redirectError` fijo a fragment por respuesta según `response_type`/`response_mode`, con `state` e `iss` solo después de validar el destino; ante redirect inválido devolver error local.
- `src/Features/OAuth/Authentication/Domain/OidcFlowContext.php`: conservar estado, tipo y modo de respuesta para construir éxito y error coherentes.
- `test/integration/Features/OAuth/` y `test/unit/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtmlIntegrationUnitTest.php`: probar caracteres reservados, query previa y errores en code/hybrid.
