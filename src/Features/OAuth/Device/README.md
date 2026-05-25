# Device Bounded Context

Implements the OAuth 2.0 Device Authorization Grant (RFC 8628) for input-constrained clients such as smart TVs, CLI tools, and IoT devices.

## Responsibility

This context owns the **device authorization flow**: generating device/user code pairs, exposing the user verification URI, tracking polling state, and binding an authorization to the device code once the user approves it on a separate browser session.

## Domain Concepts

| Concept | Type | Description |
|---|---|---|
| `DeviceAuthorization` | Entity | Core aggregate: `deviceCode`, `userCode`, `tenant`, `clientId`, `scope`, `audiences`, `interval`, `requestedAt`, `expiresAt`, `status`, `auth`, `lastPollAt`. Exposes `isExpired()`, `expiresIn()` |
| `DeviceAuthorizationStatus` | Enum | `PENDING` → `APPROVED` / `DENIED` |
| `DeviceAuthorizationGrantType` | ValueObject | Identifies the `urn:ietf:params:oauth:grant-type:device_code` grant type constant |

## Ports (Gateways)

| Gateway | Responsibility |
|---|---|
| `DeviceAuthorizationGateway` | Store a new authorization, find by device code or user code, update status |

## Application Services

- **`DeviceAuthorizationService`** — Orchestrates the device flow: creates the authorization record, resolves polling results, and marks the authorization as approved/denied.

## Infrastructure

### Driven (Outbound Adapters)

- **`DeviceAuthorizationSqlAdapter`** — SQL persistence for device authorizations.

### Driver (Inbound Adapters)

- **`Rest/DeviceAuthorizationController`** — `POST /device/authorization` endpoint.
- **`Html/DeviceVerificationHtml`** — User-facing verification page where the user enters the user code and approves the request.

## Key Invariants

- **Polling interval** is enforced: clients polling more frequently than `interval` seconds receive a `slow_down` error.
- **Expiration** is checked on every poll: expired authorizations return `expired_token`.
- The `userCode` is short and human-typeable (typically 8 uppercase alphanumeric characters).
- Once `APPROVED` or `DENIED`, the status is terminal — no further state changes are accepted.

## Interactions with Other Contexts

```
Device ──grant resolved by──> Authentication  (TokenGranter/ResolverForDevice polls device status)
       ──user approves via──> Authentication  (device verification page uses the same auth flow)
```
