# OIDC Module — Federated / Delegated Login

Federated login allows users to authenticate via an external identity provider without entering credentials directly. The module supports OAuth 2.0-based providers (e.g., Google, GitHub, Microsoft, Apple) and SAML2 identity providers.

---

## Architecture

Federated login is encapsulated behind the `DelegateLoginGateway` port. Provider-specific logic lives in implementations of `DelegatedLoginProvider`:

```
DelegateLoginGateway
  ├─ providers(tenant): DelegatedLoginProvider[]
  ├─ getProvider(tenant, providerId): ?DelegatedLoginProvider
  └─ save(tenant, clientId, userData, providerId): AuthenticationResult

DelegatedLoginProvider (one per provider)
  ├─ info(): DelegatedProviderDescription
  ├─ delegatedUrl(redirectUri, state): DelegatedLoginEndpoint
  └─ authorize(redirectUri, request): ?DelegatedUserData
```

The module does not prescribe a specific protocol for provider communication — any provider that can produce a `DelegatedUserData` (user code, name, email) after a browser redirect is compatible.

---

## Flow

```
Browser             DelegateForm          External Provider       DelegatedController
   │                     │                       │                       │
   │── POST /authorize ──►│                       │                       │
   │   step=delegated     │                       │                       │
   │                      │ getProvider(…)        │                       │
   │                      │ delegatedUrl(…)       │                       │
   │◄── 302 to provider ──│                       │                       │
   │─────────────────────────────────────────────►│                       │
   │                                              │ provider authenticates│
   │◄─────────────── 302 callback ────────────────│                       │
   │   /oauth/openid/-/delegated/verify?...       │                       │
   │───────────────────────────────────────────────────────────────────►  │
   │                                                                       │ decode state
   │                                                                       │ 302 to tenant
   │◄──────────────────────────────────── /authorize?step=delegated ───────│
   │                                      &provider=X&provider-data=Y      │
   │── POST /authorize ──►│                       │                       │
   │   step=delegated     │ provider.authorize(…) │                       │
   │                      │ gateway.save(…)       │                       │
   │◄── 302 to client ────│                       │                       │
```

---

## Step 1: Redirect to Provider (`DelegateForm::render`)

When `POST /authorize` arrives with `step=delegated-login` and no `provider-data` in the body:

1. `DelegateLoginGateway::getProvider(tenant, providerId)` returns the provider.
2. `provider.delegatedUrl(callbackUri, state)` returns a `DelegatedLoginEndpoint`:
   - `method`: `GET` or `POST`
   - `url`: provider's authorization URL
   - `params`: any additional parameters
3. The `state` value encodes the full OIDC flow context (tenant, original state, nonce, redirect URI, client id, scope, response type) as base64 JSON. This allows `DelegatedController.verify` to reconstruct the flow after the provider returns.
4. The form generates a **302 redirect** (for GET-based providers) or an auto-submit HTML form (for POST-based providers) to the external provider.

---

## Step 2: Provider Callback (`DelegatedController::verify`)

The provider redirects the user-agent to the module's tenant-agnostic callback endpoint:

```
GET /oauth/openid/-/delegated/verify
  ?provider=<provider-id>
  &state=<base64-encoded-context>
  &code=<auth-code>   # OAuth2 providers
  ...                  # provider-specific params
```

**Security:** The `state` parameter value is the base64-encoded context from Step 1. `DelegatedController` MUST decode `state` to extract the tenant before routing — the endpoint itself is tenant-agnostic.

Processing:
1. Decode `state` to extract `tenant` and original OIDC context.
2. Redirect to the tenant's `/authorize` with original params plus:
   - `step=delegated-login`
   - `provider=<provider-id>`
   - `provider-data=<base64-encoded callback params>` (all provider-returned query params)

---

## Step 3: Provider Validation (`DelegateForm::render` with provider-data)

When `GET /authorize` carries `provider-data`, the form renders an auto-submit HTML form that POSTs back to `/authorize` with `step=delegated-login`. This converts the GET (from the redirect) into a POST that can carry a CSID.

