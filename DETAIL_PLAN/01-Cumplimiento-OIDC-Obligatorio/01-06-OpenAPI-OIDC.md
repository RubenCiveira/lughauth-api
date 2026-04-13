# 01-06 — Anotaciones OpenAPI en Controladores OIDC

**Prioridad:** P1  
**Spec:** OpenAPI 3.0 (swagger-php)  
**Esfuerzo estimado:** Bajo (1-2 días)  
**Dependencias previas:** ninguna (puede hacerse en paralelo)  
**Habilita:** documentación completa en `/management/apidoc`, generación de SDKs

---

## Contexto

Los controladores de la feature `Access/` tienen anotaciones `#[OA\...]` completas
y aparecen en el spec generado por `/management/apidoc`. Los controladores OIDC no
tienen ninguna anotación, por lo que toda la superficie pública del servidor OIDC
está ausente de la documentación.

**Controladores sin documentar:**
- `AuthorizeHtml.php` — GET/POST `/openid/{tenant}/authorize`
- `TokenController.php` — POST `/openid/{tenant}/token`
- `UserInfoController.php` — GET/POST `/openid/{tenant}/userinfo`
- `LogoutController.php` — GET `/openid/{tenant}/logout`
- `JwksController.php` — GET `/openid/{tenant}/jwks`
- `OpenIdConfigurationController.php` — GET `/.well-known/openid-configuration`
- `DelegatedController.php` — GET `/openid/-/delegated/verify`
- `DeviceAuthorizationController.php` — POST/GET `/openid/{tenant}/device`
- `ApiKeyController.php` — POST `/api/client/api-key/validate`
- *(Nuevos de tareas previas)* `IntrospectController.php`, `ScopeConsentController.php`

---

## Qué implementar

Añadir atributos PHP `#[OA\...]` de `zircote/swagger-php` a cada controlador OIDC,
siguiendo el patrón establecido en los controladores de `Access/`.

---

## Dónde y cómo hacer los cambios

### Patrón a seguir

Ejemplo de controlador `Access/` bien documentado:
```php
#[OA\Post(
    path: '/api/access/trusted-clients',
    summary: 'Create trusted client',
    tags: ['TrustedClient'],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: TrustedClientApiDTO::class)
    ),
    responses: [
        new OA\Response(response: 200, description: 'Created'),
        new OA\Response(response: 422, description: 'Validation error'),
    ]
)]
```

### A. TokenController

**Archivo:** `src/Features/Oidc/Authentication/Infrastructure/Driver/Rest/TokenController.php`

```php
#[OA\Post(
    path: '/openid/{tenant}/token',
    summary: 'OAuth 2.0 Token Endpoint',
    description: 'Exchange authorization code, refresh token, or device code for tokens. Supports: authorization_code, refresh_token, password, client_credentials, urn:ietf:params:oauth:grant-type:device_code',
    tags: ['OIDC'],
    parameters: [
        new OA\Parameter(name: 'tenant', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(properties: [
                new OA\Property(property: 'grant_type',    type: 'string', example: 'authorization_code'),
                new OA\Property(property: 'code',          type: 'string'),
                new OA\Property(property: 'redirect_uri',  type: 'string'),
                new OA\Property(property: 'client_id',     type: 'string'),
                new OA\Property(property: 'client_secret', type: 'string'),
                new OA\Property(property: 'code_verifier', type: 'string', description: 'PKCE verifier'),
                new OA\Property(property: 'refresh_token', type: 'string'),
            ])
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Token response',
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'access_token',  type: 'string'),
                new OA\Property(property: 'token_type',    type: 'string', example: 'Bearer'),
                new OA\Property(property: 'expires_in',    type: 'integer'),
                new OA\Property(property: 'id_token',      type: 'string'),
                new OA\Property(property: 'refresh_token', type: 'string'),
                new OA\Property(property: 'scope',         type: 'string'),
            ])
        ),
        new OA\Response(response: 400, description: 'OAuth error (invalid_grant, invalid_client, etc.)'),
        new OA\Response(response: 401, description: 'Client authentication failed'),
    ]
)]
```

### B. UserInfoController

**Archivo:** `src/Features/Oidc/Authentication/Infrastructure/Driver/Rest/UserInfoController.php`

```php
#[OA\Get(
    path: '/openid/{tenant}/userinfo',
    summary: 'OIDC Userinfo Endpoint',
    description: 'Returns claims about the authenticated user. Requires Bearer access token with openid scope.',
    tags: ['OIDC'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'tenant', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'User claims',
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'sub',         type: 'string'),
                new OA\Property(property: 'email',       type: 'string'),
                new OA\Property(property: 'name',        type: 'string'),
                new OA\Property(property: 'given_name',  type: 'string'),
                new OA\Property(property: 'family_name', type: 'string'),
            ])
        ),
        new OA\Response(response: 401, description: 'Unauthorized'),
    ]
)]
```

### C. JwksController

**Archivo:** `src/Features/Oidc/Key/Infrastructure/Driver/Rest/JwksController.php`

