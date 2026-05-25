# Mfa Bounded Context

Manages multi-factor authentication (MFA) method enrollment and configuration for users.

## Responsibility

This context owns the **MFA method model**: which methods a user has enrolled, and the setup flow for enrolling new ones (seed, QR code, provisioning URL). It acts as a support context for the Authentication flow — when a challenge requires MFA, this context provides the data needed to render the challenge and validate the response.

## Domain Concepts

| Concept | Type | Description |
|---|---|---|
| `PublicLoginMfaBuildResponse` | ValueObject | MFA setup payload returned to the client: `seed`, `message`, `image` (QR code), `url` (provisioning URI) |

## Ports (Gateways)

| Gateway | Responsibility |
|---|---|
| `UserMfaGateway` | Retrieve the list of enrolled MFA methods for a given user |

## Application Services

- **`UserMfa`** — Resolves a user's enrolled MFA methods and constructs the `PublicLoginMfaBuildResponse` for the setup/verification step.

## Infrastructure

### Driven (Outbound Adapters)

- **`UserMfaAdapter`** — Reads MFA configuration from the underlying user store (TOTP secrets, enrolled methods).

## Key Invariants

- MFA data (TOTP seeds) is read-only from this context's perspective — enrollment persistence is delegated to the user store.
- The `PublicLoginMfaBuildResponse` is only exposed during setup; active authentication challenges do not return the seed.

## Interactions with Other Contexts

```
Mfa ──step in──> Authentication   (MFA challenge rendering and validation)
    ──displayed in──> Profile     (MFA enrollment management panel)
```
