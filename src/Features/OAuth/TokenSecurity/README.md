# TokenSecurity Bounded Context

Manages asymmetric key pairs for JWT signing, token storage, and token revocation.

## Responsibility

This context is the **cryptographic backbone** of the authorization server. It owns key pair generation and rotation, JWT signing and verification, the JWKS (JSON Web Key Set) public endpoint, and the token revocation blacklist. All other contexts that issue or validate tokens depend on this context.

## Domain Concepts

| Concept | Type | Description |
|---|---|---|
| `KeyPair` | Entity | An RSA key pair (private + public) with its `kid`, validity period, and active flag |
| `KeyConfig` | ValueObject | Rotation policy: `ttl = 7 days`, `futures = 3` (future keys kept published), `retention = 30 days` (how long retired keys stay published) |
| `TokenType` | Enum | Purpose of a JWT, written as its `typ` header: `at+jwt`, `JWT` (ID), `refresh+jwt`, `logout+jwt`, `presession+jwt` |
| `VerificationPolicy` | ValueObject | Purposes a consumer accepts and whether `nbf`/`exp` are enforced |

## Ports (Gateways)

| Gateway | Responsibility |
|---|---|
| `TokenSigner` | Sign a JWT for a purpose with the tenant's active key (`kid` + `typ` header); verify signature, purpose and time window |
| `TokenStoreGateway` | Persist each tenant's signing key pairs with their validity window |
| `KeyPairGenerator` | Generate new RSA key pairs (only used by key rotation) |
| `TokenRevocationGateway` | Add a token to the revocation blacklist; check whether a token is revoked |

## Infrastructure

### Driven (Outbound Adapters)

- **`JoseTokenSigner`** — Implements `TokenSigner` using a JOSE library (RS256). Never creates keys.
- **`TokenStoreSqlAdapter`** — SQL persistence of signing keys (`_oauth_keys_storer`).
- **`OpenSslKeyPairGenerator`** — RSA-4096 key pairs via the openssl extension.
- **`TokenRevocationSqlAdapter`** — SQL-backed revocation blacklist.

### Driver (Inbound Adapters)

- **`Rest/JwksController`** — `GET /oauth/openid/{tenant}/jwks` — exposes the tenant's public JWKS, cached per tenant.

### Application

- **`KeyRotationService`** — Provisions and rotates every tenant's keys from the scheduler (`rotateAll`, every 15 minutes) and when a tenant is created (`ProvisionTenantKeys` listener).

## Key Rotation Policy

- Keys are **per tenant** and chained without overlap; each signs for **7 days** (`KeyConfig::ttl`).
- **3 future keys** (`KeyConfig::futures`) are published before they start signing, so relying parties can cache the JWKS across rotations.
- Retired keys stay published for **30 days** (`KeyConfig::retention`), then `KeyRotationService` purges them.
- Keys are never generated while serving a request: without an active key, signing fails with `NoActiveKeyException`.

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
