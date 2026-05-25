# Client Bounded Context

Manages OAuth 2.0 client registration, configuration, and M2M (machine-to-machine) API key credentials.

## Responsibility

This context owns the **client identity model**: what grant types a client supports, which scopes it can request in M2M flows, token TTL configuration, request object signing requirements, and dynamic client registration (DCR). It is the authoritative source for client metadata consumed by the Authentication context during token issuance.

## Domain Concepts

| Concept | Type | Description |
|---|---|---|
| `ClientData` | ValueObject | Static client configuration (id, grants, secretLogin, allowedScopesM2m, m2mTokenTtlSeconds, requestObjectSigningAlg, jwksUri, jwksJson) |
| `ApiKeyData` | ValueObject | M2M credentials (client_id + secret) used for `client_credentials` grant |
| `DynamicClientData` | ValueObject | Mutable client attributes managed via DCR |
| `DynamicClientRequest` | ValueObject | Validated input for DCR create/update operations |

## Ports (Gateways)

| Gateway | Responsibility |
|---|---|
| `ClientStoreGateway` | Look up a client by id (read-only, tenant-scoped) |
| `DynamicClientGateway` | CRUD operations for dynamically registered clients |
| `ApiKeyStoreGateway` | Store and verify M2M API key credentials |

## Application Use Cases

| Use Case | Description |
|---|---|
| `RegisterClientUsecase` | Creates a new dynamic client; returns `RegisterClientResult` with client credentials |
| `ReadDynamicClientUsecase` | Retrieves the current configuration of a dynamic client |
| `UpdateDynamicClientUsecase` | Updates a dynamic client's metadata |
| `DeleteDynamicClientUsecase` | Removes a dynamic client registration |

## Infrastructure

### Driven (Outbound Adapters)

- **`ClientStoreAdapter`** — Reads static client configuration from the tenant data store.
- **`DynamicClientAdapter`** — Persists dynamic client data (implements DCR storage).
- **`ApiKeyStoreAdapter`** — Manages M2M API key storage and verification.

### Driver (Inbound Adapters)

- **`Rest/DynamicClientController`** — DCR endpoints (`POST /register`, `GET /register/{id}`, `PUT /register/{id}`, `DELETE /register/{id}`).
- **`Rest/ApiKeyController`** — API key management endpoints for M2M clients.

## Interactions with Other Contexts

```
Client ──consumed by──> Authentication  (client validation on every token request)
       ──consumed by──> Par             (client validation for pushed auth requests)
       ──consumed by──> Consent         (client scope configuration)
```
