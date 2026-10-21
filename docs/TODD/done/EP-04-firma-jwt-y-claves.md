# EP-04 — Firma JWT por propósito y gestión de claves

- **Tareas:** [025](025-jwt-kid-y-proposito.md) (`kid` y propósito de cada JWT) y [029](029-rotacion-claves-fuera-request.md) (claves fuera de la petición, JWKS por tenant).
- **Estado:** hecho. **Prioridad:** crítica. **Tamaño:** M. **Depende de:** nada. **Desbloquea:** EP-05 (049, 032), EP-06, EP-09 (`id_token_hint`), EP-14.
- **Esquema:** B.3, índice `(tenant, since, expiration)` en `_oauth_keys_storer`. `keyid` ya existe.

## Por qué juntas

Ambas reescriben [JoseTokenSigner](../../../src/Features/OAuth/TokenSecurity/Infrastructure/Driven/JoseTokenSigner.php) y el contrato [TokenSigner](../../../src/Features/OAuth/TokenSecurity/Domain/Gateway/TokenSigner.php). 025 necesita que la clave usada para firmar sea la que se publica con ese `kid`. 029 cambia cómo se elige y se crea esa clave. Hacerlas por separado obliga a tocar dos veces `sign()` y `keysAsJwks()`, que hoy mezclan firma, creación de claves RSA-4096 y `echo` de errores OpenSSL ([línea 222](../../../src/Features/OAuth/TokenSecurity/Infrastructure/Driven/JoseTokenSigner.php#L222)).

## Componente común

- Enum **`TokenType`** en `TokenSecurity/Domain`: `Access` (`typ: at+jwt`), `Id` (`JWT`), `Refresh` (tipo propio), `Logout` (`logout+jwt`), `PreSession` (con `signKeypass`, exclusivo).
- `TokenSigner::sign(TokenType, claims, tenant)` escribe `kid`, `alg` y `typ` en la **cabecera** JWS. Sin datos privados en la cabecera, sin `typ=Bearer` en el payload.
- `TokenSigner::verify(token, TokenType esperado, VerificationPolicy)`. La política (issuer, audiencia, tenant) la completa EP-06; aquí basta con el propósito.
- Servicio de **rotación de claves** independiente de la petición.

## Plan

1. **Contrato.** Introducir `TokenType` y adaptar las llamadas actuales sin cambiar claims: [TokenController](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php), [OidcResponseBuilder](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Services/OidcResponseBuilder.php), [LogoutToken](../../../src/Features/OAuth/Authentication/Domain/LogoutToken.php) y pre-sesión.
2. **Cabecera.** `kid` de la clave activa del tenant y `typ` por tipo.
3. **Verificación por propósito** en [ResolverForRefresh](../../../src/Features/OAuth/Authentication/Application/TokenGranter/ResolverForRefresh.php), [UserInfoController](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/UserInfoController.php), [JwtVerifierMiddleware](../../../src/Bootstrap/Middleware/JwtVerifierMiddleware.php), logout y pre-sesión. El endurecimiento de issuer, audiencia y tenant del middleware queda en EP-06.
4. **Compatibilidad.** Los refresh sin `jti`/`fid` ya se rechazan desde la 020, así que solo hace falta tolerar **access** sin `typ` durante su TTL (horas). Fijar fecha de corte en configuración y eliminar la rama al vencer.
5. **Claves (029).**
   - Separar `checkNewNeeded`/`createKeyPair` de `sign`/`keysAsJwks`; firmar nunca genera RSA. Si no hay clave activa: error controlado y log, sin salida HTTP.
   - Comando o job de rotación por tenant (en [OAuthCleanupScheduler](../../../src/Features/OAuth/Session/Application/OAuthCleanupScheduler.php) o un comando nuevo de TokenSecurity) con ventana dual: la clave futura se publica antes de activarse y la antigua sigue publicada durante la vida máxima de los tokens (refresh: `fexp` = 30 días).
   - Caché de JWKS por tenant al invalidar.
   - Migración del índice B.3.

## Pruebas

- Decodificar cabeceras: `kid` presente en el JWKS del tenant y `typ` correcto por tipo.
- Rechazo cruzado: ID como access, access como refresh, logout token como ID, etc.
- JWKS aislado entre tenants; verificación con la clave antigua durante la ventana ([DiscoveryTest](../../../test/integration/Features/OAuth/DiscoveryTest.php)).
- Firmar sin clave activa no escribe en la salida; como fallback solo genera la clave activa (ver «Fallback sin cron»).

## Aceptación

Unión de 025 y 029: cada verificador rechaza un token válido de otro propósito, todo JWT referencia una clave publicada (y se rechaza si no la nombra), las claves las aprovisiona la rotación programada y firmar solo crea la clave activa como fallback cuando esa rotación no ha corrido, y cada tenant publica e invalida solo su JWKS.

## Fuera de alcance

Claims y perfil RFC 9068 del access (049, EP-05); ES256/EdDSA (050, EP-14).

## Resultado

### Decisiones tomadas

- **`typ` por propósito** ([TokenType](../../../src/Features/OAuth/TokenSecurity/Domain/TokenType.php)): `at+jwt`, `JWT` (ID), `refresh+jwt`, `logout+jwt` y `presession+jwt`. El payload ya no lleva `typ`. Solo `signKeypass` firma pre-sesión; `sign(TokenType::PreSession, …)` lanza excepción.
- **Contrato:** `sign(TokenType, claims, tenant, expiration)` y `verify(tenant, token, VerificationPolicy)`. [VerificationPolicy](../../../src/Features/OAuth/TokenSecurity/Domain/VerificationPolicy.php) lleva los propósitos aceptados y si se comprueba `nbf`/`exp`. `ignoringTime()` sustituye a `parseSignedPayload`, y `verifyTokenPayload` desaparece. EP-06 añadirá issuer, audiencia y tenant a la política.
- **Verificación por `kid`:** un token solo se verifica con la clave publicada que indica su `kid`; sin `kid`, o con un `kid` ausente del JWKS, se rechaza. Vale para `JoseTokenSigner` y para `JwtVerifierMiddleware`: sin `kid` rechaza sin volver a descargar el JWKS; con un `kid` desconocido lo descarga una vez por si la clave acaba de rotar.
- **Caché del middleware versionada:** la verificación cacheada por token usa el prefijo `JwtVerifierMiddleware::CACHE_PREFIX` (`verify_access_v2_`), así que los resultados guardados antes del despliegue (tokens sin `typ` ni `kid`) dejan de autenticar al momento en vez de hasta su `exp`.
- **Consumidores:** refresh (`TokenController` y `ResolverForRefresh`) exige `Refresh`; `id_token_hint` de logout exige `Id` sin tiempos; revocación acepta `Access` y `Refresh` sin tiempos; introspección acepta `Access` y `Refresh` (EP-06 decidirá si el refresh sigue activo); el middleware exige `at+jwt`.
- **Sin compatibilidad:** se descarta el punto 4 del plan. Cualquier token emitido antes del despliegue (sin `typ` ni `kid` en la cabecera) se rechaza, también los access: quien tenga uno pide otro y los usuarios con refresh tokens antiguos vuelven a iniciar sesión.
- **Claves por tenant:** desaparece `useTenant = false`. Las claves compartidas antiguas (`tenant = '-'`) se siguen publicando en el JWKS de todos los tenants hasta que se purgan, y firman para un tenant que aún no tiene claves propias, para que el cambio no tenga corte. Nunca se crean claves nuevas con `'-'`. El middleware sigue con una sola URL de JWKS: cada frontend la configura con la de su tenant.
- **Rotación** ([KeyRotationService](../../../src/Features/OAuth/TokenSecurity/Application/KeyRotationService.php)): encadena claves sin solaparlas (cada una empieza cuando acaba la anterior). Rellena hasta la activa + `futures` (3) cuando la cobertura baja de `futures × ttl`, publica las futuras antes de activarlas y conserva las retiradas `retention` = 30 días (`fexp` del refresh); después las purga. Invalida solo la caché JWKS del tenant rotado (`KeyRotationService::jwksCacheKey`).
- **Cuándo se rota:** `rotateAll` cada 15 minutos desde el scheduler (`OAuthPlugin::registerSchedulers`), sobre los tenants habilitados de `access_tenant`, y `rotate` al crear un tenant (listener [ProvisionTenantKeys](../../../src/Features/OAuth/TokenSecurity/Application/Listener/ProvisionTenantKeys.php), también en la instalación). Un fallo se registra en el log y se reintenta en la siguiente ejecución.
- **Fallback sin cron:** el scheduler es la vía principal, pero no un requisito. Si al firmar el tenant no tiene clave activa, se crea solo esa (un RSA) en la petición y las futuras se generan al terminar, con la respuesta ya enviada. Si tiene clave activa pero menos de 3 futuras, la cadena se completa también al terminar. Un bloqueo por tenant evita duplicados entre el scheduler y peticiones concurrentes. El firmante usa el puerto [SigningKeyProvisioner](../../../src/Features/OAuth/TokenSecurity/Domain/Gateway/SigningKeyProvisioner.php), que implementa `KeyRotationService`.
- **Sin clave posible:** si no se puede crear la clave, `sign` registra un error y lanza `NoActiveKeyException`, sin escribir en la salida. La generación vive en [OpenSslKeyPairGenerator](../../../src/Features/OAuth/TokenSecurity/Infrastructure/Driven/OpenSslKeyPairGenerator.php) y lleva los errores de OpenSSL en la excepción.

### Qué cambió

- `TokenStoreGateway`: `latestExpiration`, `listKeys(tenant, expiringAfter)`, `purgeExpired` y `tenantsToProvision`. `TokenStoreSqlAdapter` deja de borrar claves en su constructor (es decir, en cada petición).
- Migración `20261020120000`: índice `(tenant, since, expiration)` en `_oauth_keys_storer` (B.3).
- Pruebas: [TokenSigningTest](../../../test/integration/Features/OAuth/TokenSigningTest.php) (matriz cruzada de propósitos, `keypass` del refresh no vale como pre-sesión, ventana de retención, rechazo de tokens sin `typ` o sin `kid`, y fallback: firma sin clave, cadena corta y fallo de generación), [TokenStoreSqlAdapterTest](../../../test/integration/Features/OAuth/TokenStoreSqlAdapterTest.php) (MySQL), [DiscoveryTest](../../../test/integration/Features/OAuth/DiscoveryTest.php) (JWKS aislado y caché por tenant), `TokenEndpointTest` (`kid` publicado y `typ` por tipo en lo emitido; access e ID rechazados como refresh), `KeyRotationServiceUnitTest` y el middleware (ID, refresh, logout, pre-sesión y tokens sin `typ` rechazados).

### Pendiente fuera de esta épica

- Cuando se purguen las claves `'-'` (30 días después de su expiración), eliminar `LEGACY_TENANT` de `TokenStoreSqlAdapter`.
- Issuer, audiencia y tenant en `VerificationPolicy` y en el middleware (EP-06).