---

## Step 4: Authentication (`DelegateForm::authenticate`)

When the POST arrives with `step=delegated-login` and `provider-data`:

1. `provider.authorize(callbackUri, request)` — validates the provider's callback and extracts `DelegatedUserData` (provider user `code`, `name`, `email`).
2. `DelegateLoginGateway::save(tenant, clientId, userData, providerId)` — provisions or retrieves the user account. Returns a full `AuthenticationResult` as if the user had authenticated with credentials.
3. Call `AuthenticateUser::preAuthenticate` to complete any remaining challenge checks and build the session.

---

## `DelegatedLoginProvider` Contract

### `info(): DelegatedProviderDescription`

Returns metadata displayed in the login form:

| Field | Description |
|-------|-------------|
| `id` | Unique provider identifier (e.g., `"google"`, `"github"`) |
| `name` | Display name shown on the login button |
| `logo` | URL or data URI for the provider logo |
| `automatic` | If `true`, the login form auto-redirects to this provider when it is the only one configured |

### `delegatedUrl(redirectUri, state): DelegatedLoginEndpoint`

Constructs the URL to redirect the user to for authentication. `redirectUri` MUST point to `DelegatedController::verify`. `state` is the base64-encoded OIDC flow context.

For **OAuth 2.0 providers**, this typically constructs an authorization URL per RFC 6749 §4.1.1:

```
{provider_authorization_endpoint}
  ?response_type=code
  &client_id={provider_client_id}
  &redirect_uri={callback_uri}
  &scope={provider_scopes}
  &state={base64_context}
```

### `authorize(redirectUri, request): ?DelegatedUserData`

Called server-side after the provider redirects the user back. For **OAuth 2.0 providers**:

1. Extract `code` from the callback request.
2. Verify `state` matches the outgoing request.
3. Exchange `code` for tokens via the provider's token endpoint (server-to-server, per RFC 6749 §4.1.3).
4. Extract user identity from the token or userinfo endpoint.
5. Return `DelegatedUserData` on success, `null` on any failure.

Returns `null` if the provider response is invalid, tampered, or the user denied access (`access_denied`).

---

## `DelegateLoginGateway::save` Contract

Responsible for user provisioning and identity linking:

1. Check whether a user account linked to `(providerId, userData.code)` already exists for `tenant`.
2. If yes: load the existing user → return full `AuthenticationResult`.
3. If no: create a new user account auto-linked to the provider identity → return full `AuthenticationResult`.
4. The result MUST include `sub`, `name`, `email`, `scope`, `roles`, and `groups`.

**Security requirement:** The implementation MUST NOT allow a federated identity to claim an existing local account using only the `email` match. Account linking MUST require explicit user confirmation, or a separate secure linking flow.

---

## State Encoding

The `state` parameter passed to the external provider encodes:

```json
{
  "tenant":        "tenant-name",
  "state":         "<original-oauth-state>",
  "nonce":         "<original-nonce>",
  "redirect_uri":  "<original-redirect-uri>",
  "client_id":     "<client-id>",
  "scope":         "openid email",
  "response_type": "code"
}
```

Encoded as `base64(json_encode(...))`. This allows `DelegatedController::verify` to reconstruct the full OIDC context from the opaque `state` value without any server-side state.

---

## Supported Provider Types

The module provides a `DelegatedLoginProvider` implementation for each of the following (host-configurable via `DelegateLoginGateway`):

| Provider | Protocol | Notes |
|----------|----------|-------|
| Google | OAuth 2.0 + OpenID Connect | Uses `google-auth-library` or `league/oauth2-google` |
| GitHub | OAuth 2.0 | Uses `/user` API for identity |
| Microsoft | OAuth 2.0 + OpenID Connect | Azure AD / Entra ID |
| Apple | OAuth 2.0 + Sign in with Apple | Uses JWT identity token |
| SAML2 | SAML 2.0 | Any IdP with SP-initiated SSO |

The provider list per tenant is configured via `DelegateLoginGateway::providers(tenant)`.
