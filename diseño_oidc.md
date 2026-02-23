# Arquitectura y Diseño del namespace `Oidc`

## Visión General

El namespace `Features\Oidc` implementa un servidor OpenID Connect / OAuth 2.0 completo con soporte multi-tenant. Sigue una arquitectura hexagonal (ports & adapters) organizada en sub-módulos cohesivos, cada uno con sus capas `Domain`, `Application` e `Infrastructure`.

El namespace root es `Civi\Lughauth\Features\Oidc`.

---

## Estructura de Sub-módulos

```
Oidc/
├── Authentication/    # Flujo principal authorize + token + formularios HTML
├── Client/            # Resolución y validación de clientes OAuth (TrustedClient, ApiKey)
├── Common/            # Utilidades compartidas: UserLoader, migraciones, .well-known
├── DelegateLogin/     # Login social/delegado (Google OAuth, extensible)
├── Key/               # Gestión de claves RSA, firma/verificación JWT (RS256)
├── Mfa/               # Multi-factor authentication (TOTP)
├── Scopes/            # Consentimiento granular de scopes por usuario/cliente
├── Session/           # Sesiones de autenticación y códigos temporales
└── User/              # Casos de uso del usuario OIDC: login, consent, register, change-pass
```

---

## 1. Sub-módulo `Authentication`

### Responsabilidad
Orquesta todo el flujo de autenticación OIDC: authorize (HTML), token (REST), userinfo, logout. Es el **punto de entrada principal** del OIDC.

### Domain

| Clase | Descripción |
|-------|-------------|
| `OidcFlowContext` | Value object inmutable que captura todo el contexto de una petición authorize: `tenant`, `clientId`, `redirect`, `scope`, `responseType`, `state`, `nonce`, `audiences`, `prompt`, `locale`, `baseUrl`, `issuer`, `sessionId`, `preSessionId`. Se construye con `fromRequest(ServerRequestInterface, tenant, Context)`. |
| `AuthenticationRequest` | Agrupa los datos validados del cliente: `ClientData client`, `scope`, `redirect`, `responseType`, `audiences[]`. |
| `AuthenticationResult` | Resultado de un intento de autenticación. Si `valid=true` contiene `id`, `tenant`, `tenantName`, `scope`, `audiences`, `roles`, `groups`, `name`, `email`. Si `valid=false` contiene un `error` (constante) y `errorMessage`. Los errores posibles definen qué paso siguiente se necesita (ver sección Steps). |
| `ChallengesState` | Estado acumulativo de los challenges completados durante el flujo: `withMfa`, `session`, `username`, `extra[]`. Es inmutable (métodos `withXxx` devuelven nueva instancia). Se serializa a/desde array para persistir en cookies. |
| `StepName` | Enum con los pasos del formulario: `LOGIN`, `CONSENT`, `SCOPES_CONSENT`, `MFA`, `NEW_MFA`, `RECOVER_PASS`, `NEW_PASS`, `REGISTER_USER`, `DELEGATED_LOGIN`. |
| `StepInput` | DTO de entrada para cada step: `OidcFlowContext`, `AuthenticationRequest`, `ChallengesState`, `?array body`, `ServerRequestInterface`. |
| `StepResult` | Resultado de un step: `TYPE_RENDER` (devuelve `ResponseInterface` con HTML) o `TYPE_PROCEED` (devuelve `PublicLoginAuthResponse` para redirect con tokens). |
| `OidcUrlBuilder` | Construye URLs de authorize, check-session, recover-pass y register-user con query params OIDC. |
| `LoginException` | Excepción que encapsula un `AuthenticationResult` fallido. Se lanza desde los forms para que `AuthorizeHtml` la capture y redirija al step correcto. |

### Constantes de error → Step siguiente (mapeo en `OidcStepRouter::ERROR_MAP`)

| Error constante | Step destino |
|-----------------|-------------|
| `ERR_CONSENT_REQUIRED` | `consent` |
| `ERR_SCOPES_CONSENT_REQUIRED` | `scopes-consent` |
| `ERR_MFA_REQUIRED` | `mfa` |
| `ERR_NEW_MFA_REQUIRED` | `build-mfa` |
| `ERR_NEW_PASSWORD_REQUIRED` | `new-pass` |
| `ERR_WAITING_PASSCHANGE_CODE` | `recover-pass` |
| `ERR_WAITING_USER_VERIFY` | `register-user` |
| `ERR_UNKNOW_USER` | `login` |
| `ERR_WRONG_CREDENTIAL` | `login` |
| `ERR_NOT_ALLOWED_ACCESS` | `login` |

