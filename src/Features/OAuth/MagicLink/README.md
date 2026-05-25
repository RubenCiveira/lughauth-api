# MagicLink Bounded Context

Implements passwordless authentication via single-use email links (magic links).

## Responsibility

This context owns the full **magic link lifecycle**: generating a cryptographically safe token, sending it by email, and verifying it when the user clicks the link. Successful verification injects the user into the main authentication flow as a verified identity step.

## Domain Concepts

| Concept | Type | Description |
|---|---|---|
| `MagicLink` | AggregateRoot | The core entity. Fields: `uid`, `tenantId`, `userUid`, `clientId`, `tokenHash`, `redirectUri`, `scope`, `state`, `nonce`, `createdAt`, `expiresAt`, `usedAt`. Exposes `isValid()`, `isExpired()`, `isUsed()` |
| `MagicLinkSession` | ValueObject | Temporary session created upon successful link verification: `sessionId` + `expiration` |

## Ports (Gateways)

| Gateway | Responsibility |
|---|---|
| `MagicLinkGateway` | Store a new magic link; find by token hash; mark as used (idempotent) |
| `MagicLinkEnabledGateway` | Check whether magic link login is enabled for a given tenant/client |
| `MagicLinkSessionGateway` | Create and resolve the temporary session tied to a verified magic link |
| `MagicLinkCodeGateway` | Generate the raw token and compute its hash |
| `MagicLinkMailGateway` | Deliver the magic link to the user's email address |

## Application Use Cases

| Use Case | Description |
|---|---|
| `RequestMagicLinkUsecase` | Validates the request, generates a token, persists the `MagicLink`, and dispatches the email |
| `VerifyMagicLinkUsecase` | Looks up the link by token hash, checks validity (not expired, not used), marks it used, and returns a `MagicLinkVerifyResult` with a session |

## Infrastructure

### Driven (Outbound Adapters)

- **`MagicLinkSqlAdapter`** — Persists `MagicLink` aggregates to SQL.
- **`MagicLinkCodeAdapter`** — Token generation and hashing.
- **`MagicLinkMailAdapter`** — Email delivery integration.
- **`MagicLinkSessionAdapter`** — Temporary session storage.
- **`MagicLinkEnabledAdapter`** — Reads per-tenant feature flag.

### Driver (Inbound Adapters)

- **`Html/MagicLinkVerifyHtml`** — Handles the browser redirect when a user clicks the magic link.
- **`Rest/MagicLinkRequestController`** — `POST /magic-link` endpoint to request a link.

## Key Invariants

- The raw token is **never stored** — only its hash. The hash is compared on verification.
- A `MagicLink` is **single-use**: once `usedAt` is set, subsequent verification attempts are rejected.
- Links expire (typically 15 minutes); `isExpired()` is checked before `isUsed()`.
- Magic link login is **opt-in per tenant**: the `MagicLinkEnabledGateway` gate is checked before issuance.

## Interactions with Other Contexts

```
MagicLink ──step in──> Authentication  (VerifyMagicLinkUsecase resolves the magic-link step)
```
