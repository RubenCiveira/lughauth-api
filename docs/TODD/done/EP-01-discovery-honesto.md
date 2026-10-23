# EP-01 — Discovery honesto y por capacidades

- **Tareas:** [030](030-discovery-honesto.md) (núcleo). Incluye la **parte de retirada** de discovery de [034](034-pkce-s256-y-politica.md), [037](037-iss-respuesta-autorizacion.md), [045](045-response-mode-form-post.md), [046](046-par-no-sobrescribible.md), [053](../053-mtls-certificados.md), [054](../054-jarm.md), [055](../055-ciba.md), [056](../056-subject-pairwise.md), [057](../057-jwe-id-userinfo.md), [060](../060-userinfo-firmado.md), [061](../061-claims-parameter.md), [062](../pospuestos/062-session-management-iframe.md) y [067](067-hints-y-acr-step-up.md) (ya publica `acr_values_supported`). La implementación de cada una sigue en su épica.
- **Estado:** hecho. **Prioridad:** alta. **Tamaño:** S. **Depende de:** nada. **Desbloquea:** el resto activa flags en vez de editar el documento.
- **Esquema:** sin cambios.

## Por qué juntas

Todas las retiradas tocan los mismos tres archivos ([OpenIdConfiguration](../../../src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php), [OpenIdConfigurationController](../../../src/Features/OAuth/Oidc/Infrastructure/Driver/Rest/OpenIdConfigurationController.php) y [MtlsDiscoveryContributor](../../../src/Features/OAuth/Oidc/Infrastructure/Driven/MtlsDiscoveryContributor.php)) y se verifican con el mismo test de contrato. Hacerlas una a una en cada épica opcional deja el discovery mintiendo hasta que se aprueben, lo que puede no ocurrir nunca.

## Componente común

Un conjunto de **capacidades** (p. ej. `OidcCapabilities` en `Oidc/Domain`) del que se deriva el documento: grants, `response_types`, `response_modes`, algoritmos y flags. Cada épica posterior activa su capacidad cuando la implementa. El controller construye el documento con el issuer canónico del tenant y aplica solo los contributors cuyos endpoints están registrados.

## Plan

1. **Inventario.** Tabla campo a campo de `OpenIdConfiguration` frente a la ruta o el código que lo implementa ([OAuthPlugin](../../../src/Bootstrap/Plugin/OAuthPlugin.php)). Se guarda en esta épica al cerrarla.
2. **Retirar lo inexistente:**
   - CIBA: `backchannel_authentication_endpoint` y modos `poll`/`ping` (055).
   - `response_modes`: `form_post` (045) y modos JARM (054).
   - `subject_types_supported`: solo `public` (056).
   - Algoritmos `*_encryption_*` de ID token y UserInfo (057), `authorization_signing/encryption_*` (054) y `userinfo_signing_alg_values_supported` (060).
   - `claims_parameter_supported: false` (061); quitar `check_session_iframe` (062).
   - `require_pushed_authorization_requests: false` mientras 046 no lo exija (046).
   - Alias mTLS por defecto (053): `MtlsDiscoveryContributor` solo publica si hay terminación TLS de confianza configurada.
   - `acr_values_supported` fijo (067).
   - `authorization_response_iss_parameter_supported: false` hasta EP-08 (037).
   - `code_challenge_methods_supported: ["S256"]` (034); anunciar solo S256 es correcto aunque todavía se acepte `plain`.
   - `implicit` y `response_types` con `token` si se decide retirarlos (ver EP-08).
3. **Capacidades.** Sustituir los literales por valores derivados de las capacidades y de los contributors, conectando [DiscoveryContributorGateway](../../../src/Features/OAuth/Oidc/Domain/Gateway/DiscoveryContributorGateway.php) solo con endpoints reales.
4. **Test de contrato.** [DiscoveryTest](../../../test/integration/Features/OAuth/DiscoveryTest.php) y [ApiDocOidcContractTest](../../../test/unit/Features/OAuth/ApiDocOidcContractTest.php): cada `*_endpoint` anunciado existe en `OAuthPlugin`, y cada flag activo tiene una capacidad que lo respalda.

## Aceptación

- La de 030: cada endpoint, algoritmo o grant anunciado tiene implementación y test; los no disponibles no figuran.
- Activar una capacidad en una épica posterior es un cambio de una línea y lo cubre el test de contrato.

## Inventario (al cerrar)