### Application

| Clase | Descripción |
|-------|-------------|
| `AuthenticateUser` | Servicio central que crea la sesión y devuelve `PublicLoginAuthResponse`. Tiene tres modos: `authenticate` (usuario+password), `preAuthenticate` (ya validado, challenges ok), `sessionAuthenticated` (re-autenticar desde sesión). Genera el `authData` (claims del token) y el `idData` (claims del id_token) y persiste la sesión via `SessionStoreGateway`. |
| `SessionManager` | Gestión básica de sesiones: `loadSession`, `removeSesion`. |
| `TokenGranterMediator` | Mediator/Strategy para el endpoint `/token`. Registra resolvers: `ResolverForPassword` (grant_type=password) y `ResolverForRefresh` (grant_type=refresh_token). |
| `TokenGranterStrategy` | Interfaz strategy: `canHandle(grantType, params)`, `authenticate(tenant, client, params)`. |
| `ResolverForPassword` | Delega a `LoginUsecase.validatedUserData`. |
| `ResolverForRefresh` | Verifica el refresh token JWT, extrae el `keypass` (subject id), delega a `LoginUsecase.fillPreLoadById`. |

### Infrastructure – Driver (REST Controllers)

| Controller | Ruta | Descripción |
|-----------|------|-------------|
| `TokenController` | `POST /openid/{tenant}/token` | Soporta `authorization_code`, `refresh_token`, `password`. Genera `access_token`, `id_token`, `refresh_token` como JWTs firmados con RS256. |
| `UserInfoController` | `GET /openid/{tenant}/userinfo` | Devuelve `sub`, `name` del token autenticado. |
| `LogoutController` | `GET /openid/{tenant}/logout` | Elimina sesión, limpia cookies, redirige a `post_logout_redirect_uri`. También soporta `POST .../revoke` (revocación de token). |
| `DelegatedController` | `GET /openid/-/delegated/verify` | Punto de callback para login social; redirige de vuelta al authorize del tenant. |

### Infrastructure – Driver (HTML / Formularios)

#### Controlador principal: `AuthorizeHtml`

Orquesta el flujo authorize del navegador. Tres entry-points:

1. **`authorize(GET)`** → Si hay sesión activa muestra página de verificación CSID (auto-submit); si `prompt=none` y no hay sesión → redirect con error; sino → renderiza `LoginForm`.
2. **`formAuthorize(POST)`** → Recibe los POST de los formularios. Verifica CSID, ejecuta el step actual (authenticate), si tiene éxito → redirect con tokens. Si falla (LoginException) → renderiza el step que corresponda al error.
3. **`refresh(POST)`** → Re-autenticación desde sesión existente (check-session). Verifica CSID coincide con el de la sesión.

#### Router de Steps: `OidcStepRouter`

Resuelve qué `StepForm` ejecutar según:
1. El campo `step` del POST/query.
2. Si no hay step, el `ERROR_MAP` del `AuthenticationResult`.
3. Fallback: `login`.

Método `run(StepInput, ResponseInterface, ?error, ?step, ?csid)`:
- Si `csid != null` → llama `form.authenticate(input)` → devuelve `StepResult::proceed`.
- Si `csid == null` → llama `form.render(input, response, error)` → devuelve `StepResult::render`.

---

## 2. Sistema de Steps (Formularios)

### Interfaces base

```
StepRenderer
  └─ render(StepInput, ResponseInterface, ?AuthenticationResult): ResponseInterface

StepAuthenticator
  └─ authenticate(StepInput): PublicLoginAuthResponse

StepForm extends StepRenderer, StepAuthenticator
```

Cada formulario implementa `StepForm` y tiene dos responsabilidades:
- **render**: generar HTML del formulario (GET / error).
- **authenticate**: procesar el POST y devolver `PublicLoginAuthResponse` o lanzar `LoginException`.

### Steps detallados

