# EP-06 — Validación de bearer en los consumidores

- **Tareas:** [026](../026-validar-issuer-audience-bearer.md) (issuer, audience y tenant), [028](../028-revocacion-en-bearer.md) (revocación en bearer) y [035](../035-userinfo-conforme.md) (UserInfo GET/POST). Completa el lado consumidor de [025](../025-jwt-kid-y-proposito.md) y [049](../049-perfil-at-jwt.md).
- **Prioridad:** crítica. **Tamaño:** M. **Depende de:** EP-04 (propósito). 028 puede empezar antes. **Desbloquea:** EP-12, EP-13, EP-14 (060).
- **Esquema:** sin cambios; `_oauth_revoked_jti` ya tiene `tenant_id` e índices (B.5).

## Por qué juntas

Las tres modifican [JwtVerifierMiddleware](../../../src/Bootstrap/Middleware/JwtVerifierMiddleware.php), incluida su caché de identidad, y los dos consumidores que lo usan: [UserInfoController](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/UserInfoController.php) e [IntrospectionController](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/IntrospectionController.php). La caché es el punto delicado de 026 y de 028: una identidad cacheada no debe saltarse ni el tenant ni la revocación. Diseñarla una vez evita reabrirla.

## Componente común

**`AccessTokenPolicy`**: un único verificador de access token que aplican middleware, UserInfo e introspección:

1. Firma y tiempos con `TokenType::Access` (EP-04).
2. `iss` = issuer canónico del tenant **de la ruta**, no el configurado opcionalmente en `security.jwt.verify.*`.
3. `aud` según la ruta o el resource server.
4. Claim `tenant` obligatorio e igual al de la ruta; sin el `'main'` por defecto de las líneas [182](../../../src/Bootstrap/Middleware/JwtVerifierMiddleware.php#L182) y [243](../../../src/Bootstrap/Middleware/JwtVerifierMiddleware.php#L243).
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
