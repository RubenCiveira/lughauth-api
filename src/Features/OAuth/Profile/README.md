# Profile Bounded Context

Manages the authenticated user's OIDC profile, active sessions, password, and MFA method enrollment.

## Responsibility

This context owns the **user self-service surface**: reading and updating OIDC standard claims (given name, birthdate, locale…), reviewing and revoking active sessions, changing passwords, and managing MFA enrollments. It exposes both a REST API (`/me`) and a server-rendered HTML dashboard.

## Domain Concepts

| Concept | Type | Description |
|---|---|---|
| `OidcProfile` | Entity | Full OIDC profile: `uid`, `userUid`, `givenName`, `familyName`, `middleName`, `nickname`, `preferredUsername`, `pictureUrl`, `websiteUrl`, `gender`, `birthdate`, `zoneinfo`, `locale`, `phoneNumber`, `phoneNumberVerified`, `addressJson`, `updatedAt`, `version` |
| `OidcProfileData` | ValueObject | Mutable subset of `OidcProfile` used for update operations |
| `ActiveSession` | ValueObject | A live session: session id, client, creation time, last activity, device metadata |
| `MfaSetup` | ValueObject | Current MFA enrollment state for the user |

## Ports (Gateways)

| Gateway | Responsibility |
|---|---|
| `ProfileGateway` | Read and update the user's OIDC profile |
| `SessionsGateway` | List active sessions; revoke individual sessions |
| `PasswordGateway` | Change the user's password (current + new) |
| `MfaGateway` | Read and update MFA method enrollment |

## Infrastructure

### Driven (Outbound Adapters)

- **`ProfileAdapter`** — Profile read/write from the data store.
- **`SessionsAdapter`** — Active session enumeration and revocation.
- **`PasswordAdapter`** — Password change with current-password verification.
- **`MfaAdapter`** — MFA enrollment data access.

### Driver (Inbound Adapters)

**REST API**
- **`Rest/ProfileMeController`** — `GET /me`, `PATCH /me` — read and update OIDC profile.
- **`Rest/UserSessionController`** — `GET /me/sessions`, `DELETE /me/sessions/{id}` — session management.

**HTML Dashboard (`Html/`)**
- **`ProfileHtml`** — Main dashboard renderer.
- `Panels/ProfileViewPanel` — Read-only profile display.
- `Panels/ProfileEditPanel` — Profile edit form.
- `Panels/ChangePasswordPanel` — Password change form.
- `Panels/MfaPanel` — MFA enrollment and management.
- `Panels/SessionsPanel` — Active session list with revocation actions.

## Key Invariants

- Profile updates use **optimistic concurrency via `version`**: a stale update (version mismatch) is rejected to prevent lost-update race conditions.
- Password change always requires the **current password** — no privileged bypass at this layer.
- Session revocation is **immediate**: the revoked session token is blacklisted in `TokenSecurity`.

## Interactions with Other Contexts

```
Profile ──reads──>   Session       (active sessions)
        ──reads──>   Mfa           (enrolled methods)
        ──writes to──> TokenSecurity (session revocation)
        ──exposed via──> Authentication (OIDC /userinfo response uses profile claims)
```
