# UserInvitation Bounded Context

Manages invite-based user onboarding: creating, sending, accepting, resending, and cancelling invitations.

## Responsibility

This context owns the **invitation lifecycle** from issuance to acceptance. An administrator invites a user by email; the user receives a single-use link that, when clicked, opens a registration form pre-filled with the invitation context. Upon submission the invitation is consumed and the user account is created.

## Domain Concepts

| Concept | Type | Description |
|---|---|---|
| `UserInvitationSession` | ValueObject | Temporary session created after a valid invitation link is opened: `sessionId` + `expiration` |

## Ports (Gateways)

| Gateway | Responsibility |
|---|---|
| `UserInvitationCodeGateway` | Generate and hash the one-time invitation token |
| `UserInvitationMailGateway` | Send the invitation email with the acceptance link |
| `UserInvitationQueryGateway` | Find invitations by token hash or user/tenant |
| `UserInvitationSessionGateway` | Create and resolve the temporary acceptance session |

## Application Use Cases

| Use Case | Description |
|---|---|
| `InvitationCreateUsecase` | Creates an invitation record, generates a token, and sends the email. Returns `InvitationCreateResult` |
| `InvitationAcceptUsecase` | Validates the token, opens an acceptance session, and triggers account creation. Accepts `InvitationAcceptParams`, returns `InvitationAcceptResult` |
| `InvitationResendUsecase` | Regenerates the token and resends the invitation email |
| `InvitationCancelUsecase` | Marks an invitation as cancelled, preventing future acceptance |

## Infrastructure

### Driven (Outbound Adapters)

- **`UserInvitationCodeAdapter`** — Token generation and hashing.
- **`UserInvitationMailAdapter`** — Email delivery integration.
- **`UserInvitationQueryAdapter`** — Reads invitation records from the data store.
- **`UserInvitationSessionAdapter`** — Temporary session storage for the acceptance flow.

### Driver (Inbound Adapters)

- **`Html/InvitationAcceptHtml`** — Renders the registration form for the invited user when they click the link.
- **`Rest/InvitationCreateController`** — `POST /invitations` endpoint for administrators.
- **`Rest/InvitationResendController`** — `POST /invitations/{id}/resend`.
- **`Rest/InvitationCancelController`** — `DELETE /invitations/{id}`.

## Key Invariants

- Invitation tokens are **single-use** and **hashed at rest** — the raw token is only present in the email link.
- An accepted invitation cannot be resent or cancelled.
- The acceptance session has a short TTL — if the user abandons the form, they must request a new link (resend).
- Invitations are **tenant-scoped**: a link issued for tenant A cannot be used to create an account in tenant B.

## Interactions with Other Contexts

```
UserInvitation ──creates user via──> User    (account creation on acceptance)
               ──sends email via──> Common  (OAuthMailAdapter)
```
