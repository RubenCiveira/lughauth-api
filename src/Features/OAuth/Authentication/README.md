# Authentication Bounded Context

Orchestrates the full OAuth 2.0 / OIDC authorization flow, from the initial authorization request to token issuance and session management.

## Responsibility

This context owns the **multi-step authentication pipeline**: it coordinates user identity verification, step sequencing (MFA, consent, password change, WebAuthn), token granting, and session binding. It does not own individual steps — those are delegated to their respective contexts (MagicLink, Mfa, WebAuthn, Consent, etc.).

## Domain Concepts

| Concept | Type | Description |
|---|---|---|
| `AuthenticationRequest` | ValueObject | Initial authorization parameters (client, scopes, PKCE, response_type) |
| `AuthenticationResult` | ValueObject | Outcome of a completed authentication step |
| `ChallengesState` | ValueObject | Pending steps to complete before token issuance |
| `StepInput` / `StepResult` | ValueObject | Input/output of a single authentication step |
| `StepName` | Enum | Identifies each step in the pipeline (mfa, new_password, consent…) |
| `OidcFlowContext` | ValueObject | OIDC parameters carried throughout the flow (nonce, state, redirect_uri) |
| `ActiveUser` | ValueObject | Authenticated identity after successful credential verification |
| `PkceChallenge` | ValueObject | PKCE code_challenge / code_verifier pair |
| `LogoutToken` | ValueObject | JWT token used for back-channel logout (RFC 9126) |

## Application Services

- **`AuthenticateUser`** — Entry point for the authorization endpoint. Validates the request, resolves the current step, and returns either a challenge or a redirect.
- **`ActiveUserFindService`** — Resolves the currently authenticated user from a session.
- **`SessionManager`** — Creates and binds authentication sessions to issued tokens.
- **`BackChannelLogoutDispatcher`** — Sends back-channel logout notifications to registered clients.
- **`TokenGranterMediator`** — Selects and executes the appropriate token grant strategy.

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

- **`Html/AuthorizeHtml`** — Renders the authorization UI (login, MFA, consent forms).
- **`Html/OidcStepRouter`** — Routes the current challenge state to the correct form.
- **`Html/Forms/`** — Individual form renderers per step (LoginForm, MagicLinkLoginForm, NewMfaForm, ScopeConsentForm, WebAuthnLoginForm…).
- **`Rest/TokenController`** — `/token` endpoint (all grant types).
- **`Rest/IntrospectionController`** — `/introspect` endpoint (RFC 7662).
- **`Rest/LogoutController`** — `/logout` endpoint.
- **`Rest/UserInfoController`** — `/userinfo` endpoint (OIDC).
- **`Rest/DelegatedController`** — Handles callback from external identity providers.

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
              ──uses──> Par             (resolve pushed auth requests)
              ──emits──> User           (LoginSucceededEvent listener)
```