#### `LoginForm` (step: `login`)
- **Render**: Formulario de usuario + password. Incluye botones de login social (delegated providers), link de recover-pass (si habilitado en TenantConfig), link de register-user (si habilitado).
- **Authenticate**: Descifra la password (cifrada client-side con AES), llama `AuthenticateUser.authenticate(request, challenges, tenant, username, password, ...)`.
- **Dependencias**: `AuthenticateUser`, `DelegateLogin` (lista providers), `ChangePasswordUsecase` (allowRecover), `RegisterUserUsecase` (allowRegister).
- **Seguridad**: El password se cifra en el cliente con JS (`oauth.min.js`) usando la clave temporal del servidor, y se descifra server-side con `HtmlSecurer.decrypt()`. Se firma un CSID con `jsrsasign` para proteger contra CSRF.

#### `ConsentForm` (step: `consent`)
- **Render**: Muestra términos de uso (textarea readonly) con checkbox "Accept".
- **Authenticate**: Si se acepta, persiste el consentimiento via `ConsentUsecase.storeAcceptedConsent` y llama `AuthenticateUser.preAuthenticate`. Si no se acepta, lanza `LoginException(consentRequired)`.

#### `ScopesConsentForm` (step: `scopes-consent`)
- **Render**: Muestra scopes pendientes divididos en "required" (lista) y "optional" (checkboxes). Obtiene la lista de `ScopesConsentUsecase.pendingScopes`.
- **Authenticate**: Combina scopes requeridos + opcionales seleccionados, persiste via `ScopesConsentUsecase.storeAcceptedScopes`, reconstruye `AuthenticationRequest` con los scopes aprobados y llama `AuthenticateUser.preAuthenticate`.

#### `UseMfaForm` (step: `mfa`)
- **Render**: Campo para código OTP (TOTP).
- **Authenticate**: Verifica el OTP con `PublicMfa.verifyOtp`. Si correcto, actualiza `challenges.withMfa(true)` y llama `preAuthenticate`. Si incorrecto, lanza `LoginException(mfaRequired('wrong_auth_code'))`.

#### `NewMfaForm` (step: `build-mfa`)
- **Render**: Genera QR code para configurar TOTP (via `PublicMfa.configurationForNewMfa`). Muestra imagen QR + campo para código de verificación. El seed se cifra server-side y se envía como hidden field.
- **Authenticate**: Descifra el seed, verifica el OTP con `PublicMfa.verifyNewOpt` (que además persiste el seed si es válido). Si correcto → `preAuthenticate`. Si no → `LoginException(newMfaRequired)`.

#### `NewPassForm` (step: `new-pass`)
- **Render**: Formulario con old password + new password (ambos cifrados client-side).
- **Authenticate**: Descifra ambos passwords, llama `ChangePasswordUsecase.forceUpdatePassword`. Si éxito → `preAuthenticate`. Si falla → `LoginException(newPasswordRequired)`.

#### `RecoverPassForm` (step: `recover-pass`)
Tiene 3 sub-pantallas internas:
1. **paintAsk** (sin `recover_send` ni error): Pide email/username del usuario.
2. **paintWait** (con error `WAITING_PASSCHANGE_CODE`): Muestra mensaje "revisa tu email" con link al formulario de código.
3. **paintConfirm** (con `recover_send=true`): Formulario de código de verificación + nueva password.
- **Authenticate**: Si llega `user` → genera código de recuperación via `ChangePasswordUsecase.requestForChange` y lanza `LoginException(waitNewpass)`. Si llega `code` + `new_pass` → valida con `ChangePasswordUsecase.validateChangeRequest`, si OK → `preAuthenticate`.

#### `RegisterUserForm` (step: `register-user`)
Tiene 3 sub-pantallas internas (misma lógica que RecoverPass):
1. **paintAsk**: Formulario de email + password + términos de uso (checkbox si hay terms).
2. **paintWait**: Mensaje "revisa tu email" con link al formulario de código.
3. **paintConfirm** (con `verify_send=true`): Campo de código de verificación.
- **Authenticate**: Si llega `user` → verifica aceptación de condiciones, llama `RegisterUserUsecase.requestForRegister` y lanza `LoginException(waitNewuserVerify)`. Si llega `code` → verifica con `RegisterUserUsecase.verifyRegister`, si OK → `preAuthenticate`.

