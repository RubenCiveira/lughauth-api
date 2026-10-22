# TokenSecurity Bounded Context

Manages asymmetric key pairs for JWT signing, token storage, and token revocation.

## Responsibility

This context is the **cryptographic backbone** of the authorization server. It owns key pair generation and rotation, JWT signing and verification, the JWKS (JSON Web Key Set) public endpoint, and the token revocation blacklist. All other contexts that issue or validate tokens depend on this context.

## Domain Concepts

| Concept | Type | Description |
|---|---|---|
| `KeyPair` | Entity | An RSA key pair (private + public) with its `kid`, validity period, and active flag |
| `KeyConfig` | ValueObject | Rotation policy: `ttl = 7 days`, `futures = 3` (future keys kept published), `retention = 30 days` (how long retired keys stay published) |
| `TokenType` | Enum | Purpose of a JWT, written as its `typ` header: `at+jwt`, `JWT` (ID), `refresh+jwt`, `logout+jwt`, `presession+jwt`, `authz-request+jwt` (reference to a PAR/JAR request between login steps) |
| `ReplayPurpose` | Enum | Use of a one-time JWT whose `jti` is recorded: `client_assertion`, `request_object`, `dpop`, `jwt_bearer`, `authorization_request` (a `request_ref` that completed its authorization) |
| `VerificationPolicy` | ValueObject | Purposes a consumer accepts and whether `nbf`/`exp` are enforced |

## Ports (Gateways)

| Gateway | Responsibility |
|---|---|
| `TokenSigner` | Sign a JWT for a purpose with the tenant's active key (`kid` + `typ` header); verify signature, purpose and time window |
| `TokenStoreGateway` | Persist each tenant's signing key pairs with their validity window |
| `KeyPairGenerator` | Generate new RSA key pairs (only used by key rotation) |
| `SigningKeyProvisioner` | Fallback used by the signer when the scheduled rotation fell behind |
| `TokenRevocationGateway` | Add a token to the revocation blacklist; check whether a token is revoked |
| `ReplayJtiGateway` | Record the `jti` of a one-time JWT per tenant, client and purpose; reports a replay when it was already recorded; `release` reopens a `jti` whose guarded operation failed (`_oauth_replay_jti`, purged by `OAuthCleanupScheduler`) |

## Infrastructure

### Driven (Outbound Adapters)

- **`JoseTokenSigner`** — Implements `TokenSigner` using a JOSE library (RS256). Falls back to `SigningKeyProvisioner` when the tenant has no active key or too few future ones.
- **`TokenStoreSqlAdapter`** — SQL persistence of signing keys (`_oauth_keys_storer`).
- **`OpenSslKeyPairGenerator`** — RSA-4096 key pairs via the openssl extension.
- **`TokenRevocationSqlAdapter`** — SQL-backed revocation blacklist.
- **`ReplayJtiSqlAdapter`** — SQL anti-replay store; the primary key `(tenant_id, client_id, purpose, jti)` makes `INSERT IGNORE` the atomic check.

### Driver (Inbound Adapters)

- **`Rest/JwksController`** — `GET /oauth/openid/{tenant}/jwks` — exposes the tenant's public JWKS, cached per tenant.

### Application

- **`KeyRotationService`** — Provisions and rotates every tenant's keys from the scheduler (`rotateAll`, every 15 minutes) and when a tenant is created (`ProvisionTenantKeys` listener). It also implements `SigningKeyProvisioner`, the signer's fallback.

## Key Rotation Policy

- Keys are **per tenant** and chained without overlap; each signs for **7 days** (`KeyConfig::ttl`).
- **3 future keys** (`KeyConfig::futures`) are published before they start signing, so relying parties can cache the JWKS across rotations.
- Retired keys stay published for **30 days** (`KeyConfig::retention`), then `KeyRotationService` purges them.
- The scheduler is the main path, but it is not required. As a fallback, signing without an active key creates that one key on the spot (one RSA generation) and leaves the future keys for after the response; with an active key but fewer than 3 future ones, the chain is completed after the response. A per-tenant lock prevents duplicates between the scheduler and concurrent requests. Only if no key can be created does signing fail with `NoActiveKeyException`.

## Key Invariants

- Only the **active key** signs new tokens; a token verifies only against the published key named by its `kid`.
- Each consumer states the token purposes it accepts; a valid token of another purpose is rejected.
- A revoked token is rejected even if the signature is valid.
- The `kid` header in every JWT allows verifiers to select the correct public key from the JWKS.
- Private keys are **never exposed** outside this context.

## Interactions with Other Contexts

```
TokenSecurity ──signs tokens for──>   Authentication  (all token issuance)
              ──verifies tokens for──> Authentication  (introspection, revocation check)
              ──publishes keys via──>  Oidc            (JWKS URI in the discovery document)
              ──revokes tokens for──>  Profile         (session revocation)
              ──revokes tokens for──>  Authentication  (RevokeTokenUsecase)
```
