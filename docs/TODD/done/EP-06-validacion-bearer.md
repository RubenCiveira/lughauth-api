# EP-06 — Validación de bearer en los consumidores

- **Tareas:** [026](026-validar-issuer-audience-bearer.md) (issuer, audience y tenant), [028](028-revocacion-en-bearer.md) (revocación en bearer) y [035](035-userinfo-conforme.md) (UserInfo GET/POST). Completa el lado consumidor de [025](025-jwt-kid-y-proposito.md) y [049](049-perfil-at-jwt.md).
- **Estado:** hecho (026, 028 y 035). **Prioridad:** crítica. **Tamaño:** M. **Depende de:** EP-04 (propósito). 028 puede empezar antes. **Desbloquea:** EP-12, EP-13, EP-14 (060).
- **Esquema:** sin cambios; `_oauth_revoked_jti` ya tiene `tenant_id` e índices (B.5).

## Por qué juntas

Las tres modifican [JwtVerifierMiddleware](../../../src/Bootstrap/Middleware/JwtVerifierMiddleware.php), incluida su caché de identidad, y los dos consumidores que lo usan: [UserInfoController](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/UserInfoController.php) e [IntrospectionController](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/IntrospectionController.php). La caché es el punto delicado de 026 y de 028: una identidad cacheada no debe saltarse ni el tenant ni la revocación. Diseñarla una vez evita reabrirla.

## Componente común

**`AccessTokenPolicy`**: un único verificador de access token que aplican middleware, UserInfo e introspección:

1. Firma y tiempos con `TokenType::Access` (EP-04).
2. `iss` = issuer canónico del tenant **de la ruta**, no el configurado opcionalmente en `security.jwt.verify.*`.
3. `aud` según la ruta o el resource server.
4. Claim `tenant` obligatorio e igual al de la ruta; sin el `'main'` por defecto que aplicaba `JwtVerifierMiddleware`.
5. `jti` no revocado ([TokenRevocationGateway](../../../src/Features/OAuth/TokenSecurity/Domain/Gateway/TokenRevocationGateway.php)), **también con la caché caliente**.

La caché guarda solo el resultado criptográfico y los claims, con TTL acotado. Los pasos 2, 4 y 5 se reevalúan siempre.

## Plan

1. **Revocación (028).** Inyectar `TokenRevocationGateway` en el middleware y comprobar `jti`/tenant en cada petición; revisar el índice y la conservación hasta `exp` en [TokenRevocationSqlAdapter](../../../src/Features/OAuth/TokenSecurity/Infrastructure/Driven/TokenRevocationSqlAdapter.php).
2. **Issuer, audience y tenant (026).** Decidir la semántica de `audiences`. Hoy, desde `7343131f`, exige **todas**; se recomienda «al menos una aceptada» (RFC 7519 §4.1.3). Aplicar los valores efectivos aunque no haya configuración global y rechazar tokens sin `tenant`.
3. **Introspección.** Misma política; `active: false` para ID, refresh, revocados y de otro tenant.
4. **UserInfo (035).**
   - Registrar POST `/userinfo` en [OAuthPlugin](../../../src/Bootstrap/Plugin/OAuthPlugin.php) con el mismo handler.
   - Exigir scope `openid`.
   - `email_verified` y `address` solo si se conocen y el scope los permite, mediante el `ClaimsMapper` de EP-05 si ya existe.
   - 401 con `WWW-Authenticate: Bearer error="invalid_token"`.

## Pruebas

- `iss`/`aud` ausentes o ajenos y token válido de otro tenant, tanto por HTTP como en consumo local.
- Revocar después de la primera validación y repetir con la caché caliente: rechazado antes de `exp` ([IntrospectionTest](../../../test/integration/Features/OAuth/IntrospectionTest.php)).
- UserInfo GET y POST equivalentes; ID y refresh rechazados; 401 con cabecera.

## Aceptación

Unión de 026, 028 y 035: un token con `iss`, `aud` o tenant incorrectos, o revocado, falla en todos los consumidores aunque no exista configuración global opcional.

## Fuera de alcance

DPoP y mTLS (EP-13) y UserInfo firmado o cifrado (EP-14). Ambas épicas se apoyarán en `AccessTokenPolicy`.

## Resultado

### Decisiones tomadas

