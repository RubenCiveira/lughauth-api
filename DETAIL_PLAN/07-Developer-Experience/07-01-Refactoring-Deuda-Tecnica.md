# 07-01 — Refactoring y Deuda Técnica

**Prioridad:** P0 — hacer antes de cualquier feature nueva  
**Esfuerzo estimado:** Medio (3-4 días en total, distribuible)  
**Dependencias previas:** ninguna  
**Referencias:** `refactor_oidc.md`, `tareas.md` en raíz del proyecto

---

## Contexto

El archivo `refactor_oidc.md` documenta deuda técnica identificada. Estas tareas
deben resolverse antes de añadir más funcionalidad para evitar que la deuda
se propague a código nuevo.

---

## Tareas de refactoring

### A. Renombrar `autenticate` → `authenticate` (typo generalizado)

**Impacto:** ~40 métodos, tests y mocks en el namespace `Oidc/Authentication/`

**Archivos afectados** (buscar con `grep -r "autenticate" src/ test/`):
- `AuthenticateUser.php` — nombre de clase (puede ser correcto, verificar)
- Métodos como `autenticate()`, `getAutenticateUser()`, etc.
- Tests correspondientes que mockean estos métodos

**Proceso de cambio:**
1. Crear script de búsqueda: `grep -rn "autenticat[^e]" src/ test/ --include="*.php"`
2. Usar `sed -i 's/autenticate/authenticate/g'` o find-replace en IDE
3. Ejecutar `composer test` para verificar que no se rompió nada
4. Un commit por archivo cambiado para facilitar revisión

**Nota:** Antes de renombrar métodos públicos de interfaces, verificar si hay tests
que mockan esos métodos con el nombre incorrecto.

### B. Eliminar `AuthorizedChalleges` (clase legacy) y conversiones `->toLegacy()`

**Estado actual:** El flujo de authorize usa conversiones:
```php
$challenges->toLegacy()  // convierte al formato antiguo
```

**Objetivo:** Eliminar la clase `AuthorizedChalleges` (si existe) y hacer que el
código use directamente `ChallengesState` o el objeto equivalente moderno.

**Proceso:**
1. Identificar todos los usos: `grep -rn "toLegacy\|AuthorizedChalleges" src/`
2. Entender qué campos adicionales tiene `AuthorizedChalleges` vs `ChallengesState`
3. Mover esos campos a `ChallengesState` si son necesarios
4. Eliminar llamadas a `->toLegacy()`
5. Eliminar la clase `AuthorizedChalleges`

### C. Colapsar capa redundante Gateway → Repository

**Estado actual:** En algunos features hay 3 capas:
```
Application → Gateway (interfaz) → Repository (interfaz 2?) → Adapter (implementación)
```

**Objetivo:** Simplificar a 2 capas:
```
Application → Gateway (interfaz) → Adapter (implementación)
```

**Features a revisar:**
- `Oidc/Authentication/` — verificar si hay doble capa de interfaces
- Comparar con `Access/TrustedClient/` que usa el patrón correcto:
  `TrustedClientReadGateway` → `TrustedClientReadRepositoryAdapter`

**Proceso:**
1. Identificar las clases `*Repository*` que son solo wrappers de adapters
2. Hacer que el Gateway apunte directamente al Adapter
3. Actualizar el Provider.php del feature
4. Eliminar la capa intermedia

### D. Corregir `UserMfa.storeSeed()` — orden de parámetros

**Archivo:** `src/Features/Oidc/Mfa/Application/Usecase/UserMfa.php`

El orden de parámetros en la firma pública no coincide con el de la implementación.

**Proceso:**
1. Leer `UserMfa.storeSeed()` actual
2. Identificar el contrato correcto comparando con los tests
3. Corregir la firma y todos los call sites
4. Actualizar tests

### E. Añadir `jti` a todos los tokens emitidos

**Prerequisito para:** 01-02 Introspection, 01-04 Token Revocation

**Archivos:**
- `src/Features/Oidc/Key/Infrastructure/Driven/JoseTokenSigner.php` — añadir `jti` en `sign()`
- Todos los builders de payload JWT que no incluyan `jti`

```php
// En JoseTokenSigner::sign()
if (!isset($claims['jti'])) {
    $claims['jti'] = \Ramsey\Uuid\Uuid::uuid4()->toString();
}
```

### F. Cleanup jobs para datos expirados

Implementar o verificar que existen jobs para limpiar:

| Tabla | Campo de expiración | Frecuencia sugerida |
|-------|---------------------|---------------------|
| `_oauth_temporal_codes` | `expires_at` | Cada 15 min |
| `_oauth_session` | `expires_at` | Diariamente |
| `_oauth_temporal_keys` | `expires_at` | Cada hora |
| `_oauth_revoked_jti` | `expires_at` | Diariamente |
| `_webauthn_challenge` | `expires_at` | Cada 15 min |
| `_mfa_otp_code` | `expires_at` | Cada 15 min |

**Archivo de referencia existente:** buscar en `src/` si hay algún `CleanupCommand` o tarea de mantenimiento.

**Archivo nuevo si no existe:** `src/Bootstrap/Management/Cli/CleanupExpiredDataCommand.php`

### G. Normalizar namespace y estructura de tests

**Estado actual:** Tests de Oidc están en `test/Features/Oidc/Authentication/` pero
algunos features de Oidc (`Mfa`, `Scopes`, `Key`, `Session`) no tienen tests.

**Qué hacer:**
- Crear estructura de tests para: `test/Features/Oidc/Mfa/`, `test/Features/Oidc/Scopes/`,
  `test/Features/Oidc/Key/`, `test/Features/Oidc/Session/`
- Añadir tests básicos de contrato para las interfaces de gateway

---

## Checklist de validación post-refactoring

Tras cada tarea:

- [ ] `composer test` pasa sin errores
- [ ] `vendor/bin/psalm` sin warnings nuevos (análisis estático)
- [ ] `vendor/bin/php-cs-fixer fix --dry-run` sin diferencias
- [ ] Ninguna referencia a clases/métodos eliminados (`grep -rn "AuthorizedChalleges\|toLegacy"`)
- [ ] OpenAPI spec sigue generándose correctamente (`GET /management/apidoc`)
