# TokenSecurity Bounded Context

Manages asymmetric key pairs for JWT signing, token storage, and token revocation.

## Responsibility

This context is the **cryptographic backbone** of the authorization server. It owns key pair generation and rotation, JWT signing and verification, the JWKS (JSON Web Key Set) public endpoint, and the token revocation blacklist. All other contexts that issue or validate tokens depend on this context.

## Domain Concepts

| Concept | Type | Description |
|---|---|---|
| `KeyPair` | Entity | An RSA key pair (private + public) with its `kid`, validity period, and active flag |
| `KeyConfig` | ValueObject | Rotation policy: `ttl = 7 days`, `futures = 3` (number of pre-generated future keys to keep ready) |

## Ports (Gateways)

| Gateway | Responsibility |
|---|---|
| `TokenSigner` | Sign a JWT payload with the active private key; verify a JWT signature against known public keys |
| `TokenStoreGateway` | Persist issued tokens (jti, expiration, subject) for introspection and cleanup |
| `TokenRevocationGateway` | Add a token to the revocation blacklist; check whether a token is revoked |

## Infrastructure

### Driven (Outbound Adapters)

- **`JoseTokenSigner`** — Implements `TokenSigner` using a JOSE library (RS256/RS384/RS512).
- **`TokenStoreSqlAdapter`** — SQL persistence for issued token records.
- **`TokenRevocationSqlAdapter`** — SQL-backed revocation blacklist.

### Driver (Inbound Adapters)

- **`Rest/JwksController`** — `GET /.well-known/jwks.json` — exposes the public JWKS document for clients to verify tokens.

## Key Rotation Policy

- Keys have a **7-day TTL** (`KeyConfig::ttl`).
- **3 future keys** are pre-generated (`KeyConfig::futures`) so that tokens signed with a new key are verifiable before the key becomes the active signing key.
- The `Session` context's `OAuthCleanupScheduler` drives periodic cleanup of expired keys.

## Key Invariants

- Only the **active key** signs new tokens; all non-expired keys can **verify** incoming tokens.
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
