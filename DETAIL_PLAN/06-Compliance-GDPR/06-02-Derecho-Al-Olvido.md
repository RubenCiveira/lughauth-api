# 06-02 — Derecho al Olvido (GDPR Art. 17)

**Prioridad:** P3  
**Esfuerzo estimado:** Medio (3 días)  
**Dependencias previas:** 06-01 Exportación (para inventario de tablas)

---

## Contexto

GDPR Art. 17 requiere que el usuario pueda solicitar la eliminación de todos sus
datos personales. Para datos requeridos por auditoría legal, se permite
anonimización en lugar de borrado.

---

## Qué implementar

```
POST /api/me/account/delete-request   # Solicitar eliminación (requiere verificación)
GET  /api/me/account/delete-request   # Estado de la solicitud
POST /api/me/account/delete-confirm   # Confirmar con código de email
```

---

## Dónde y cómo hacer los cambios

### A. Migración

```sql
CREATE TABLE IF NOT EXISTS access_deletion_request (
  uid           VARCHAR(36)  NOT NULL,
  tenant_id     VARCHAR(36)  NOT NULL,
  user_uid      VARCHAR(36)  NOT NULL,
  status        ENUM('pending_confirmation','confirmed','processing','completed','cancelled') NOT NULL DEFAULT 'pending_confirmation',
  confirmation_code_hash VARCHAR(64) NULL,
  confirmation_expires_at DATETIME   NULL,
  requested_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  confirmed_at  DATETIME     NULL,
  completed_at  DATETIME     NULL,
  PRIMARY KEY (uid),
  INDEX idx_deletion_user (user_uid, tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### B. Application — RequestDeletionUsecase

```php
public function request(string $userUid, string $tenant): void
{
    // 1. Verificar que no hay solicitud pendiente
    $existing = $this->readGateway->findPendingByUser($userUid, $tenant);
    if ($existing !== null) return;

    // 2. Generar código de confirmación
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash = hash('sha256', $code);

    $request = new DeletionRequest(
        uid:              Uuid::uuid4()->toString(),
        tenant:           $tenant,
        userUid:          $userUid,
        status:           DeletionStatus::PendingConfirmation,
        codeHash:         $hash,
        codeExpiresAt:    new \DateTimeImmutable('+15 minutes'),
        requestedAt:      new \DateTimeImmutable(),
    );
    $this->writeGateway->store($request);

    // 3. Enviar código de confirmación por email
    $user = $this->userLoader->load($userUid, $tenant);
    $this->notificationDispatch->send(
        to: $user->email,
        templateKey: 'account_deletion_confirm',
        variables: ['code' => $code, 'expires_in_minutes' => 15],
        tenant: $tenant,
    );
}
```

### C. Application — ProcessDeletionUsecase

Borrado en cascada o anonimización:

```php
public function process(string $deletionRequestUid, string $tenant): void
{
    $req = $this->readGateway->findById($deletionRequestUid, $tenant);
    $userUid = $req->userUid;

    // === BORRADO DEFINITIVO ===
    $this->scopeConsentGateway->deleteByUser($userUid, $tenant);
    $this->sessionGateway->revokeAll($userUid, $tenant);
    $this->patGateway->revokeAllByUser($userUid, $tenant);
    $this->webAuthnGateway->deleteByUser($userUid, $tenant);
    $this->mfaMethodGateway->deleteByUser($userUid, $tenant);
    $this->magicLinkGateway->deleteByUser($userUid, $tenant);
    $this->orgMemberGateway->removeUser($userUid, $tenant);
    $this->profileGateway->deleteByUser($userUid, $tenant);

    // === ANONIMIZACIÓN (datos de auditoría — no se pueden borrar) ===
    $anonymousId = 'deleted_user_' . substr(hash('sha256', $userUid), 0, 8);
    $this->userWriteGateway->anonymize($userUid, $tenant, [
        'email'    => $anonymousId . '@deleted.invalid',
        'name'     => '[Deleted User]',
        'password' => null,
        'mfa_seed' => null,
    ]);

    // Anonimizar en audit log
    $this->auditGateway->anonymizeActor($userUid, $anonymousId, $tenant);

    $this->writeGateway->markCompleted($deletionRequestUid);

    // Nota: La cuenta queda deshabilitada, no se puede hacer login con ella
}
```

### D. Retención mínima

Los datos de auditoría (`_audit_action`, `_audit_change`) se anonimizan pero no se
borran hasta cumplir el período de retención legal (configurable, mínimo 90 días
para muchas regulaciones).

---

## Tests a incluir

### Test unitario — ProcessDeletionUsecase

Con mocks:
- Todos los gateways de borrado son llamados
- `anonymize()` es llamado en lugar de `delete()` para datos de auditoría
- Email del usuario reemplazado por `@deleted.invalid`
- Tras la eliminación, `access_user.enabled = false`

### Test integración

- `POST /api/me/account/delete-request` → `202`, email enviado
- `POST /api/me/account/delete-confirm` con código correcto → `200`, proceso iniciado
- `POST /api/me/account/delete-confirm` con código incorrecto → `400`
- Tras eliminación completa, login con ese usuario → `401`
- Tokens emitidos antes de la eliminación → `401` (sesiones revocadas)
