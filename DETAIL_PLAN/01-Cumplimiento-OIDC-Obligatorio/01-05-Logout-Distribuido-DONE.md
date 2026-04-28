# 01-05 — Logout Distribuido (Back-Channel y Front-Channel)

[x] DONE.

**Prioridad:** P1  
**Spec:** OIDC Back-Channel Logout 1.0 / OIDC Front-Channel Logout 1.0  
**Esfuerzo estimado:** Medio (3-4 días)  
**Dependencias previas:** 01-04 Token Revocation (sesiones con `revoked_at`)  
**Habilita:** SSO real con múltiples aplicaciones

---

## Contexto

El logout actual en `LogoutController.php` limpia las cookies del navegador del usuario
(`AUTH_SESSION_ID_{TENANT}`, `PRE_SESSION_ID`) y redirige a `post_logout_redirect_uri`,
pero no notifica a los Relying Parties (RPs) que tenían sesiones activas con ese usuario.

En un entorno SSO con múltiples aplicaciones, el usuario sigue autenticado en cada RP
aunque haya cerrado sesión en el IdP.

---

## Qué implementar

### Back-Channel Logout (server-to-server)

Al cerrar sesión, el servidor envía un `logout_token` JWT a cada RP registrado
con `backchannel_logout_uri`. La aplicación RP debe terminar la sesión del usuario.

### Front-Channel Logout (iframe en browser)

La página de logout renderiza iframes con las URLs de logout de cada RP. El navegador
del usuario visita esas URLs, haciendo que cada RP limpie su sesión.

---

## Dónde y cómo hacer los cambios

### A. Campos nuevos en access_trusted_client

**Archivo nuevo:** `migrations/mysql/schema/YYYYMMDDHHMMSS_AddLogoutUrisToTrustedClient.php`

```sql
ALTER TABLE access_trusted_client
  ADD COLUMN backchannel_logout_uri              VARCHAR(250) NULL,
  ADD COLUMN backchannel_logout_session_required TINYINT(1)   NOT NULL DEFAULT 0,
  ADD COLUMN frontchannel_logout_uri             VARCHAR(250) NULL,
  ADD COLUMN frontchannel_logout_session_required TINYINT(1)  NOT NULL DEFAULT 0;
```

### B. Domain — TrustedClient VO nuevos

**Archivos nuevos en:** `src/Features/Access/TrustedClient/Domain/ValueObject/`

```
TrustedClientBackchannelLogoutUriVO.php
TrustedClientFrontchannelLogoutUriVO.php
```

Seguir el mismo patrón que `TrustedClientAllowedRedirectsUrlVO.php`.

Actualizar `TrustedClient.php` y `TrustedClientAttributes.php` con los nuevos VOs.

### C. Domain — LogoutToken builder

**Archivo nuevo:** `src/Features/Oidc/Authentication/Domain/LogoutToken.php`

El `logout_token` es un JWT firmado por el IdP con claims específicos:
```php
final class LogoutToken
{
    public static function build(
        string $issuer,
        string $subject,
        string $clientId,
        string $sessionId,
        TokenSigner $signer,
    ): string {
        $claims = [
            'iss'    => $issuer,
            'sub'    => $subject,
            'aud'    => $clientId,
            'iat'    => time(),
            'jti'    => Uuid::uuid4()->toString(),
            'events' => ['http://schemas.openid.net/event/backchannel-logout' => new \stdClass()],
            'sid'    => $sessionId,
        ];
        // NO incluir 'nonce' — el spec lo prohíbe en logout tokens
        return $signer->sign($claims);
    }
}
```

### D. Application — BackChannelLogoutDispatcher

**Archivo nuevo:** `src/Features/Oidc/Authentication/Application/BackChannelLogoutDispatcher.php`

```php
final class BackChannelLogoutDispatcher
{
    public function __construct(
        private readonly TrustedClientReadGateway $clients,
        private readonly TokenSigner $signer,
        private readonly HttpClientInterface $http,  // GuzzleHttp
    ) {}

    public function dispatch(string $userSub, string $sessionId, string $issuer, string $tenant): void
    {
        // Encontrar todos los clientes con backchannel_logout_uri del tenant
        $clients = $this->clients->findWithBackchannelLogoutUri($tenant);

        foreach ($clients as $client) {
            $logoutToken = LogoutToken::build(
                issuer: $issuer,
                subject: $userSub,
                clientId: $client->clientId,
                sessionId: $sessionId,
                signer: $this->signer,
            );

            try {
                $this->http->postAsync($client->backchannelLogoutUri, [
                    'form_params' => ['logout_token' => $logoutToken],
                    'timeout'     => 5,
                ]);
            } catch (\Throwable) {
                // RFC: best-effort, no bloquear el logout del usuario
                // Loggear el fallo para reintento manual si es necesario
            }
        }
    }
}
```

