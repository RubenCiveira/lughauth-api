# 03-03 — MFA por SMS y Email OTP

**Prioridad:** P3  
**Esfuerzo estimado:** Medio (3-4 días)  
**Dependencias previas:** `Notification/Outbox` para OTP email  
**Habilita:** segundo factor para usuarios sin smartphone (TOTP)

---

## Contexto

El sistema actual solo soporta TOTP (Google Authenticator). SMS y Email OTP son
métodos de segundo factor más accesibles para usuarios menos técnicos, aunque
ligeramente menos seguros que TOTP.

---

## Qué implementar

### Nuevos métodos MFA

- **Email OTP:** código de 6 dígitos enviado al email del usuario, TTL 10 minutos
- **SMS OTP:** código de 6 dígitos enviado por SMS vía proveedor configurable por tenant

### Tabla multi-método

Un usuario puede tener múltiples métodos MFA registrados y elegir cuál usar.

---

## Dónde y cómo hacer los cambios

### A. Migración

```sql
-- Tabla de métodos MFA del usuario (reemplaza MFA seed en access_user)
CREATE TABLE IF NOT EXISTS access_user_mfa_method (
  uid        VARCHAR(36)   NOT NULL,
  tenant_id  VARCHAR(36)   NOT NULL,
  user_uid   VARCHAR(36)   NOT NULL,
  method     ENUM('totp','email','sms') NOT NULL,
  identifier VARCHAR(200)  NULL,          -- email o phone, NULL para TOTP
  secret     TEXT          NULL,          -- TOTP seed encriptado, NULL para email/sms
  enabled    TINYINT(1)    NOT NULL DEFAULT 1,
  verified   TINYINT(1)    NOT NULL DEFAULT 0,
  created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (uid),
  INDEX idx_mfa_user (user_uid, tenant_id),
  UNIQUE KEY uk_mfa_method (user_uid, tenant_id, method)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de códigos OTP temporales (email y SMS)
CREATE TABLE IF NOT EXISTS _mfa_otp_code (
  uid        VARCHAR(36)  NOT NULL,
  tenant_id  VARCHAR(36)  NOT NULL,
  user_uid   VARCHAR(36)  NOT NULL,
  method     ENUM('email','sms') NOT NULL,
  code_hash  VARCHAR(64)  NOT NULL,
  attempts   TINYINT      NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME     NOT NULL,
  used_at    DATETIME     NULL,
  PRIMARY KEY (uid),
  INDEX idx_otp_user    (user_uid, tenant_id),
  INDEX idx_otp_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Configuración SMS por tenant
CREATE TABLE IF NOT EXISTS access_tenant_sms_config (
  uid        VARCHAR(36)   NOT NULL,
  tenant_id  VARCHAR(36)   NOT NULL UNIQUE,
  provider   VARCHAR(50)   NOT NULL,     -- 'twilio', 'aws_sns', 'vonage'
  account_sid VARCHAR(100) NOT NULL,
  auth_token  TEXT         NOT NULL,     -- encriptado
  from_number VARCHAR(20)  NOT NULL,
  enabled     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### B. Interfaz SmsProvider

**Archivo nuevo:** `src/Features/Oidc/Mfa/Domain/Gateway/SmsProviderGateway.php`

```php
interface SmsProviderGateway
{
    public function send(string $phoneNumber, string $message, string $tenant): void;
}
```

**Adapters:**
- `src/Features/Oidc/Mfa/Infrastructure/Driven/TwilioSmsAdapter.php`
- `src/Features/Oidc/Mfa/Infrastructure/Driven/AwsSnsAdapter.php`

### C. Application — OtpMfaUsecase

**Archivo nuevo:** `src/Features/Oidc/Mfa/Application/Usecase/OtpMfa.php`

```php
public function sendOtp(string $userUid, string $method, string $tenant): void
{
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash = hash('sha256', $code);

    // Almacenar código hasheado
    $this->otpGateway->store(new OtpCode(
        uid: Uuid::uuid4()->toString(),
        tenant: $tenant,
        userUid: $userUid,
        method: $method,
        codeHash: $hash,
        expiresAt: new \DateTimeImmutable('+10 minutes'),
    ));

    $user = $this->userLoader->load($userUid, $tenant);

    if ($method === 'email') {
        $this->notificationDispatch->send($user->email, 'mfa_otp', ['code' => $code], $tenant);
    } elseif ($method === 'sms') {
        $phone = $this->mfaMethodGateway->findPhoneForUser($userUid, $tenant);
        $this->smsProvider->send($phone, "Tu código de verificación: {$code}", $tenant);
    }
}

public function verifyOtp(string $userUid, string $code, string $tenant): bool
{
    $hash = hash('sha256', $code);
    $otp  = $this->otpGateway->findActiveByUser($userUid, $tenant);

    if ($otp === null || $otp->isExpired() || $otp->isUsed()) return false;
    if ($otp->attempts >= 3) {
        $this->otpGateway->markUsed($otp->uid);  // bloquear tras 3 intentos
        return false;
    }

    if (!hash_equals($otp->codeHash, $hash)) {
        $this->otpGateway->incrementAttempts($otp->uid);
        return false;
    }

    $this->otpGateway->markUsed($otp->uid);
    return true;
}
```

### D. HTML Forms — paso use-mfa adaptado

**Archivo:** `src/Features/Oidc/Authentication/Infrastructure/Driver/Html/Forms/UseMfaForm.php`

Modificar para soportar múltiples métodos:
- Si el usuario tiene múltiples métodos MFA → mostrar selector de método
- Para TOTP: formulario de código actual (sin cambios)
- Para Email/SMS: mostrar botón "Enviar código" → llama `OtpMfaUsecase::sendOtp()` → campo de código
- Verificar código con `OtpMfaUsecase::verifyOtp()` o `UserMfa::verify()` según método

### E. TenantConfig — métodos MFA habilitados

```sql
ALTER TABLE access_tenant_config
  ADD COLUMN mfa_methods_json TEXT NULL;  -- JSON: ["totp","email","sms"]
```

---

## Tests a incluir

### Test unitario — OtpMfaUsecase

Con mocks de `OtpGateway`, `NotificationDispatch`, `SmsProviderGateway`:

- `sendOtp('email')` → `notificationDispatch.send()` llamado, código guardado hasheado
- `sendOtp('sms')` → `smsProvider.send()` llamado
- `verifyOtp()` código correcto → `true`, OTP marcado como usado
- `verifyOtp()` código incorrecto → `false`, intentos incrementados
- `verifyOtp()` tras 3 intentos fallidos → OTP bloqueado, `false`
- `verifyOtp()` OTP expirado → `false`
- `verifyOtp()` OTP ya usado → `false`

### Test unitario — SmsProvider (mock de API externa)

- Twilio adapter llama a la API correcta con el número y mensaje
- Fallo de red → excepción capturada con mensaje claro

### Test integración — flujo MFA email en authorize

- Usuario con MFA email, login → paso `use-mfa` solicita código
- Click "Enviar código" → email enviado (mock SMTP)
- Código correcto → acceso concedido
- Código incorrecto 3 veces → bloqueo
