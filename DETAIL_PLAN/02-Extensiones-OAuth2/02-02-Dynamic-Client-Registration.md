# 02-02 — Dynamic Client Registration — RFC 7591 / RFC 7592

**Prioridad:** P2  
**Spec:** RFC 7591 (registro), RFC 7592 (gestión posterior)  
**Esfuerzo estimado:** Medio (3-4 días)  
**Dependencias previas:** ninguna  
**Habilita:** registro programático de clientes sin intervención de administrador

---

## Contexto

Actualmente los clientes OAuth (`access_trusted_client`) solo pueden crearse via la
Management API por un administrador con role ROOT. Dynamic Client Registration permite
que los Relying Parties se registren ellos mismos, opcionalmente requiriendo un
`initial_access_token` como mecanismo de autorización.

---

## Qué implementar

### RFC 7591 — Registro

```
POST /openid/{tenant}/register
[Authorization: Bearer <initial_access_token>]  -- opcional según política
Content-Type: application/json

{
  "client_name": "My App",
  "redirect_uris": ["https://app.example.com/callback"],
  "grant_types": ["authorization_code", "refresh_token"],
  "response_types": ["code"],
  "scope": "openid email profile",
  "token_endpoint_auth_method": "client_secret_basic",
  "logo_uri": "https://app.example.com/logo.png",
  "backchannel_logout_uri": "https://app.example.com/logout"
}
```

Respuesta `201 Created`:
```json
{
  "client_id": "generated-uuid",
  "client_secret": "generated-secret",
  "client_id_issued_at": 1234567890,
  "client_secret_expires_at": 0,
  "registration_access_token": "token-for-rfc7592-management",
  "registration_client_uri": "https://auth.example.com/openid/tenant/register/generated-uuid",
  ... (echo de los parámetros de registro)
}
```

### RFC 7592 — Gestión posterior

```
GET    /openid/{tenant}/register/{client_id}   # Leer configuración
PUT    /openid/{tenant}/register/{client_id}   # Actualizar
DELETE /openid/{tenant}/register/{client_id}   # Eliminar (self-service)
Authorization: Bearer <registration_access_token>
```

---

## Dónde y cómo hacer los cambios

### A. Migración — campos adicionales en access_trusted_client

```sql
ALTER TABLE access_trusted_client
  ADD COLUMN registration_access_token_hash VARCHAR(64) NULL,
  ADD COLUMN registration_access_token_exp  DATETIME    NULL,
  ADD COLUMN client_name                    VARCHAR(200) NULL,
  ADD COLUMN logo_uri                       VARCHAR(500) NULL,
  ADD COLUMN client_uri                     VARCHAR(500) NULL,
  ADD COLUMN policy_uri                     VARCHAR(500) NULL,
  ADD COLUMN tos_uri                        VARCHAR(500) NULL,
  ADD COLUMN token_endpoint_auth_method     VARCHAR(50) NOT NULL DEFAULT 'client_secret_basic',
  ADD COLUMN grant_types_json               TEXT NULL,    -- JSON array
  ADD COLUMN response_types_json            TEXT NULL,    -- JSON array
  ADD COLUMN dynamically_registered         TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN registered_at                  DATETIME NULL;
```

### B. Nuevo sub-feature: src/Features/Oidc/Registration/

```
src/Features/Oidc/Registration/
├── Domain/
│   ├── ClientRegistrationRequest.php     # VO: los parámetros del POST
│   ├── ClientRegistrationResult.php      # VO: client_id, secret, registration_token
│   ├── InitialAccessToken.php            # VO: token de autorización inicial
│   └── Gateway/
│       └── ClientRegistrationGateway.php
├── Application/
│   └── Usecase/
│       ├── RegisterClient/
│       │   ├── RegisterClientUsecase.php
│       │   ├── RegisterClientParams.php
│       │   └── RegisterClientResult.php
│       ├── ReadClientRegistration/
│       │   └── ReadClientRegistrationUsecase.php
│       ├── UpdateClientRegistration/
│       │   └── UpdateClientRegistrationUsecase.php
│       └── DeleteClientRegistration/
│           └── DeleteClientRegistrationUsecase.php
└── Infrastructure/
    ├── Driven/
    │   └── ClientRegistrationSqlAdapter.php
    └── Driver/
        └── Rest/
            └── DynamicClientRegistrationController.php
```

### C. Domain — ClientRegistrationRequest

