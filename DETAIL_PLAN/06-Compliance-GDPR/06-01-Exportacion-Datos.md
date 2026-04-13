# 06-01 — Exportación de Datos (GDPR Art. 15 / Art. 20)

**Prioridad:** P3  
**Esfuerzo estimado:** Medio (3 días)  
**Dependencias previas:** `_long_tasks` (ya existe), `Notification/Outbox` (ya existe)

---

## Contexto

GDPR Art. 15 (derecho de acceso) y Art. 20 (portabilidad) requieren que el usuario
pueda obtener todos sus datos personales en un formato legible y exportable.

---

## Qué implementar

```
POST /api/me/data-export           # Solicitar exportación (async)
GET  /api/me/data-export/{task_id} # Estado de la tarea
GET  /api/me/data-export/{task_id}/download  # Descargar ZIP (cuando esté listo)
```

---

## Dónde y cómo hacer los cambios

### A. Migración

```sql
CREATE TABLE IF NOT EXISTS access_data_export_request (
  uid         VARCHAR(36)  NOT NULL,
  tenant_id   VARCHAR(36)  NOT NULL,
  user_uid    VARCHAR(36)  NOT NULL,
  status      ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  file_path   VARCHAR(500) NULL,         -- ruta del ZIP generado
  file_size   BIGINT       NULL,
  requested_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME    NULL,
  download_count INT       NOT NULL DEFAULT 0,
  expires_at  DATETIME     NULL,          -- el archivo se elimina después de X días
  PRIMARY KEY (uid),
  INDEX idx_export_user (user_uid, tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### B. Nuevo sub-feature: src/Features/Access/DataExport/

```
src/Features/Access/DataExport/
├── Domain/
│   ├── DataExportRequest.php
│   └── Gateway/
│       ├── DataExportReadGateway.php
│       └── DataExportWriteGateway.php
├── Application/
│   ├── Usecase/
│   │   ├── RequestDataExport/
│   │   │   └── RequestDataExportUsecase.php
│   │   └── ProcessDataExport/
│   │       └── ProcessDataExportUsecase.php  # genera el ZIP
└── Infrastructure/...
```

### C. Application — ProcessDataExportUsecase

```php
public function process(string $exportRequestUid, string $tenant): void
{
    $exportRequest = $this->readGateway->findById($exportRequestUid, $tenant);
    $userUid = $exportRequest->userUid;

    // Recopilar todos los datos del usuario
    $data = [
        'profile'         => $this->userLoader->load($userUid, $tenant),
        'sessions'        => $this->sessionGateway->findByUser($userUid, $tenant),
        'scope_consents'  => $this->scopeConsentGateway->findAllConsentsForUser($userUid, $tenant),
        'api_keys'        => $this->patGateway->findByUser($userUid, $tenant),
        'audit_log'       => $this->auditLogGateway->findByActor($userUid, $tenant),
        'invitations_sent'=> $this->invitationGateway->findByInviter($userUid, $tenant),
    ];

    // Serializar como JSON
    $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    // Crear ZIP
    $zipPath = "/tmp/export_{$exportRequestUid}.zip";
    $zip = new \ZipArchive();
    $zip->open($zipPath, \ZipArchive::CREATE);
    $zip->addFromString('profile.json',     json_encode($data['profile'], JSON_PRETTY_PRINT));
    $zip->addFromString('sessions.json',    json_encode($data['sessions'], JSON_PRETTY_PRINT));
    $zip->addFromString('consents.json',    json_encode($data['scope_consents'], JSON_PRETTY_PRINT));
    $zip->addFromString('audit_log.json',   json_encode($data['audit_log'], JSON_PRETTY_PRINT));
    $zip->addFromString('all_data.json',    $jsonContent);
    $zip->close();

    // Guardar ruta y notificar por email
    $this->writeGateway->markCompleted($exportRequestUid, $zipPath, filesize($zipPath));

    $user = $this->userLoader->load($userUid, $tenant);
    $downloadUrl = "{$this->baseUrl}/api/me/data-export/{$exportRequestUid}/download";
    $this->notificationDispatch->send(
        to: $user->email,
        templateKey: 'data_export_ready',
        variables: ['download_url' => $downloadUrl, 'expires_in_days' => 7],
        tenant: $tenant,
    );
}
```

### D. Limitación de solicitudes

Un usuario solo puede tener una exportación activa a la vez:
```php
$existing = $this->readGateway->findActiveByUser($userUid, $tenant);
if ($existing !== null) {
    throw new \DomainException('An export is already in progress');
}
```

---

## Tests a incluir

### Test unitario — ProcessDataExportUsecase

Con mocks:
- Todos los gateways devuelven datos → ZIP generado, `markCompleted` llamado, email enviado
- Exportación doble → excepción

### Test integración

- `POST /api/me/data-export` → `202` con task_id
- `GET /api/me/data-export/{task_id}` → status `completed`
- `GET /api/me/data-export/{task_id}/download` → ZIP descargado
- ZIP contiene `all_data.json` con datos del usuario
