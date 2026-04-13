# 04-03 — Sistema de Invitaciones

**Prioridad:** P2  
**Esfuerzo estimado:** Medio (3-4 días)  
**Dependencias previas:** `Notification/Outbox` (ya existe)

---

## Contexto

Actualmente los usuarios solo pueden registrarse por autoservicio (si el tenant
lo permite). No existe forma de invitar a un usuario específico por email con
un rol pre-asignado. Esta funcionalidad es estándar en cualquier BaaS o plataforma
SaaS multi-tenant.

---

## Qué implementar

```
POST   /api/access/invitations              # Crear y enviar invitación
GET    /api/access/invitations              # Listar invitaciones (pendientes/aceptadas/expiradas)
GET    /api/access/invitations/{uid}        # Ver detalle de invitación
DELETE /api/access/invitations/{uid}        # Cancelar invitación pendiente
POST   /api/access/invitations/{uid}/resend # Reenviar email de invitación

# Endpoint público para aceptar (no requiere autenticación)
GET    /openid/{tenant}/invitation/accept?token=xxx  # Muestra formulario de registro
POST   /openid/{tenant}/invitation/accept            # Crea cuenta y crea sesión OIDC
```

---

## Dónde y cómo hacer los cambios

### A. Migración

```sql
CREATE TABLE IF NOT EXISTS access_user_invitation (
  uid          VARCHAR(36)   NOT NULL,
  tenant_id    VARCHAR(36)   NOT NULL,
  email        VARCHAR(200)  NOT NULL,
  role_uid     VARCHAR(36)   NULL,
  invited_by   VARCHAR(36)   NOT NULL,   -- user_uid del invitante
  token_hash   VARCHAR(64)   NOT NULL,   -- SHA-256 del token enviado
  metadata_json TEXT         NULL,       -- datos pre-rellenados para el registro
  status       ENUM('pending','accepted','cancelled','expired') NOT NULL DEFAULT 'pending',
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at   DATETIME      NOT NULL,    -- TTL: 7 días por defecto
  accepted_at  DATETIME      NULL,
  accepted_by  VARCHAR(36)   NULL,       -- user_uid creado al aceptar
  PRIMARY KEY (uid),
  UNIQUE KEY uk_invitation_token (token_hash),
  INDEX idx_invitation_email  (tenant_id, email),
  INDEX idx_invitation_status (tenant_id, status),
  INDEX idx_invitation_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### B. Nuevo sub-feature: src/Features/Access/Invitation/

Estructura hexagonal estándar del proyecto:

```
src/Features/Access/Invitation/
├── Domain/
│   ├── Invitation.php
│   ├── InvitationStatus.php            # enum: pending, accepted, cancelled, expired
│   └── Gateway/
│       ├── InvitationReadGateway.php
│       └── InvitationWriteGateway.php
├── Application/
│   ├── Policy/
│   │   └── Allow/
│   │       └── Create/
│   │           └── InvitationCreateOnlyForTenantAdminAllow.php
│   └── Usecase/
│       ├── Create/
│       │   ├── InvitationCreateUsecase.php
│       │   ├── InvitationCreateParams.php
│       │   └── InvitationCreateResult.php
│       ├── Cancel/
│       │   └── InvitationCancelUsecase.php
│       ├── Resend/
│       │   └── InvitationResendUsecase.php
│       └── Accept/
│           ├── InvitationAcceptUsecase.php
│           └── InvitationAcceptParams.php
└── Infrastructure/
    ├── Driven/
    │   ├── InvitationReadAdapter.php
    │   └── InvitationWriteAdapter.php
    └── Driver/
        ├── Rest/
        │   ├── InvitationListController.php
        │   ├── InvitationCreateController.php
        │   ├── InvitationCancelController.php
        │   └── InvitationResendController.php
        └── Html/
            └── InvitationAcceptHtml.php   # Formulario público de aceptación
```

### C. Domain — Invitation

```php
final class Invitation
{
    public function __construct(
        public readonly string $uid,
        public readonly string $tenant,
        public readonly string $email,
        public readonly ?string $roleUid,
        public readonly string $invitedBy,
        public readonly InvitationStatus $status,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $expiresAt,
        public readonly ?\DateTimeImmutable $acceptedAt = null,
        public readonly ?array $metadata = null,
    ) {}

