# OIDC Module — Token Endpoint & Grant Types

Implements **RFC 6749 §§4–6** (grant types), **OpenID Connect Core 1.0 §3.1.3** (ID Token), and **RFC 7519** (JWT structure).

## Endpoint

```
POST /oauth/openid/{tenant}/token
Content-Type: application/x-www-form-urlencoded
```

## Successful Token Response

**RFC 6749 §5.1** — HTTP 200, `Content-Type: application/json`.

Required response headers:
```
Cache-Control: no-store
Pragma: no-cache
```

| Field | Level | Notes |
|-------|-------|-------|
| `access_token` | REQUIRED | RS256-signed JWT |
| `token_type` | REQUIRED | `"Bearer"` |
| `expires_in` | RECOMMENDED | Lifetime in seconds |
| `refresh_token` | OPTIONAL | Absent for `client_credentials` |
| `id_token` | REQUIRED (OIDC) | Absent for `client_credentials` |
| `scope` | REQUIRED if different from requested | Granted scope string |

```json
{
  "token_type": "Bearer",
  "expires_in": 600,
  "access_token": "<JWT>",
  "id_token":     "<JWT>",
  "refresh_token": "<JWT>"
}
```

---

## Access Token Claims

All tokens are RS256-signed JWTs. Standard JWT registered claims plus domain-specific claims:

| Claim | RFC / Spec | Notes |
|-------|-----------|-------|
| `iss` | RFC 7519 §4.1.1 | `{baseUrl}/oauth/openid/{tenant}` |
| `sub` | RFC 7519 §4.1.2 | Subject (user id or client id for M2M) |
| `aud` | RFC 7519 §4.1.3 | Client id string, or array when extra audiences requested |
| `exp` | RFC 7519 §4.1.4 | Expiry (epoch seconds) |
| `iat` | RFC 7519 §4.1.6 | Issued-at (epoch seconds) |
| `azp` | OIDC Core §2 | Authorized party; included when `aud` has multiple values |
| `scope` | RFC 6749 §3.3 | Space-separated granted scopes |
| `tenant` | Custom | Tenant identifier |
| `roles` | Custom | User roles array |
| `groups` | Custom | User groups array |
| `name` | OIDC §5.1 | User display name; present when `profile` scope granted |
| `email` | OIDC §5.1 | User email; present when `email` scope granted |

For `client_credentials` only:

| Claim | Value |
|-------|-------|
| `client_id` | The authenticating client id |
| `token_use` | `"client_credentials"` |

---

## ID Token Structure

**OIDC Core 1.0 §2**

The `id_token` is a JWT containing the user's identity. Clients MUST validate all of the following before trusting it (OIDC Core §3.1.3.7):

1. If encrypted: decrypt using registered keys/algorithms.
2. `iss` MUST exactly match the Provider's Issuer Identifier (`{baseUrl}/oauth/openid/{tenant}`).
3. `aud` MUST contain the client's `client_id`.
4. `azp`, if present, MUST match `client_id`.
5. Validate RS256 signature using the tenant's JWKS (`GET /jwks`).
6. Current time MUST be before `exp`.
7. `iat` MAY be used to reject tokens too far in the past.
8. If `nonce` was in the auth request, `nonce` claim MUST be present and match.
9. If `max_age` was requested, verify `auth_time` is recent enough.

| Claim | Level | Notes |
|-------|-------|-------|
| `iss` | REQUIRED | Issuer identifier |
| `sub` | REQUIRED | Subject (≤ 255 ASCII chars) |
| `aud` | REQUIRED | Must contain the RP's `client_id` |
| `exp` | REQUIRED | Expiry (epoch seconds) |
| `iat` | REQUIRED | Issued-at (epoch seconds) |
| `nonce` | REQUIRED if in request | Exact value from authorization request |
| `auth_time` | REQUIRED if `max_age` requested | Time of authentication (epoch seconds) |
| `azp` | RECOMMENDED if multiple audiences | Authorized party |
| `name` | OPTIONAL | Present when `profile` scope granted |
| `email` | OPTIONAL | Present when `email` scope granted |

---

## Standard Scope → Claims Mapping

**OIDC Core 1.0 §5.4**

| Scope | Claims included |
|-------|----------------|
| `openid` | `sub` (always in ID Token) |
| `profile` | `name`, `given_name`, `family_name`, `nickname`, `preferred_username`, `picture`, `website`, `gender`, `birthdate`, `zoneinfo`, `locale`, `updated_at` |
| `email` | `email`, `email_verified` |
| `address` | `address` (JSON object) |
| `phone` | `phone_number`, `phone_number_verified` |

---

## Grant Types

### `authorization_code` (RFC 6749 §4.1.3)

Exchanges a one-time authorization code for tokens.

