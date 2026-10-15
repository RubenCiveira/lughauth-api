# Análisis de capacidades OIDC — `src/Features/OAuth`

> Fecha: 2026-10-15 · Compara **lughauth-api** (PHP, este repositorio) con **phylax-api** (Java/Quarkus, `java-micros/phylax/phylax-api`), un port del mismo sistema con los mismos bounded contexts.
> Método: comparación **código contra código**. Los documentos de planificación de phylax (`plan/OAUTH_STATE.md`, `OAUTH_PLAN.md`) son de mayo de 2026 y están desfasados respecto a su código, así que no se han tomado como fuente. Tampoco se ha ejecutado la OpenID Conformance Suite.

## 1. Resumen

lughauth tiene una **superficie OIDC amplia**: Authorization Code con PKCE obligatorio, PAR, Device Flow, DCR, JAR (request objects firmados), introspección, revocación, back- y front-channel logout, MFA TOTP, WebAuthn, magic links, login federado (Google, Microsoft, Apple, GitHub, SAML), invitaciones, perfil de usuario y RGPD. En **amplitud** está a la par de phylax, o por delante en varios puntos.

En **profundidad y seguridad** está por detrás:

1. Hay **fallos de seguridad que bloquean el uso en producción** (§3). El más grave es una validación de `redirect_uri` con los argumentos invertidos que, combinada con un endpoint de token que no autentica al cliente en `authorization_code`, permite robar códigos de autorización.
2. Faltan piezas de **OIDC Core** que phylax ya tiene: `prompt=login`, `max_age`, los claims `auth_time`/`acr`/`amr`/`sid`/`c_hash` en el ID token, rotación de refresh con detección de reutilización y uso de códigos de recuperación MFA en el login.
3. El **discovery anuncia capacidades inexistentes** (CIBA, implicit, `form_post`, `pairwise`, cifrado JWE, `require_pushed_authorization_requests: true`…), lo que rompe a los clientes que confían en él.

Recomendación: **Fase 0 (seguridad) antes de cualquier desarrollo nuevo**, después alinear con OIDC Core y portar lo que phylax ya resuelve (§6).

