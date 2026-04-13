# 03-04 — Proveedores de Login Social Adicionales

**Prioridad:** P3  
**Esfuerzo estimado:** Medio (3-4 días por proveedor, según complejidad)  
**Dependencias previas:** patrón `DelegateLogin` existente (Google OAuth ya implementado)

---

## Contexto

El sistema `DelegateLogin` ya tiene una abstracción en
`src/Features/Oidc/DelegateLogin/Domain/DelegatedLoginProvider.php` y un adapter
`GoogleOAuthProvider.php`. Añadir nuevos proveedores es plug-and-play si se sigue
la misma interfaz.

**Proveedores a añadir (por prioridad):**
1. **GitHub OAuth** — muy demandado para herramientas de developer
2. **Microsoft / Azure AD** — proveedores enterprise, usa OIDC estándar
3. **Apple Sign In** — obligatorio para apps iOS que ofrecen login social
4. **SAML 2.0 genérico** — para integraciones enterprise/SSO corporativo

---

## Dónde y cómo hacer los cambios

### A. Interfaz base — revisar DelegatedLoginProvider

**Archivo:** `src/Features/Oidc/DelegateLogin/Domain/DelegatedLoginProvider.php`

Verificar que la interfaz expone:
```php
interface DelegatedLoginProvider
{
    public function getAuthorizationUrl(string $state, string $redirectUri): string;
    public function exchangeCode(string $code, string $redirectUri): DelegatedUserData;
    public function getProviderKey(): string;  // 'google', 'github', 'microsoft', 'apple'
}
```

**Archivo:** `src/Features/Oidc/DelegateLogin/Domain/DelegatedUserData.php`

Verificar que contiene:
```php
final class DelegatedUserData
{
    public function __construct(
        public readonly string  $providerUserId,
        public readonly string  $email,
        public readonly ?string $name = null,
        public readonly ?string $givenName = null,
        public readonly ?string $familyName = null,
        public readonly ?string $pictureUrl = null,
        public readonly bool    $emailVerified = false,
    ) {}
}
```

### B. GitHub OAuth Provider

**Archivo nuevo:** `src/Features/Oidc/DelegateLogin/Infrastructure/Provider/GitHubOAuthProvider.php`

```php
final class GitHubOAuthProvider implements DelegatedLoginProvider
{
    private const AUTH_URL  = 'https://github.com/login/oauth/authorize';
    private const TOKEN_URL = 'https://github.com/login/oauth/access_token';
    private const API_URL   = 'https://api.github.com/user';
    private const EMAIL_URL = 'https://api.github.com/user/emails';

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly HttpClientInterface $http,
    ) {}

    public function getAuthorizationUrl(string $state, string $redirectUri): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'    => $this->clientId,
            'redirect_uri' => $redirectUri,
            'scope'        => 'user:email read:user',
            'state'        => $state,
        ]);
    }

    public function exchangeCode(string $code, string $redirectUri): DelegatedUserData
    {
        // 1. Intercambiar code por access_token
        $tokenResponse = $this->http->post(self::TOKEN_URL, [
            'json'    => ['client_id' => $this->clientId, 'client_secret' => $this->clientSecret, 'code' => $code],
            'headers' => ['Accept' => 'application/json'],
        ]);
        $accessToken = json_decode($tokenResponse->getBody(), true)['access_token'];

        // 2. Obtener perfil
        $userResponse = $this->http->get(self::API_URL, [
            'headers' => ['Authorization' => "Bearer {$accessToken}", 'User-Agent' => 'LughAuth'],
        ]);
        $user = json_decode($userResponse->getBody(), true);

        // 3. Obtener email verificado (puede estar ausente en /user si es privado)
        $email = $user['email'] ?? $this->fetchPrimaryEmail($accessToken);

        return new DelegatedUserData(
            providerUserId: (string) $user['id'],
            email: $email,
            name: $user['name'] ?? $user['login'],
            pictureUrl: $user['avatar_url'] ?? null,
            emailVerified: true,
        );
    }

    public function getProviderKey(): string { return 'github'; }
}
```

### C. Microsoft / Azure AD Provider (OIDC estándar)

**Archivo nuevo:** `src/Features/Oidc/DelegateLogin/Infrastructure/Provider/MicrosoftOAuthProvider.php`

Microsoft usa OIDC estándar — se puede reutilizar `league/oauth2-client` o
implementar directamente usando el discovery endpoint de Microsoft:

```
https://login.microsoftonline.com/{tenant_id}/v2.0/.well-known/openid-configuration
```

Scopes: `openid email profile User.Read`

