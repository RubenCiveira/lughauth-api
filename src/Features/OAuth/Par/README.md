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
| `ParRequestGateway` | Persist a `ParRequest`, find by request URI, mark as used |

## Application Use Cases

| Use Case | Description |
|---|---|
| `PushAuthorizationUsecase` | Validates the pushed parameters, creates a `ParRequest`, returns a `PushAuthorizationResult` with the `request_uri` and `expires_in` |
| `ResolveParRequestUsecase` | Resolves a `request_uri` to its stored parameters for consumption by the authorization endpoint |

## Infrastructure

### Driven (Outbound Adapters)

- **`ParRequestSqlAdapter`** — SQL persistence for PAR records.

### Driver (Inbound Adapters)

- **`Rest/ParController`** — `POST /par` endpoint (authenticated with client credentials).

## Key Invariants

- A `ParRequest` is **single-use**: once resolved, subsequent lookups for the same `request_uri` are rejected.
- Default expiration is **60 seconds** — tight enough to prevent replay while allowing for network latency.
- Only **confidential clients** (those capable of authenticating) may use PAR.
- The `request_uri` is opaque to the user agent and carries no authorization parameters in the URL.

## Interactions with Other Contexts

```
Par ──consumed by──> Authentication  (ResolveParRequestUsecase called when request_uri is present in the authorization request)
    ──validates──>   Client          (client authentication required before pushing)
```
