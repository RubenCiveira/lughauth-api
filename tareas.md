# Tareas pendientes de refactorización OIDC

## Tarea 1: Fix naming — "autenticate" → "authenticate"

### Contexto
El typo "autenticate" (falta la 'h') se propagó por todo el flujo de autenticación OIDC y también a las policy classes autogeneradas de Access. Hay dos ámbitos: métodos del paquete Oidc (impacto funcional) y nombres de clases autogeneradas en Access (cosmético pero consistencia).

### 1.1 Renombrar métodos en el paquete Oidc (ámbito funcional)

**Definiciones de método a renombrar:**

| Fichero | Línea | Antes | Después |
|---------|-------|-------|---------|
| `src/Features/Oidc/Authentication/Application/AuthenticateUser.php` | 26 | `sessionAutenticated(` | `sessionAuthenticated(` |
| `src/Features/Oidc/Authentication/Application/AuthenticateUser.php` | 46 | `preAutenticate(` | `preAuthenticate(` |
| `src/Features/Oidc/Authentication/Application/AuthenticateUser.php` | 66 | `autenticate(` | `authenticate(` |
| `src/Features/Oidc/Authentication/Application/AuthenticateUser.php` | 88 | `autenticateWithState(` | `authenticateWithState(` |
| `src/Features/Oidc/Authentication/Application/AuthenticateUser.php` | 99 | `$this->autenticate(` | `$this->authenticate(` |
| `src/Features/Oidc/Authentication/Application/TokenGranter/TokenGranterStrategy.php` | 15 | `autenticate(` | `authenticate(` |
| `src/Features/Oidc/Authentication/Application/TokenGranter/TokenGranterMediator.php` | 19 | `autenticate(` | `authenticate(` |
| `src/Features/Oidc/Authentication/Application/TokenGranter/TokenGranterMediator.php` | 27 | `$granter->autenticate(` | `$granter->authenticate(` |
| `src/Features/Oidc/Authentication/Application/TokenGranter/ResolverForPassword.php` | 27 | `autenticate(` | `authenticate(` |
| `src/Features/Oidc/Authentication/Application/TokenGranter/ResolverForRefresh.php` | 31 | `autenticate(` | `authenticate(` |

**Llamadas a actualizar:**

| Fichero | Línea | Método llamado |
|---------|-------|---------------|
| `src/Features/Oidc/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php` | 88 | `->sessionAutenticated(` → `->sessionAuthenticated(` |
| `src/Features/Oidc/Authentication/Infrastructure/Driver/Html/Forms/LoginForm.php` | 140 | `->autenticate(` → `->authenticate(` |
| `src/Features/Oidc/Authentication/Infrastructure/Driver/Html/Forms/ConsentForm.php` | 102 | `->preAutenticate(` → `->preAuthenticate(` |
| `src/Features/Oidc/Authentication/Infrastructure/Driver/Html/Forms/ScopesConsentForm.php` | 109, 143 | `->preAutenticate(` → `->preAuthenticate(` |
| `src/Features/Oidc/Authentication/Infrastructure/Driver/Html/Forms/UseMfaForm.php` | 91, 105 | `->preAutenticate(` → `->preAuthenticate(` |
| `src/Features/Oidc/Authentication/Infrastructure/Driver/Html/Forms/NewMfaForm.php` | 110 | `->preAutenticate(` → `->preAuthenticate(` |
| `src/Features/Oidc/Authentication/Infrastructure/Driver/Html/Forms/NewPassForm.php` | 40 | `->preAutenticate(` → `->preAuthenticate(` |
| `src/Features/Oidc/Authentication/Infrastructure/Driver/Html/Forms/RecoverPassForm.php` | 59 | `->preAutenticate(` → `->preAuthenticate(` |
| `src/Features/Oidc/Authentication/Infrastructure/Driver/Html/Forms/RegisterUserForm.php` | 64 | `->preAutenticate(` → `->preAuthenticate(` |
| `src/Features/Oidc/Authentication/Infrastructure/Driver/Html/Forms/DelegateForm.php` | 45 | `->preAutenticate(` → `->preAuthenticate(` |
| `src/Features/Oidc/Authentication/Infrastructure/Driver/Rest/TokenController.php` | 56, 75 | `->autenticate(` → `->authenticate(` |

**Tests a actualizar:**