Capacidades en [OidcCapabilities::implemented()](../../../src/Features/OAuth/Oidc/Domain/OidcCapabilities.php); rutas en [OAuthPlugin](../../../src/Bootstrap/Plugin/OAuthPlugin.php). Los `*_endpoint`/`*_uri` los comprueba `DiscoveryTest::testEveryAnnouncedEndpointIsRegisteredInOAuthPlugin`.

| Campo | Valor publicado | Respaldo |
|---|---|---|
| `issuer` | `Context::getIssuer($tenant)` | issuer canónico, nunca derivado del `Host` |
| `authorization_endpoint` | `/authorize` | `AuthorizeHtml::authorize/formAuthorize` |
| `token_endpoint` | `/token` | `TokenController::post` |
| `introspection_endpoint` | `/introspect` | `IntrospectionController::post` |
| `userinfo_endpoint` | `/userinfo` | `UserInfoController::get` |
| `end_session_endpoint` | `/logout` | `LogoutController::logout` |
| `jwks_uri` | `/jwks` | `JwksController::get` |
| `registration_endpoint` | `/register` | `DynamicClientController` |
| `revocation_endpoint` | `/revoke` | `LogoutController::revoke` |
| `device_authorization_endpoint` | `/device` | `DeviceAuthorizationController::post` |
| `pushed_authorization_request_endpoint` | `/par` | `ParController::post` |
| `grant_types_supported` | `authorization_code`, `implicit`, `refresh_token`, `client_credentials`, `password`, device_code | `TokenController` + `TokenGranter/*`; `implicit` por `OidcResponseBuilder` |
| `response_types_supported` | `code`, `token`, `id_token` | `OidcResponseBuilder` (retirada de `token`: EP-08) |
| `response_modes_supported` | `query`, `fragment` | `OidcResponseBuilder`; **retirado** `form_post` (045) y JARM (054) |
| `subject_types_supported` | `public` | **retirado** `pairwise` (056) |
| `id_token_signing_alg_values_supported` | `RS256` | `JoseTokenSigner` |
| `request_object_signing_alg_values_supported` | `RS256`, `ES256`, `PS256` | `RequestObjectValidator` |
| `token_endpoint_auth_methods_supported` | `client_secret_basic`, `client_secret_post`, `none` | `TokenController::authenticateClient` |
| `introspection_endpoint_auth_methods_supported` | `client_secret_basic`, `client_secret_post` | `IntrospectionController::authenticateClient` |
| `revocation_endpoint_auth_methods_supported` | `client_secret_basic`, `client_secret_post`, `none` | `LogoutController::authenticateClient` |
| `code_challenge_methods_supported` | `S256` | `PkceChallenge` (aún acepta `plain`, 034) |
| `request_parameter_supported` | `true` | `RequestObjectValidator` |
| `request_uri_parameter_supported` / `require_request_uri_registration` | `false` | `request_uri` solo para PAR |
| `require_pushed_authorization_requests` | `false` | 046 |
| `claims_parameter_supported` | `false` | 061 |
| `authorization_response_iss_parameter_supported` | `false` | EP-08 (037) |
| `backchannel_logout_supported` / `_session_supported` | `true` | `BackChannelLogoutDispatcher` (logout+jwt con `sid`) |
| `frontchannel_logout_supported` / `_session_supported` | `true` | `LogoutController` (iframes con `iss`/`sid`) |
| `tls_client_certificate_bound_access_tokens` | `false` | 053 |
| `mtls_endpoint_aliases` | solo si `app.oauth.mtls-base-url` | `MtlsDiscoveryContributor`; sin alias CIBA |
| **Retirados** | `backchannel_authentication_endpoint`, `backchannel_token_delivery_modes_supported`, `backchannel_authentication_request_signing_alg_values_supported` (055); `check_session_iframe` (062; `/check-session` es refresco silencioso, no iframe); `acr_values_supported` (067); `id_token_encryption_*`, `userinfo_encryption_*` (057); `userinfo_signing_alg_values_supported` (060); `authorization_signing/encryption_*` (054); `request_object_encryption_*`; `*_endpoint_auth_signing_alg_values_supported` (sin `private_key_jwt`, 044) | — |

Los contributors solo pueden **añadir** campos: si redefinen uno base, `OpenIdConfigurationController` lanza `LogicException`.

## Fuera de alcance

La implementación de los protocolos retirados (EP-08, EP-12 a EP-15) y la documentación (066, EP-16).