> ⚠️ **Advertencia sobre phylax**: su `JoseTokenSigner.signKeypass()` pone `currentKey.getPrivateKey()` en la cabecera `kid` ([phylax JoseTokenSigner.java:151](/Users/ruben.civeiraiglesia/Documents/Proyectos/Personal/java-micros/phylax/phylax-api/src/main/java/net/civeira/phylax/features/oauth/tokensecurity/infrastructure/driven/JoseTokenSigner.java#L151)). Lo usa `buildChallengerToken`, que emite la cookie de pre-sesión que recibe el navegador ([OidcCookieManager.java:62](/Users/ruben.civeiraiglesia/Documents/Proyectos/Personal/java-micros/phylax/phylax-api/src/main/java/net/civeira/phylax/features/oauth/authentication/infrastructure/driver/html/OidcCookieManager.java#L62)). Es decir, **phylax publica la clave privada de firma del tenant**. lughauth no tiene este fallo. **No portar esa parte** y corregirla en phylax.

## 2. Inventario actual de lughauth

18 submódulos, ~400 ficheros, 27.900 líneas. Rutas en [OAuthPlugin.php](../src/Bootstrap/Plugin/OAuthPlugin.php).

| Área | Endpoints (`/oauth/openid/{tenant}/…`) | Estado real |
|---|---|---|
| Discovery / JWKS | `GET /.well-known/openid-configuration`, `GET /jwks` | Funciona; el documento sobreanuncia (§4) |
| Autorización | `GET/POST /authorize`, `GET/POST /check-session` | Code + PKCE obligatorio; implicit/hybrid parcial; solo `prompt=none` |
| Token | `POST /token` | `authorization_code`, `refresh_token`, `password`, `client_credentials`, `device_code` |
| UserInfo | `GET /userinfo` | Claims por scope (`email`, `profile`, `phone`); solo GET |
| Introspección / revocación | `POST /introspect`, `POST /revoke` | Con autenticación de cliente; la revocación no se aplica al validar bearer |
| Logout | `GET /logout` | RP-initiated + back-channel + front-channel; solo GET |
| PAR | `POST /par` | Funciona; no es obligatorio aunque se anuncia que sí |
| Device | `POST /device`, `GET/POST /device/verify` | Completo (`slow_down`, `verification_uri_complete`) |
| DCR | `POST/GET/PUT/DELETE /register[/{id}]` | RFC 7591/7592; `initial_access_token` sin validar |
| Federado | `GET /oauth/openid/-/delegated/verify` | Google, Microsoft, Apple, GitHub, SAML 2 |
| WebAuthn | `/webauthn/{register,authenticate}/{begin,finish}` | Ceremonias completas |
| Magic link | `POST /magic-link/request`, `GET /magic-link/verify` | Funciona |
| Perfil | `/me`, `/me/{edit,password,mfa,sessions,passkeys,consents,recovery-codes,data-export,delete-account,invite}` | Amplio |
| API REST «me» | `/api/me/{profile,sessions,consents,gdpr/*}` | Amplio |
| Invitaciones | `/api/access/invitations`, `/invitation/accept` | Ciclo completo |

## 3. Fallos de seguridad (bloqueantes)

Ordenados por gravedad. Todos verificados en el código.

### 3.1 Crítico — Validación de `redirect_uri` invertida

[ClientStoreAdapter.php:134](../src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php#L134):

```php
if (str_starts_with($this->context->getBaseUrl(), $redirectUrl)) { $allowed = true; }
```

Comprueba que la **URL base empiece por el redirect**, no al revés. Se acepta, **para cualquier cliente**, cualquier `redirect_uri` que sea prefijo de la URL base. Con una base `https://auth.example.com` valen `https://auth.example.co` o `https://auth.exam`, que son otros dominios registrables. Afecta a `/authorize`, a logout (`post_logout_redirect_uri`) y a magic link. Está así desde el commit inicial.

**Cadena de explotación**: el atacante inicia un flujo con su propio PKCE y un `redirect_uri` truncado que controla. La víctima, con sesión activa, recibe el code en el dominio del atacante, y este lo canjea en `/token` sin autenticarse como cliente (3.2).

Agravante: `getBaseUrl()` se construye a partir de la cabecera `Host` ([Context.php:129](../src/Shared/Context.php#L129)), igual que el `iss` de los tokens y el documento de discovery.

### 3.2 Alto — `/token` con `authorization_code` no autentica al cliente

[TokenController.php:125-141](../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php#L125-L141): se recupera el code y se emiten tokens sin:

- autenticar al cliente confidencial (RFC 6749 §4.1.3);
- comprobar que el `client_id` de la petición es el del code;
- comprobar que `redirect_uri` coincide con el usado en `/authorize`.

PKCE es obligatorio en `/authorize` ([AuthorizeHtml.php:359](../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php#L359)) y se verifica en `/token`, lo que mitiga el robo pasivo del code, pero no el escenario 3.1, en el que el atacante elige el `code_challenge`. phylax tiene el mismo hueco.

Además, el borrado del code no es atómico: `SELECT` y luego `DELETE` ([TemporalKeysSqlAdapter.php:225-246](../src/Features/OAuth/Session/Infrastructure/Driven/TemporalKeysSqlAdapter.php#L225-L246)). Dos peticiones concurrentes pueden canjearlo dos veces, y no se revocan los tokens ante la reutilización de un code.

### 3.3 Alto — `audience` arbitraria en todos los grants

`$params['audience']` se añade directamente al `aud` del access token ([TokenController.php:150](../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php#L150), [179](../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php#L179), [208](../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php#L208)). Cualquier cliente puede obtener tokens válidos para cualquier resource server que valide `aud`. phylax toma las audiencias del propio refresh token.

### 3.4 Alto — Refresh token sin vincular, sin rotación y sin límite

- El cliente se resuelve con `preValidatedClient($params['client_id'])`, **sin secreto**, y no se compara con el `azp` del refresh token ([TokenController.php:151](../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php#L151)). Un refresh robado sirve con cualquier `client_id` público.
- Cada uso emite un refresh nuevo de 10 h ([TokenController.php:263](../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php#L263)) **sin revocar el anterior**. No hay detección de reutilización, y la sesión se puede extender indefinidamente.
- No se comprueba que la sesión siga viva: `withSession(true)` ([ResolverForRefresh.php:66](../src/Features/OAuth/Authentication/Application/TokenGranter/ResolverForRefresh.php#L66)). El logout no invalida los refresh.

phylax ya resuelve las tres cosas: vínculo con el cliente, rotación con `revokeJti` y, ante una reutilización, `revokeAllForUser`.

### 3.5 Alto — Logout que difunde la identidad a todos los clientes

`BackChannelLogoutDispatcher` ([línea 63](../src/Features/OAuth/Authentication/Application/BackChannelLogoutDispatcher.php#L63)) y el front-channel ([LogoutController.php:122](../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/LogoutController.php#L122)) notifican a **todos los `TrustedClient` habilitados**, hayan participado o no en la sesión, y les envían `sub` y `sid`. Los envíos HTTP son síncronos dentro de la petición.

Como `TrustedClient` **no tiene tenant** (los clientes son globales y `publicClientData()` ignora el parámetro `$tenant`), la difusión cruza tenants. Si un tenant habilita DCR, cualquiera puede registrar un `backchannel_logout_uri` y recibir el `sub` de cada usuario que cierre sesión en cualquier tenant.

phylax filtra por «tiene URL de back-channel», pero también notifica a todos esos clientes.

### 3.6 Medio — Otros

| Hallazgo | Evidencia |
|---|---|
| `initial_access_token` de DCR: solo se comprueba que no esté vacío | [RegisterClientUsecase.php:72](../src/Features/OAuth/Client/Application/Usecase/RegisterClient/RegisterClientUsecase.php#L72) |
| Los clientes DCR pueden pedir cualquier `grant_type` (incluido `password`) | `DynamicClientAdapter::create` copia `grantTypes` sin filtrar |
| ROPC (`password`) habilitado por defecto si el cliente no define grants | [ClientStoreAdapter.php:191](../src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php#L191) |
| `state` y `nonce` concatenados sin `urlencode` en la redirección: permite inyectar parámetros | [OidcResponseBuilder.php:68](../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Services/OidcResponseBuilder.php#L68) |
| Errores de autorización siempre en el fragmento (`#error=`), sin `state` ni `iss` | [AuthorizeHtml.php:433](../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php#L433) |
| Los parámetros de la query **pisan** los del PAR (RFC 9126 §4 exige usar solo los del PAR) | [AuthorizeHtml.php:381](../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php#L381) |
| La revocación no se consulta al validar bearer (middleware, `/userinfo`) | `isRevoked` solo se usa en introspección y refresh; [JwtVerifierMiddleware.php:98](../src/Bootstrap/Middleware/JwtVerifierMiddleware.php#L98) |
| JWT sin `kid` en la cabecera: con rotación hay varias claves en el JWKS y los RP no saben cuál usar | [JoseTokenSigner.php:85](../src/Features/OAuth/TokenSecurity/Infrastructure/Driven/JoseTokenSigner.php#L85) |
| `verifyTokenPayload` no valida `iss`, `aud` ni el tipo de token (id/access/refresh/pre-sesión firmados con la misma clave y sin `typ` en cabecera) | [JoseTokenSigner.php:130](../src/Features/OAuth/TokenSecurity/Infrastructure/Driven/JoseTokenSigner.php#L130) |
| Generación RSA-4096 síncrona dentro de la petición de firma, `echo` de errores OpenSSL y claves de caché JWKS no separadas por tenant | [JoseTokenSigner.php:52-53](../src/Features/OAuth/TokenSecurity/Infrastructure/Driven/JoseTokenSigner.php#L52-L53), [218-222](../src/Features/OAuth/TokenSecurity/Infrastructure/Driven/JoseTokenSigner.php#L218-L222) |
| Secreto de cliente cifrado de forma reversible y comparado con `!==` (no en tiempo constante) | [ClientStoreAdapter.php:55](../src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php#L55) |
| Logout destruye la sesión **antes** de validar la petición y sin confirmación (CSRF de logout) | [LogoutController.php:97](../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/LogoutController.php#L97) |
| Respuesta de `/token` sin `Cache-Control: no-store`; algunos errores como `UnauthorizedException` en vez de JSON OAuth (`invalid_grant`) | `TokenController::handlePost` |
| `str_contains($scopes, 'profile')` acepta scopes como `myprofile` | [TokenController.php:239](../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php#L239) |

## 4. Discovery: anunciado frente a implementado

[OpenIdConfiguration::forTenant](../src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php#L93) publica valores fijos que no reflejan el código:

| Anunciado | Realidad |
|---|---|
| `backchannel_authentication_endpoint: …/backchannel` (CIBA) | No hay ruta |
| `grant_types_supported: implicit` y `response_types: token, id_token` | Parcial: hybrid incorrecto (§5) y sin control por cliente |
| `response_modes_supported: form_post` | No implementado |
| `subject_types_supported: pairwise` | No implementado |
| `id_token_encryption_*`, `userinfo_encryption_*`, `request_object_encryption_*`, `authorization_*` (JARM) | No implementado |
| `userinfo_signing_alg_values_supported` | `/userinfo` devuelve JSON sin firmar |
| `require_pushed_authorization_requests: true` | No se exige PAR |
| `check_session_iframe: …/check-session` | Es un endpoint de refresco silencioso, no el iframe de OIDC Session Management |
| `authorization_response_iss_parameter_supported: true` (RFC 9207) | No se añade `iss` a la respuesta |
| `claims_parameter_supported: true` | No se procesa `claims` |
| `introspection/revocation_endpoint_auth_signing_alg_values_supported` | Solo hay `client_secret_basic`/`post` |
| `claims_supported: sub, iss, name, email` | Se emiten más claims (`roles`, `groups`, `tenant`, perfil) y faltan los de OIDC |

phylax construye el discovery con **contributors por contexto** (`AuthorizationEndpointContributor`, `SessionContributor`, `TokenSecurityContributor`…), de forma que cada módulo anuncia lo suyo. Es mejor diseño, aunque phylax también sobreanuncia (CIBA responde 403; `login-status-iframe` devuelve `<h1>Check</h1>`; `private_key_jwt`/`tls_client_auth` aparecen anunciados en introspección y revocación). En lughauth existe `DiscoveryContributorGateway`, pero solo lo usa `MtlsDiscoveryContributor`.

## 5. Matriz comparativa

Leyenda: ✅ implementado · ⚠️ parcial o con defectos · ❌ ausente · 📣 solo anunciado · ? no verificado.

### 5.1 OIDC Core y OAuth 2.0 base

| Capacidad | lughauth | phylax | Nota |
|---|:-:|:-:|---|
| Authorization Code + PKCE | ⚠️ | ⚠️ | PKCE obligatorio en ambos; ninguno autentica al cliente en el canje |
| PKCE solo S256 | ❌ (acepta `plain`) | ✅ en `/token` | OAuth 2.1 / BCP: solo S256 |
| Validación exacta de `redirect_uri` | ❌ (3.1) | ⚠️ (comodín `*` permitido) | |
| `state` / `nonce` | ⚠️ sin codificar | ⚠️ sin codificar | |
| `prompt=none` | ✅ | ✅ | |
| `prompt=login` / `select_account` | ❌ | ✅ | Forzar reautenticación |
| `prompt=consent` | ❌ | ? | |
| `max_age` y TTL de SSO | ❌ | ✅ | |
| `login_hint`, `id_token_hint` (en authorize) | ❌ | ⚠️ | phylax usa el `sid` del hint |
| `acr_values` / step-up | ❌ | ⚠️ | phylax emite `acr` según MFA |
| ID token: `auth_time` | ❌ (se guarda, no se emite) | ✅ | Obligatorio con `max_age` |
| ID token: `acr`, `amr` | ❌ | ✅ | |
| ID token: `sid` | ❌ | ✅ | Necesario para back-channel |
| ID token: `at_hash` | ⚠️ solo implicit | ✅ | |
| ID token: `c_hash` (hybrid) | ❌ | ✅ | |
| Hybrid flow (`code id_token`) | ⚠️ id_token en la query | ? | Debe ir en el fragmento |
| `response_mode=form_post` | 📣 | ❌ | |
| `iss` en la respuesta de autorización (RFC 9207) | 📣 | ⚠️ solo en fragmento, no en el flujo code | |
| Errores conformes (query/fragment, `state`, `iss`) | ❌ | ? | |
| UserInfo por scope | ✅ | ✅ | |
| UserInfo por POST | ❌ | ❌ | OIDC Core §5.3.1 |
| Parámetro `claims` | 📣 | ❌ | |
| `ui_locales` | ❌ | ❌ | |
| Cabecera `kid` en los JWT | ❌ | ✅ | |
| Algoritmos de firma | RS256 | RS256 | Ninguno ofrece ES256/EdDSA |
| Rotación de claves con ventana | ⚠️ (pre-genera futuras; sin `kid`) | ? | |

### 5.2 Grants y tokens

| Capacidad | lughauth | phylax | Nota |
|---|:-:|:-:|---|
| `refresh_token` vinculado al cliente | ❌ | ✅ | |
| Rotación de refresh + detección de reutilización | ❌ | ✅ | |
| Audiencias controladas | ❌ (3.3) | ✅ | |
| `client_credentials` | ✅ | ✅ | lughauth interseca scopes M2M |
| `password` (ROPC) | ✅ (activo por defecto) | ✅ | Eliminar o restringir (OAuth 2.1) |
| `device_code` (RFC 8628) | ✅ | ✅ | |
| Grant `mfa` (mfa_token + OTP) | ❌ | ✅ | Para clientes nativos con ROPC |
| Grant `delegated` | ❌ | ✅ | |
| Token exchange (RFC 8693) | ❌ | ❌ | |
| JWT bearer (RFC 7523 §2.1) | ❌ | ❌ | |
| Access token JWT profile (RFC 9068, `typ: at+jwt`) | ❌ | ❌ | |
| Introspección (RFC 7662) | ✅ | ✅ | phylax añade `no-store` |
| Revocación (RFC 7009) | ⚠️ no aplicada al validar bearer | ✅ endpoint `/revocation` | En phylax no se ha verificado si el bearer consulta la revocación |

### 5.3 Clientes

| Capacidad | lughauth | phylax | Nota |
|---|:-:|:-:|---|
| `client_secret_basic` / `client_secret_post` | ✅ | ✅ | |
| `private_key_jwt` / `client_secret_jwt` | ❌ | 📣 | |
| mTLS (RFC 8705) | 📣 (alias) | 📣 | |
| DCR / gestión (RFC 7591/7592) | ⚠️ (3.6) | ⚠️ | Ambos aceptan cualquier `initial_access_token` no vacío |
| Clientes por tenant | ❌ (globales) | ? | Contradice el modelo multi-tenant documentado |
| Atributos de cliente usados (`requirePkce`, `tokenEndpointAuthMethod`, `responseTypes`, `allowAllScopes`, `isResourceServer`, `*LogoutSessionRequired`) | ❌ existen en `TrustedClient` y no se aplican | ? | |

### 5.4 Solicitudes avanzadas

| Capacidad | lughauth | phylax | Nota |
|---|:-:|:-:|---|
| PAR (RFC 9126) | ⚠️ (los params de query pisan; no se exige) | ✅ (prevalecen los del PAR) | |
| JAR, `request` firmado (RFC 9101) | ⚠️ (sin `aud`/`exp`/`jti`; HTTP desde dominio) | ❌ | **Ventaja de lughauth** |
| `request_uri` externo | ❌ | ❌ | |
| JARM | 📣 | ❌ | |
| RAR (RFC 9396) | ❌ | ❌ | |
| Resource indicators (RFC 8707) | ❌ | ❌ | |
| DPoP (RFC 9449) | ❌ | ❌ | |
| CIBA | 📣 | 📣 (403) | |

### 5.5 Sesión y logout

| Capacidad | lughauth | phylax | Nota |
|---|:-:|:-:|---|
| RP-initiated logout | ⚠️ (solo GET; destruye antes de validar) | ✅ GET+POST | |
| Back-channel logout | ⚠️ (difusión a todos; síncrono) | ⚠️ (difusión a todos) | Limitar a los clientes de la sesión |
| Front-channel logout | ⚠️ (todos los clientes; sin `iss`/`sid`) | ❌ (desactivado en discovery) | |
| Session Management (iframe) | 📣 | 📣 stub | |
| API admin de sesiones de usuario | ❌ | ✅ (`/api/{tenant}/access/users/{uid}/sessions`) | |
| Autoservicio de sesiones | ✅ | ✅ | |
| Limpieza programada de codes y sesiones | ✅ `OAuthCleanupScheduler` | ✅ | |

### 5.6 Autenticación de usuario

| Capacidad | lughauth | phylax | Nota |
|---|:-:|:-:|---|
| Login + registro + recuperación de contraseña | ✅ | ✅ | |
| MFA TOTP | ✅ | ✅ | |
| Generación de códigos de recuperación MFA | ✅ (perfil) | ✅ | |
| **Uso** de códigos de recuperación en el login | ❌ | ✅ `RecoverMfaStep` | En lughauth los códigos no sirven para nada |
| WebAuthn / passkeys | ✅ | ✅ | |
| Magic link | ✅ | ✅ | |
| Federado OIDC/OAuth | ✅ Google, Microsoft, Apple, GitHub | ⚠️ Google | **Ventaja de lughauth** |
| Federado SAML | ✅ | ✅ | |
| Proveedor OIDC genérico (Okta, Keycloak…) | ❌ | ❌ | |
| Invitaciones | ✅ | ✅ | |
| Rate limiting | ✅ middleware genérico | ? | |

### 5.7 Consentimiento y RGPD

| Capacidad | lughauth | phylax |
|---|:-:|:-:|
| Consentimiento de scopes por cliente | ✅ | ✅ |
| Términos de uso versionados | ✅ | ✅ |
| Finalidades RGPD + historial | ✅ (`/api/me/consents*`) | ? |
| Exportación y borrado de datos | ✅ | ✅ |
| Revocación de consentimiento desde el perfil | ✅ | ✅ |

### 5.8 Calidad del módulo

| Aspecto | lughauth | phylax |
|---|---|---|
| Tests | 64 ficheros, mayoría unitarios; 6 de integración de endpoints | 53 ficheros, de ellos **37 de flujos/escenarios end-to-end** |
| Servicio de tokens | La emisión está en el controller (`TokenController` construye los claims) | Separado: `JwtTokenBuilder` + `ClaimsMapper` + `TokenVerifier` |
| Discovery | Valores fijos | Contributors por contexto |
| Documentación ([docs/OAuth/](OAuth/)) | Desfasada: habla de `Features/Oidc` y de un core «portable» que no se corresponde con el acoplamiento actual a `Access` | — |

## 6. Qué falta implementar (hoja de ruta)

### Fase 0 — Seguridad (bloqueante, antes de nada)

| # | Tarea | Esfuerzo |
|---|---|---|
| 0.1 | Corregir la comparación de `redirect_uri` (coincidencia exacta; comodín solo en loopback) y dejar de derivar la URL base de `Host` (configurarla explícitamente) | S |
| 0.2 | En `authorization_code`: autenticar al cliente confidencial, exigir el `client_id` del code y el mismo `redirect_uri`; canje atómico (`DELETE … RETURNING` o `UPDATE used=1`) y revocar los tokens emitidos si se reutiliza el code | M |
| 0.3 | Ignorar `audience` libre, o validarla contra una lista permitida del cliente | S |
| 0.4 | Refresh: vincular al cliente (`azp`), rotar con `revokeJti`, detectar reutilización (`revokeAllForUser`) y comprobar que la sesión existe. Portar de phylax `TokenController` y `RefreshTokenInfo` | M |
| 0.5 | Logout: notificar solo a los clientes con sesión del usuario (guardar los `client_id` por `sid`), en asíncrono; añadir `iss`/`sid` al front-channel | M |
| 0.6 | Añadir tenant a `TrustedClient`, o documentar explícitamente que los clientes son globales, y filtrar por tenant en `publicClientData`, en el logout y en DCR | M–L |
| 0.7 | Validar `initial_access_token` (phylax tiene el mismo fallo); restringir los grants que puede pedir un cliente DCR; no habilitar ROPC por defecto | S |
| 0.8 | `kid` y `typ` en la cabecera JWT; distinguir id/access/refresh/pre-sesión (`typ` o `token_use`) y validar `iss`/`aud` en `verifyTokenPayload` | S–M |
| 0.9 | Codificar `state`/`nonce`; errores en el `response_mode` correcto con `state` e `iss` | S |
| 0.10 | Consultar la lista de revocación al validar bearer (o acortar mucho la vida de los access tokens y documentarlo) | S |
| 0.11 | Sacar la generación de claves de la ruta de firma (job o comando), con claves de caché por tenant y sin `echo` | S |

### Fase 1 — Conformidad OIDC Core (y discovery honesto)

| # | Tarea | Referencia en phylax |
|---|---|---|
| 1.1 | Discovery generado a partir de lo que existe: retirar CIBA, `form_post`, `pairwise`, JWE, JARM e implicit, y poner `require_pushed_authorization_requests: false` hasta implementarlos. Migrar al patrón *contributor* | `*Contributor.java` |
| 1.2 | `prompt=login`, `select_account` y `consent`; `max_age` con `auth_time`; TTL de SSO por tenant | `AuthorizeHtml.java` (`FORCE_REAUTH_PROMPTS`, `max_age`) |
| 1.3 | Claims del ID token: `auth_time`, `acr`, `amr`, `sid`, `at_hash` en el flujo code y `c_hash` en hybrid; aud del ID token = solo el cliente | `ClaimsMapper.java` |
| 1.4 | Hybrid correcto (todo en el fragmento) y `response_type` validado contra `responseTypesJson` del cliente | `OidcResponseBuilder.java` |
| 1.5 | PKCE solo S256 y aplicar `requirePkce` del cliente | `TokenController.java` |
| 1.6 | `/userinfo` por GET y POST; `email_verified`, `address`; 401 con `WWW-Authenticate` | — |
| 1.7 | Logout por GET y POST, con confirmación cuando no hay `id_token_hint` | `AuthorizeHtml.java:310-320` |
| 1.8 | `iss` en la respuesta de autorización (RFC 9207) | `OidcResponseBuilder.java:114` |
| 1.9 | Extraer la emisión de tokens del controller a un servicio (`TokenFactory` / `ClaimsMapper` / `TokenVerifier`) | `tokensecurity/application/*` |

### Fase 2 — Portar desde phylax lo que ya resuelve

| # | Tarea | Referencia en phylax |
|---|---|---|
| 2.1 | Paso de login con código de recuperación MFA (los códigos ya se generan en el perfil) | `step/RecoverMfaStep.java`, `MfaRecoveryService` |
| 2.2 | Grant `mfa` (`mfa_token` + `mfa_code`) para clientes no interactivos | `granter/MfaGranter.java` |
| 2.3 | API de administración de sesiones de usuario | `session/infrastructure/driver/rest/UserSessionController.java` |
| 2.4 | Suite de tests end-to-end de flujos y escenarios | `src/test/.../oauth/{flow,scenario}` |
| 2.5 | Revisar la decisión sobre el grant `delegated` | `granter/DelegatedAccessGranter.java` |

### Fase 3 — Extensiones habituales en un IdP moderno

| # | Tarea | Especificación |
|---|---|---|
| 3.1 | `private_key_jwt` y `client_secret_jwt` (reutilizando la carga de JWKS de `RequestObjectValidator`) | RFC 7523, OIDC Core §9 |
| 3.2 | `response_mode=form_post` | OAuth 2.0 Form Post |
| 3.3 | Exigir PAR por cliente o tenant y usar solo los parámetros del PAR | RFC 9126 |
| 3.4 | Completar JAR: `aud`, `exp`, `nbf`, `jti` contra replay; caché y protección SSRF en `jwks_uri`; mover el cliente HTTP a infraestructura | RFC 9101 |
| 3.5 | DPoP (tokens ligados a una clave) | RFC 9449 |
| 3.6 | Access tokens con `typ: at+jwt` | RFC 9068 |
| 3.7 | Firma ES256 / EdDSA y rotación con ventana dual publicada | RFC 7518 / 8037 |
| 3.8 | Proveedor federado OIDC genérico (discovery + JWKS del IdP) | — |
| 3.9 | Token exchange (delegación/impersonación entre servicios) | RFC 8693 |

### Fase 4 — Perfil alto (FAPI 2.0 / sector regulado), solo si hace falta

mTLS y tokens ligados a certificado (RFC 8705), JARM, CIBA, identificadores `pairwise` (`sector_identifier_uri`), ID token y UserInfo cifrados (JWE), RAR (RFC 9396), resource indicators (RFC 8707) y UserInfo firmado.

### Validación

Al cerrar las fases 0 y 1, pasar la **OpenID Conformance Suite** (perfiles *Basic OP*, *Config OP*, *Dynamic OP* y *RP-Initiated/Back-Channel Logout*) y añadirla al CI. Varios de los fallos de §3 los detecta directamente.
