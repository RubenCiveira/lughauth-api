# 03-01 — WebAuthn / Passkeys (FIDO2)

**Prioridad:** P2  
**Spec:** W3C Web Authentication Level 2 / FIDO2  
**Esfuerzo estimado:** Alto (5-7 días)  
**Dependencias previas:** ninguna  
**Librería sugerida:** `web-auth/webauthn-framework` (añadir a composer.json)

---

## Contexto

WebAuthn permite autenticación sin contraseña usando el hardware del dispositivo
(Touch ID, Face ID, Windows Hello, llaves de seguridad FIDO2). Elimina completamente
el riesgo de phishing de contraseñas y es el estándar de facto para Passkeys.

**Flujo de registro:**
1. App solicita `publicKeyCredentialCreationOptions` al servidor
2. Browser invoca WebAuthn API, usuario autoriza con biométrico/PIN
3. App envía la credencial firmada al servidor que la verifica y almacena

**Flujo de autenticación:**
1. App solicita `publicKeyCredentialRequestOptions` (challenge) al servidor
2. Browser invoca WebAuthn API con la credencial almacenada
3. App envía la assertion firmada, servidor verifica y crea sesión

---

## Qué implementar

### Endpoints API WebAuthn

```
POST /openid/{tenant}/webauthn/register/begin     → publicKeyCredentialCreationOptions
POST /openid/{tenant}/webauthn/register/finish    → verifica y almacena credencial
POST /openid/{tenant}/webauthn/authenticate/begin → publicKeyCredentialRequestOptions
POST /openid/{tenant}/webauthn/authenticate/finish→ verifica assertion y crea sesión OIDC
```

### Integración en authorize flow

Paso nuevo: `webauthn-login` (alternativa al paso `login` de contraseña)
Paso nuevo: `webauthn-register` (durante registro o en perfil de usuario)

---

## Dónde y cómo hacer los cambios

### A. Añadir librería

```bash
composer require web-auth/webauthn-framework
```

### B. Migración — tabla access_user_webauthn_credential

```sql
CREATE TABLE IF NOT EXISTS access_user_webauthn_credential (
  uid              VARCHAR(36)   NOT NULL,
  tenant_id        VARCHAR(36)   NOT NULL,
  user_uid         VARCHAR(36)   NOT NULL,
  credential_id    VARCHAR(255)  NOT NULL,          -- Base64URL del credential ID
  public_key       TEXT          NOT NULL,           -- CBOR/PEM de la clave pública
  sign_count       BIGINT        NOT NULL DEFAULT 0,
  aaguid           VARCHAR(36)   NULL,               -- device authenticator id
  device_name      VARCHAR(200)  NULL,               -- nombre descriptivo
  transports_json  TEXT          NULL,               -- ["internal","usb","ble","nfc"]
  created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at     DATETIME      NULL,
  enabled          TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (uid),
  UNIQUE KEY uk_credential_tenant (credential_id, tenant_id),
  INDEX idx_webauthn_user (user_uid, tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla para almacenar challenges temporales (TTL 5 min)
CREATE TABLE IF NOT EXISTS _webauthn_challenge (
  challenge_id VARCHAR(36)   NOT NULL,
  tenant_id    VARCHAR(36)   NOT NULL,
  user_uid     VARCHAR(36)   NULL,   -- NULL durante autenticación (pre-login)
  challenge    VARCHAR(100)  NOT NULL,
  type         ENUM('register','authenticate') NOT NULL,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at   DATETIME      NOT NULL,
  PRIMARY KEY (challenge_id),
  INDEX idx_challenge_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### C. Nuevo sub-feature: src/Features/Oidc/WebAuthn/

```
src/Features/Oidc/WebAuthn/
├── Domain/
│   ├── WebAuthnCredential.php
│   ├── WebAuthnChallenge.php
│   └── Gateway/
│       ├── WebAuthnCredentialGateway.php
│       └── WebAuthnChallengeGateway.php
├── Application/
│   ├── Usecase/
│   │   ├── BeginRegistration/
│   │   │   └── BeginRegistrationUsecase.php   → retorna PublicKeyCredentialCreationOptions
│   │   ├── FinishRegistration/
│   │   │   └── FinishRegistrationUsecase.php  → verifica y persiste
│   │   ├── BeginAuthentication/
│   │   │   └── BeginAuthenticationUsecase.php → retorna PublicKeyCredentialRequestOptions
│   │   └── FinishAuthentication/
│   │       └── FinishAuthenticationUsecase.php → verifica assertion, retorna user_uid
└── Infrastructure/
    ├── Driven/
    │   ├── WebAuthnCredentialSqlAdapter.php
    │   └── WebAuthnChallengeSqlAdapter.php
    └── Driver/
        └── Rest/
            └── WebAuthnController.php
