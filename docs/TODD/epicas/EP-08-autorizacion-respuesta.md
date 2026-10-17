# EP-08 — Autorización: construcción de la respuesta

- **Tareas:** [027](../027-respuestas-autorizacion-seguras.md) (parámetros codificados y errores con `state`), [037](../037-iss-respuesta-autorizacion.md) (`iss` RFC 9207), [033](../033-hybrid-y-response-types.md) (hybrid y `response_type` por cliente), [070](../070-validacion-nonce.md) (nonce por flujo) y, opcional, [045](../045-response-mode-form-post.md) (`form_post`).
- **Prioridad:** alta. **Tamaño:** M. **Depende de:** EP-03 (`responseTypes`), EP-05 (`ClaimsMapper` y `c_hash`/`at_hash`). 027 y 037 pueden adelantarse. **Desbloquea:** EP-14 (JARM).
- **Esquema:** sin cambios; el nonce va en `_oauth_temporal_codes.code_data`.

## Por qué juntas

Las cinco cambian cómo [OidcResponseBuilder](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Services/OidcResponseBuilder.php) y [AuthorizeHtml::redirectError](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php) devuelven el resultado al RP. Hoy los parámetros se concatenan sin codificar, el error siempre va en `#fragment` sin `state` ni `iss`, y el ID token del hybrid acaba en la query. Todo se resuelve con un único constructor que recibe `response_type`, `response_mode`, la redirect ya validada y los parámetros.

## Componente común

**`AuthorizationResponseWriter`**:

- Modo efectivo: `query` para `code`; `fragment` para cualquier `response_type` con token o `id_token`; `form_post` si se solicita y está permitido (045). JARM (054) se añadirá como modo más.
- Codifica con `http_build_query` (RFC 3986) respetando la query que ya tenga la URI registrada.
- Siempre `state` (una vez) e `iss` (037), en éxitos y en errores redirigibles. Nunca devuelve `nonce` como parámetro.
- Si la redirect no es válida, error local sin redirigir.

## Plan

1. **Contexto.** [OidcFlowContext](../../../src/Features/OAuth/Authentication/Domain/OidcFlowContext.php) normaliza `response_type` (conjunto ordenado), lee `response_mode` y conserva `state`.
2. **Writer (027 + 037).** Implementarlo y migrar éxitos y errores. Activar `authorization_response_iss_parameter_supported` en discovery (EP-01).
3. **Response types (033).** Validar el `response_type` contra `ClientData::responseTypes` antes de mostrar el login. Hybrid `code id_token` en fragment con `c_hash`, y `at_hash` si se devuelve token, usando el mapper de EP-05.
4. **Implicit.** Decidir si se retira (recomendado, OAuth 2.1) o se mantiene con las mismas reglas; reflejarlo en discovery.
5. **Nonce (070).** Obligatorio si el `response_type` incluye `id_token` en el front-channel; se persiste en el code ([TemporalAuthCode](../../../src/Features/OAuth/Session/Domain/TemporalAuthCode.php)) y se emite íntegro en el ID token; el `/token` nunca acepta un nonce externo.
6. **Opcional (045).** `form_post`: HTML autoenviado con campos escapados y CSP estricta; activar en discovery tras probarlo.

## Pruebas

- Caracteres reservados en `state` no crean parámetros; query previa en la URI registrada se conserva.
- Errores en code y hybrid llevan `state` e `iss` en el modo correcto; redirect inválida da error local.
- `code id_token` en fragment con `c_hash`; `response_type` no registrado da error sin token.
- Nonce ausente donde se exige; nonce emitido igual al solicitado.
- `iss` idéntico al discovery del tenant ([DiscoveryTest](../../../test/integration/Features/OAuth/DiscoveryTest.php)); [AuthorizeHtmlIntegrationUnitTest](../../../test/unit/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtmlIntegrationUnitTest.php).

## Aceptación

Unión de 027, 033, 037 y 070 (y 045 si se aprueba).
