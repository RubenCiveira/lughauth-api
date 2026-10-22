# Authentication Bounded Context

Orchestrates the full OAuth 2.0 / OIDC authorization flow, from the initial authorization request to token issuance and session management.

## Responsibility

This context owns the **multi-step authentication pipeline**: it coordinates user identity verification, step sequencing (MFA, consent, password change, WebAuthn), token granting, and session binding. It does not own individual steps — those are delegated to their respective contexts (MagicLink, Mfa, WebAuthn, Consent, etc.).

## Domain Concepts

| Concept | Type | Description |
|---|---|---|
| `AuthenticationRequest` | ValueObject | Initial authorization parameters (client, scopes normalised as exact values, PKCE, response_type) |
| `ScopeSet` | ValueObject | Scope as a set of exact values (`myprofile` never grants `profile`) |
| `TokenGrant` | ValueObject | What a grant or authorization response authorised (client, subject, scope, audiences, session, nonce, refresh family) |
| `TokenLifetimes` | ValueObject | Access, ID, refresh and refresh-family lifetimes of a tenant (`TokenLifetimesGateway`, read from tenant config) |
| `AuthenticationResult` | ValueObject | Outcome of a completed authentication step |
| `ChallengesState` | ValueObject | Pending steps to complete before token issuance |
| `StepInput` / `StepResult` | ValueObject | Input/output of a single authentication step |
| `StepName` | Enum | Identifies each step in the pipeline (mfa, new_password, consent…) |
| `OidcFlowContext` | ValueObject | OIDC parameters carried throughout the flow (nonce, state, redirect_uri), plus the `request_ref` of a PAR/JAR request |
| `ResolvedAuthorizationRequest` | ValueObject | Authorization parameters with their origin (query, PAR or JAR) and the signed reference that carries a PAR/JAR request between login steps |
| `RequestObjectValidator` | Domain service | Verifies a JAR request object (RFC 9101): signature, `client_id`/`iss` = client, `aud` = tenant issuer, `exp`/`nbf`/`iat`, single-use `jti` |
| `ActiveUser` | ValueObject | Authenticated identity after successful credential verification |
| `UiLocale` | ValueObject | Login UI language for one request: first supported of `ui_locales`, then `Accept-Language` by q-value, else `es`; supported `es` and `en` (catalogues `forms.es.yaml`, `forms.yaml`) |
| `PkceChallenge` | ValueObject | PKCE code_challenge / code_verifier pair; only `S256`, with RFC 7636 syntax for challenge and verifier |
| `LogoutToken` | ValueObject | JWT token used for back-channel logout (RFC 9126) |

## Application Services

- **`AuthorizationRequestResolver`** — Decides once where the `/authorize` parameters come from: a pushed request (`request_uri`, only the outer `client_id` is read and must match), a verified request object (`request`), the signed reference issued after consuming one of those (`request_ref`), or the query. Rejects direct requests when the client or the tenant requires PAR.
- **`AuthenticateUser`** — Entry point for the authorization endpoint. Validates the request, resolves the current step, and returns either a challenge or a redirect.
- **`ActiveUserFindService`** — Resolves the currently authenticated user from a session.
- **`SessionManager`** — Creates and binds authentication sessions to issued tokens.
- **`BackChannelLogoutDispatcher`** — Sends back-channel logout notifications to registered clients.
- **`TokenGranterMediator`** — Selects and executes the appropriate token grant strategy.
- **`TokenFactory`** — Issues the access, ID and refresh tokens of a `TokenGrant`, for the token endpoint and for the authorization response (implicit/hybrid), with the tenant's lifetimes.
- **`ClaimsMapper`** — Single place for the claims of each token type: access (RFC 9068), ID (`aud` = client; `auth_time`, `amr`, `acr`, public `sid` only from the session; `nonce`, `at_hash`, `c_hash`, `s_hash`) and refresh.

### Token Grant Strategies (`TokenGranter/`)

| Strategy | Grant Type |
|---|---|
| `ResolverForPassword` | `password` |
| `ResolverForRefresh` | `refresh_token` |
| `ResolverForClientCredentials` | `client_credentials` |
| `ResolverForDevice` | `urn:ietf:params:oauth:grant-type:device_code` |

