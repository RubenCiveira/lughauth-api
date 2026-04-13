# 03-02 — Magic Links / Passwordless Email Login

**Prioridad:** P2  
**Esfuerzo estimado:** Bajo-Medio (2-3 días)  
**Dependencias previas:** `Notification/Outbox` (ya existe)  
**Habilita:** autenticación sin contraseña, onboarding frictionless

---

## Contexto

Un magic link es un enlace de un solo uso enviado al email del usuario que lo autentica
directamente al hacer clic. Elimina la necesidad de recordar contraseñas y es más
seguro que contraseñas débiles. El sistema de notificaciones existente
(`Notification/Outbox`, `NotificationDispatchService`) es el backend perfecto.

**Diferencia con recover-pass existente:** El recover-pass es un flujo de 3 pasos
para cambiar contraseña. Magic link crea directamente una sesión OIDC sin modificar
credenciales.

---

## Qué implementar

### Endpoint de solicitud

```
POST /openid/{tenant}/magic-link/request
Content-Type: application/json

{ "email": "user@example.com", "client_id": "xxx", "redirect_uri": "https://app.example.com/callback" }
```

Respuesta: `202 Accepted` (siempre, sin revelar si el email existe — anti-enumeration)

### Endpoint de verificación

```
GET /openid/{tenant}/magic-link/verify?token=<token>&client_id=xxx
```

Si el token es válido: crea sesión y redirige como si fuera un authorize exitoso
(con `code` para authorization code flow, o directamente con tokens).

---

## Dónde y cómo hacer los cambios

### A. Migración — tabla _oauth_magic_link

```sql
CREATE TABLE IF NOT EXISTS _oauth_magic_link (
  uid        VARCHAR(36)  NOT NULL,
  tenant_id  VARCHAR(36)  NOT NULL,
  user_uid   VARCHAR(36)  NOT NULL,
  client_id  VARCHAR(36)  NOT NULL,
  token_hash VARCHAR(64)  NOT NULL,         -- SHA-256 del token enviado
  redirect_uri VARCHAR(500) NOT NULL,
  scope      VARCHAR(500) NOT NULL DEFAULT 'openid email',
  state      VARCHAR(500) NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME     NOT NULL,          -- TTL: 15 minutos
  used_at    DATETIME     NULL,
  PRIMARY KEY (uid),
  UNIQUE KEY uk_token_hash (token_hash),
  INDEX idx_magic_link_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### B. Nuevo sub-feature: src/Features/Oidc/MagicLink/

```
src/Features/Oidc/MagicLink/
├── Domain/
│   ├── MagicLinkToken.php
│   └── Gateway/
│       └── MagicLinkGateway.php
├── Application/
│   ├── Usecase/
│   │   ├── RequestMagicLink/
│   │   │   └── RequestMagicLinkUsecase.php
│   │   └── VerifyMagicLink/
│   │       └── VerifyMagicLinkUsecase.php
└── Infrastructure/
    ├── Driven/
    │   └── MagicLinkSqlAdapter.php
    └── Driver/
        └── Rest/
            └── MagicLinkController.php
```

### C. Domain — MagicLinkToken

```php
final class MagicLinkToken
{
    public readonly string $tokenHash;

    public function __construct(
        public readonly string $uid,
        public readonly string $tenant,
        public readonly string $userUid,
        public readonly string $clientId,
        public readonly string $redirectUri,
        public readonly string $scope,
        public readonly ?string $state,
        public readonly \DateTimeImmutable $expiresAt,
        public readonly ?\DateTimeImmutable $usedAt = null,
    ) {
        $this->tokenHash = '';  // asignado externamente
    }

    public static function create(string $tenant, string $userUid, string $clientId,
                                   string $redirectUri, string $scope, ?string $state): array
    {
        $rawToken = bin2hex(random_bytes(32));  // 64 chars, alta entropía
        $token = new self(
            uid: Uuid::uuid4()->toString(),
            tenant: $tenant,
            userUid: $userUid,
            clientId: $clientId,
            redirectUri: $redirectUri,
            scope: $scope,
            state: $state,
            expiresAt: new \DateTimeImmutable('+15 minutes'),
        );
        return [$rawToken, $token];  // rawToken para el email, token.hash para DB
    }

