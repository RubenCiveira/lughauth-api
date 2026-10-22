# EP-08 — Autorización: construcción de la respuesta

- **Tareas:** [027](027-respuestas-autorizacion-seguras.md) (parámetros codificados y errores con `state`), [037](037-iss-respuesta-autorizacion.md) (`iss` RFC 9207), [033](033-hybrid-y-response-types.md) (hybrid y `response_type` por cliente), [070](070-validacion-nonce.md) (nonce por flujo) y, opcional, [045](045-response-mode-form-post.md) (`form_post`).
- **Estado:** hecho (027, 033, 037, 070 y 045, aprobada por producto; implicit retirado). **Prioridad:** alta. **Tamaño:** M. **Depende de:** EP-03 (`responseTypes`), EP-05 (`ClaimsMapper` y `c_hash`/`at_hash`). 027 y 037 pueden adelantarse. **Desbloquea:** EP-14 (JARM).
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

## Resultado

### Decisiones tomadas

- **Implicit retirado (OAuth 2.1):** `/authorize` solo acepta `code` y los hybrid (`code id_token`, `code token`, `code id_token token`), que son [ResponseType::SUPPORTED](../../../src/Features/OAuth/Authentication/Domain/ValueObject/ResponseType.php). `token`, `id_token` e `id_token token` dan `unsupported_response_type`. Discovery deriva `response_types_supported` de esa lista. El grant `implicit` sigue en `grant_types_supported` porque RFC 7591 §2.1 lo exige para los hybrid; [DcrMetadataPolicy](../../../src/Features/OAuth/Client/Domain/DcrMetadataPolicy.php) rechaza registrar un `response_type` no soportado (también `none`), aunque el cliente tenga el grant.
- **`form_post` (045) aprobado e implementado** como tercer modo de [ResponseMode](../../../src/Features/OAuth/Authentication/Domain/ValueObject/ResponseMode.php).
- **`response_type` como conjunto:** se normaliza al orden `code id_token token` (`OidcFlowContext::fromRequest`), y el registrado por el cliente se compara también como conjunto, así que `id_token code` equivale a `code id_token`.
- **Modo efectivo:** el solicitado o, si no se pide ninguno, `query` para `code` y `fragment` para los hybrid. `query` con tokens o un modo desconocido son `invalid_request`, y ese error se entrega en el modo por defecto (`OidcFlowContext::responseMode()`). `response_mode` viaja entre pasos con `continuationParams()`.
- **Orden de validación** ([AuthorizeHtml::rejectInvalidRequest](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php)), siempre después de validar la redirect y antes de mostrar el login: `response_type` ausente (`invalid_request`), no soportado (`unsupported_response_type`) o no registrado para el cliente (`unauthorized_client`); modo inválido; `id_token` sin `nonce` o sin scope `openid` (`invalid_request`); audiencia (`invalid_target`); PKCE.
- **Errores:** `prompt=none` sin sesión o sin `csid` válido devuelve `login_required` (antes `No session` / `Only refresh from same device`, que no son códigos OAuth). Si la redirect no está registrada, el error es local (`UnauthorizedException`, sin redirigir). Los errores de PAR/JAR del resolvedor siguen siendo locales, porque se producen antes de conocer una redirect validada.
- **Nonce (070):** obligatorio cuando el `response_type` incluye `id_token`, y opcional con `code`. Se guarda tal cual en el `TemporalAuthCode` y `/token` usa solo ese valor; el `nonce` enviado al endpoint de token se ignora. Ya no se devuelve como parámetro de la respuesta: antes se añadía `&nonce=` si no había code.
- **Parámetros repetidos en la URI registrada:** si la redirect ya lleva en su query o fragment un parámetro con el nombre de uno de la respuesta (`state`, `code`, `iss`, `error`…, también codificado como `st%61te`), se elimina, y la respuesta lo envía una sola vez con su valor. RFC 6749 §3.1.2 obliga a conservar la query registrada, pero un duplicado haría ambigua la respuesta y rompería el «`state` una vez». Con `form_post` se eliminan también de la URL de acción del formulario.
- **Magic link limitado a code en query:** el enlace del correo solo puede devolver un code en la query, así que solo se ofrece (botón en `LoginForm`) y solo se envía (`MagicLinkLoginForm::authenticate`) cuando el flujo es `response_type=code` con modo `query` (`OidcFlowContext::isCodeInQuery()`). En hybrid, `fragment` o `form_post` no aparece, y forzar el paso da `UnauthorizedException` sin enviar nada.
- **`token_type=Bearer`** se añade cuando el hybrid devuelve un access token (RFC 6749 §4.2.2).

