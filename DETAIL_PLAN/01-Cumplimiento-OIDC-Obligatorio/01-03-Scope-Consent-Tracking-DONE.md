# 01-03 — Scope Consent Tracking

[x] DONE

**Prioridad:** P0  
**Spec:** OIDC Core 1.0 §3.1.2.4 — Consent  
**Esfuerzo estimado:** Medio-Alto (4-5 días)  
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

## Decisión arquitectónica

La persistencia del consentimiento de scopes **no es un concern del protocolo OIDC**; es un
dato del usuario dentro del contexto `Access`. Por tanto:

- Se crea `Features/Access/UserConsentedScopes/` como bounded context completo, siguiendo
  el mismo patrón que `Features/Access/UserAcceptedTermnsOfUse/` (modelo autogenerado,
  gateways read/write, eventos de dominio).
- El `ScopesConsentGateway` en `Oidc/Scopes` actúa como capa anti-corrupción: el adaptador
  delega al usecase de `UserConsentedScopes` en lugar de tocar la base de datos directamente.
- La revocación de consentimientos se expone como **página HTML de gestión** (no solo REST),
  unificando en una sola pantalla los consentimientos de scopes y los términos de uso
  aceptados (`UserAcceptedTermnsOfUse`). Esto sirve de base para 06-03 GDPR.

```
Features/Access/UserConsentedScopes/   ← modelo, persistencia, CRUD, eventos
Features/Oidc/Scopes/                  ← protocolo OIDC, delega al contexto anterior
```

---

## Qué implementar

### 1. Persistencia de consentimientos

Bounded context `UserConsentedScopes` con tabla para (usuario, cliente, tenant, scope).

### 2. Lógica de presentación inteligente

El paso `scopes-consent` solo aparece si hay scopes **nuevos** que el usuario no ha
consentido previamente. Si ya existe consentimiento para todos los scopes solicitados,
el paso se salta (salvo `prompt=consent`).

### 3. Página HTML de gestión de consentimientos

Ruta autenticada donde el usuario ve y revoca todos sus consentimientos activos:
scopes concedidos por aplicación y términos de uso aceptados.

---

## Dónde y cómo hacer los cambios

### A. Migración — tabla access_user_consented_scope

**Archivo nuevo:** `migrations/mysql/schema/YYYYMMDDHHMMSS_CreateUserConsentedScope.php`