- **Un único verificador:** [AccessTokenPolicy](../../../src/Features/OAuth/TokenSecurity/Application/AccessTokenPolicy.php) (TokenSecurity, Application) aplica los cinco pasos. `verify(tenant, token, audiences)` verifica la firma con las claves locales del tenant (`TokenSigner`, solo `TokenType::Access`) y después llama a `check()`. `check(claims, tenant, audiences)` aplica los pasos 2 a 5 a claims cuya firma ya está verificada, por ejemplo contra un JWKS remoto. Lo usan el middleware, UserInfo e introspección.
- **Caché:** solo guarda los claims ya verificados, con clave por tenant y SHA-256 del token y una vida de `min(exp − ahora, 300 s)` (`AccessTokenPolicy::MAX_CACHE_TTL`). `exp`/`nbf`, `iss`, `aud`, `tenant` y la revocación se evalúan en cada petición. El middleware guarda igual sus verificaciones contra el JWKS (prefijo `verify_access_v3_`: claims o rechazo, ya no la identidad serializada) y reconstruye la identidad en cada petición. Las entradas `v2` se ignoran.
- **Issuer:** `iss` debe ser el issuer canónico (`Context::getIssuer`) del tenant que consume el token. En rutas con `{tenant}` es el tenant de la ruta. En el resto de rutas (APIs `/api/*`), el tenant se deduce del propio `iss`, que tiene que ser canónico de este despliegue (`AccessTokenPolicy::tenantOfIssuer`). Si `security.jwt.verify.issuer` está configurado, además debe coincidir con él. Ya no basta con la configuración opcional.
- **Audiencia («al menos una»):** `aud` es obligatorio siempre. Si el consumidor indica audiencias, el token debe contener al menos una (RFC 7519 §4.1.3), en vez de todas como desde `7343131f`. `security.jwt.verify.audiences` es una lista separada por comas (se recortan espacios) y solo se aplica a las rutas sin `{tenant}`, que son las del resource server. **Sin esa configuración, la audiencia efectiva es el nombre de la aplicación** (`AppConfig::$name`, de `APP_NAME`, `app` por defecto): un token dirigido a otro recurso se rechaza igual. UserInfo e introspección son endpoints del propio emisor y no restringen la audiencia, porque el `aud` de un access token es el cliente más los recursos concedidos.
- **Tenant:** el claim `tenant` es obligatorio, sin `'main'` por defecto, y debe ser el tenant consumidor. Hoy contiene el **uid** en los tokens de usuario (`LoginAdapter`) y el **nombre** en `client_credentials` (`ResolverForClientCredentials`), así que la política acepta cualquiera de los dos. El uid se resuelve con `TenantQueryRepositoryGateway::findOneByName` y se cachea 300 s.
- **Revocación (028):** `TokenRevocationGateway::isRevoked(jti, tenant)` se consulta en cada petición, también con la caché caliente, y un token sin `jti` se rechaza. La clave es el nombre del tenant, el mismo con el que escribe `/revoke` ([RevokeTokenUsecase](../../../src/Features/OAuth/Authentication/Application/Usecase/RevokeToken/RevokeTokenUsecase.php)). `TokenRevocationSqlAdapter` ya consultaba por `(jti, tenant_id)` con `LIMIT 1` sobre la PK, y `cleanup()` solo borra filas con `expires_at` pasado: la revocación dura hasta el `exp` del token. No hizo falta cambiar el esquema.
- **Middleware por ruta:** en rutas con `{tenant}` (las de `/oauth/openid/{tenant}`), [JwtVerifierMiddleware](../../../src/Bootstrap/Middleware/JwtVerifierMiddleware.php) verifica con las claves locales de ese tenant (`AccessTokenPolicy::verify`). Antes usaba siempre el JWKS configurado, que en `.env` es el de `main`, así que UserInfo rechazaba los tokens de cualquier otro tenant. En el resto de rutas sigue usando el JWKS configurado, con un reintento tras refrescarlo, y luego `check()`. El middleware sigue desactivado con `security.jwt.verify.publickey.location = '-'`. El esquema `Bearer` se reconoce sin distinguir mayúsculas (RFC 7235 §2.1), igual que en UserInfo: antes `bearer <token>` pasaba sin verificar.
- **UserInfo (035):** [UserInfoController](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/UserInfoController.php) verifica el bearer por sí mismo, así que no depende de que el middleware esté activo. Lo lee de `Authorization: Bearer` o, en POST, del parámetro `access_token` (RFC 6750 §2.2). Se registró `POST /userinfo` con el mismo comportamiento. Exige el scope `openid` (si falta: 403 y `WWW-Authenticate: Bearer error="insufficient_scope", scope="openid"`). Un token ausente o inválido devuelve 401 con `WWW-Authenticate: Bearer error="invalid_token"`. Se añadió `address` con el scope `address`. `email` sale del claim del token. `email_verified` no se devuelve: los usuarios locales no guardan si el correo está verificado. El mapeo sigue en el controlador: `ClaimsMapper` construye claims de token a partir de un `TokenGrant`, no del perfil.
- **401 del middleware:** [ErrorsPlugin](../../../src/Bootstrap/Plugin/ErrorsPlugin.php) añade `WWW-Authenticate: Bearer error="invalid_token"` cuando la petición rechazada llevaba un bearer.
- **Introspección:** usa la misma política. Devuelve `active: false` para ID tokens, refresh tokens, tokens revocados, de otro tenant o issuer, y sin `aud` o sin `tenant`. **Cambio de comportamiento:** antes un refresh token vigente salía `active: true`.

