# Gdpr Bounded Context

Implements GDPR data subject rights: right to data portability (export) and right to erasure (delete).

## Responsibility

This context owns the operational surface for **GDPR compliance**: aggregating user data from all relevant sources for export, and orchestrating cascading deletion across the platform when a user exercises their right to be forgotten.

## Domain Concepts

| Concept | Type | Description |
|---|---|---|
| `GdprSubjectData` | ValueObject | A slice of user data from a single source: `context` (source identifier) + `data` (structured payload) |
| `GdprDataPackage` | ValueObject | The complete export package: a collection of `GdprSubjectData` entries, one per data context |

## Ports (Gateways)

| Gateway | Responsibility |
|---|---|
| `GdprDataExportGateway` | Collects and returns all personal data for a given user |
| `GdprDataDeleteGateway` | Deletes all personal data for a given user across data contexts |

## Application Use Cases

| Use Case | Params | Description |
|---|---|---|
| `ExportUserDataUsecase` | `ExportUserDataParams` | Aggregates `GdprSubjectData` from all contexts and returns an `ExportUserDataResult` containing the full `GdprDataPackage` |
| `DeleteUserDataUsecase` | `DeleteUserDataParams` | Triggers cascading deletion of all personal data; irreversible |

## Infrastructure

### Driven (Outbound Adapters)

- **`GdprDataAdapter`** — Implements both export and delete gateways by coordinating across the underlying data stores (user, sessions, consents, profile, invitations, etc.).

### Driver (Inbound Adapters)

- **`Rest/GdprExportController`** — `GET /gdpr/export` — returns the data package as a downloadable JSON response.
- **`Rest/GdprDeleteController`** — `DELETE /gdpr/user` — initiates the erasure process.

## Key Invariants

- Data deletion is **irreversible** — no soft-delete; the use case must only be callable by the authenticated subject or a privileged administrator.
- The export covers **all personal data contexts**: profile, sessions, consents, MFA, credentials, invitations.
- Both operations are **tenant-scoped**: data from other tenants is not affected.

## Interactions with Other Contexts

```
Gdpr ──reads/deletes from──> Profile        (profile data)
     ──reads/deletes from──> Session        (active sessions)
     ──reads/deletes from──> Consent        (consent records)
     ──reads/deletes from──> WebAuthn       (passkey credentials)
     ──reads/deletes from──> UserInvitation (pending invitations)
```