```php
#[OA\Get(
    path: '/openid/{tenant}/jwks',
    summary: 'JSON Web Key Set',
    description: 'Public keys for JWT signature verification. Cached for 1 hour.',
    tags: ['OIDC'],
    parameters: [
        new OA\Parameter(name: 'tenant', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'JWKS',
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'keys', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'kty', type: 'string', example: 'RSA'),
                        new OA\Property(property: 'use', type: 'string', example: 'sig'),
                        new OA\Property(property: 'kid', type: 'string'),
                        new OA\Property(property: 'alg', type: 'string', example: 'RS256'),
                        new OA\Property(property: 'n',   type: 'string'),
                        new OA\Property(property: 'e',   type: 'string'),
                    ]
                ))
            ])
        )
    ]
)]
```

### D. OpenIdConfigurationController

**Archivo:** `src/Features/Oidc/Common/Infrastructure/Driver/Rest/OpenIdConfigurationController.php`

```php
#[OA\Get(
    path: '/openid/{tenant}/.well-known/openid-configuration',
    summary: 'OpenID Connect Discovery Document',
    tags: ['OIDC'],
    responses: [new OA\Response(response: 200, description: 'OpenID Provider Metadata')]
)]
```

### E. LogoutController

**Archivo:** `src/Features/Oidc/Authentication/Infrastructure/Driver/Rest/LogoutController.php`

```php
#[OA\Get(
    path: '/openid/{tenant}/logout',
    summary: 'RP-Initiated Logout',
    tags: ['OIDC'],
    parameters: [
        new OA\Parameter(name: 'id_token_hint',          in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'post_logout_redirect_uri',in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'state',                   in: 'query', schema: new OA\Schema(type: 'string')),
    ],
    responses: [
        new OA\Response(response: 302, description: 'Redirect to post_logout_redirect_uri'),
        new OA\Response(response: 200, description: 'Logout page with front-channel iframes'),
    ]
)]
```

### F. DeviceAuthorizationController

**Archivo:** `src/Features/Oidc/Device/Infrastructure/Driver/Rest/DeviceAuthorizationController.php`

```php
#[OA\Post(
    path: '/openid/{tenant}/device',
    summary: 'Device Authorization Request (RFC 8628)',
    tags: ['OIDC'],
    responses: [
        new OA\Response(
            response: 200,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'device_code',      type: 'string'),
                new OA\Property(property: 'user_code',        type: 'string', example: 'ABCD-1234'),
                new OA\Property(property: 'verification_uri', type: 'string'),
                new OA\Property(property: 'expires_in',       type: 'integer'),
                new OA\Property(property: 'interval',         type: 'integer'),
            ])
        )
    ]
)]
```

### G. AuthorizeHtml — solo documentar como HTML endpoint

**Archivo:** `src/Features/Oidc/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php`

```php
#[OA\Get(
    path: '/openid/{tenant}/authorize',
    summary: 'Authorization Endpoint — interactive login UI',
    description: 'Returns HTML login form. Clients should redirect the user agent here.',
    tags: ['OIDC'],
    parameters: [
        new OA\Parameter(name: 'response_type',          in: 'query', required: true, schema: new OA\Schema(type: 'string', example: 'code')),
        new OA\Parameter(name: 'client_id',              in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'redirect_uri',           in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'scope',                  in: 'query', required: true, schema: new OA\Schema(type: 'string', example: 'openid email profile')),
        new OA\Parameter(name: 'state',                  in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'nonce',                  in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'code_challenge',         in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'code_challenge_method',  in: 'query', schema: new OA\Schema(type: 'string', enum: ['S256', 'plain'])),
        new OA\Parameter(name: 'prompt',                 in: 'query', schema: new OA\Schema(type: 'string', enum: ['none', 'login', 'consent', 'select_account'])),
    ],
    responses: [
        new OA\Response(response: 200, description: 'HTML login form'),
        new OA\Response(response: 302, description: 'Redirect with code (if already authenticated)'),
    ]
)]
```

### H. Añadir SecurityScheme global al spec

**Archivo:** `src/Bootstrap/Plugin/SecurityPlugin.php` o en una clase `#[OA\OpenApi]`

```php
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
)]
#[OA\SecurityScheme(
    securityScheme: 'basicAuth',
    type: 'http',
    scheme: 'basic',
)]
```

---

## Tests a incluir

### Test — spec generado incluye endpoints OIDC

**Archivo nuevo:** `test/Bootstrap/Management/ApiDocIntegrationTest.php`

Casos:
- `GET /management/apidoc` → respuesta `200` con JSON válido
- Spec incluye path `/openid/{tenant}/token`
- Spec incluye path `/openid/{tenant}/userinfo`
- Spec incluye path `/openid/{tenant}/jwks`
- Spec incluye path `/openid/{tenant}/.well-known/openid-configuration`
- Spec incluye path `/openid/{tenant}/introspect` (tras 01-02)
- Spec tiene `components.securitySchemes.bearerAuth` definido

> Nota: estos tests son principalmente de contrato — verifican que la spec no
> regresa tras un refactor que elimine anotaciones por accidente.