#### `DelegateForm` (step: `delegated-login`)
- **Render**: Si llegan `provider-data` + `provider` (callback del proveedor social) → genera auto-submit form. Si no → obtiene endpoint del proveedor con `DelegateLogin.getTargetEndpoint` y redirige (302 para GET).
- **Authenticate**: Valida la redirección con `DelegateLogin.validateRedirection`, si el resultado es válido → `preAuthenticate`.

### Servicios de soporte HTML

| Clase | Descripción |
|-------|-------------|
| `DecorateHtml` | Wrappea el contenido de los forms en un tema HTML completo. Soporta temas en `Themes/` (corporate, blue, dark-theme, etc.). Copia assets estáticos a `.assets/oidc/`. |
| `HtmlSecurer` | Genera snippets JS para: firmar CSID (`signToken` con HMAC HS256), cifrar passwords client-side (AES), focus en campos, auto-submit. Usa `TemporalKeysGateway` para claves rotativas. |
| `OidcCookieManager` | Gestiona cookies: `AUTH_SESSION_ID_{TENANT}` (sesión autenticada), `PRE_SESSION_ID` (estado de challenges entre POST). La pre-session se firma como JWT con keypass. |
| `OidcResponseBuilder` | Construye la respuesta redirect de éxito: soporta `response_type` mixtos (`code`, `id_token`, `token`). Para `code` registra un `TemporalAuthCode` y lo incluye en la URL. Para `id_token`/`token` firma JWTs directamente. |
| `PublicMfa` | Fachada simplificada sobre `UserMfa`: configurationForNewMfa, verifyOtp, verifyNewOpt (auto-persiste seed si OK). |

---

## 3. Sub-módulo `Client`

### Responsabilidad
Resuelve y valida clientes OAuth (TrustedClients) y API Keys contra el feature `Access`.

### Domain

| Clase | Descripción |
|-------|-------------|
| `ClientData` | VO: `id`, `grants[]`, `secretLogin`. |
| `ApiKeyData` | VO: `id`, `scopes[]`. |
| `ClientStoreGateway` (port) | `clientData(id, secret)` → autenticación con client_secret. `preValidatedClient(id)` → cliente ya validado. `publicClientData(id, tenant, redirectUrl, scope)` → validación de cliente público (verifica redirects permitidos). |
| `ApiKeyStoreGateway` (port) | `apiKey(key): ?ApiKeyData` |

### Infrastructure
- `ClientStoreAdapter` → implementa `ClientStoreGateway` usando `TrustedClientReadGateway` (del feature Access). Verifica enabled, secret, public-allow, redirect URLs.
- `ApiKeyStoreAdapter` → implementa `ApiKeyStoreGateway` usando `ApiKeyClientReadGateway`.
- `ApiKeyController` → `POST` endpoint para validar API keys.

---

## 4. Sub-módulo `Key`

### Responsabilidad
Gestión del ciclo de vida de claves RSA (generación, rotación, firma, verificación). Firma tokens JWT con RS256.

### Domain

| Clase | Descripción |
|-------|-------------|
| `KeyPair` | VO: `kid`, `alg` (RS256), `keyUse` (sig), `privateKey`, `publicKey` (PEM). |
| `KeyConfig` | Configuración: `ttl` (7 días), `futures` (3 claves futuras). |
| `TokenSigner` (port) | `sign(tenant, data, expiration): string` → JWT firmado. `keysAsJwks(tenant): JWKSet`. `signKeypass(tenant, data, exp)` → JWT con payload `{keypass: data}`. `verifyTokenPayload(tenant, token)` → payload o null. `verifiedKeypass(tenant, token)` → extrae keypass. |
| `TokenStoreGateway` (port) | `currentKey(tenant)`, `nextKeysExpiration(tenant)`, `listKeys(tenant)`, `saveKey(tenant, pair, start, ttl)`. |

