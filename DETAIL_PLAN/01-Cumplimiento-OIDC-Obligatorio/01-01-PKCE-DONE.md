# 01-01 — PKCE (Proof Key for Code Exchange) — RFC 7636

[x] DONE

**Prioridad:** P0  
**Spec:** RFC 7636  
**Esfuerzo estimado:** Medio (3-5 días)  
**Dependencias previas:** ninguna  
**Habilita:** 02-01 PAR, clientes SPA y apps nativas seguros

---

## Contexto

Sin PKCE, el authorization code flow es vulnerable a la intercepción del código de
autorización por parte de apps maliciosas en el mismo dispositivo. PKCE es obligatorio
según OAuth 2.1 (draft) para todos los clientes públicos.

**Estado actual:** `OidcFlowContext` (`src/Features/Oidc/Authentication/Domain/OidcFlowContext.php`)
no tiene campos `code_challenge` ni `code_challenge_method`. El authorize flow
recibe parámetros de query pero no valida ni persiste estos campos.

---

## Qué implementar

### 1. Parámetros nuevos en authorize

Aceptar y validar en `GET /openid/{tenant}/authorize`:
- `code_challenge` — Base64URL del hash SHA-256 del `code_verifier`
- `code_challenge_method` — `S256` (obligatorio) o `plain` (opcional)

Reglas:
- Si el cliente es **público** (`access_trusted_client.public_allow = true`): PKCE **obligatorio**
- Si el cliente es **confidencial**: PKCE **opcional** pero si se envía, se valida

### 2. Persistencia del challenge

Guardar `code_challenge` y `code_challenge_method` junto al código de autorización
en `_oauth_temporal_codes`.

### 3. Validación en token endpoint

En `POST /openid/{tenant}/token` con `grant_type=authorization_code`:
- Si se emitió con PKCE: `code_verifier` es **obligatorio**
- Calcular `BASE64URL(SHA256(code_verifier))` y comparar con `code_challenge` almacenado
- Error `invalid_grant` si no coincide

### 4. Discovery

Anunciar en `.well-known/openid-configuration`:
```json
{
  "code_challenge_methods_supported": ["S256", "plain"]
}
```

---

## Dónde y cómo hacer los cambios

### A. Migración de base de datos

**Archivo nuevo:** `migrations/mysql/schema/YYYYMMDDHHMMSS_AddPkceToTemporalCodes.php`

```sql
ALTER TABLE _oauth_temporal_codes
  ADD COLUMN code_challenge VARCHAR(128) NULL AFTER code,
  ADD COLUMN code_challenge_method VARCHAR(10) NULL AFTER code_challenge;
```

### B. Dominio — OidcFlowContext

**Archivo:** `src/Features/Oidc/Authentication/Domain/OidcFlowContext.php`

Añadir propiedades:
```php
public readonly ?string $codeChallenge,
public readonly ?string $codeChallengeMethod,
```

Constructor actualizado para recibir y asignar estos campos desde los query params.

### C. Dominio — Nuevo Value Object PkceChallenge

**Archivo nuevo:** `src/Features/Oidc/Authentication/Domain/ValueObject/PkceChallenge.php`

```php
final class PkceChallenge
{
    private function __construct(
        public readonly string $challenge,
        public readonly string $method,
    ) {}

    public static function fromRequest(?string $challenge, ?string $method): ?self
    {
        if ($challenge === null) return null;
        $method ??= 'plain';
        if (!in_array($method, ['S256', 'plain'], true)) {
            throw new \InvalidArgumentException("Unsupported code_challenge_method: $method");
        }
        return new self($challenge, $method);
    }

    public function verify(string $verifier): bool
    {
        return match ($this->method) {
            'S256'  => hash_equals($this->challenge, strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_')),
            'plain' => hash_equals($this->challenge, $verifier),
        };
    }
}
```

### D. Aplicación — AuthenticateUser / OidcFlowContext builder

**Archivo:** `src/Features/Oidc/Authentication/Application/AuthenticateUser.php`

En el método que construye `OidcFlowContext` desde la request HTTP:
- Extraer `code_challenge` y `code_challenge_method` de los query params
- Si el cliente es público y no se envía PKCE → lanzar `LoginException` con `error=invalid_request`

### E. Session — TemporalAuthCode

**Archivo:** `src/Features/Oidc/Session/Domain/TemporalAuthCode.php`

Añadir campos:
```php
public readonly ?string $codeChallenge;
public readonly ?string $codeChallengeMethod;
```

**Archivo:** `src/Features/Oidc/Session/Infrastructure/Driven/SessionStoreSqlAdapter.php`

Actualizar INSERT para persistir `code_challenge` y `code_challenge_method`.
Actualizar SELECT para leerlos al recuperar el código.

### F. Token Granter — authorization_code

**Archivo:** `src/Features/Oidc/Authentication/Application/TokenGranter/ResolverForAuthCode.php`
*(si no existe, añadir lógica en `TokenGranterMediator.php`)*

Al intercambiar el código:
1. Recuperar `TemporalAuthCode` con su `code_challenge`
2. Si `code_challenge` no es null:
   - Exigir `code_verifier` en el request (error `invalid_request` si falta)
   - Crear `PkceChallenge` y llamar `->verify($codeVerifier)`
   - Error `invalid_grant` si falla la verificación
3. Si `code_challenge` es null y el cliente es público → error `invalid_grant`

### G. Discovery — OpenIdConfigurationController

**Archivo:** `src/Features/Oidc/Common/Infrastructure/Driver/Rest/OpenIdConfigurationController.php`

Añadir en el array de respuesta:
```php
'code_challenge_methods_supported' => ['S256', 'plain'],
```

---

## Tests a incluir

### Test unitario — PkceChallenge

**Archivo nuevo:** `test/Features/Oidc/Authentication/Domain/ValueObject/PkceChallengeUnitTest.php`

Casos:
- `verify()` con método `S256` y verifier correcto → `true`
- `verify()` con método `S256` y verifier incorrecto → `false`
- `verify()` con método `plain` → comparación directa
- `fromRequest()` con método desconocido → excepción
- `fromRequest()` con `$challenge = null` → devuelve `null`

### Test unitario — OidcFlowContext con PKCE

**Archivo:** `test/Features/Oidc/Authentication/Domain/OidcFlowContextUnitTest.php`

Nuevos casos:
- `OidcFlowContext` construido con `code_challenge` y `code_challenge_method` correctos
- `OidcFlowContext` construido sin PKCE para cliente confidencial → válido
- `OidcFlowContext` construido sin PKCE para cliente público → excepción

### Test integración — Authorize con PKCE

**Archivo nuevo:** `test/Features/Oidc/Authentication/Infrastructure/Driver/Html/Forms/PkceAuthorizeIntegrationTest.php`

Casos:
- Request authorize con `code_challenge=xxx&code_challenge_method=S256` → flujo normal
- Request authorize cliente público sin PKCE → `error=invalid_request`
- Token request con `code_verifier` correcto → intercambio exitoso
- Token request con `code_verifier` incorrecto → `error=invalid_grant`
- Token request sin `code_verifier` cuando había challenge → `error=invalid_request`

### Test integración — Discovery

**Archivo:** `test/Features/Oidc/Common/Infrastructure/Driver/Rest/` *(crear si no existe)*

Caso:
- `GET /.well-known/openid-configuration` incluye `code_challenge_methods_supported: ["S256","plain"]`