| Fichero | Método en mock |
|---------|---------------|
| `test/.../Forms/LoginFormStepUnitTest.php` | `->method('autenticate')` → `->method('authenticate')` |
| `test/.../Forms/ConsentFormStepUnitTest.php` | `->method('preAutenticate')` → `->method('preAuthenticate')` |
| `test/.../Forms/ScopesConsentFormStepUnitTest.php` | `->method('preAutenticate')` → `->method('preAuthenticate')` |
| `test/.../Forms/UseMfaFormStepUnitTest.php` | `->method('preAutenticate')` → `->method('preAuthenticate')` |
| `test/.../Forms/NewMfaFormStepUnitTest.php` | `->method('preAutenticate')` → `->method('preAuthenticate')` |
| `test/.../Forms/NewPassFormStepUnitTest.php` | `->method('preAutenticate')` → `->method('preAuthenticate')` |
| `test/.../Forms/RecoverPassFormStepUnitTest.php` | `->method('preAutenticate')` → `->method('preAuthenticate')` |
| `test/.../Forms/RegisterUserFormStepUnitTest.php` | `->method('preAutenticate')` → `->method('preAuthenticate')` |
| `test/.../Forms/DelegateFormStepUnitTest.php` | `->method('preAutenticate')` → `->method('preAuthenticate')` |

**Criterio de fin:** `grep -r "autenticate\|Autenticate" src/Features/Oidc/` no devuelve resultados. Tests pasan.

### 1.2 Renombrar clases `IsAutenticated*Allow` en Access (cosmético, autogenerado)

Las clases `IsAutenticated*Allow` bajo `src/Features/Access/` son autogeneradas. Aparecen en 8 features (User, Role, Tenant, TenantConfig, TenantTermsOfUse, TenantLoginProvider, RelyingParty, TrustedClient, ApiKeyClient, UserIdentity, PlanTenant) con variantes CRUD+Enable/Disable.

**Accion:** Regenerar con el generador corrigiendo la plantilla, o renombrar manualmente ~50 clases + ~50 ficheros + sus imports en los Plugin.php correspondientes.

**Nota:** Esta subtarea es de menor prioridad y alto volumen. Valorar si el generador de código se puede corregir para futuras generaciones.

---

## Tarea 2: Fix `UserMfa.storeSeed()` — orden de parámetros inconsistente

### Contexto
La firma pública del usecase tiene un orden de parámetros distinto al del gateway que invoca. Funciona porque internamente reordena, pero la API pública es confusa.

### Situación actual

```php
// UserMfa.php:31 — firma pública
public function storeSeed(string $seed, string $tenant, string $username): void

// UserMfa.php:33 — llamada interna (reordena)
$this->gateway->storeSeed($tenant, $username, $seed);

// UserMfaGateway.php — firma del gateway
public function storeSeed(string $tenant, string $username, string $seed): void;
```

### Acciones

1. **Cambiar la firma de `UserMfa.storeSeed()`** para que coincida con el gateway:
   ```php
   public function storeSeed(string $tenant, string $username, string $seed): void
   ```
2. **Actualizar todos los callers** de `UserMfa::storeSeed()`. Buscar con:
   ```
   grep -rn "storeSeed" src/ test/
   ```
3. **Verificar** que los tests pasan.

### Ficheros afectados
- `src/Features/Oidc/Mfa/Application/Usecase/UserMfa.php` — cambiar firma
- Todos los callers del usecase (formularios que almacenan seed tras verificar MFA)

### Criterio de fin
La firma de `UserMfa::storeSeed()` es `(string $tenant, string $username, string $seed)`, igual que el gateway. Tests pasan.

---

## Tarea 3: Simplificar `AuthorizeHtml` — reducir God class

### Contexto
`AuthorizeHtml` tiene 373 líneas y 9 dependencias en constructor. Mezcla: parsing de request, verificación de cliente, gestión de sesiones/cookies, construcción de URLs, orquestación de steps y generación de respuestas (redirects, tokens). Aunque ya usa `OidcFlowContext` y `OidcStepRouter`, sigue concentrando demasiadas responsabilidades.

### 3.1 Extraer la construcción de respuestas OIDC a `OidcResponseBuilder`

**Motivo:** `redirectOk()` (líneas 171-228) contiene toda la lógica de generación de tokens (id_token, access_token, code), hash de tokens, y construcción de la URL de redirect con fragmentos/query. Son ~60 líneas de lógica compleja que no es responsabilidad del controller.

