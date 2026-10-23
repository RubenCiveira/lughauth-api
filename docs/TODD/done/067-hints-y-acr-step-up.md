# 067 — Evaluar hints y step-up ACR en autorización

- **Estado:** hecho (opcional de [EP-09](EP-09-sesion-sso-y-logout.md), reabierta desde `pospuestos/`). **Prioridad:** baja.
- **Evidencia:** [OidcFlowContext](../../../src/Features/OAuth/Authentication/Domain/OidcFlowContext.php) captura `prompt` pero no `login_hint`, `id_token_hint` ni `acr_values`; [OpenIdConfiguration](../../../src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php) no publica actualmente `acr_values_supported`.
- **Impacto:** RP no pueden orientar la cuenta ni solicitar un nivel de autenticación verificable.
- **Por hacer:** decidir semántica y privacidad de hints, comprobar firma/tenant del ID hint, definir ACR reales por método/MFA y forzar step-up cuando se solicite; anunciar solo ACR demostrables en discovery.
- **Aceptación:** ACR solicitado no se afirma sin autenticación suficiente y los hints no autentican por sí mismos.
- **Origen:** análisis OAuth §5.1, capacidades `login_hint`/`id_token_hint`/`acr_values`.

## Decisiones

- **Sin cambios en el modelo de datos.** El ACR no se guarda: se deriva del `amr` que la sesión ya registra junto a `auth_time` (EP-05/EP-10). La columna `_oauth_session.acr` (`TINYINT`) queda sin uso, y tampoco se usan `tenant-config.acr-values-supported` ni `trusted-client.default-acr-values`.
- **Dos niveles** ([`Acr`](../../../src/Shared/Security/Acr.php), en `Shared` para que cualquier API los compare): `SINGLE_FACTOR` para cualquier sesión autenticada (contraseña, passkey, federado, códigos de recuperación o registro) y `MULTI_FACTOR` cuando el `amr` incluye `mfa` (TOTP o código de recuperación MFA).
- **Los valores ACR que los nombran son configuración** ([`AcrValues`](../../../src/Shared/Security/AcrValues.php)), porque no hay un catálogo cerrado y deben casar con los de otros IdP y RP:

  | Nivel | Clave | Variable de entorno | Por defecto |
  |---|---|---|---|
  | `SINGLE_FACTOR` | `security.acr.single-factor` | `SECURITY_ACR_SINGLE_FACTOR` | `urn:lugh:acr:1fa` |
  | `MULTI_FACTOR` | `security.acr.multi-factor` | `SECURITY_ACR_MULTI_FACTOR` | `urn:lugh:acr:mfa` |

  Cada clave admite una lista separada por comas o espacios. El **primer valor** es el que este servidor emite en los tokens, publica en discovery y pide en los challenges. Los **demás son alias** aceptados en `acr_values` y en tokens de otros emisores (p. ej. `SECURITY_ACR_MULTI_FACTOR="urn:corp:loa:3, http://schemas.openid.net/pape/policies/2007/06/multi-factor"`). Configurar un valor reemplaza el valor por defecto, que deja de aceptarse. Un mismo valor no puede nombrar dos niveles: falla al arrancar el contenedor. Servidor OIDC y APIs deben compartir la configuración. `AcrValues` se registra en el contenedor del núcleo (`Micro`).
- **`acr_values` es voluntario:** se usa el primer valor conocido de la lista, por orden de preferencia, y los desconocidos se ignoran. El nivel pedido viaja en `AuthenticationRequest::requiredAcr` durante todo el flujo, incluido el consentimiento de scopes.
- **Usuario sin TOTP:** se le ofrece el alta (`build-mfa`) aunque ni el usuario ni el tenant exijan MFA. Una semilla existente nunca se sustituye.
- **El enlace mágico no se admite** cuando se pide MFA, porque no aporta segundo factor: el login no lo ofrece y el paso lo rechaza aunque se envíe por POST directo, sin mandar el correo. El enlace lleva firmada su propia petición de autorización, así que sin enlace para esta petición no hay forma de completarla con un solo factor.
- **El nivel es de la sesión SSO, no del cliente.** Un step-up hecho para un RP eleva también los tokens que emitan los demás RP de esa sesión, incluidos los refrescos (que leen la sesión por `sid`).

## Comportamiento

