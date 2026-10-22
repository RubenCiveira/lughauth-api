# Session Bounded Context

Manages OAuth session lifecycle, temporary authorization codes, and the signing keys used to verify session integrity.

## Responsibility

This context owns **session state** across the authorization flow. It tracks which user authenticated, when, with which client, and whether MFA was completed. It also manages short-lived authorization codes (the `code` in the authorization code flow) and the rotating signing keys used to bind codes to sessions securely.

## Domain Concepts

| Concept | Type | Description |
|---|---|---|
| `SessionInfo` | ValueObject | Core session data: `csid`, `withMfa`, `issuer`, `userId`, `clientId`, plus what tokens may publish: `publicSid` (random `sid`, never the cookie), `authTime`, `amr`, `acr`, `tenantId` |
| `TemporalAuthCode` | ValueObject | Short-lived authorization code issued after a successful authorization; consumed once |

## Ports (Gateways)

| Gateway | Responsibility |
|---|---|
| `SessionStoreGateway` | Store, retrieve, and delete session records |
| `TemporalKeysGateway` | Manage rotating signing keys used for session/code verification |

## Application Services

- **`OAuthCleanupScheduler`** — Purges expired sessions, authorization codes, revocations, WebAuthn challenges, anti-replay JTIs, PAR requests and device codes. It needs no cron: `OAuthPlugin::bindServices` registers a `register_shutdown_function` that calls `runIfDue()` after every response (once `fastcgi_finish_request` has flushed it), throttled through the cache to one run per 60 s. The stores no longer purge in their constructors, so the request path does not pay for it. Returns a `CleanupResult` summary; failed tasks are logged and retried on the next run. Without a database (not installed or unreachable) the shutdown hook does nothing, and a table not migrated yet is skipped (SQLSTATE `42S02`/`42P01`), so neither produces errors.

## Infrastructure

### Driven (Outbound Adapters)

- **`SessionStoreSqlAdapter`** — SQL-backed session store.
- **`TemporalKeysSqlAdapter`** — SQL-backed storage for rotating temporal signing keys.

## Key Invariants

- Authorization codes are **single-use**: consumed on the first token exchange.
- Sessions carry an explicit `withMfa` flag; token issuance may require MFA-verified sessions for sensitive clients.
- Signing keys rotate on a schedule; expired keys are removed by TokenSecurity's `KeyRotationService` to prevent unbounded growth.

## Interactions with Other Contexts

```
Session ──consumed by──> Authentication  (session creation after login, lookup on token exchange)
        ──consumed by──> Profile         (active session enumeration)
        ──consumed by──> TokenSecurity   (key material for token signing)
```
