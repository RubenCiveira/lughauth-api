# OIDC Module — Gateway Contracts (Ports)

This document defines the contracts that host applications must implement to integrate the OIDC module. Each interface is a **port** — the OIDC domain depends on the interface, never on a specific implementation. RFC/spec references are included for each contract.

---

## Client Management

### `ClientStoreGateway`

**RFC 6749 §2, §3.2.1, §4.1**

Resolves and authenticates OAuth clients.

```
clientData(clientId, clientSecret): ?ClientData
```
Authenticates a **confidential client** (RFC 6749 §2.1) with its `client_secret`. Returns `null` if the client does not exist or the secret is wrong. Used for: `client_credentials` grant, `password` grant, Basic-Auth authenticated requests at the token endpoint.

```
preValidatedClient(clientId): ?ClientData
```
Returns client data without secret verification. Used when the client was already authenticated by another mechanism — for example, after PKCE verification where no secret is exchanged, or for `refresh_token` grant where the code already proved client identity.

```
publicClientData(clientId, tenant, redirectUrl, scope): ?ClientData
```
Validates a **public client** (RFC 6749 §2.1, no client secret) against a given `redirectUrl` and `scope`. MUST verify that `redirectUrl` is in the client's registered redirect URI list (RFC 6749 §3.1.2). Returns `null` on any validation failure.

### `ApiKeyStoreGateway`

```
apiKey(key): ?ApiKeyData
```
Looks up an API key. Returns `null` if the key does not exist or is not active. `ApiKeyData` carries `id` and `scopes[]`.

### `DynamicClientGateway`

**RFC 7591** (Dynamic Client Registration) / **RFC 7592** (Dynamic Client Management)

```
register(request: DynamicClientRequest): DynamicClientData
create(data: DynamicClientData): void
findById(clientId): ?DynamicClientData
update(clientId, request: DynamicClientRequest): DynamicClientData
delete(clientId): void
```

---

## User Authentication

### `LoginGateway`

Central port for credential validation and user data loading.

```
validatedUserData(tenant, username, password, request: AuthenticationRequest): AuthenticationResult
```
Validates `username` and `password` for `tenant`. The full validation chain is left to the implementation but MUST produce one of the outcomes defined in [`AuthenticationResult` error constants](01-domain-model.md). Implementations are expected to enforce lockout policies (e.g., block after N failed attempts) at this point.

```
fillPreLoadById(tenant, request, challenges: ChallengesState): AuthenticationResult
```
Loads user data by the subject id stored in `challenges`. Used for: `refresh_token` grant (reload user after token verification), and re-running checks after an intermediate step like MFA.

```
fillPreAuthenticated(tenant, request, challenges: ChallengesState): AuthenticationResult
```
Runs the remaining validation checks for a user whose identity was already confirmed by a prior step (e.g., after consent form submitted). MUST skip credential checking and re-run only the outstanding checks.

### `RegisterUserGateway`

```
allowRegister(tenant): bool
```
Returns whether self-registration is enabled for `tenant`.

```
getRegisterConsent(tenant): ?string
```
Returns the HTML/text of the registration terms to show on the registration form, or `null` if none.

```
requestForRegister(url, tenant, email, password): void
```
Initiates registration: creates an unverified account and sends a verification email containing `url` as the verify link. The password MUST be stored hashed.

```
verifyRegister(tenant, code): ?string
```
Verifies the email code. Returns the username on success, `null` on invalid/expired code.

### `ChangePasswordGateway`

```
allowRecover(tenant): bool
```
Returns whether password recovery is enabled for `tenant`.

```
requestForChange(url, tenant, username): void
```
Generates a recovery code and sends it to the user's registered email. `url` is the base recover link.

```
validateChangeRequest(tenant, code, newPass): ?string
```
Validates the recovery code and atomically changes the password. Returns the username on success, `null` on invalid or expired code.

```
forceUpdatePassword(tenant, username, oldPass, newPass): bool
```
Changes a password when the user knows the old one. Returns `true` on success.

---

## Consent

### `TermsOfUseConsentGateway`

**OIDC Core §3.1.2.4** — Authorization servers SHOULD present terms of use and require consent where applicable.

```
getPendingConsent(tenant, username, audiences[]): TermsOfUseAcceptance[]
```
Returns which terms of use (per audience) the user has not yet accepted. An empty array means all terms are accepted.

```
storeAcceptedConsent(tenant, username, audiences[], consent: TermsOfUseAcceptance): void
```
Persists the user's acceptance with a timestamp.

### `ScopesConsentGateway`

**OIDC Core §3.1.2.4** — The authorization server MUST obtain the end-user's consent for releasing claims.

```
pendingScopes(tenant, username, clientId, requestedScopes[]): ScopePermission[]
```
Returns the subset of `requestedScopes` for which the user has not yet granted permission for `clientId`. Each `ScopePermission` indicates whether the scope is required or optional. An empty array means all scopes are already consented.

