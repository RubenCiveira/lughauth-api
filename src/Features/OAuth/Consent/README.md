# Consent Bounded Context

Manages user consent for OAuth scopes, GDPR data processing purposes, and Terms of Use acceptance.

## Responsibility

This context owns **all consent types** that gate authorization:
- **Scope consent** — the user must approve the scopes a client is requesting.
- **GDPR consent** — the user must consent to specific data processing purposes.
- **Terms of Use** — the user must accept the platform's terms before proceeding.

Consent is orchestrated via a queue: pending consent items are enqueued and dequeued one at a time, driving a multi-step consent flow within the authorization pipeline.

## Domain Concepts

| Concept | Type | Description |
|---|---|---|
| `PendingConsentRequest` | Entity | A queued consent item: `userId`, `tenant`, `clientId`, `redirectUri`, `scope`, `createdAt` |
| `ScopePermission` | ValueObject | Single scope with metadata: `scope`, `required`, `label`, `description` |
| `TermsOfUseAcceptance` | ValueObject | Record of a user accepting a specific ToU version |
| `GdprConsentPurposeItem` | ValueObject | A single GDPR processing purpose awaiting consent |

## Ports (Gateways)

| Gateway | Responsibility |
|---|---|
| `ConsentQueueGateway` | Enqueue, dequeue, list, and remove pending consent requests |
| `ScopesConsentGateway` | Track which scopes a user has already consented to per client |
| `GdprConsentGateway` | Persist and query GDPR purpose consents |
| `TermsOfUseConsentGateway` | Record and verify Terms of Use acceptance |

## Application Use Cases

| Use Case | Description |
|---|---|
| `ConsentOrchestrationService` | Enqueues all pending consents (`EnqueueConsentParams`) and returns the next one to resolve (`NextConsentResult`) |
| `ScopesConsentUsecase` | Records the user's scope consent decision for a client |
| `GdprConsentUsecase` | Records GDPR purpose consent |
| `TermsOfUseConsentUsecase` | Records Terms of Use acceptance |

## Infrastructure

### Driven (Outbound Adapters)

- **`ScopesConsentAdapter`** — Scope consent persistence.
- **`GdprConsentAdapter`** — GDPR consent persistence.
- **`TermsOfUseConsentAdapter`** — ToU acceptance persistence.

### Driver (Inbound Adapters)

- **`Rest/MeConsentsListController`** — Lists the user's current consent state.
- **`Rest/MeConsentsGrantController`** — Records a new consent grant.
- **`Rest/MeConsentsHistoryController`** — Returns consent history for audit.

## Key Invariants

- Consent is **tenant + client + user scoped**: the same user can have different consent states per client.
- The queue is processed **one item at a time** — `NextConsentResult` drives the UI to the correct consent form.
- Scope consent is **additive**: previously approved scopes are not re-requested; only new/unapproved scopes are enqueued.
- GDPR consents are **versioned by purpose** — a change in purpose description triggers re-consent.

## Interactions with Other Contexts

```
Consent ──step in──> Authentication    (ConsentOrchestrationService called when ConsentRequiredException is raised)
        ──read by──> Profile            (consent history panel)
```