### Infrastructure
- `JoseTokenSigner` → implementa `TokenSigner` usando `web-token/jwt-*` (JOSE). Genera RSA 4096-bit keys. Auto-rota claves: si la expiración más lejana es menor que `futures * ttl`, genera claves nuevas.
- `TokenStoreSqlAdapter` → tabla `_oauth_keys_storer` (keyid, private, public, since, expiration, tenant). Limpia claves expiradas.
- `JwksController` → `GET /openid/{tenant}/jwks`. Cachea 1h con ETag.

---

## 5. Sub-módulo `Session`

### Responsabilidad
Persistencia de sesiones autenticadas y gestión de claves temporales / auth codes.

### Domain

| Clase | Descripción |
|-------|-------------|
| `SessionInfo` | VO: `csid`, `withMfa`, `issuer`, `userId`, `clientId`. |
| `TemporalAuthCode` | VO: `AuthenticationResult data`, `ClientData client`, `nonce`, `AuthenticationRequest request`. Se serializa a JSON para los authorization codes. |
| `SessionStoreGateway` (port) | CRUD de sesiones: `loadSession`, `saveSession`, `updateSession`, `deleteSession`. |
| `TemporalKeysGateway` (port) | Claves simétricas rotativas (HS256 + AES): `currentKey`, `encrypt`, `verifyCypher`, `verifyToken`. Auth codes temporales: `registerTemporalAuthCode` (3 min TTL), `retrieveTemporalAuthCode` (one-time use). |

### Infrastructure
- `SessionStoreSqlAdapter` → tabla `_oauth_session` (session, expiration, client_id, issuer, auth_data JSON, csid). Limpia expiradas en constructor.
- `TemporalKeysSqlAdapter` → tablas `_oauth_temporal_keys` (current, old, expiration) + `_oauth_temporal_codes` (code, code_data, expiration). Rota claves cada 1h. Auth codes se eliminan tras uso.

---

## 6. Sub-módulo `Mfa`

### Responsabilidad
Multi-factor authentication con TOTP (compatible Google Authenticator).

### Domain

| Clase | Descripción |
|-------|-------------|
| `PublicLoginMfaBuildResponse` | VO: `seed`, `?message`, `?image` (data URI del QR), `?url`. |
| `UserMfaGateway` (port) | `configurationForNewMfa(tenant, username)`, `verifyOtp(tenant, username, otp)`, `verifyNewOpt(tenant, username, seed, otp)`, `storeSeed(tenant, username, seed)`. |

### Application
- `UserMfa` → Usecase pasarela sobre `UserMfaGateway`.

### Infrastructure
- `UserMfaAdapter` → usa librería `RobThree\Auth\TwoFactorAuth` con `EndroidQrCodeProvider`. Lee seed cifrado del usuario via `AesCypherService`. El label del QR viene del `TenantConfig.innerLabel`.

---

## 7. Sub-módulo `Scopes`

### Responsabilidad
Consentimiento granular de scopes OAuth por usuario+cliente.

### Domain

| Clase | Descripción |
|-------|-------------|
| `ScopePermission` | VO: `scope`, `required`, `?label`, `?description`. |
| `ScopesConsentGateway` (port) | `pendingScopes(tenant, username, clientId, scopes[]): ScopePermission[]`. `storeAcceptedScopes(tenant, username, clientId, scopes[])`. |

### Application
- `ScopesConsentUsecase` → normaliza scopes (split por espacios, dedup), delega a gateway.

### Infrastructure
- `ScopesConsentAdapter` → Implementación stub (devuelve `[]` / no-op). **Pendiente de implementar completamente.**

---

## 8. Sub-módulo `DelegateLogin`

### Responsabilidad
Login social / federado. Extensible con providers.

### Domain

| Clase | Descripción |
|-------|-------------|
| `DelegatedLoginProvider` (interface) | `info(): DelegatedProviderDescription`. `delegeatedUrl(redirect, state): DelegatedLoginEndpoint`. `authorize(redirect, request): ?DelegatedUserData`. |
| `DelegatedProviderDescription` | VO: `id`, `name`, `logo`, `automatic`. |
| `DelegatedLoginEndpoint` | VO: `method` (GET/POST), `url`, `params[]`. |
| `DelegatedUserData` | VO: `code`, `name`, `email`. |
| `DelegateLoginGateway` (port) | `save(tenant, client, user, providerId): AuthenticationResult`. `providers(tenant): DelegatedLoginProvider[]`. `getProvider(tenant, id)`. |

