# Client Bounded Context

Manages OAuth 2.0 client registration, configuration, and M2M (machine-to-machine) API key credentials.

## Responsibility

This context owns the **client identity model**: what grant types a client supports, which scopes it can request in M2M flows, token TTL configuration, request object signing requirements, and dynamic client registration (DCR). It is the authoritative source for client metadata consumed by the Authentication context during token issuance.

## Domain Concepts

| Concept | Type | Description |
|---|---|---|
| `ClientData` | ValueObject | Client configuration snapshot: id, owner tenant (null = global), grants (never `password` by default), response types, PKCE/PAR requirements, token endpoint auth method, audiences, M2M scopes/TTL, JAR keys, logout flags |
| `DcrRegistrationRules` | ValueObject | Tenant DCR rules: policy, allowed grant types and auth methods, password permission |
| `DcrInitialAccess` | ValueObject | A verified initial access token being consumed, with its optional grant types |
| `DcrMetadataPolicy` | Domain service | Validates DCR metadata (redirect URIs, grants, response types, auth method) against the rules |
| `ApiKeyData` | ValueObject | M2M credentials (client_id + secret) used for `client_credentials` grant |
| `DynamicClientData` | ValueObject | Mutable client attributes managed via DCR |
| `DynamicClientRequest` | ValueObject | Validated input for DCR create/update operations |

## Ports (Gateways)

| Gateway | Responsibility |
|---|---|
| `ClientStoreGateway` | Look up a client by id from a tenant: its own clients or global ones, never another tenant's |
| `DynamicClientGateway` | CRUD operations for dynamically registered clients, owned by the tenant, and the tenant DCR rules |
| `DcrInitialAccessTokenGateway` | Issue initial access tokens and consume them atomically |
| `ApiKeyStoreGateway` | Store and verify M2M API key credentials |

## Application Use Cases

| Use Case | Description |
|---|---|
| `RegisterClientUsecase` | Creates a new dynamic client; returns `RegisterClientResult` with client credentials |
| `ReadDynamicClientUsecase` | Retrieves the current configuration of a dynamic client |
| `UpdateDynamicClientUsecase` | Updates a dynamic client's metadata |
| `DeleteDynamicClientUsecase` | Removes a dynamic client registration |
| `IssueInitialAccessTokenUsecase` | Issues a DCR initial access token and returns it once |

## Infrastructure

### Driven (Outbound Adapters)

- **`ClientStoreAdapter`** — Reads static client configuration from the tenant data store.
- **`DynamicClientAdapter`** — Persists dynamic client data (implements DCR storage).
- **`DcrInitialAccessTokenSqlAdapter`** — Initial access tokens over the `dcr-initial-access-token` entity.
- **`TrustedClientFinder`** — Resolves a TrustedClient by code as seen from a tenant.
- **`ApiKeyStoreAdapter`** — Manages M2M API key storage and verification.

### Driver (Inbound Adapters)

- **`Rest/DynamicClientController`** — DCR endpoints (`POST /register`, `GET /register/{id}`, `PUT /register/{id}`, `DELETE /register/{id}`).
- **`Rest/ApiKeyController`** — API key management endpoints for M2M clients.
- **`Rest/InitialAccessTokenIssueController`** — `POST /api/access/dcr-initial-access-tokens/~/issue` (tenant admin).

## Interactions with Other Contexts

```
Client ──consumed by──> Authentication  (client validation on every token request)
       ──consumed by──> Par             (client validation for pushed auth requests)
       ──consumed by──> Consent         (client scope configuration)
```