### E. LogoutController — integrar dispatchers

**Archivo:** `src/Features/Oidc/Authentication/Infrastructure/Driver/Rest/LogoutController.php`

Modificar el flujo de logout para:

1. Obtener `sid` de la sesión activa del usuario
2. Llamar a `BackChannelLogoutDispatcher::dispatch()` (async con Guzzle)
3. Recopilar `frontchannel_logout_uri` de todos los clientes activos del tenant
4. Construir respuesta HTML con iframes para front-channel logout:

```php
// En lugar de redirect inmediato, renderizar página intermedia con iframes
$frontChannelUris = $this->clients->findWithFrontchannelLogoutUri($tenant);
if (!empty($frontChannelUris)) {
    return $this->renderLogoutPage($response, $frontChannelUris, $postLogoutRedirectUri);
}
// Si no hay front-channel URIs, redirect directo
return $response->withHeader('Location', $postLogoutRedirectUri)->withStatus(302);
```

La página de logout con iframes:
```html
<!-- Template Twig: logout_with_iframes.twig -->
<html>
<body onload="setTimeout(redirect, 2000)">
  {% for uri in frontchannelUris %}
    <iframe src="{{ uri }}" style="display:none" width="0" height="0"></iframe>
  {% endfor %}
  <script>
    function redirect() { window.location = "{{ postLogoutRedirectUri }}"; }
  </script>
</body>
</html>
```

### F. TrustedClientReadGateway — nuevos métodos

**Archivo:** `src/Features/Access/TrustedClient/Domain/Gateway/TrustedClientReadGateway.php`

Añadir:
```php
/** @return TrustedClient[] */
public function findWithBackchannelLogoutUri(string $tenant): array;

/** @return TrustedClient[] */
public function findWithFrontchannelLogoutUri(string $tenant): array;
```

Implementar en `TrustedClientReadRepositoryAdapter.php`:
```sql
SELECT * FROM access_trusted_client
WHERE tenant_id = ? AND backchannel_logout_uri IS NOT NULL AND enabled = 1
```

### G. Discovery — anunciar soporte

**Archivo:** `src/Features/Oidc/Common/Infrastructure/Driver/Rest/OpenIdConfigurationController.php`

```php
'backchannel_logout_supported'         => true,
'backchannel_logout_session_supported' => true,
'frontchannel_logout_supported'        => true,
'frontchannel_logout_session_supported'=> true,
'end_session_endpoint'                 => $issuer . '/logout',
```

### H. Endpoint RP-Initiated Logout — validar id_token_hint

**Archivo:** `src/Features/Oidc/Authentication/Infrastructure/Driver/Rest/LogoutController.php`

Soporte para parámetros estándar:
- `id_token_hint` — JWT del id_token anterior (para identificar al usuario sin sesión activa)
- `post_logout_redirect_uri` — debe estar en lista blanca del cliente
- `state` — reenviar al RP en el redirect post-logout
- `logout_hint` — email o sub del usuario (opcional)

---

## Tests a incluir

### Test unitario — LogoutToken

**Archivo:** `test/Features/Oidc/Authentication/Domain/LogoutTokenUnitTest.php`

Casos:
- Token generado tiene claims correctos: `iss`, `sub`, `aud`, `iat`, `jti`, `events`, `sid`
- Token no contiene `nonce` (prohibido por spec)
- Token `events` tiene la key correcta: `http://schemas.openid.net/event/backchannel-logout`

### Test unitario — BackChannelLogoutDispatcher

**Archivo:** `test/Features/Oidc/Authentication/Application/BackChannelLogoutDispatcherUnitTest.php`

Con mocks de `TrustedClientReadGateway` y `HttpClientInterface`:

Casos:
- 2 clientes con `backchannel_logout_uri` → 2 llamadas HTTP POST
- Cliente sin URI → no se llama HTTP
- Fallo en llamada HTTP → no lanza excepción (best-effort)
- `logout_token` enviado en body como `form_params`

### Test integración — LogoutController con back-channel

**Archivo:** `test/Features/Oidc/Authentication/Infrastructure/Driver/Rest/LogoutControllerIntegrationTest.php`
*(ampliar existing tests)*

Casos adicionales:
- Logout con sesión activa → clientes con `backchannel_logout_uri` reciben POST
- Logout con `id_token_hint` válido → identifica usuario correctamente
- Logout con `post_logout_redirect_uri` no registrado → error `invalid_request`
- `state` en request → incluido en redirect post-logout

### Test integración — Front-channel logout page

Casos:
- Clientes con `frontchannel_logout_uri` → respuesta HTML con iframes
- Sin clientes front-channel → redirect directo sin HTML intermedio