**Acciones:**
1. Crear `Authentication/Infrastructure/Driver/Html/Services/OidcResponseBuilder.php`
2. Mover a esta clase:
   - `redirectOk()` → método público `buildSuccessRedirect(OidcFlowContext, PublicLoginAuthResponse, ClientData, AuthenticationRequest, ResponseInterface): ResponseInterface`
   - `generateHash()` (estática, línea 230) — que además está duplicada en `AuthenticateUser.php`
   - `hasResponse()` (línea 306) — helper para parsear `response_type`
3. Dependencias que se mueven junto: `KeysManagerService`, `TemporalKeysGateway`
4. Actualizar `AuthorizeHtml`:
   - Quitar `KeysManagerService` y `TemporalKeysGateway` del constructor
   - Inyectar `OidcResponseBuilder`
   - Llamar a `$this->responseBuilder->buildSuccessRedirect(...)` en `refresh()` y `formAuthorize()`

**Resultado:** `AuthorizeHtml` baja de 9 a 8 dependencias y pierde ~70 líneas. `redirectOk` queda en un sitio reutilizable si se añaden nuevos drivers.

### 3.2 Unificar construcción de URLs con `OidcUrlBuilder` existente

**Motivo:** `AuthorizeHtml` tiene `buildAuthorizeUrl()` (líneas 342-356) y `buildCheckSessionUrl()` (líneas 358-372) que duplican la lógica que ya resuelve `OidcUrlBuilder` en Domain.

**Acciones:**
1. Añadir a `OidcUrlBuilder` un método `checkSessionUrl(...)` análogo a `authorizeUrl()` pero con path `/check-session`
2. Inyectar `OidcUrlBuilder` en `AuthorizeHtml`
3. Reemplazar `buildAuthorizeUrl()` por `$this->urlBuilder->authorizeUrl(...)`
4. Reemplazar `buildCheckSessionUrl()` por `$this->urlBuilder->checkSessionUrl(...)`
5. Eliminar los dos métodos privados de `AuthorizeHtml`

**Resultado:** Se eliminan ~30 líneas duplicadas y la construcción de URLs queda centralizada.

### 3.3 Extraer gestión de cookies a `OidcCookieManager`

**Motivo:** `clearSession()` (líneas 278-284), `storePreSession()` (líneas 286-299), y `cookiePath()` (línea 301) manejan cookies con lógica de naming, paths y expiración que se repite.

**Acciones:**
1. Crear `Authentication/Infrastructure/Driver/Html/Services/OidcCookieManager.php`
2. Mover:
   - `clearSession(ResponseInterface, string $tenant): ResponseInterface`
   - `storePreSession(OidcFlowContext, ChallengesState): Cookie`
   - `cookiePath(string $tenant): string`
   - La cookie de sesión autenticada (`AUTH_SESSION_ID_*`) que se crea en `redirectOk`
3. Dependencia: `KeysManagerService` (para `signKeypass` en `storePreSession`) — si ya se movió a `OidcResponseBuilder`, compartir o inyectar en ambos
4. Actualizar callers en `AuthorizeHtml`

**Resultado:** `AuthorizeHtml` pierde ~25 líneas más y la lógica de cookies queda centralizada.

### 3.4 Eliminar `autenticateWithState()` wrapper innecesario

**Motivo:** `AuthenticateUser.autenticateWithState()` (línea 88-100) solo delega a `autenticate()` sin añadir lógica.

**Acciones:**
1. Buscar callers de `autenticateWithState` — verificar que no hay ninguno fuera de la propia clase
2. Si no hay callers externos, eliminar el método
3. Si los hay, reemplazar las llamadas por `authenticate()` directamente

**Resultado:** Una indirección menos.

### Resultado esperado de la tarea 3

| Métrica | Antes | Después |
|---------|-------|---------|
| Líneas de `AuthorizeHtml` | ~373 | ~200 |
| Dependencias en constructor | 9 | 6-7 |
| Métodos privados | 11 | 5-6 |
| Lógica de tokens/JWT en controller | Sí | No |
| URLs duplicadas vs `OidcUrlBuilder` | 2 métodos | 0 |

### Criterio de fin
`AuthorizeHtml` solo orquesta: construir contexto → verificar cliente → cargar sesión → delegar a router → delegar respuesta. Tests de integración pasan.

---