| Parameter | Level | Notes |
|-----------|-------|-------|
| `grant_type` | REQUIRED | `"authorization_code"` |
| `code` | REQUIRED | One-time code from authorization response |
| `redirect_uri` | REQUIRED if in auth request | Must be identical to the original |
| `client_id` | REQUIRED for public clients | |
| `code_verifier` | REQUIRED if PKCE used | RFC 7636 §4.6 |

**Processing:**

1. `TemporalKeysGateway::retrieveTemporalAuthCode(code)` — retrieve and atomically delete (one-use). Return 401 if not found or expired (max 10 min, per RFC 6749 §4.1.2 recommendation; implementation uses 3 min).
2. **PKCE verification** (RFC 7636 §4.6), if `codeChallenge` was stored:
   - `code_verifier` MUST be present → `invalid_request` (400) if absent.
   - `S256`: `BASE64URL(SHA256(ASCII(code_verifier))) == codeChallenge` → `invalid_grant` (400) on mismatch.
   - `plain`: `code_verifier == codeChallenge` → `invalid_grant` (400) on mismatch.
3. Reconstruct claims from `TemporalAuthCode.data` and `request`.
4. Sign and return `access_token` + `id_token` + `refresh_token`.

---

### `refresh_token` (RFC 6749 §6)

Obtains a new access token using a refresh token.

| Parameter | Level | Notes |
|-----------|-------|-------|
| `grant_type` | REQUIRED | `"refresh_token"` |
| `refresh_token` | REQUIRED | The refresh token |
| `client_id` | REQUIRED if not authenticating | |
| `scope` | OPTIONAL | MUST NOT exceed the original scope |
| `audience` | OPTIONAL (custom) | Comma-separated extra audiences |

**Processing:**

1. `TokenSigner::verifyTokenPayload` — verify RS256 signature and `exp`. Reject if invalid or expired.
2. Extract `original_scope` from refresh token payload.
3. Resolve client via `ClientStoreGateway::preValidatedClient`.
4. `TokenGranterMediator::authenticate("refresh_token", …)` → `ResolverForRefresh`:
   - Extract `keypass` (subject id) from `{keypass: userId}` payload.
   - `LoginGateway::fillPreLoadById` to reload current user data.
5. Sign and return new `access_token` + `id_token` + `refresh_token`.

**Scope rule:** Requested `scope` MUST be a subset of `original_scope`. If not specified, `original_scope` is used.

**Refresh token lifetime:** 10 hours. Per RFC 6749 §6, if a new refresh token is issued, the old one MUST be considered invalidated.

---

### `password` — Resource Owner Password Credentials (RFC 6749 §4.3)

```
grant_type=password
```

| Parameter | Level | Notes |
|-----------|-------|-------|
| `grant_type` | REQUIRED | `"password"` |
| `username` | REQUIRED | |
| `password` | REQUIRED | Plaintext (transport-level protection via TLS) |
| `scope` | OPTIONAL | |
| `client_id` | REQUIRED | Via Basic Auth or body |
| `client_secret` | REQUIRED | Via Basic Auth or body |

Client authentication: HTTP Basic Auth (`Authorization: Basic base64(client_id:client_secret)`) or body params.

**Processing:**

1. `ClientStoreGateway::clientData(clientId, clientSecret)` — authenticate client.
2. Verify `password` grant is in `client.grants` AND `client.secretLogin=true`.
3. `TokenGranterMediator::authenticate("password", …)` → `ResolverForPassword` → `LoginGateway::validatedUserData`.
4. Sign and return tokens.

> **Note:** This grant bypasses the browser-based MFA and consent challenge steps. The gateway implementation may enforce additional restrictions.

---

### `client_credentials` (RFC 6749 §4.4)

Machine-to-machine token for clients authenticating on their own behalf.

| Parameter | Level | Notes |
|-----------|-------|-------|
| `grant_type` | REQUIRED | `"client_credentials"` |
| `scope` | OPTIONAL | Must be within `client.allowedScopesM2m` |
| `audience` | OPTIONAL (custom) | Comma-separated extra audiences |

Client authentication: HTTP Basic Auth or body `client_id` + `client_secret`. Per RFC 6749 §4.4, MUST only be used by **confidential clients**.

**Processing:**

1. `ClientStoreGateway::clientData(clientId, clientSecret)`.
2. Verify `client_credentials` is in `client.grants`.
3. `TokenGranterMediator::authenticate("client_credentials", …)` → `ResolverForClientCredentials`.
4. Sign and return **access token only** (no `id_token`, no `refresh_token`).

**Token TTL:** `client.m2mTokenTtlSeconds` (default 3600 s).