```sql
CREATE TABLE IF NOT EXISTS access_user_consented_scope (
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

### B. Bounded context — Features/Access/UserConsentedScopes/

Siguiendo el patrón de `UserAcceptedTermnsOfUse`, generar el modelo completo:

```
src/Features/Access/UserConsentedScopes/
├── Domain/
│   ├── UserConsentedScope.php            ← entidad (uid, user, client, scope, grantedAt, revokedAt)
│   ├── UserConsentedScopeRef.php
│   ├── UserConsentedScopeAttributes.php
│   ├── Event/
│   │   ├── UserConsentedScopeEvent.php
│   │   ├── UserConsentedScopeCreateEvent.php
│   │   ├── UserConsentedScopeUpdateEvent.php
│   │   └── UserConsentedScopeDeleteEvent.php
│   ├── Gateway/
│   │   ├── UserConsentedScopeReadGateway.php
│   │   ├── UserConsentedScopeWriteGateway.php
│   │   ├── UserConsentedScopeFilter.php
│   │   ├── UserConsentedScopeCursor.php
│   │   ├── UserConsentedScopeSlide.php
│   │   └── UserConsentedScopeAttributesSlide.php
│   └── ValueObject/...                   ← UidVO, UserVO, ClientVO, ScopeVO, GrantedAtVO, RevokedAtVO, VersionVO
├── Application/
│   └── Usecase/
│       ├── Grant/UserConsentedScopeGrantUsecase.php    ← otorga uno o varios scopes
│       ├── Revoke/UserConsentedScopeRevokeUsecase.php  ← revoca todos los scopes de un cliente
│       ├── List/UserConsentedScopeListUsecase.php      ← scopes activos de un usuario (para la página)
│       └── Pending/UserConsentedScopePendingUsecase.php ← scopes aún no consentidos (para el flow OIDC)
├── Infrastructure/
│   ├── Driver/
│   │   ├── UserConsentedScopePlugin.php
│   │   └── Html/
│   │       └── UserConsentedScopeConsentPageController.php  ← ver sección F
│   ├── Driven/
│   │   ├── UserConsentedScopeReadRepositoryAdapter.php
│   │   └── UserConsentedScopeWriteRepositoryAdapter.php
│   └── Connector/Pdo/UserConsentedScopePdoConnector.php
```

Campos del modelo `UserConsentedScope`:
- `uid` — identificador de fila
- `user` — ref a `UserRef`
- `client` — ref a `ClientIdentityRef`
- `scope` — string (p.ej. `email`, `profile`)
- `grantedAt` — fecha de concesión
- `revokedAt` — fecha de revocación (null si activo)
- `version` — versión optimista

### C. Dominio Oidc — ScopePermission actualizado

**Archivo:** `src/Features/Oidc/Scopes/Domain/ScopePermission.php`

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

### D. Gateway Oidc — ScopesConsentGateway actualizado

**Archivo:** `src/Features/Oidc/Scopes/Domain/Gateway/ScopesConsentGateway.php`

```php
interface ScopesConsentGateway
{
    /** @return string[] scopes ya consentidos (activos) */
    public function findGrantedScopes(string $userUid, string $clientUid, string $tenant): array;

    /** Persiste los scopes concedidos en esta sesión */
    public function grantScopes(string $userUid, string $clientUid, string $tenant, array $scopes): void;

    /** Revoca todos los consentimientos de un usuario para un cliente */
    public function revokeConsent(string $userUid, string $clientUid, string $tenant): void;
}
```

### E. Infrastructure Oidc — ScopesConsentAdapter (capa anti-corrupción)

**Archivo:** `src/Features/Oidc/Scopes/Infrastructure/Driven/ScopesConsentAdapter.php`

El adaptador ya no habla con la DB directamente: delega en los usecases de
`UserConsentedScopes`.

```php
class ScopesConsentAdapter implements ScopesConsentGateway
{
    public function __construct(
        private readonly UserConsentedScopeGrantUsecase $grantUsecase,
        private readonly UserConsentedScopePendingUsecase $pendingUsecase,
        private readonly UserConsentedScopeRevokeUsecase $revokeUsecase,
    ) {}

    public function findGrantedScopes(string $userUid, string $clientUid, string $tenant): array
    {
        // delega a pendingUsecase para obtener los ya consentidos
    }

    public function grantScopes(string $userUid, string $clientUid, string $tenant, array $scopes): void
    {
        // delega a grantUsecase
    }

    public function revokeConsent(string $userUid, string $clientUid, string $tenant): void
    {
        // delega a revokeUsecase
    }
}
```

### F. Application Oidc — ScopesConsentUsecase completo

**Archivo:** `src/Features/Oidc/Scopes/Application/Usecase/ScopesConsentUsecase.php`

```php
/**
 * Devuelve los scopes que AÚN NO tienen consentimiento activo.
 * Si todos están consentidos → devuelve [].
 */
public function getPendingScopes(string $userUid, string $clientUid, string $tenant, array $requestedScopes): array;

/**
 * Persiste la decisión del usuario (scopes granted del formulario).
 */
public function saveConsent(string $userUid, string $clientUid, string $tenant, array $grantedScopes): void;

/**
 * Revoca todos los consentimientos de un usuario para un cliente.
 */
