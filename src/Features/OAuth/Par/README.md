# Par Bounded Context

Implements OAuth 2.0 Pushed Authorization Requests (RFC 9126), allowing clients to pre-register authorization parameters server-side before redirecting the user.

## Responsibility

This context owns the **PAR lifecycle**: receiving authorization parameters from a confidential client over a back-channel, validating them, persisting them under a `request_uri`, and resolving them when the authorization endpoint receives that URI. This prevents authorization parameters from being exposed in browser history or referrer headers.

## Domain Concepts

| Concept | Type | Description |
|---|---|---|
| `ParRequest` | AggregateRoot | Pushed authorization record: `requestUri`, `tenant`, `clientId`, `params`, `expiresAt`, `usedAt`. Exposes `isExpired()`, `isUsed()`, static `create()` factory. The `requestUri` follows the `urn:ietf:params:oauth:request_uri:…` format |

## Ports (Gateways)

| Gateway | Responsibility |
|---|---|
| `ParRequestGateway` | Persist a `ParRequest`, find by request URI, consume it atomically (only one caller wins) |
| `TenantParPolicyGateway` | Whether a tenant requires PAR for every client (`tenant-config.require-pushed-authorization-requests`) |

## Application Use Cases

| Use Case | Description |
|---|---|
| `PushAuthorizationUsecase` | Authenticates the client, validates the pushed parameters (their `client_id` must be the authenticated client; no nested `request_uri`), creates a `ParRequest`, returns a `PushAuthorizationResult` with the `request_uri` and `expires_in` |
| `ResolveParRequestUsecase` | Resolves a `request_uri` for the client named at the authorization endpoint and consumes it, for `AuthorizationRequestResolver` (Authentication) |

## Infrastructure

### Driven (Outbound Adapters)

- **`ParRequestSqlAdapter`** — SQL persistence for PAR records; `consume` is `UPDATE … WHERE used_at IS NULL AND expires_at >= now`.
- **`TenantConfigParPolicyAdapter`** — Reads the tenant-wide PAR policy from the tenant configuration.

### Driver (Inbound Adapters)

- **`Rest/ParController`** — `POST /par` endpoint (authenticated with client credentials).

## Key Invariants

- A `ParRequest` is **single-use**: once resolved, subsequent lookups for the same `request_uri` are rejected, also under concurrency.
- A `ParRequest` is only resolved for the client that pushed it, and its parameters are never merged with the query of `/authorize`.
- A client with `require-pushed-authorization-requests`, or every client of a tenant with that configuration, cannot use `/authorize` without a `request_uri`. Discovery announces `require_pushed_authorization_requests` only for the tenant-wide setting.
- Default expiration is **60 seconds** — tight enough to prevent replay while allowing for network latency.
- Only **confidential clients** (those capable of authenticating) may use PAR.
- The `request_uri` is opaque to the user agent and carries no authorization parameters in the URL. After it is consumed, the login steps carry a signed (not encrypted) `request_ref` with the parameters: the user agent can read them but not change them, and it completes a single authorization.

## Interactions with Other Contexts

```
Par ──consumed by──> Authentication  (ResolveParRequestUsecase called when request_uri is present in the authorization request)
    ──validates──>   Client          (client authentication required before pushing)
```