```
storeAcceptedScopes(tenant, username, clientId, approvedScopes[]): void
```
Persists the user's scope choices.

### `GdprConsentGateway`

```
getPendingPurposes(tenant, username): GdprConsentPurposeItem[]
storeConsent(tenant, username, purposeKey, granted: bool): void
listConsents(tenant, username): GdprConsentPurposeItem[]
listConsentHistory(tenant, username): array
```

---

## Session Management

### `SessionStoreGateway`

**OIDC Session Management 1.0**

```
loadSession(state): ?SessionInfo
```
Loads an active session by its session token. MUST return `null` if not found or expired.

```
saveSession(state, client, issuer, challenges, authResult, csid, expiration): void
```
Persists a new session.

```
updateSession(newState, oldState): void
```
Rotates the session token atomically (old → new). MUST be safe against concurrent calls.

```
deleteSession(state): void
```
Terminates a session (used on logout and on CSID mismatch).

**Implementation requirements:**
- `loadSession` MUST return `null` for expired records.
- Expired sessions SHOULD be pruned periodically.
- Session tokens MUST be globally unique and cryptographically random.
- Tenant isolation MUST be maintained — session tokens from one tenant MUST NOT resolve in another.

### `TemporalKeysGateway`

Manages rotating symmetric keys (HS256 + AES) and one-time authorization codes (RFC 6749 §4.1.2).

```
currentKey(): string
```
Returns the current symmetric key material. Used by `HtmlSecurer` to generate the browser-side CSID and AES encryption key.

```
encrypt(token): ?string
```
AES-encrypts a string. Returns `null` on failure.

```
verifyCypher(token): ?string
```
AES-decrypts a string. MUST try the current key first, then the previous key (for tokens generated just before rotation). Returns `null` on failure.

```
verifyToken(token): ?string
```
Verifies an HMAC-signed token. MUST try current key first, then previous key. Returns the payload on success, `null` on tampered or expired tokens.

```
registerTemporalAuthCode(code: TemporalAuthCode): string
```
Stores a one-time authorization code (RFC 6749 §4.1.2: single-use, short-lived — TTL: 3 min). Returns the opaque code string.

```
retrieveTemporalAuthCode(code): ?TemporalAuthCode
```
Retrieves and **atomically deletes** a one-time code. Returns `null` if not found or expired. The deletion MUST be atomic to prevent double-use.

**Symmetric key rotation policy:**
- Keys rotate every **1 hour**.
- Both `current` and `old` keys are retained simultaneously during the window.
- `verifyCypher` and `verifyToken` MUST try both keys so tokens generated just before rotation remain valid.

---

## Token Security

### `TokenSigner`

**RFC 7519** (JWT), **RFC 7518** (JWA), **RFC 7517** (JWK)

```
sign(tenant, data[], expiration): string
```
Signs a JWT with RS256 (RFC 7518 §3, RECOMMENDED). `data` becomes the JWT payload. Sets standard claims: `iss`, `iat`, `exp`.

```
keysAsJwks(tenant): JWKSet
```
Returns the current and near-future public keys for `tenant` as a JWKS document (RFC 7517 §5). Must include all keys whose validity window overlaps with outstanding token lifetimes.

```
signKeypass(tenant, data[], expiration): string
```
Signs a compact JWT where the payload is `{keypass: data}`. Used for pre-session cookies and refresh tokens.

```
verifyTokenPayload(tenant, token): ?array
```
Verifies an RS256 JWT signature and `exp`. Returns the decoded payload claims or `null` if invalid or expired.

```
verifiedKeypass(tenant, token): mixed
```
Verifies a keypass JWT and extracts the `keypass` value.

```
parseSignedPayload(tenant, token): ?array
```
Verifies the JWT RS256 signature but **ignores `exp`/`nbf`**. Used for revocation flows where expired tokens must still be parsed to extract their identifier (per RFC 7009 §2.2 — revocation must succeed even for expired tokens).

### `TokenStoreGateway`

**RFC 7517 §5**

```
currentKey(tenant): ?KeyPair
nextKeysExpiration(tenant): ?DateTimeImmutable
listKeys(tenant): KeyPair[]
saveKey(tenant, pair, since, ttl): void
```

**Key rotation requirements (RFC 7518 §3):**
- RSA key size MUST be ≥ 2048 bits.
- Expired keys MUST be retained until `since + ttl` has passed so that tokens signed with them can still be verified.
- `listKeys` MUST return all keys whose verification window is still active (for JWKS publication).

**Rotation trigger (evaluated by `TokenSigner` implementation):**
```
if nextKeysExpiration(tenant) < now + (3 * 7days):
    generate and store 3 new key pairs
```

### `TokenRevocationGateway`

