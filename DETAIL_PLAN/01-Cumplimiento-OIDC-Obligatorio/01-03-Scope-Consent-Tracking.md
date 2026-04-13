# 01-03 — Scope Consent Tracking

**Prioridad:** P0  
**Spec:** OIDC Core 1.0 §3.1.2.4 — Consent  
**Esfuerzo estimado:** Medio (3-4 días)  
**Dependencias previas:** ninguna  
**Habilita:** 06-03 Gestión de Consentimientos GDPR

---

## Contexto

El módulo `src/Features/Oidc/Scopes/` existe pero es un stub:
- `ScopesConsentAdapter.php` devuelve `[]` sin consultar la base de datos
- `ScopesConsentForm.php` existe en el HTML driver pero el formulario no persiste
- El authorize flow tiene el paso `scopes-consent` registrado pero nunca guarda la decisión

Resultado: cada login muestra el formulario de scopes aunque el usuario ya haya consentido.

---

## Qué implementar

### 1. Persistencia de consentimientos

Tabla para guardar la decisión por (usuario, cliente, tenant, scope).

### 2. Lógica de presentación inteligente

El paso `scopes-consent` solo debe aparecer si hay scopes **nuevos** que el usuario
no ha consentido previamente. Si ya existe consentimiento para todos los scopes solicitados,
el paso se salta automáticamente (salvo que `prompt=consent` en la request).

### 3. Revocación de consentimiento

API para que el usuario pueda revocar el consentimiento a un cliente específico.

---

## Dónde y cómo hacer los cambios

### A. Migración — tabla _oauth_scope_consent

**Archivo nuevo:** `migrations/mysql/schema/YYYYMMDDHHMMSS_CreateScopeConsent.php`

```sql
CREATE TABLE IF NOT EXISTS _oauth_scope_consent (
  uid         VARCHAR(36)  NOT NULL,
  tenant_id   VARCHAR(36)  NOT NULL,
  user_uid    VARCHAR(36)  NOT NULL,
  client_uid  VARCHAR(36)  NOT NULL,
  scope       VARCHAR(100) NOT NULL,
  granted     TINYINT(1)   NOT NULL DEFAULT 1,
  granted_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at  DATETIME     NULL,
  PRIMARY KEY (uid),
  UNIQUE KEY uk_consent (tenant_id, user_uid, client_uid, scope),
  INDEX idx_consent_user   (user_uid, tenant_id),
  INDEX idx_consent_client (client_uid, tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### B. Dominio — ScopePermission actualizado

**Archivo:** `src/Features/Oidc/Scopes/Domain/ScopePermission.php`

Añadir campos:
```php
final class ScopePermission
{
    public function __construct(
        public readonly string $scope,
        public readonly bool $granted,
        public readonly ?\DateTimeImmutable $grantedAt = null,
        public readonly ?\DateTimeImmutable $revokedAt = null,
    ) {}

    public function isActive(): bool
    {
        return $this->granted && $this->revokedAt === null;
    }
}
```

### C. Dominio — Gateway actualizado

**Archivo:** `src/Features/Oidc/Scopes/Domain/Gateway/ScopesConsentGateway.php`

Reemplazar interfaz stub con:
```php
interface ScopesConsentGateway
{
    /** @return string[] scopes ya consentidos (activos) */
    public function findGrantedScopes(string $userUid, string $clientUid, string $tenant): array;

    /** Persiste los scopes concedidos en esta sesión */
    public function grantScopes(string $userUid, string $clientUid, string $tenant, array $scopes): void;

    /** Revoca todos los consentimientos de un usuario para un cliente */
    public function revokeConsent(string $userUid, string $clientUid, string $tenant): void;

    /** @return array<string, ScopePermission[]> por cliente */
    public function findAllConsentsForUser(string $userUid, string $tenant): array;
}
```

### D. Application — ScopesConsentUsecase completo

**Archivo:** `src/Features/Oidc/Scopes/Application/Usecase/ScopesConsentUsecase.php`

Tres métodos principales:

```php
/**
 * Devuelve los scopes que AÚN NO tienen consentimiento activo.
 * Si todos están consentidos → devuelve [].
 */
public function getPendingScopes(string $userUid, string $clientUid, string $tenant, array $requestedScopes): array;

/**
 * Persiste la decisión del usuario (scopes granted/denied del formulario).
 */
public function saveConsent(string $userUid, string $clientUid, string $tenant, array $grantedScopes): void;

/**
 * Revoca todos los consentimientos de un usuario para un cliente.
 */