public function revokeConsent(string $userUid, string $clientUid, string $tenant): void;
```

### G. Authorize Flow — integración en paso scopes-consent

**Archivo:** `src/Features/Oidc/Authentication/Infrastructure/Driver/Html/Forms/ScopesConsentForm.php`

En el método que decide si mostrar el paso:
```php
$requestedScopes = explode(' ', $context->scope);
$consentableScopes = array_diff($requestedScopes, ['openid']);
$pendingScopes = $this->scopesConsentUsecase->getPendingScopes(
    $userUid, $clientUid, $context->tenant, $consentableScopes
);
if (empty($pendingScopes) && $context->prompt !== 'consent') {
    return StepResult::skip();
}
```

En el método que procesa el submit:
```php
$grantedScopes = $input->get('granted_scopes', []);
$this->scopesConsentUsecase->saveConsent($userUid, $clientUid, $context->tenant, $grantedScopes);
```

### H. Página HTML de gestión de consentimientos

**Archivo nuevo:** `src/Features/Access/UserConsentedScopes/Infrastructure/Driver/Html/UserConsentedScopeConsentPageController.php`

Ruta: `GET /account/{tenant}/consents` (autenticada con sesión activa)

La página muestra dos secciones:

**Sección 1 — Aplicaciones con acceso**
Por cada cliente con scopes consentidos activos:
- Nombre del cliente, fecha de primer acceso
- Lista de scopes concedidos con descripción humana
- Botón "Revocar acceso" → `POST /account/{tenant}/consents/{client_uid}/revoke`

**Sección 2 — Términos de uso aceptados**
Lee de `UserAcceptedTermnsOfUse` (read-only, con fecha de aceptación por versión).

Esta página es la base sobre la que 06-03 añadirá los propósitos GDPR como sección 3.

### I. Discovery — scopes_supported

**Archivo:** `src/Features/Oidc/Common/Infrastructure/Driver/Rest/OpenIdConfigurationController.php`

```php
'scopes_supported' => ['openid', 'email', 'profile', 'offline_access'],
'claims_supported' => ['sub', 'iss', 'aud', 'exp', 'iat', 'email', 'name', 'given_name', 'family_name'],
```

---

## Tests a incluir

### Test unitario — ScopePermission

**Archivo:** `test/Features/Oidc/Scopes/Domain/ScopePermissionUnitTest.php`

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

### Test unitario — UserConsentedScopePendingUsecase

**Archivo:** `test/Features/Access/UserConsentedScopes/Application/Usecase/Pending/UserConsentedScopePendingUsecaseUnitTest.php`

- Devuelve solo los scopes sin registro activo en gateway
- Ignora registros con `revokedAt != null`

### Test integración — UserConsentedScopeReadRepositoryAdapter

**Archivo:** `test/Features/Access/UserConsentedScopes/Infrastructure/Driven/UserConsentedScopeReadRepositoryAdapterIntegrationTest.php`

Requiere DB de test:
- `grant` → `findGranted` devuelve los mismos scopes
- `grant` dos veces (idempotente) → sin duplicados
- `revoke` → `findGranted` devuelve `[]`

### Test integración — Formulario scopes-consent

**Archivo:** `test/Features/Oidc/Authentication/Infrastructure/Driver/Html/Forms/ScopesConsentFormStepUnitTest.php`
*(ya existe, ampliar)*

- Usuario con todos los scopes consentidos → `StepResult::skip()`
- Usuario con scopes nuevos → form renderizado con scopes pendientes
- Submit del formulario → `saveConsent()` llamado con los scopes marcados

### Test integración — Página de gestión

**Archivo:** `test/Features/Access/UserConsentedScopes/Infrastructure/Driver/Html/UserConsentedScopeConsentPageControllerTest.php`

- `GET /account/{tenant}/consents` sin sesión → redirect a login
- `GET /account/{tenant}/consents` con sesión → lista clientes y scopes
- `POST /account/{tenant}/consents/{client_uid}/revoke` → revoca y redirige a la misma página
- La sección de términos de uso muestra las aceptaciones del usuario