### Componente común

[AuthorizationResponseWriter](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Services/AuthorizationResponseWriter.php) entrega éxitos (`success`) y errores (`error`) en el modo del flujo, siempre con `state` (una vez y solo si el cliente lo envió) e `iss` (issuer canónico del tenant, el mismo de discovery):

- `query`/`fragment`: 302. `ResponseMode::redirectUri` codifica con `http_build_query` RFC 3986 y añade los parámetros tras la query o el fragment que ya tenga la URI registrada.
- `form_post`: página 200 con un formulario autoenviado y campos escapados (`htmlspecialchars` con `ENT_QUOTES | ENT_SUBSTITUTE`). La CSP es `default-src 'none'`, `script-src` con el hash SHA-256 del único script, `form-action` limitado al origen de la redirect (o al esquema de una app nativa), `base-uri 'none'` y `frame-ancestors 'none'`. También envía `Cache-Control: no-store` y `Referrer-Policy: no-referrer`, e incluye un botón `<noscript>` como alternativa.
- `errorButton`: el botón «volver» de la página de error de sesión (`renderSessionError`) usa el mismo modo: un enlace o, con `form_post`, un formulario POST.

[OidcResponseBuilder](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Services/OidcResponseBuilder.php) solo decide qué se emite (code, tokens con `c_hash`/`at_hash` de EP-05) y delega en el writer. JARM (054) será un caso más de `ResponseMode`.

El code del magic link ([VerifyMagicLinkUsecase](../../../src/Features/OAuth/MagicLink/Application/Usecase/VerifyMagicLink/VerifyMagicLinkUsecase.php)) también es una respuesta de autorización: usa `ResponseMode::Query->redirectUri` con `state` e `iss` (antes concatenaba sin respetar la query registrada).

### Qué cambió

- Discovery: `response_types_supported` = los cuatro anteriores; `response_modes_supported` = `query`, `fragment`, `form_post`; `authorization_response_iss_parameter_supported: true`.
- Pruebas: [ResponseTypeUnitTest](../../../test/unit/Features/OAuth/Authentication/Domain/ValueObject/ResponseTypeUnitTest.php) (normalización, soportados, modo por tipo, codificación, query y fragment previos, caracteres reservados en `state`); [AuthorizationResponseWriterUnitTest](../../../test/unit/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizationResponseWriterUnitTest.php) (query/fragment, `state` e `iss`, error en el modo por defecto, `form_post` escapado y CSP, esquema nativo, botón de error); [AuthorizeHtmlIntegrationUnitTest](../../../test/unit/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtmlIntegrationUnitTest.php) (implicit retirado, hybrid no registrado, tokens en query, nonce y `openid`, `login_required`, redirect no registrada sin redirigir); [AuthorizationResponseTokensTest](../../../test/integration/Features/OAuth/AuthorizationResponseTokensTest.php) (`iss` igual al de discovery, hybrid por `form_post` con `c_hash`, `token_type`, sin `nonce`); [TokenEndpointTest](../../../test/integration/Features/OAuth/TokenEndpointTest.php) (un nonce enviado a `/token` no sustituye al solicitado); [MagicLinkLoginFormStepUnitTest](../../../test/unit/Features/OAuth/Authentication/Infrastructure/Driver/Html/Forms/MagicLinkLoginFormStepUnitTest.php) (enlace solo para code en query); duplicados de la URI registrada en `ResponseTypeUnitTest` y `AuthorizationResponseWriterUnitTest`; DCR, magic link y discovery de dominio.
- **Cambios de comportamiento:** los errores de `code` van ahora en la query (antes siempre en `#fragment`) y llevan `state` e `iss`. Un cliente sin `response-types-json` solo puede usar `code`, así que los hybrid exigen registrarlos. `response_type` ausente ya no produce una redirect vacía.

### Pendiente o fuera de alcance

- **Magic link en hybrid, `fragment` o `form_post`:** ahora no está disponible (ver decisiones). Para ofrecerlo habría que guardar la solicitud completa en el enlace (con una referencia como `request_ref`, que además llevaría el PKCE, que hoy el code del magic link tampoco tiene) y, al verificarlo, volver a `/authorize` con la sesión ya creada para que la respuesta salga por `AuthorizationResponseWriter`. Requiere una migración de `_oauth_magic_link`.
- Los errores del resolvedor PAR/JAR (`invalid_request_object`, etc.) siguen siendo locales; RFC 9101 permite redirigirlos cuando `client_id` y `redirect_uri` son fiables.