### Application
- `DelegateLogin` → Orquesta: lista providers, genera endpoint de redirección (con state codificado en base64 que incluye tenant), valida la redirección de vuelta.

### Infrastructure
- `DelegateLoginAdapter` → Lee `TenantLoginProvider` del feature Access. Instancia el provider adecuado. En el `save`, si el usuario no existe lo crea automáticamente.
- `GoogleOAuthProvider` → Implementación para Google OAuth usando `league/oauth2-client`. Lee `clientId`/`secretId` de la configuración del `TenantLoginProvider`.

### Flujo delegated login
1. `LoginForm` muestra botones sociales por cada provider del tenant.
2. El POST con `step=delegated-login` va a `DelegateForm.render` que redirige al provider externo.
3. El provider callback llega a `DelegatedController.verify` → redirige de vuelta al authorize del tenant con `step=delegated-login` + `provider-data` + `provider`.
4. `DelegateForm.render` con `provider-data` genera auto-submit form.
5. `DelegateForm.authenticate` valida con el provider, crea/busca usuario, y llama `preAuthenticate`.

---

## 9. Sub-módulo `User` (OIDC)

### Responsabilidad
Casos de uso específicos del usuario en el contexto OIDC.

### Domain – Gateways (ports)

| Gateway | Métodos |
|---------|---------|
| `LoginGateway` | `validatedUserData(tenant, username, password, client)` → valida credenciales. `fillPreLoadById(tenant, client, challenges)` → carga datos por subject id. `fillPreAuthenticated(tenant, client, challenges)` → carga datos por username ya validado. |
| `ConsentGateway` | `getPendingConsent(tenant, username): ?string` (texto de terms). `storeAcceptedConsent(tenant, username)`. |
| `ChangePasswordGateway` | `requestForChange(url, tenant, username)`. `allowRecover(tenant): bool`. `validateChangeRequest(tenant, code, newPass): ?string` (username). `forceUpdatePassword(tenant, username, oldPass, newPass): bool`. |
| `RegisterUserGateway` | `allowRegister(tenant): bool`. `getRegisterConsent(tenant): ?string`. `requestForRegister(url, tenant, email, password)`. `verifyRegister(tenant, code): ?string` (username). |

### Domain – Value Objects

| Clase | Descripción |
|-------|-------------|
| `PublicLoginAuthResponse` | Resultado final de autenticación exitosa: `tenant`, `authData[]` (claims del access token), `authExpiration`, `idData[]` (claims adicionales del id_token), `idExpiration`, `sessionId`, `sessionExpiration`. |

### Application – Usecases

| Usecase | Descripción |
|---------|-------------|
| `LoginUsecase` | Envuelve `LoginGateway` con dispatching de eventos (`AuthenticationResult` se despacha como evento si el login es válido y no es sesión). |
| `ConsentUsecase` | Pasarela sobre `ConsentGateway`. |
| `ChangePasswordUsecase` | Pasarela sobre `ChangePasswordGateway`. |
| `RegisterUserUsecase` | Pasarela sobre `RegisterUserGateway`. |

### Application – Listeners

| Listener | Evento | Descripción |
|----------|--------|-------------|
| `NotifyLogin` | `AuthenticationResult` | Envía email al loguearse. |
| `NotifyCreate` | `UserCreateEvent` | Envía email al crear usuario. |
| `NotifyRecover` | `UserAccessTemporalCodeGeneratePasswordRecoverEvent` | Envía email con link de recuperación. |

### Infrastructure – Adapters

| Adapter | Descripción |
|---------|-------------|
| `LoginAdapter` | Implementa `LoginGateway`. Flujo de `validatedUserData`: checkTenant → checkUser → checkPassword → checkTemporalPassword → checkMfaConfigurationRequired → checkMfa → checkTerms → checkScopesConsent → markLoginOk. Cada check puede devolver un `AuthenticationResult` con error específico. Gestiona intentos fallidos (3 max → bloqueo 6h). Carga roles por audience (TrustedClient/RelyingParty + roles globales). |
| `ConsentAdapter` | Persiste aceptación de términos via `UserAcceptedTermnsOfUseWriteGateway`. |
| `ChangePasswordAdapter` | Genera código de recuperación (md5 random), persiste en `UserAccessTemporalCode`, valida código y cambia password. |
| `RegisterUserAdapter` | Crea usuario con `UserApproveOptions::UNVERIFIED`, genera código de verificación. Al verificar, cambia estado a ACCEPTED o PENDING según `TenantConfig.enableRegisterUsers`. |