```php
public function __construct(
    private readonly string $clientId,
    private readonly string $clientSecret,
    private readonly string $tenantId,  // 'common', 'organizations', o UUID del tenant Azure
    private readonly HttpClientInterface $http,
) {}
```

### D. Apple Sign In Provider

**Archivo nuevo:** `src/Features/Oidc/DelegateLogin/Infrastructure/Provider/AppleOAuthProvider.php`

Apple Sign In tiene particularidades importantes:
- El cliente debe generar un JWT como `client_secret` (firmado con la clave privada de Apple)
- El nombre del usuario solo se recibe en el **primer** login (no en logins posteriores)
- Requiere `nonce` para prevenir replay attacks
- Scopes: `name email`

```php
public function __construct(
    private readonly string $clientId,        // com.example.app
    private readonly string $teamId,           // Apple Team ID
    private readonly string $keyId,            // Apple Key ID
    private readonly string $privateKeyPem,    // ES256 private key
    private readonly HttpClientInterface $http,
) {}

private function generateClientSecret(): string
{
    // JWT firmado con ES256 usando la private key de Apple
    // claims: iss=teamId, iat, exp (+6 meses máx), aud='https://appleid.apple.com', sub=clientId
}
```

### E. SAML 2.0 Provider

**Archivo nuevo:** `src/Features/Oidc/DelegateLogin/Infrastructure/Provider/Saml2Provider.php`

SAML 2.0 requiere librería dedicada. Opciones:
- `onelogin/php-saml` (más simple)
- `lightsaml/lightsaml` (más completa)

```bash
composer require onelogin/php-saml
```

Configuración por tenant en `access_tenant_login_provider`:
```sql
ALTER TABLE access_tenant_login_provider
  ADD COLUMN saml_idp_metadata_url VARCHAR(500) NULL,
  ADD COLUMN saml_idp_entity_id    VARCHAR(500) NULL,
  ADD COLUMN saml_idp_sso_url      VARCHAR(500) NULL,
  ADD COLUMN saml_idp_cert         TEXT NULL;
```

### F. Registro de proveedores en DelegateLoginAdapter

**Archivo:** `src/Features/Oidc/DelegateLogin/Infrastructure/Driven/DelegateLoginAdapter.php`

Añadir factory method o map de proveedores:
```php
private function resolveProvider(string $providerKey, string $tenant): DelegatedLoginProvider
{
    return match ($providerKey) {
        'google'    => $this->container->get(GoogleOAuthProvider::class),
        'github'    => $this->container->get(GitHubOAuthProvider::class),
        'microsoft' => $this->container->get(MicrosoftOAuthProvider::class),
        'apple'     => $this->container->get(AppleOAuthProvider::class),
        'saml2'     => $this->samlProviderFactory->forTenant($tenant),
        default     => throw new \InvalidArgumentException("Unknown provider: $providerKey"),
    };
}
```

### G. Configuración en .env y TenantLoginProvider

**Variables de entorno nuevas:**
```env
GITHUB_CLIENT_ID=xxx
GITHUB_CLIENT_SECRET=xxx
MICROSOFT_CLIENT_ID=xxx
MICROSOFT_CLIENT_SECRET=xxx
MICROSOFT_TENANT_ID=common
APPLE_CLIENT_ID=com.example.app
APPLE_TEAM_ID=xxx
APPLE_KEY_ID=xxx
APPLE_PRIVATE_KEY_PATH=/secrets/apple_auth_key.p8
```

---

## Tests a incluir

### Test unitario — GitHubOAuthProvider

Con mock de `HttpClientInterface`:
- `getAuthorizationUrl()` → URL con client_id, scope, state correctos
- `exchangeCode()` → llama a token endpoint y a API de usuario
- `exchangeCode()` → email privado → llama a `/user/emails` y obtiene primario verificado
- `exchangeCode()` → error de red → excepción con mensaje claro

### Test unitario — MicrosoftOAuthProvider

- `getAuthorizationUrl()` → URL de Microsoft con tenant_id correcto
- `exchangeCode()` → parsea id_token JWT de Microsoft

### Test unitario — AppleOAuthProvider

- `generateClientSecret()` → JWT firmado con alg ES256, claims correctos
- `exchangeCode()` con nombre en primer login → `DelegatedUserData` con nombre
- `exchangeCode()` sin nombre (login posterior) → nombre null

### Test integración — Delegate flow con GitHub

- `GET /delegated/verify?provider=github&code=xxx` → usuario creado/encontrado → sesión OIDC
- Error de GitHub API → error page apropiado

### Test integración — SAML 2.0

- Redirect a IdP con SAML Request correcto
- Procesamiento de SAML Response → usuario autenticado