**RFC 7009**

```
revoke(tenant, tokenId): void
isRevoked(tenant, tokenId): bool
```

`isRevoked` MUST be consulted by `IntrospectionController` and `UserInfoController` before accepting any token as valid.

---

## MFA

### `UserMfaGateway`

**RFC 6238** (TOTP — Time-Based One-Time Password)

```
configurationForNewMfa(tenant, username): PublicLoginMfaBuildResponse
```
Generates a new TOTP seed for `username` and returns QR code data. MUST NOT persist the seed at this point (the user must first verify with `verifyNewOpt`).

```
verifyOtp(tenant, username, otp): bool
```
Verifies an OTP code against the user's stored seed using the TOTP algorithm (RFC 6238 §4 — 30-second window, 6 digits).

```
verifyNewOpt(tenant, username, seed, otp): bool
```
Verifies OTP against a newly generated, not-yet-persisted `seed`. If valid, MUST persist the seed. Prevents storage of unverified seeds.

```
storeSeed(tenant, username, seed): void
```
Explicitly persists a TOTP seed (used in exceptional flows).

---

## Federated Login

### `DelegateLoginGateway`

```
providers(tenant): DelegatedLoginProvider[]
```
Returns all configured federated identity providers for `tenant`.

```
getProvider(tenant, providerId): ?DelegatedLoginProvider
```
Returns a specific provider by `providerId`.

```
save(tenant, clientId, userData: DelegatedUserData, providerId): AuthenticationResult
```
Creates or retrieves a user account linked to the federated identity. On first login, provisions the user account automatically. Returns the full `AuthenticationResult` as if the user had logged in with credentials.

**Security contract:** The implementation MUST ensure that a federated identity cannot claim an existing local account without explicit account linking. The `userData.email` alone MUST NOT be sufficient to link to an existing account.

---

## PAR

### `ParRequestGateway`

**RFC 9126 §2**

```
store(request: ParRequest): void
```
Persists a PAR request. `request.used` MUST be `false` on initial store. MUST honour `request.expiresAt`.

```
findByUri(requestUri, tenant): ?ParRequest
```
Returns the PAR request. MUST return `null` if: not found, `expiresAt` is in the past, or `used=true`.

```
markUsed(requestUri, tenant): void
```
Marks a PAR request as consumed (one-time use, per RFC 9126 §4). MUST be atomic.

**Security contracts:**
- `request_uri` MUST be bound to the client that created it.
- A different client attempting to use a `request_uri` MUST be rejected.

---

## Device Authorization

### `DeviceAuthorizationGateway`

**RFC 8628**

```
create(authorization: DeviceAuthorization): void
findByDeviceCode(deviceCode): ?DeviceAuthorization
findByUserCode(tenant, userCode): ?DeviceAuthorization
approve(deviceCode, authResult): bool
deny(deviceCode): bool
touchPoll(deviceCode, now): void
consume(deviceCode): void
```

**Implementation contracts:**
- `touchPoll` MUST record last-poll timestamp and signal `slow_down` if the device polls faster than `DeviceAuthorization.interval` seconds (RFC 8628 §3.5).
- `approve` and `deny` MUST be idempotent — multiple calls after the first MUST be safe.
- `consume` MUST be atomic — prevents the device code from being exchanged twice.
- Records past `expiresAt` MUST be treated as not found by `findByDeviceCode` and `findByUserCode`.
- `user_code` values SHOULD avoid visually ambiguous characters (RFC 8628 §6.1 recommends the character set `BCDFGHJKLMNPQRSTVWXZ`).

---

## WebAuthn

### `WebAuthnCredentialGateway`

**W3C WebAuthn Level 2**

```
save(credential: WebAuthnCredential): void
findByUserId(userId, tenant): WebAuthnCredential[]
findByCredentialId(credentialId, tenant): ?WebAuthnCredential
updateCounter(credentialId, tenant, newCounter): void
delete(credentialId, userId, tenant): void
```

`updateCounter` MUST be called after every successful authentication — the counter is the primary replay attack defense (WebAuthn §6.1.1).

### `WebAuthnChallengeGateway`

```
save(challenge: WebAuthnChallenge): void
findById(challengeId): ?WebAuthnChallenge
delete(challengeId): void
```

Challenges MUST be single-use (delete on retrieval) and short-lived (≤ 5 min).

---

## Profile

### `ProfileGateway`

```
load(userId, tenant): ?OidcProfile
save(userId, tenant, data: OidcProfileData): void
```

### `PasswordGateway`

```
change(userId, tenant, oldPassword, newPassword): bool
```

### `MfaGateway`

```
load(userId, tenant): MfaSetup
save(userId, tenant, setup: MfaSetup): void
```

### `SessionsGateway`

```
list(userId, tenant): ActiveSession[]
revoke(sessionId, userId, tenant): void
```
