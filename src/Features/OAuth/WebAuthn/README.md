# WebAuthn Bounded Context

Implements FIDO2 / WebAuthn (passkey) registration and authentication flows per the W3C Web Authentication specification.

## Responsibility

This context owns the **passkey lifecycle**: challenge generation, credential registration, and credential-based authentication. It integrates as an authentication step within the main authorization flow, providing a phishing-resistant second factor (or primary factor for passwordless setups).

## Domain Concepts

| Concept | Type | Description |
|---|---|---|
| `WebAuthnChallenge` | Entity | Tracks a pending challenge: `challengeId`, `tenantId`, `userUid`, `challenge`, `type` (register/authenticate), `optionsJson`, `createdAt`, `expiresAt`, `verified`, `verifiedAt`, `verifiedUserUid`, `verifiedUsername` |
| `WebAuthnCredential` | Entity | Stored passkey credential bound to a user; includes public key material and usage metadata |

## Ports (Gateways)

| Gateway | Responsibility |
|---|---|
| `WebAuthnChallengeGateway` | Persist, find, and mark challenges as verified |
| `WebAuthnCredentialGateway` | CRUD operations for stored passkey credentials |

## Application Use Cases

| Use Case | Description |
|---|---|
| `BeginRegistrationUsecase` | Generates registration challenge options (`PublicKeyCredentialCreationOptions`) |
| `FinishRegistrationUsecase` | Verifies the authenticator attestation and persists the new credential |
| `BeginAuthenticationUsecase` | Generates authentication challenge options (`PublicKeyCredentialRequestOptions`) |
| `FinishAuthenticationUsecase` | Verifies the authenticator assertion and marks the challenge as verified |

## Infrastructure

### Driven (Outbound Adapters)

- **`WebAuthnChallengeSqlAdapter`** — Persists challenges to SQL storage with expiration.
- **`WebAuthnCredentialSqlAdapter`** — Stores and retrieves passkey credentials.

### Driver (Inbound Adapters)

- **`Rest/WebAuthnController`** — REST endpoints for all four flow steps (begin/finish registration, begin/finish authentication).

### Services

- **`WebAuthnVerifier`** — Encapsulates the FIDO2 library integration for attestation and assertion verification.

## Key Invariants

- Challenges expire; a new challenge must be requested if the previous one timed out.
- A credential can only be bound to one user per tenant.
- The `verifiedUserUid` on a challenge is set only after successful assertion — it must match the claimed identity before the Authentication context accepts the step.

## Interactions with Other Contexts

```
WebAuthn ──step in──> Authentication  (FinishAuthenticationUsecase resolves the WebAuthn step)
         ──displayed in──> Profile    (credential management in the profile dashboard)
```