    public function isExpired(): bool { return $this->expiresAt < new \DateTimeImmutable(); }
    public function isUsed(): bool    { return $this->usedAt !== null; }
}
```

### D. Application — RequestMagicLinkUsecase

```php
public function request(string $email, string $clientId, string $redirectUri,
                         string $tenant, string $scope = 'openid email'): void
{
    // 1. Buscar usuario por email (silencioso si no existe — anti-enumeration)
    $user = $this->loginGateway->findByEmail($email, $tenant);
    if ($user === null) return;  // No revelar que el usuario no existe

    // 2. Validar cliente y redirect_uri
    $client = $this->clientStore->findByClientId($clientId, $tenant);
    if (!$client || !$client->hasRedirectUri($redirectUri)) return;

    // 3. Generar token y persistir
    [$rawToken, $magicLink] = MagicLinkToken::create($tenant, $user->uid, $clientId, $redirectUri, $scope, null);
    $hash = hash('sha256', $rawToken);
    $this->gateway->store($magicLink, $hash);

    // 4. Construir URL del magic link
    $verifyUrl = "{$this->baseUrl}/openid/{$tenant}/magic-link/verify?token={$rawToken}&client_id={$clientId}";

    // 5. Enviar email via Notification/Outbox existente
    $this->notificationDispatch->send(
        to: $email,
        templateKey: 'magic_link',
        variables: ['verify_url' => $verifyUrl, 'expires_in_minutes' => 15],
        tenant: $tenant,
    );
}
```

### E. Application — VerifyMagicLinkUsecase

```php
public function verify(string $rawToken, string $clientId, string $tenant): string
{
    // 1. Buscar por hash
    $hash = hash('sha256', $rawToken);
    $magicLink = $this->gateway->findByHash($hash, $tenant);

    if ($magicLink === null || $magicLink->isExpired() || $magicLink->isUsed()) {
        throw new OAuthException('invalid_grant', 'Magic link is invalid, expired or already used');
    }

    // 2. Validar client_id coincide
    if ($magicLink->clientId !== $clientId) {
        throw new OAuthException('invalid_grant');
    }

    // 3. Marcar como usado (single-use)
    $this->gateway->markUsed($magicLink->uid);

    // 4. Crear código de autorización temporal (reutilizar _oauth_temporal_codes)
    $authCode = $this->sessionManager->createTemporalCode(
        userUid: $magicLink->userUid,
        clientId: $clientId,
        tenant: $tenant,
        scope: $magicLink->scope,
        redirectUri: $magicLink->redirectUri,
    );

    // 5. Redirigir a redirect_uri con code
    return $magicLink->redirectUri . '?code=' . $authCode
        . ($magicLink->state ? '&state=' . $magicLink->state : '');
}
```

### F. TenantConfig — habilitar magic links

```sql
ALTER TABLE access_tenant_config
  ADD COLUMN magic_link_enabled TINYINT(1) NOT NULL DEFAULT 0;
```

### G. Plantilla de email

**Archivo nuevo:** `templates/magic_link.twig`

```twig
<!DOCTYPE html>
<html>
<body>
  <h2>Iniciar sesión en {{ tenant_name }}</h2>
  <p>Haz clic en el enlace para acceder. Válido durante {{ expires_in_minutes }} minutos.</p>
  <a href="{{ verify_url }}" style="...">Iniciar sesión</a>
  <p>Si no solicitaste este acceso, ignora este email.</p>
</body>
</html>
```

---

## Tests a incluir

### Test unitario — MagicLinkToken

- `create()` → `rawToken` de 64 chars, `tokenHash` = SHA-256 de rawToken
- `isExpired()` con expiresAt pasado → `true`
- `isUsed()` con `usedAt` null → `false`, con fecha → `true`

### Test unitario — RequestMagicLinkUsecase

Con mocks:
- Email existente + cliente válido → gateway llamado, email enviado
- Email no existente → sin error, sin email enviado (anti-enumeration)
- `redirect_uri` no registrada → sin error (silencioso, por seguridad)

### Test unitario — VerifyMagicLinkUsecase

Con mocks:
- Token válido → devuelve redirect URL con `code`
- Token expirado → excepción `invalid_grant`
- Token ya usado → excepción `invalid_grant`
- Token inexistente → excepción `invalid_grant`
- `client_id` no coincide → excepción `invalid_grant`
- Token usado dos veces → segundo intento falla (idempotencia)

### Test integración — MagicLinkController

- `POST /magic-link/request` → siempre `202` (con o sin usuario existente)
- `GET /magic-link/verify?token=válido` → redirect con `code`
- `GET /magic-link/verify?token=expirado` → error page
- Código obtenido del magic link → intercambiable en `/token` → tokens válidos
