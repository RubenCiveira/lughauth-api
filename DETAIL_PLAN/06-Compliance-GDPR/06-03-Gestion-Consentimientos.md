# 06-03 — Gestión de Consentimientos de Procesamiento (GDPR Art. 7)

**Prioridad:** P3  
**Esfuerzo estimado:** Medio (2-3 días)  
**Dependencias previas:** 01-03 Scope Consent Tracking (modelo de referencia)

---

## Contexto

GDPR Art. 7 requiere que el consentimiento para el procesamiento de datos personales sea:
- Libre (no condicionado al servicio)
- Específico (por cada propósito)
- Informado (con descripción clara)
- Inequívoco (acción afirmativa explícita)
- Revocable (en cualquier momento)

`access_user_accepted_termns_of_use` solo gestiona T&C pero no propósitos específicos
de procesamiento de datos.

---

## Qué implementar

### Propósitos de consentimiento (configurables por tenant)

- `marketing` — comunicaciones de marketing
- `analytics` — análisis de uso
- `third_party` — compartir con terceros
- `personalization` — personalización de experiencia

### API

```
GET /api/me/consents                # Estado de todos los consentimientos del usuario
PUT /api/me/consents/{purpose}      # Otorgar o revocar un consentimiento
GET /api/me/consents/history        # Historial de cambios de consentimiento

# Admin — gestión de propósitos del tenant
GET/POST      /api/access/consent-purposes
PUT/DELETE    /api/access/consent-purposes/{uid}
```

---

## Dónde y cómo hacer los cambios

### A. Migración

```sql
-- Definición de propósitos por tenant
CREATE TABLE IF NOT EXISTS access_consent_purpose (
  uid         VARCHAR(36)   NOT NULL,
  tenant_id   VARCHAR(36)   NOT NULL,
  purpose_key VARCHAR(100)  NOT NULL,
  name        VARCHAR(200)  NOT NULL,
  description TEXT          NOT NULL,
  required    TINYINT(1)    NOT NULL DEFAULT 0,  -- si es obligatorio, no se puede revocar
  enabled     TINYINT(1)    NOT NULL DEFAULT 1,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (uid),
  UNIQUE KEY uk_purpose_tenant (tenant_id, purpose_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Consentimientos de usuarios
CREATE TABLE IF NOT EXISTS access_user_consent (
  uid          VARCHAR(36)   NOT NULL,
  tenant_id    VARCHAR(36)   NOT NULL,
  user_uid     VARCHAR(36)   NOT NULL,
  purpose_key  VARCHAR(100)  NOT NULL,
  granted      TINYINT(1)    NOT NULL DEFAULT 0,
  version      VARCHAR(20)   NOT NULL,           -- versión del propósito al consentir
  granted_at   DATETIME      NULL,
  revoked_at   DATETIME      NULL,
  ip_address   VARCHAR(45)   NULL,
  user_agent   VARCHAR(500)  NULL,
  PRIMARY KEY (uid),
  UNIQUE KEY uk_user_consent (tenant_id, user_uid, purpose_key),
  INDEX idx_consent_user (user_uid, tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### B. Nuevo sub-feature: src/Features/Access/UserConsent/

```
src/Features/Access/UserConsent/
├── Domain/
│   ├── UserConsent.php
│   ├── ConsentPurpose.php
│   └── Gateway/
│       ├── UserConsentReadGateway.php
│       └── UserConsentWriteGateway.php
├── Application/
│   └── Usecase/
│       ├── GetUserConsents/
│       │   └── GetUserConsentsUsecase.php
│       ├── UpdateUserConsent/
│       │   └── UpdateUserConsentUsecase.php
│       └── GetConsentHistory/
│           └── GetConsentHistoryUsecase.php
└── Infrastructure/...
```

### C. Integración en authorize flow

En el paso `consent` del authorize flow (formulario de T&C), ampliar para mostrar
también los propósitos de consentimiento que aún no han sido decididos:

```php
// En ConsentForm.php
$pendingPurposes = $this->consentUsecase->getPendingPurposes($userUid, $tenant);
if (!empty($pendingPurposes)) {
    // Añadir formulario de consentimientos al paso consent
}
```

### D. Claim en JWT

Si el scope `consents` está concedido, incluir en el access token:
```json
{
  "consents": {
    "marketing": true,
    "analytics": false,
    "third_party": false
  }
}
```

---

## Tests a incluir

### Test unitario — UpdateUserConsentUsecase

Con mocks:
- Otorgar consentimiento no requerido → `granted = true`
- Revocar consentimiento requerido → excepción (no se puede revocar)
- Registra `ip_address` y `user_agent` de la request
- Cambio de consentimiento crea nuevo registro en historial

### Test integración

- `GET /api/me/consents` → lista todos los propósitos del tenant con estado del usuario
- `PUT /api/me/consents/marketing` `{ granted: false }` → `200`
- `PUT /api/me/consents/required_purpose` `{ granted: false }` → `422` (no revocable)
- `GET /api/me/consents/history` → historial ordenado por fecha desc
- Access token con scope `consents` → claim `consents` en JWT