### Use Cases

- **`RevokeTokenUsecase`** — Revokes an active access or refresh token (RFC 7009).

## Domain Events

| Event | Trigger |
|---|---|
| `LoginSucceededEvent` | User authenticated successfully (carries userId, clientId, sessionId, grantType) |
| `LoginFailedEvent` | Invalid credentials or step failure |
| `UserLockedEvent` | Account locked after repeated failures |
| `UserUnlockedEvent` | Account unlocked |
| `OidcEvent` | Base event for OIDC-specific flow events |

## Exceptions (flow control)

Exceptions are used to signal required next steps — they carry enough state for the UI to render the appropriate form:

| Exception | Meaning |
|---|---|
| `LoginException` | Wraps `AuthenticationResult` + `ChallengesState` for UI rendering |
| `MfaRequiredException` | MFA step must be completed |
| `NewMfaRequiredException` | User must enroll a new MFA method |
| `NewPasswordRequiredException` | Password must be changed before proceeding |
| `ConsentRequiredException` | Scope consent pending |
| `ClientScopeConsentRequiredException` | Client-level scope consent pending |
| `WrongCredentialsException` | Bad username/password |
| `UnknownUserException` | User not found |
| `NotAllowedAccessUserException` | User exists but cannot access this client |
| `OAuthTokenException` | Generic token issuance error |

## Infrastructure

### Driver (Inbound Adapters)

- **`Html/AuthorizeHtml`** — Renders the authorization UI (login, MFA, consent forms). After consuming a PAR or JAR request it redirects to `/authorize?client_id=…&request_ref=…`, because the step forms post back to their own URL. PKCE is required for public and `require-pkce` clients, at `/authorize` and again when `/token` redeems the code.
- **`Html/OidcStepRouter`** — Routes the current challenge state to the correct form.
- **`Html/Forms/`** — Individual form renderers per step (LoginForm, MagicLinkLoginForm, NewMfaForm, ScopeConsentForm, WebAuthnLoginForm…).
- **`Rest/TokenController`** — `/token` endpoint (all grant types): HTTP parsing, client authentication and grant resolution; tokens come from `TokenFactory`. Every response carries `Cache-Control: no-store`.
- **`Rest/IntrospectionController`** — `/introspect` endpoint (RFC 7662). Applies `AccessTokenPolicy`: only active access tokens of the tenant are `active`.
- **`Rest/LogoutController`** — `/logout` endpoint.
- **`Rest/UserInfoController`** — `GET`/`POST /userinfo` endpoint (OIDC). Verifies the bearer (header, or `access_token` on POST) with `AccessTokenPolicy`, requires `openid` and answers RFC 6750 errors with `WWW-Authenticate`.
- **`Rest/DelegatedController`** — Handles callback from external identity providers.

### Driven (Outbound Adapters)

- **`HttpRemoteJwksFetcher`** — `RemoteJwksFetcher` port: downloads a client `jwks_uri` over HTTPS only, refusing hosts that resolve to non-public addresses and pinning the connection to the checked address (SSRF and DNS rebinding); no redirects, 5 s timeout, 64 KiB maximum, cached per client for 5 minutes.
- **`TenantConfigTokenLifetimesAdapter`** — `TokenLifetimesGateway` from the tenant configuration.

### Services

- **`HtmlSecurer`** — Applies CSP and security headers to HTML responses.
- **`OidcCookieManager`** — Manages OIDC session cookies (nonce, state).
- **`OidcResponseBuilder`** — Constructs the final authorization response (code, token, id_token).

## Interactions with Other Contexts

```
Authentication ──uses──> Session        (session creation/lookup)
              ──uses──> TokenSecurity   (JWT signing)
              ──uses──> Client          (client validation)
              ──uses──> Consent         (scope consent orchestration)
              ──uses──> MagicLink       (passwordless step)
              ──uses──> Mfa             (MFA step)
              ──uses──> WebAuthn        (passkey step)
              ──uses──> DelegateLogin   (external provider callback)
              ──uses──> Par             (resolve pushed auth requests, tenant PAR policy)
              ──uses──> TokenSecurity   (request object jti replay store)
              ──emits──> User           (LoginSucceededEvent listener)
```