---

## 10. Sub-módulo `Common`

### Responsabilidad
Utilidades compartidas entre sub-módulos OIDC.

| Clase | Descripción |
|-------|-------------|
| `UserLoaderAdapter` | Servicio compartido para: `checkTenant` (verifica existencia + enabled), `checkUser/checkUserSubjet/checkUserNameOrEmail` (verifica existencia + enabled + no bloqueado + approved). También: `userCodeForUpdate` (obtiene/crea `UserAccessTemporalCode`), `loadTenantTerms`, `checkUserByRecoveryCode`, `checkUserByRegisterCode`. |
| `OpenIdConfigurationController` | `GET /openid/{tenant}/.well-known/openid-configuration`. Devuelve discovery document completo conforme a OpenID Connect Discovery. |
| `OidcMigrationProvider` | Proveedor de migraciones SQL para las tablas OIDC. |
| `InstallUsecase` | Caso de uso de instalación inicial. |

---

## Relaciones con el feature `Access`

El módulo OIDC **no persiste entidades propias de negocio** (excepto sesiones y claves). Consume los gateways del feature `Access` como adaptadores driven:

| Feature Access | Uso en OIDC |
|----------------|-------------|
| `Tenant` | Resolución de tenants por nombre, verificación de enabled |
| `TenantConfig` | Configuración OIDC del tenant: `forceMfa`, `allowRecoverPass`, `allowRegister`, `enableRegisterUsers`, `innerLabel` |
| `TenantTermsOfUse` | Texto de términos de uso por tenant |
| `TenantLoginProvider` | Proveedores de login social por tenant |
| `User` | Entidad usuario: credenciales, estado, MFA seed, temporal password, blocked |
| `UserIdentity` | Roles del usuario por audience (TrustedClient/RelyingParty) |
| `UserAccessTemporalCode` | Códigos temporales: recovery, register verification, login attempts |
| `UserAcceptedTermnsOfUse` | Registro de aceptación de términos |
| `TrustedClient` | Clientes OAuth confiables (con secret, redirects, public-allow) |
| `ApiKeyClient` | API Keys |
| `RelyingParty` | Relying parties para audiencias |
| `Role` | Roles de acceso |

---

## Endpoints OIDC (resumen)

| Método | Ruta | Controlador |
|--------|------|-------------|
| GET | `/openid/{tenant}/.well-known/openid-configuration` | `OpenIdConfigurationController` |
| GET | `/openid/{tenant}/authorize` | `AuthorizeHtml.authorize` |
| POST | `/openid/{tenant}/authorize` | `AuthorizeHtml.formAuthorize` |
| POST | `/openid/{tenant}/check-session` | `AuthorizeHtml.refresh` |
| POST | `/openid/{tenant}/token` | `TokenController.post` |
| GET | `/openid/{tenant}/userinfo` | `UserInfoController.get` |
| GET | `/openid/{tenant}/logout` | `LogoutController.logout` |
| POST | `/openid/{tenant}/revoke` | `LogoutController.revoke` |
| GET | `/openid/{tenant}/jwks` | `JwksController.get` |
| GET | `/openid/-/delegated/verify` | `DelegatedController.verify` |
| POST | `/oauth/api-key` | `ApiKeyController.post` |

---

## Tablas SQL usadas

| Tabla | Sub-módulo | Descripción |
|-------|-----------|-------------|
| `_oauth_keys_storer` | Key | Pares RSA con kid, since, expiration, tenant |
| `_oauth_session` | Session | Sesiones autenticadas |
| `_oauth_temporal_keys` | Session | Clave simétrica rotativa (current/old) |
| `_oauth_temporal_codes` | Session | Authorization codes temporales (3 min TTL) |

---

## Flujo completo authorize (Authorization Code Flow)

