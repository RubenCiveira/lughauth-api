# 05-03 — Feature Flags por Tenant

**Prioridad:** P3  
**Esfuerzo estimado:** Medio (2-3 días)  
**Dependencias previas:** ninguna

---

## Contexto

Los feature flags permiten habilitar/deshabilitar funcionalidades por tenant
sin necesidad de despliegues. `access_tenant_config` ya gestiona flags de sistema
(MFA, registro, etc.) pero no tiene una API dinámica para flags personalizados.

---

## Qué implementar

### API de gestión (admin)

```
GET/POST      /api/access/feature-flags
GET/PUT/DELETE /api/access/feature-flags/{uid}
```

### API de evaluación (SDK)

```
GET /api/flags                    # Todos los flags del tenant (autenticado)
GET /api/flags/{key}              # Flag específico
POST /api/flags/evaluate-batch    # Evaluar múltiples flags con contexto de usuario
```

---

## Dónde y cómo hacer los cambios

### A. Migración

```sql
CREATE TABLE IF NOT EXISTS access_feature_flag (
  uid                  VARCHAR(36)   NOT NULL,
  tenant_id            VARCHAR(36)   NOT NULL,
  flag_key             VARCHAR(100)  NOT NULL,
  enabled              TINYINT(1)    NOT NULL DEFAULT 0,
  rollout_percentage   TINYINT       NOT NULL DEFAULT 100,  -- 0-100%
  conditions_json      TEXT          NULL,  -- condiciones adicionales
  description          VARCHAR(500)  NULL,
  created_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (uid),
  UNIQUE KEY uk_flag_key_tenant (tenant_id, flag_key),
  INDEX idx_flag_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### B. Nuevo sub-feature: src/Features/Access/FeatureFlag/

```
src/Features/Access/FeatureFlag/
├── Domain/
│   ├── FeatureFlag.php
│   └── Gateway/
│       ├── FeatureFlagReadGateway.php
│       └── FeatureFlagWriteGateway.php
├── Application/
│   └── Usecase/
│       ├── EvaluateFlag/
│       │   └── EvaluateFlagUsecase.php
│       └── {Create,Update,Delete,List}/...
└── Infrastructure/
    ├── Driven/
    │   └── FeatureFlagSqlAdapter.php
    └── Driver/
        └── Rest/
            └── FeatureFlagController.php
```

### C. Application — EvaluateFlagUsecase

```php
public function evaluate(string $key, string $tenant, string $userUid): bool
{
    $flag = $this->gateway->findByKey($key, $tenant);

    if ($flag === null || !$flag->enabled) return false;

    // Rollout parcial por porcentaje (determinista por user_uid)
    if ($flag->rolloutPercentage < 100) {
        $hash    = crc32($userUid . $key) % 100;
        $enabled = $hash < $flag->rolloutPercentage;
        return $enabled;
    }

    return true;
}
```

### D. Caching

Los flags deben cachearse (Redis/Symfony Cache existente) por tenant con TTL de 60s:
```php
$cacheKey = "feature_flags_{$tenant}";
$flags = $this->cache->get($cacheKey, fn() => $this->gateway->findAllByTenant($tenant));
```

---

## Tests a incluir

### Test unitario — EvaluateFlagUsecase

- Flag habilitado, rollout 100% → `true`
- Flag deshabilitado → `false`
- Flag no existe → `false`
- Rollout 50%: mismo user_uid siempre da mismo resultado (determinista)
- Rollout 0% → siempre `false`

### Test integración — /api/flags

- `GET /api/flags` → lista con `enabled` por usuario
- `GET /api/flags/webauthn` habilitado → `{ enabled: true }`
- `GET /api/flags/webauthn` deshabilitado → `{ enabled: false }`