**Audience in `aud` claim:** `[clientId, ...extraAudiences]` (deduplicated). If single element, `aud` is a string; if multiple, an array.

---

### `urn:ietf:params:oauth:grant-type:device_code` (RFC 8628 §3.4)

See [Device Authorization Flow](05-device-and-par-flows.md) for the full flow. Token request:

| Parameter | Level | Notes |
|-----------|-------|-------|
| `grant_type` | REQUIRED | `"urn:ietf:params:oauth:grant-type:device_code"` |
| `device_code` | REQUIRED | From device authorization response |
| `client_id` | REQUIRED for public clients | |

---

## Token Endpoint Error Response

**RFC 6749 §5.2** — HTTP 400 (HTTP 401 for `invalid_client` with `WWW-Authenticate` header).

```json
{
  "error": "invalid_grant",
  "error_description": "Authorization code has expired or is invalid."
}
```

| Error code | HTTP | Meaning |
|-----------|------|---------|
| `invalid_request` | 400 | Missing parameter, invalid value, or malformed request |
| `invalid_client` | 401 | Client authentication failed |
| `invalid_grant` | 400 | Invalid/expired/revoked code or mismatched redirect URI |
| `unauthorized_client` | 400 | Client not authorized for this grant type |
| `unsupported_grant_type` | 400 | Unknown or unsupported `grant_type` |
| `invalid_scope` | 400 | Requested scope exceeds originally granted scope |

---

## `TokenGranterMediator`

An application-level strategy registry. Each grant type registers a `TokenGranterStrategy`:

```
interface TokenGranterStrategy {
    canHandle(grantType, params): bool
    authenticate(grantType, tenant, request, params): ?AuthenticationResult
}
```

| Strategy | Grant type |
|----------|-----------|
| `ResolverForPassword` | `password` |
| `ResolverForRefresh` | `refresh_token` |
| `ResolverForClientCredentials` | `client_credentials` |
| `ResolverForDevice` | `urn:ietf:params:oauth:grant-type:device_code` |

---

## Introspection (`POST /introspect`)

**RFC 7662**

```
POST /oauth/openid/{tenant}/introspect
Authorization: Basic <client_id:client_secret>
Content-Type: application/x-www-form-urlencoded

token=<token>&token_type_hint=access_token
```

Active token response:

```json
{
  "active": true,
  "sub": "user-id",
  "scope": "openid email",
  "client_id": "my-client",
  "exp": 1735689600,
  "iat": 1735686000,
  "iss": "https://example.com/oauth/openid/tenant1"
}
```

Inactive token response:

```json
{ "active": false }
```

Implementation uses `TokenSigner::verifyTokenPayload`. Revoked tokens (`TokenRevocationGateway::isRevoked`) MUST return `active: false`.

---

## Token Revocation (`POST /revoke`)

**RFC 7009**

```
POST /oauth/openid/{tenant}/revoke
Content-Type: application/x-www-form-urlencoded

token=<token>&token_type_hint=access_token|refresh_token
```

Marks the token as revoked via `TokenRevocationGateway::revoke`. Returns HTTP 200 regardless of whether the token was valid (per RFC 7009 §2.2 — prevents information leakage).

`TokenSigner::parseSignedPayload` is used for tokens that may have already expired — it verifies the signature while ignoring `exp`/`nbf`, allowing extraction of the token identifier for revocation.

---

## UserInfo (`GET /userinfo`)

**OIDC Core §5.3**

```
GET /oauth/openid/{tenant}/userinfo
Authorization: Bearer <access_token>
```

Returns claims from the access token payload. At minimum: `sub` and `name`.

The implementation verifies the access token signature (`TokenSigner::verifyTokenPayload`) and MUST reject revoked tokens.

---

## JWKS (`GET /jwks`)

**RFC 7517 §5**

```
GET /oauth/openid/{tenant}/jwks
```

Returns all current and near-future public keys for `tenant` in JWKS format:

```json
{
  "keys": [
    {
      "kty": "RSA",
      "use": "sig",
      "alg": "RS256",
      "kid": "key-id-1",
      "n": "<base64urlUInt modulus>",
      "e": "AQAB"
    }
  ]
}
```

RSA key parameters per **RFC 7518 §6.3**: `n` (modulus) and `e` (public exponent) encoded as Base64urlUInt. Key size MUST be ≥ 2048 bits (RFC 7518 §3).

Response is cached with 1-hour ETag.

---

## OpenID Connect Discovery (`GET /.well-known/openid-configuration`)

**OIDC Discovery 1.0**

```
GET /oauth/openid/{tenant}/.well-known/openid-configuration
```

Returns the Provider Metadata document describing all supported endpoints, scopes, claims, and algorithms for the tenant. Consumers should use this endpoint to auto-configure rather than hardcoding URLs.