```
1. GET /authorize?client_id=X&redirect_uri=Y&scope=Z&response_type=code&state=S&nonce=N
   ├─ Si hay sesión → cisdPage (auto-submit POST a check-session con CSID firmado)
   ├─ Si prompt=none y no hay sesión → redirect con error
   └─ Sino → renderiza LoginForm

2. POST /authorize (step=login, username, password cifrado)
   ├─ LoginForm.authenticate → AuthenticateUser.authenticate
   │   └─ LoginAdapter.validatedUserData:
   │       ├─ checkTenant → checkUser → checkPassword
   │       ├─ Si temporal password → return ERR_NEW_PASSWORD_REQUIRED → NewPassForm
   │       ├─ Si necesita configurar MFA → return ERR_NEW_MFA_REQUIRED → NewMfaForm
   │       ├─ Si tiene MFA → return ERR_MFA_REQUIRED → UseMfaForm
   │       ├─ Si hay terms pendientes → return ERR_CONSENT_REQUIRED → ConsentForm
   │       ├─ Si hay scopes pendientes → return ERR_SCOPES_CONSENT_REQUIRED → ScopesConsentForm
   │       └─ Si todo OK → AuthenticationResult(valid=true)
   ├─ Si valid → AuthenticateUser.saveIt → crea sesión → PublicLoginAuthResponse
   │   └─ OidcResponseBuilder.buildSuccessRedirect:
   │       ├─ response_type=code → registra TemporalAuthCode → redirect?code=X&state=S
   │       ├─ response_type=id_token → firma JWT → redirect#id_token=X
   │       └─ response_type=token → firma JWT → redirect#access_token=X
   └─ Si LoginException → renderiza el step del error (ERROR_MAP)

3. POST /token (grant_type=authorization_code, code=X)
   ├─ Recupera TemporalAuthCode (one-time use)
   ├─ Firma access_token + id_token + refresh_token
   └─ Response JSON: {token_type, expires_in, id_token, access_token, refresh_token}
```

---

## Seguridad

- **CSID (Client Session ID)**: Token JWT firmado con HS256 usando clave temporal simétrica. Se genera client-side con JS y se verifica server-side. Protege contra CSRF.
- **Passwords cifradas client-side**: AES con clave temporal del servidor. El campo visible es `type_password`, el cifrado va en `password` hidden.
- **Pre-session cookie**: JWT firmado con RS256 que contiene los `ChallengesState`. Permite mantener estado entre POSTs sin server-side state adicional.
- **Claves RSA rotativas**: 3 claves futuras, TTL 7 días. Auto-generación cuando se detecta que faltan.
- **Claves temporales simétricas**: Rotan cada 1h. Se mantiene current + old para transición.
- **Auth codes**: TTL 3 minutos, one-time use (se eliminan tras lectura).
- **Login throttling**: 3 intentos fallidos → bloqueo 6 horas (via `UserAccessTemporalCode.failedLoginAttempts`).

---

## Notas para la migración desde Java/Quarkus

1. **La estructura de paquetes es directamente traducible**: cada sub-módulo (Authentication, Client, Key, etc.) mapea a un package Java. Los `Domain/Gateway` son interfaces (ports) y los `Infrastructure/Driven` son implementaciones (adapters).

2. **Los `StepForm` son el punto más complejo**: cada formulario combina renderizado HTML + lógica de autenticación. En Java podrían ser controllers separados o una state machine con handlers.

3. **El `OidcStepRouter` + `ERROR_MAP` implementa una mini state machine**: los errores del `LoginAdapter` determinan el siguiente step. Esto es el corazón del flujo.

4. **`ChallengesState` viaja entre requests** via cookie firmada (`PRE_SESSION_ID`). Es el mecanismo para acumular challenges (MFA completado, username, etc.) sin estado en servidor.

5. **Las dependencias con `Access`** son solo via gateways (ports). La implementación Java puede usar repositorios JPA/Panache directamente en los adapters.

6. **`ScopesConsentAdapter` es un stub**: la persistencia de consentimiento de scopes está pendiente de implementar.

7. **Los listeners (`NotifyLogin`, `NotifyCreate`, `NotifyRecover`) son hardcoded**: en producción deberían ser configurables por tenant.

8. **`TokenController` soporta 3 grant types**: `authorization_code`, `refresh_token`, `password` (con Basic Auth para client credentials).