## Tarea 4: Reubicar `KeysManagerService` — sacarlo de Domain

### Contexto
`KeysManagerService` vive en `src/Features/Oidc/Key/Domain/` pero es lógica de infraestructura pura: usa la librería Jose para crear/verificar JWTs, genera pares RSA con OpenSSL, y gestiona rotación de claves. El dominio no debería conocer detalles de JWT ni de criptografía.

### 4.1 Crear interfaz en Domain, mover implementación a Infrastructure

**Acciones:**
1. Crear interfaz `src/Features/Oidc/Key/Domain/TokenSigner.php`:
   ```php
   interface TokenSigner {
       public function sign(string $tenant, array $data, \DateInterval $expiration): string;
       public function keysAsJwks(string $tenant): JWKSet;
       public function signKeypass(string $tenant, array $data, \DateInterval $expiration): string;
       public function verifyTokenPayload(string $tenant, string $token): ?array;
       public function verifiedKeypass(string $tenant, string $token): mixed;
   }
   ```
2. Mover `KeysManagerService.php` a `src/Features/Oidc/Key/Infrastructure/JoseTokenSigner.php`
3. Hacer que `JoseTokenSigner implements TokenSigner`
4. Actualizar el contenedor DI en `OidcPlugin.php`:
   ```php
   $def[TokenSigner::class] = \DI\autowire(JoseTokenSigner::class);
   ```
5. Actualizar todos los consumidores para que dependan de `TokenSigner` (interfaz) en vez de `KeysManagerService` (clase concreta):
   - `AuthenticateUser.php` — si `OidcResponseBuilder` absorbe la firma de tokens (tarea 3.1), el caller directo será `OidcResponseBuilder`
   - `AuthorizeHtml.php` — `signKeypass` en `storePreSession` (o `OidcCookieManager` si se extrae en tarea 3.3)
   - `HtmlSecurer.php`
   - `TokenController.php`
   - `OidcPlugin.php` (registro DI)

### 4.2 Reemplazar excepciones genéricas

**Acciones:**
1. Crear `src/Features/Oidc/Key/Domain/Exception/NoActiveKeyException.php`:
   ```php
   class NoActiveKeyException extends \RuntimeException {}
   ```
2. En `JoseTokenSigner.php` (ex `KeysManagerService`), línea 31:
   - Antes: `throw new \Exception('There is no active key')`
   - Después: `throw new NoActiveKeyException('There is no active key for tenant: ' . $tenant)`
3. En `TemporalKeysSqlAdapter.php`, línea ~140:
   - Crear `src/Features/Oidc/Session/Domain/Exception/SignatureVerificationException.php`
   - Antes: `throw new \Exception("Signature verification failed")`
   - Después: `throw new SignatureVerificationException("Signature verification failed")`

### 4.3 Eliminar `generateHash()` duplicado

**Acciones:**
1. `AuthenticateUser::generateHash()` (línea 160) es `public static` — ya es accesible
2. Eliminar el duplicado en `AuthorizeHtml::generateHash()` (línea 230)
3. Si se crea `OidcResponseBuilder` (tarea 3.1), usar `AuthenticateUser::generateHash()` desde allí
4. Alternativa: extraer a una clase util `src/Features/Oidc/Common/OidcHash.php` con un método estático `halfHash(string): string`

### Criterio de fin
- `Key/Domain/` solo contiene interfaces, VOs y excepciones — ninguna clase con `use Jose\*` ni `openssl_*`
- `grep -rn "KeysManagerService" src/` solo aparece en `Key/Infrastructure/JoseTokenSigner.php` y en el registro DI
- No quedan `throw new \Exception(` en el paquete Oidc
- No quedan duplicados de `generateHash()`
- Tests pasan

---

## Orden de ejecución recomendado

```
Tarea 2 (storeSeed)     ← Cambio pequeño, aislado. Se puede hacer primero.
    ↓
Tarea 1 (naming)        ← Rename mecánico. Tocar antes de refactorizar evita conflictos.
    ↓
Tarea 4 (KeysManager)   ← Crear interfaz TokenSigner antes de mover código en AuthorizeHtml.
    ↓
Tarea 3 (AuthorizeHtml) ← Se beneficia de TokenSigner (interfaz) y naming ya corregido.
```

Cada tarea es independiente y commiteable por separado. Ejecutarlas en este orden minimiza conflictos entre tareas.