```php
final class ClientRegistrationRequest
{
    public function __construct(
        public readonly array  $redirectUris,           // requerido
        public readonly ?string $clientName = null,
        public readonly array  $grantTypes = ['authorization_code'],
        public readonly array  $responseTypes = ['code'],
        public readonly ?string $scope = null,
        public readonly string $tokenEndpointAuthMethod = 'client_secret_basic',
        public readonly ?string $logoUri = null,
        public readonly ?string $backchannelLogoutUri = null,
        public readonly ?string $frontchannelLogoutUri = null,
    ) {}

    public static function fromArray(array $data): self
    {
        if (empty($data['redirect_uris'])) {
            throw new \InvalidArgumentException('redirect_uris is required');
        }
        return new self(
            redirectUris: $data['redirect_uris'],
            clientName: $data['client_name'] ?? null,
            // ...
        );
    }
}
```

### D. Application — RegisterClientUsecase

```php
public function register(RegisterClientParams $params): RegisterClientResult
{
    // 1. Validar initial_access_token si la política lo requiere
    if ($this->policy->requiresInitialAccessToken($params->tenant)) {
        $this->validateInitialAccessToken($params->initialAccessToken, $params->tenant);
    }

    // 2. Validar redirect_uris (no localhost en producción, etc.)
    foreach ($params->request->redirectUris as $uri) {
        $this->validateRedirectUri($uri);
    }

    // 3. Generar client_id, client_secret, registration_access_token
    $clientId = Uuid::uuid4()->toString();
    $secret   = bin2hex(random_bytes(32));
    $rat      = bin2hex(random_bytes(32));

    // 4. Persistir usando TrustedClientWriteGateway (reutilizar gateway existente)
    // ... crear TrustedClient con dynamically_registered = true

    // 5. Devolver resultado completo
    return new RegisterClientResult(
        clientId: $clientId,
        clientSecret: $secret,
        registrationAccessToken: $rat,
        registrationClientUri: "{$params->issuer}/register/{$clientId}",
    );
}
```

### E. Reutilización de TrustedClient existente

**Clave:** No crear una tabla nueva. Reutilizar `access_trusted_client` añadiendo
las columnas de la migración A. Implementar `ClientRegistrationGateway` como un
wrapper que llama a `TrustedClientWriteGateway` con los campos mapeados.

### F. DynamicClientRegistrationController

```php
#[OA\Post(path: '/openid/{tenant}/register', summary: 'Dynamic Client Registration (RFC 7591)', tags: ['OIDC'])]
public function post(...): ResponseInterface { /* 201 Created */ }

#[OA\Get(path: '/openid/{tenant}/register/{client_id}', summary: 'Read Client Registration (RFC 7592)', tags: ['OIDC'])]
public function get(...): ResponseInterface { /* 200 con configuración */ }

#[OA\Put(path: '/openid/{tenant}/register/{client_id}', summary: 'Update Client Registration (RFC 7592)', tags: ['OIDC'])]
public function put(...): ResponseInterface { /* 200 actualizado */ }

#[OA\Delete(path: '/openid/{tenant}/register/{client_id}', summary: 'Delete Client Registration (RFC 7592)', tags: ['OIDC'])]
public function delete(...): ResponseInterface { /* 204 */ }
```

Autenticación para RFC 7592: `Authorization: Bearer <registration_access_token>`
(verificar hash contra `registration_access_token_hash` en DB).

### G. Política de registro — TenantConfig

Añadir campo a `access_tenant_config`:
```sql
ADD COLUMN dynamic_registration_policy ENUM('open','token_required','disabled') NOT NULL DEFAULT 'disabled'
```

### H. Discovery

```php
'registration_endpoint' => $issuer . '/register',
```

---

## Tests a incluir

### Test unitario — ClientRegistrationRequest

- `fromArray()` sin `redirect_uris` → excepción
- `fromArray()` con datos mínimos → objeto creado correctamente
- `fromArray()` con todos los campos → mapeados correctamente

### Test unitario — RegisterClientUsecase

Con mocks:
- Política `open` → registro sin token → `201`
- Política `token_required` + token válido → `201`
- Política `token_required` + sin token → `401`
- Política `disabled` → `403`
- `redirect_uri` inválida (javascript:, data:) → error

### Test integración — POST /register

- Registro mínimo → `201` con `client_id`, `client_secret`, `registration_access_token`
- `GET /register/{id}` con `registration_access_token` correcto → `200`
- `GET /register/{id}` con token incorrecto → `401`
- `DELETE /register/{id}` → `204`, cliente ya no puede autenticarse
- `PUT /register/{id}` actualiza `redirect_uris` → verificar en authorize flow