### Qué cambió

- Pruebas de extremo a extremo: [BearerHttpTest](../../../test/integration/Features/OAuth/BearerHttpTest.php) monta la app Slim real con las rutas de `OAuthPlugin`, el enrutado, `JwtVerifierMiddleware` y `ErrorsPlugin` en el orden de `Micro`, más una ruta de recurso `/api/whoami`. Comprueba: token emitido por `/token` aceptado en UserInfo de su tenant; rechazado en UserInfo de otro tenant, tanto en cabecera (middleware) como en el cuerpo (controlador); ID token rechazado; la API exige su audiencia; esquema `bearer` en minúsculas verificado; revocar por `/revoke` tras validaciones cacheadas lo rechaza en la API, en UserInfo y en `/introspect`.
- Pruebas: [UserInfoTest](../../../test/integration/Features/OAuth/UserInfoTest.php) (tokens reales de `/token`: GET, POST y `access_token` en el cuerpo dan los mismos claims; claims por scope; sin `openid`; sin token; ID y refresh rechazados; revocado tras una validación cacheada; token válido de otro tenant; `client_credentials`; tokens firmados por el tenant con `iss`, `aud` o `tenant` ausentes o ajenos), [IntrospectionTest](../../../test/integration/Features/OAuth/IntrospectionTest.php) (revocado con caché caliente, ID y refresh inactivos, sin `tenant`), `AccessTokenPolicyUnitTest` (cada rechazo, tenant por nombre o uid, `exp` revisado sobre claims cacheados, `tenantOfIssuer`) y `JwtVerifierMiddlewareUnitTest` (issuer ajeno, ausente o distinto del configurado; sin tenant o de otro; sin audiencia; ninguna o al menos una de las configuradas; revocado con caché caliente; TTL acotado; rutas de tenant verificadas con sus claves; audiencia ajena rechazada sin configuración; esquema `Bearer` sin distinguir mayúsculas).

### Pendiente fuera de esta épica

- **Claim `tenant` inconsistente:** uid en tokens de usuario y nombre en `client_credentials`. Por eso `Identity::getTenant()` de un token M2M en `/api/*` es un nombre, mientras que los filtros `TenantAccessible` esperan un uid. Conviene unificarlo (uid) en la emisión.
- **Bearer opaco de DCR:** `/register/{client_id}` recibe el registration access token, opaco, como `Authorization: Bearer`. Con el middleware activo, en esa ruta se intenta verificar como JWT y se rechaza con 401, igual que antes de esta épica. Habría que excluir esas rutas del middleware o marcar las que validan su propio bearer.
- **Remote AS:** las rutas sin `{tenant}` solo aceptan tokens con issuer canónico de este despliegue. Si alguna vez el middleware valida tokens de otro servidor de autorización, harán falta un issuer configurado explícito y una revocación remota (introspección).
- El análisis estático no se pudo ejecutar: `.ci/psalm.xml` resuelve `src` relativo a `.ci/`.