- **Sesión fresca por debajo del ACR pedido** ([`SessionSufficiencyPolicy`](../../../src/Features/OAuth/Authentication/Application/SessionSufficiencyPolicy.php) → `StepUp`). El usuario queda identificado por la sesión (`IdentificationMethod::SESSION`) y solo se le pide el TOTP, o su alta. El pre-session guarda el `sid` público de esa sesión (`AuthenticateUser::STEP_UP_SID`), nunca su clave. Al probar el factor, [`AuthenticateUser`](../../../src/Features/OAuth/Authentication/Application/AuthenticateUser.php) localiza esa misma sesión por el `sid` (`SessionStoreGateway::findActiveSessionIdBySid`), le añade `otp mfa` al `amr` y mueve su `auth_time`. Si esa sesión ya no está activa (logout o caducidad) o es de otro usuario, el step-up se rechaza: no se abre otra sesión MFA ni se eleva la que tenga el navegador en ese momento. Con `prompt=none` se devuelve `interaction_required`.
- **Garantía central.** `AuthenticateUser::saveIt` no guarda ni reutiliza una sesión cuyo `amr` no alcance el nivel pedido: lanza el paso de MFA (o el alta) conservando la identificación lograda. Cubre el login con contraseña, passkey, federado, recuperación y registro.
- **La identificación por sesión solo sirve para el step-up.** Sin el segundo factor probado en ese flujo no es un login: un step-up a medias, reenviado sin `acr_values`, no emite nada ni mueve `auth_time`, así que no puede saltarse `prompt=login` ni `max_age`.
- **`login_hint`** (máx. 256 caracteres) solo rellena, escapado, el usuario del formulario de login.
- **`id_token_hint`** debe ser un ID token de este tenant emitido al cliente (firma, emisor y audiencia; se acepta caducado, [`IdTokenHintVerifier`](../../../src/Features/OAuth/Authentication/Application/IdTokenHintVerifier.php)). Si no lo es, se responde `invalid_request`. Si nombra a otro usuario, la sesión no se reutiliza (login, o `login_required` con `prompt=none`). Nunca autentica por sí mismo.
- **Tokens:** el ID token y el access token llevan `acr` (el valor configurado del nivel), `amr` y `auth_time` de la sesión ([`ClaimsMapper`](../../../src/Features/OAuth/Authentication/Application/ClaimsMapper.php)).
- **Discovery** publica en `acr_values_supported` el valor emitido de cada nivel, y `acr` en `claims_supported`.

## Step-up en las APIs (RFC 9470)

- [`JwtVerifierMiddleware`](../../../src/Bootstrap/Middleware/JwtVerifierMiddleware.php) pasa `acr` y `auth_time` del access token al [`AuthenticationContext`](../../../src/Shared/Security/AuthenticationContext.php), que resuelve el nivel con los valores configurados (alias incluidos). `auth_time` nunca se sustituye por `iat`. `amr` se admite como lista.
- La lógica exige el nivel con `$context->getAuthenticationContext()->requireAcr(Acr::MULTI_FACTOR, 300)`. Si decide ella misma que no basta, lanza `throw $context->getAuthenticationContext()->stepUp(Acr::MULTI_FACTOR, 300, 'Confirma la transferencia')`, que construye la [`InsufficientUserAuthenticationException`](../../../src/Shared/Exception/InsufficientUserAuthenticationException.php) con el valor configurado (con `null` como nivel solo pide una autenticación más reciente).
- [`ErrorsPlugin`](../../../src/Bootstrap/Plugin/ErrorsPlugin.php) responde `401` con `WWW-Authenticate: Bearer error="insufficient_user_authentication", error_description="…", acr_values="urn:lugh:acr:mfa", max_age=300`, y repite los datos en el cuerpo JSON. El cliente redirige a `/authorize` con esos `acr_values`/`max_age` y repite la llamada con el token nuevo.
- CORS expone `WWW-Authenticate` por defecto (`app.http.cors.expose.headers`), para que un cliente de navegador pueda leer el challenge.

## Pruebas

- `AcrStepUpUnitTest`: niveles derivados del `amr`, valores configurados y alias, conflicto entre niveles, preferencia de `acr_values`, `satisfies` con `max_age` y challenge con el valor configurado.
- `SessionSufficiencyPolicyUnitTest`: `StepUp`, `InteractionRequired`, valores desconocidos y `id_token_hint` de otro usuario.
- `AuthenticateUserStepUpUnitTest`: OTP o alta según semilla, fusión del `amr` en la sesión que inició el step-up, step-up rechazado si esa sesión se cerró, es de otro usuario o no consta, y step-up a medias rechazado.
- `AuthorizeHtmlIntegrationUnitTest`: step-up de la sesión, alta, sesión MFA reutilizada, `prompt=none`, hint inválido u otro usuario.
- `MagicLinkLoginFormStepUnitTest` (POST directo con MFA pedido) y `LoginFormStepUnitTest` (`login_hint` escapado y sin enlace mágico), `NewMfaFormStepUnitTest`, `ErrorsPluginTest`, `JwtVerifierMiddlewareUnitTest`.
- `TokenEndpointTest`, `SessionStoreSqlAdapterTest` y `OidcDomainUnitTest` se actualizan: `acr` en ID y access token, y en discovery.

## Fuera de alcance

- Usar `trusted-client.default-acr-values` cuando el RP no envía `acr_values`.
- Valores ACR distintos por tenant (la configuración es global).
- El parámetro `claims` con `acr` esencial ([061](../061-claims-parameter.md)).
- Marcas de tiempo por factor: solo hay un `auth_time` por sesión, que tras el step-up pasa al momento del OTP.
