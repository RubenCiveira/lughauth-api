# EP-04 — Firma JWT por propósito y gestión de claves

- **Tareas:** [025](../025-jwt-kid-y-proposito.md) (`kid` y propósito de cada JWT) y [029](../029-rotacion-claves-fuera-request.md) (claves fuera de la petición, JWKS por tenant).
- **Prioridad:** crítica. **Tamaño:** M. **Depende de:** nada. **Desbloquea:** EP-05 (049, 032), EP-06, EP-09 (`id_token_hint`), EP-14.
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
- Firmar sin clave activa no genera RSA ni escribe en la salida.

## Aceptación

Unión de 025 y 029: cada verificador rechaza un token válido de otro propósito, todo JWT referencia una clave publicada, firmar no genera claves y cada tenant publica e invalida solo su JWKS.

## Fuera de alcance

Claims y perfil RFC 9068 del access (049, EP-05); ES256/EdDSA (050, EP-14).