public function revokeConsent(string $userUid, string $clientUid, string $tenant): void;
```

### E. Infrastructure — ScopesConsentAdapter (implementación real)

**Archivo:** `src/Features/Oidc/Scopes/Infrastructure/Driven/ScopesConsentAdapter.php`

Reemplazar stub con implementación real usando PDO:

```php
public function findGrantedScopes(string $userUid, string $clientUid, string $tenant): array
{
    $sql = 'SELECT scope FROM _oauth_scope_consent
            WHERE user_uid = ? AND client_uid = ? AND tenant_id = ?
              AND granted = 1 AND revoked_at IS NULL';
    // ...
}

public function grantScopes(string $userUid, string $clientUid, string $tenant, array $scopes): void
{
    // INSERT ... ON DUPLICATE KEY UPDATE granted = 1, revoked_at = NULL, granted_at = NOW()
}
```

### F. Authorize Flow — integración en paso scopes-consent

**Archivo:** `src/Features/Oidc/Authentication/Infrastructure/Driver/Html/Forms/ScopesConsentForm.php`

En el método que decide si mostrar el paso:
```php
// Obtener scopes pedidos por el cliente
$requestedScopes = explode(' ', $context->scope);
// Filtrar 'openid' que no necesita consentimiento explícito
$consentableScopes = array_diff($requestedScopes, ['openid']);
// Consultar cuáles ya están consentidos
$pendingScopes = $this->scopesConsentUsecase->getPendingScopes(
    $userUid, $clientUid, $context->tenant, $consentableScopes
);
// Si no hay pendientes y prompt != 'consent' → saltar paso
if (empty($pendingScopes) && $context->prompt !== 'consent') {
    return StepResult::skip();
}
```

En el método que procesa el submit del formulario:
```php
$grantedScopes = $input->get('granted_scopes', []);
$this->scopesConsentUsecase->saveConsent($userUid, $clientUid, $context->tenant, $grantedScopes);
```

### G. API de revocación de consentimiento

**Archivo nuevo:** `src/Features/Oidc/Scopes/Infrastructure/Driver/Rest/ScopeConsentController.php`

```
DELETE /openid/{tenant}/consent/{client_uid}
Authorization: Bearer <access_token>
```

Respuesta: `204 No Content`

Registrar en el Plugin de Oidc/Scopes correspondiente.

### H. Discovery — scopes_supported

**Archivo:** `src/Features/Oidc/Common/Infrastructure/Driver/Rest/OpenIdConfigurationController.php`

Asegurarse de incluir:
```php
'scopes_supported' => ['openid', 'email', 'profile', 'offline_access'],
'claims_supported' => ['sub', 'iss', 'aud', 'exp', 'iat', 'email', 'name', 'given_name', 'family_name'],
```

---

## Tests a incluir

### Test unitario — ScopePermission

**Archivo:** `test/Features/Oidc/Scopes/Domain/ScopePermissionUnitTest.php`

Casos:
- `isActive()` cuando `granted=true` y `revokedAt=null` → `true`
- `isActive()` cuando `revokedAt` tiene fecha → `false`
- `isActive()` cuando `granted=false` → `false`

### Test unitario — ScopesConsentUsecase

**Archivo:** `test/Features/Oidc/Scopes/Application/Usecase/ScopesConsentUsecaseUnitTest.php`

Con mock de `ScopesConsentGateway`:

Casos `getPendingScopes`:
- User ya consintió todos los scopes → devuelve `[]`
- User consintió solo `email`, pide `email profile` → devuelve `['profile']`
- Primera vez, sin consentimientos → devuelve todos los scopes consentibles
- `prompt=consent` aunque todo esté consentido → devuelve todos los scopes

Casos `saveConsent`:
- Gateway llamado con los scopes correctos
- No llama gateway con scope `openid`

### Test integración — ScopesConsentAdapter

**Archivo:** `test/Features/Oidc/Scopes/Infrastructure/Driven/ScopesConsentAdapterIntegrationTest.php`

Requiere DB de test:
- `grantScopes()` → `findGrantedScopes()` devuelve los mismos scopes
- `grantScopes()` dos veces (idempotente) → sin duplicados
- `revokeConsent()` → `findGrantedScopes()` devuelve `[]`

### Test integración — Formulario scopes-consent

**Archivo:** `test/Features/Oidc/Authentication/Infrastructure/Driver/Html/Forms/ScopesConsentFormStepUnitTest.php`
*(ya existe, ampliar)*

Casos adicionales:
- Usuario con todos los scopes consentidos → `StepResult::skip()`
- Usuario con scopes nuevos → form renderizado con scopes pendientes
- Submit del formulario → `saveConsent()` llamado con los scopes marcados

### Test integración — DELETE /consent/{client_uid}

**Archivo:** `test/Features/Oidc/Scopes/Infrastructure/Driver/Rest/ScopeConsentControllerTest.php`

Casos:
- Token válido + cliente válido → `204`
- Token inválido → `401`
- Cliente inexistente → `404`