```

### D. Application — BeginRegistrationUsecase

```php
public function begin(string $userUid, string $tenant, string $rpId): array
{
    // 1. Cargar datos del usuario
    $user = $this->userLoader->load($userUid, $tenant);

    // 2. Generar challenge aleatorio
    $challenge = base64_encode(random_bytes(32));

    // 3. Almacenar challenge temporal
    $this->challengeGateway->store(new WebAuthnChallenge(
        challengeId: Uuid::uuid4()->toString(),
        tenant: $tenant,
        userUid: $userUid,
        challenge: $challenge,
        type: 'register',
        expiresAt: new \DateTimeImmutable('+5 minutes'),
    ));

    // 4. Obtener credenciales existentes (para excluir)
    $existingCredentials = $this->credentialGateway->findByUser($userUid, $tenant);

    // 5. Construir PublicKeyCredentialCreationOptions con web-auth/webauthn-framework
    return [
        'challenge' => $challenge,
        'rp' => ['name' => $tenant, 'id' => $rpId],
        'user' => [
            'id'          => base64_encode($userUid),
            'name'        => $user->email,
            'displayName' => $user->name ?? $user->email,
        ],
        'pubKeyCredParams' => [
            ['alg' => -7,  'type' => 'public-key'],  // ES256
            ['alg' => -257,'type' => 'public-key'],  // RS256
        ],
        'authenticatorSelection' => [
            'residentKey'        => 'preferred',
            'userVerification'   => 'preferred',
        ],
        'excludeCredentials' => array_map(
            fn($c) => ['id' => $c->credentialId, 'type' => 'public-key'],
            $existingCredentials
        ),
        'timeout' => 60000,
    ];
}
```

### E. HTML Forms — paso webauthn-login

**Archivo nuevo:** `src/Features/Oidc/Authentication/Infrastructure/Driver/Html/Forms/WebAuthnLoginForm.php`

Implementa `StepForm`. Renderiza una página HTML que:
1. Llama a `POST /webauthn/authenticate/begin` via fetch
2. Invoca `navigator.credentials.get(options)` con el challenge
3. Envía la assertion a `POST /webauthn/authenticate/finish`
4. Si éxito: hace submit del formulario con session_id para continuar el flow

### F. TenantConfig — habilitar WebAuthn

```sql
ALTER TABLE access_tenant_config
  ADD COLUMN webauthn_enabled    TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN webauthn_rp_id      VARCHAR(200) NULL,
  ADD COLUMN webauthn_rp_name    VARCHAR(200) NULL;
```

---

## Tests a incluir

### Test unitario — BeginRegistrationUsecase

Con mocks:
- Usuario válido → devuelve options con challenge, rp, user, pubKeyCredParams
- Credenciales existentes → incluidas en `excludeCredentials`
- Challenge almacenado en gateway

### Test unitario — FinishRegistrationUsecase

Con fixture de credencial de prueba (WebAuthn test vectors):
- Assertion válida → credencial persistida
- Challenge expirado → excepción
- Assertion con challenge incorrecto → excepción
- `sign_count` menor que el almacenado (replay attack) → excepción

### Test unitario — FinishAuthenticationUsecase

- Assertion válida → devuelve `user_uid`
- `sign_count` actualizado tras autenticación exitosa
- Credencial deshabilitada → falla

### Test integración — WebAuthnController

- `POST /webauthn/register/begin` sin sesión → `401`
- `POST /webauthn/register/begin` con sesión → `200` con options
- `POST /webauthn/authenticate/begin` → `200` con options
- Flujo completo con WebAuthn test client → autenticación exitosa
