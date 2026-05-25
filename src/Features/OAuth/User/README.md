# User Bounded Context

Manages core user lifecycle operations: credential-based login, registration, and password changes.

## Responsibility

This context owns the **user identity operations** that interact directly with credential storage: verifying login credentials, creating new user accounts, and updating passwords. It also dispatches email notifications on key lifecycle events and constructs the public login API response consumed by SPAs and mobile clients.

## Domain Concepts

| Concept | Type | Description |
|---|---|---|
| `PublicLoginAuthResponse` | ValueObject | JSON login response for public API clients: `tenant`, `auth` (access token), `issuer`, `clientId`, `authExpiration`, `idData` (ID token), `idExpiration`, `sessionId`, `sessionExpiration` |
| `RecoveryNotificationData` | ValueObject | Data payload for password recovery emails |
| `RegistrationNotificationData` | ValueObject | Data payload for registration welcome emails |

## Ports (Gateways)

| Gateway | Responsibility |
|---|---|
| `LoginGateway` | Verify user credentials against the underlying identity store |
| `RegisterUserGateway` | Create a new user account |
| `ChangePasswordGateway` | Update a user's password hash |
| `UserRegistrationMailGateway` | Send a welcome/verification email after registration |
| `PasswordRecoveryMailGateway` | Send a password recovery email |

## Application Services & Use Cases

| Component | Description |
|---|---|
| `LoginUsecase` | Verifies credentials via `LoginGateway`; on success, emits `LoginSucceededEvent` |
| `RegisterUserUsecase` | Creates the account, sends the welcome email |
| `ChangePasswordUsecase` | Updates the password; used for both user-initiated and forced changes |

## Event Listeners

| Listener | Event | Action |
|---|---|---|
| `NotifyLogin` | `LoginSucceededEvent` | Sends a login notification email if configured |
| `NotifyCreate` | User creation event | Sends the registration welcome email |
| `NotifyRecover` | Password recovery event | Sends the recovery email with a reset link |

## Infrastructure

### Driven (Outbound Adapters)

- **`LoginAdapter`** — Credential verification against the user store (password hashing/comparison).
- **`RegisterUserAdapter`** — User record creation with hashed credentials.
- **`ChangePasswordAdapter`** — Password hash update.

## Key Invariants

- Passwords are **never stored or transmitted in plain text** — only hashed values are persisted.
- The `LoginGateway` returns an opaque failure result (not an exception) to avoid timing attacks.
- `PublicLoginAuthResponse` is only constructed when the full token issuance is complete — partial flows do not return this object.

## Interactions with Other Contexts

```
User ──credential verified by──> Authentication  (LoginUsecase called from the password grant flow)
     ──account created for──>    UserInvitation  (RegisterUserUsecase triggered on invitation acceptance)
     ──emits events consumed by──> (notification listeners within this context)
     ──password changed by──>    Profile          (ChangePasswordUsecase via PasswordGateway)
```