    public function isPending(): bool { return $this->status === InvitationStatus::Pending; }
    public function isExpired(): bool { return $this->expiresAt < new \DateTimeImmutable(); }
    public function canBeAccepted(): bool { return $this->isPending() && !$this->isExpired(); }
}
```

### D. Application — InvitationCreateUsecase

```php
public function create(InvitationCreateParams $params): InvitationCreateResult
{
    // 1. Validar que el invitante tiene permisos (admin/root del tenant)
    // -> via AllowDecision event (patrón del proyecto)

    // 2. Verificar que no hay invitación pendiente para ese email en el tenant
    $existing = $this->readGateway->findPendingByEmail($params->email, $params->tenant);
    if ($existing !== null && !$existing->isExpired()) {
        throw new \DomainException('There is already a pending invitation for this email');
    }

    // 3. Generar token
    $rawToken = bin2hex(random_bytes(32));
    $hash     = hash('sha256', $rawToken);

    // 4. Crear invitación
    $invitation = new Invitation(
        uid: Uuid::uuid4()->toString(),
        tenant: $params->tenant,
        email: $params->email,
        roleUid: $params->roleUid,
        invitedBy: $params->invitedByUserUid,
        status: InvitationStatus::Pending,
        createdAt: new \DateTimeImmutable(),
        expiresAt: new \DateTimeImmutable('+7 days'),
        metadata: $params->metadata,
    );
    $this->writeGateway->store($invitation, $hash);

    // 5. Construir URL de aceptación
    $acceptUrl = "{$params->baseUrl}/openid/{$params->tenant}/invitation/accept?token={$rawToken}";

    // 6. Enviar email vía Notification/Outbox
    $this->notificationDispatch->send(
        to: $params->email,
        templateKey: 'invitation',
        variables: [
            'accept_url'     => $acceptUrl,
            'invited_by'     => $params->invitedByEmail,
            'expires_in_days'=> 7,
        ],
        tenant: $params->tenant,
    );

    return new InvitationCreateResult(uid: $invitation->uid, expiresAt: $invitation->expiresAt);
}
```

### E. Application — InvitationAcceptUsecase

```php
public function accept(InvitationAcceptParams $params): string  // retorna redirect URL con code
{
    // 1. Buscar por hash
    $hash = hash('sha256', $params->token);
    $invitation = $this->readGateway->findByHash($hash, $params->tenant);

    if ($invitation === null || !$invitation->canBeAccepted()) {
        throw new \DomainException('Invalid or expired invitation');
    }

    // 2. Crear usuario (reutilizar RegisterUserUsecase existente)
    $userUid = $this->registerUser->register(
        email: $invitation->email,
        password: $params->password,
        tenant: $params->tenant,
        skipEmailVerification: true,  // la invitación ya verificó el email
    );

    // 3. Asignar rol si se especificó en la invitación
    if ($invitation->roleUid !== null) {
        $this->roleAssignment->assign($userUid, $invitation->roleUid, $params->tenant);
    }

    // 4. Marcar invitación como aceptada
    $this->writeGateway->markAccepted($invitation->uid, $userUid);

    // 5. Crear sesión OIDC directamente
    return $this->sessionManager->createTemporalCode(
        userUid: $userUid,
        clientId: $params->clientId,
        tenant: $params->tenant,
        scope: 'openid email profile',
        redirectUri: $params->redirectUri,
    );
}
```

### F. HTML Form — InvitationAcceptHtml

**Archivo:** `src/Features/Access/Invitation/Infrastructure/Driver/Html/InvitationAcceptHtml.php`

Página pública (no requiere sesión):
- `GET /openid/{tenant}/invitation/accept?token=xxx` → formulario con email pre-rellenado
- `POST /openid/{tenant}/invitation/accept` → llama `InvitationAcceptUsecase`
- Si éxito: redirige con `code` (si `client_id` y `redirect_uri` incluidos en el token) o a página de bienvenida

---

## Tests a incluir

### Test unitario — Invitation

- `canBeAccepted()` estado pending + no expirada → `true`
- `canBeAccepted()` estado accepted → `false`
- `canBeAccepted()` expirada → `false`

### Test unitario — InvitationCreateUsecase

Con mocks:
- Email nuevo + admin válido → invitación creada, email enviado
- Invitación pendiente existente no expirada → excepción
- Sin permisos → excepción de autorización

### Test unitario — InvitationAcceptUsecase

Con mocks:
- Token válido + contraseña → usuario creado, rol asignado, invitación marcada accepted
- Token expirado → excepción
- Token inválido → excepción
- Token correcto pero invitación ya accepted → excepción

### Test integración — Invitation endpoints

- `POST /api/access/invitations` admin → `201` con uid y expires_at
- `POST /api/access/invitations` usuario normal → `403`
- `GET /api/access/invitations` → lista con estado correcto
- `GET /openid/{tenant}/invitation/accept?token=válido` → `200` formulario HTML
- `POST /openid/{tenant}/invitation/accept` con contraseña → redirect con code
- Code obtenido → intercambiable por tokens → usuario autenticado
- `DELETE /api/access/invitations/{uid}` → `204`, token ya no funciona
