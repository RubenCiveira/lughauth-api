# 02-03 — Client Credentials Grant Completo (M2M)

**Prioridad:** P1  
**Spec:** RFC 6749 §4.4  
**Esfuerzo estimado:** Bajo (1-2 días)  
**Dependencias previas:** ninguna  
**Habilita:** autenticación máquina-a-máquina sin usuario

---

## Contexto

El `grant_type=client_credentials` puede estar parcialmente implementado en
`TokenGranterMediator.php`. El objetivo es completar el soporte para el caso
de uso M2M donde servicios backend obtienen tokens de acceso usando solo
sus credenciales de cliente, sin ningún usuario involucrado.

**Diferencia clave con otros grants:**
- `sub` del token = `client_id` (no es un `user_uid`)
- No hay `refresh_token` emitido (tokens de larga duración o renovación directa)
- Los scopes son los configurados para el cliente, no los consentidos por un usuario

---

## Qué implementar

### Token endpoint

```
POST /openid/{tenant}/token
Authorization: Basic base64(client_id:client_secret)
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials&scope=api:read api:write
```

Respuesta:
```json
{
  "access_token": "eyJ...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "scope": "api:read api:write"
}
```

*No se emite `refresh_token` ni `id_token` en este grant.*

---

## Dónde y cómo hacer los cambios

### A. Campos en access_trusted_client

```sql
ALTER TABLE access_trusted_client
  ADD COLUMN allowed_scopes_m2m   TEXT NULL,          -- JSON array de scopes permitidos
  ADD COLUMN m2m_token_ttl_seconds INT NOT NULL DEFAULT 3600;
```

### B. ResolverForClientCredentials

**Archivo nuevo:** `src/Features/Oidc/Authentication/Application/TokenGranter/ResolverForClientCredentials.php`

Implementa `TokenGranterStrategy`:

```php
final class ResolverForClientCredentials implements TokenGranterStrategy
{
    public function supports(string $grantType): bool
    {
        return $grantType === 'client_credentials';
    }

    public function resolve(TokenGranterParams $params): TokenGranterResult
    {
        // 1. Autenticar cliente (Basic Auth o client_secret_post)
        $client = $this->clientStore->findAndVerify($params->clientId, $params->clientSecret, $params->tenant);
        if ($client === null) {
            throw new OAuthException('invalid_client', 'Client authentication failed');
        }

        // 2. Verificar que el cliente tiene grant_type client_credentials habilitado
        if (!in_array('client_credentials', $client->allowedGrantTypes, true)) {
            throw new OAuthException('unauthorized_client');
        }

        // 3. Validar y recortar scopes al conjunto permitido
        $requestedScopes = $params->scope ? explode(' ', $params->scope) : [];
        $allowedScopes   = $client->allowedScopesM2m ?? [];
        $grantedScopes   = empty($requestedScopes)
            ? $allowedScopes
            : array_intersect($requestedScopes, $allowedScopes);

        if (empty($grantedScopes) && !empty($requestedScopes)) {
            throw new OAuthException('invalid_scope');
        }

        // 4. Emitir access_token con sub = client_id
        $accessToken = $this->signer->sign([
            'iss'       => $params->issuer,
            'sub'       => $client->clientId,    // <-- diferencia clave
            'aud'       => $params->tenant,
            'client_id' => $client->clientId,
            'scope'     => implode(' ', $grantedScopes),
            'iat'       => time(),
            'exp'       => time() + $client->m2mTokenTtlSeconds,
            'jti'       => Uuid::uuid4()->toString(),
            'token_use' => 'client_credentials',
        ]);

        return new TokenGranterResult(
            accessToken: $accessToken,
            tokenType: 'Bearer',
            expiresIn: $client->m2mTokenTtlSeconds,
            scope: implode(' ', $grantedScopes),
        );
    }
}
```

### C. TokenGranterMediator — registrar nuevo resolver

**Archivo:** `src/Features/Oidc/Authentication/Application/TokenGranter/TokenGranterMediator.php`

Inyectar y registrar `ResolverForClientCredentials` en el array de estrategias.

### D. TrustedClient — nuevos VOs

**Archivos nuevos en** `src/Features/Access/TrustedClient/Domain/ValueObject/`:
- `TrustedClientAllowedScopesM2mVO.php` — array de scopes para M2M
- `TrustedClientM2mTokenTtlVO.php` — TTL en segundos

Seguir patrón de `TrustedClientAllowedRedirectsVO.php`.

Actualizar `TrustedClientAttributes.php` y `TrustedClient.php`.

### E. ClientStore — método de verificación

**Archivo:** `src/Features/Oidc/Client/Domain/Gateway/ClientStoreGateway.php`

Añadir método:
```php
public function findAndVerify(string $clientId, string $secret, string $tenant): ?ClientData;
```

Verificar: `hash_equals(hash('sha256', $secret), $storedSecretHash)`

### F. API Management — exponer campos M2M en TrustedClient

**Archivo:** `src/Features/Access/TrustedClient/Infrastructure/Driver/Rest/TrustedClientApiDTO.php`

Añadir campos:
```php
public ?array $allowedScopesM2m = null;
public ?int   $m2mTokenTtlSeconds = 3600;
```

---

## Tests a incluir

### Test unitario — ResolverForClientCredentials

Con mocks de `ClientStoreGateway` y `TokenSigner`:

Casos:
- Credenciales válidas + scopes en subconjunto permitido → token emitido con `sub = client_id`
- Credenciales inválidas → `invalid_client`
- Grant `client_credentials` no habilitado para el cliente → `unauthorized_client`
- Scope solicitado fuera del permitido → `invalid_scope`
- Sin scope → usa todos los scopes permitidos del cliente
- Token emitido no contiene `refresh_token`
- Token emitido no contiene `id_token`
- `sub` del JWT == `client_id` (no `user_uid`)

### Test integración — POST /token con client_credentials

Casos:
- `POST /token` grant=client_credentials → `200` con access_token, sin refresh_token
- Verificar JWT: `sub` = client_id, `token_use` = "client_credentials"
- Access token usado en `/userinfo` → comportamiento definido (puede devolver solo claims del cliente)
- Access token usado en endpoint con scope requerido que el cliente tiene → `200`
- Access token con scope que el cliente no tiene → `403`
