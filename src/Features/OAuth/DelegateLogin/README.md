# DelegateLogin Bounded Context

Implements federated identity login via external providers (Google, GitHub, Apple, Microsoft, SAML 2.0).

## Responsibility

This context owns the **external provider integration layer**: discovering available providers, redirecting the user to the provider's authorization endpoint, and processing the callback to extract a verified external identity. The resolved `DelegatedUserData` is then handed back to the Authentication context to complete the local session.

## Domain Concepts

| Concept | Type | Description |
|---|---|---|
| `DelegatedLoginProvider` | Interface | Plugin contract: `info()` returns provider metadata, `delegatedUrl()` returns the redirect URL, `authorize()` processes the callback and returns user data |
| `DelegatedLoginEndpoint` | ValueObject | Provider-specific OAuth endpoint URLs (authorization, token, userinfo) |
| `DelegatedProviderDescription` | ValueObject | Public metadata about a provider: name, icon, display label |
| `DelegatedUserData` | ValueObject | Normalized external user attributes (sub, email, name, picture…) extracted after successful provider callback |

## Ports (Gateways)

| Gateway | Responsibility |
|---|---|
| `DelegateLoginGateway` | Discovers configured providers for a tenant and resolves provider instances |

## Application Services

- **`DelegateLogin`** — Orchestrates the delegation flow: resolves the provider by name, builds the redirect URL (begin), or processes the callback and returns `DelegatedUserData` (finish).

## Providers

| Provider | Protocol |
|---|---|
| `GoogleOAuthProvider` | OAuth 2.0 + OIDC |
| `GitHubOAuthProvider` | OAuth 2.0 |
| `AppleOAuthProvider` | OAuth 2.0 + Sign in with Apple |
| `MicrosoftOAuthProvider` | OAuth 2.0 + OIDC (Azure AD) |
| `Saml2Provider` | SAML 2.0 |

## Infrastructure

### Driven (Outbound Adapters)

- **`DelegateLoginAdapter`** — Reads per-tenant provider configuration and instantiates the correct `DelegatedLoginProvider` implementation.

### Provider Implementations (`Infrastructure/Provider/`)

Each provider implements `DelegatedLoginProvider` and handles its own OAuth state, PKCE, and token exchange logic.

## Key Invariants

- Providers are **tenant-configured**: not all providers are available in all tenants.
- The `authorize()` method validates state/nonce to prevent CSRF.
- `DelegatedUserData` is **normalized** — downstream code never depends on provider-specific field names.
- A SAML 2.0 SP-initiated flow follows the same `info() / delegatedUrl() / authorize()` contract as OAuth providers.

## Interactions with Other Contexts

```
DelegateLogin ──callback resolved by──> Authentication  (DelegatedController calls DelegateLogin.authorize(), then continues the auth flow)
              ──links to existing──>    User             (external identity is matched or linked to a local account)
```
