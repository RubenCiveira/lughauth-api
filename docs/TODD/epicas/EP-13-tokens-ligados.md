# EP-13 — Tokens ligados a clave o certificado

- **Tareas:** [048](../048-dpop.md) (DPoP, RFC 9449) y [053](../053-mtls-certificados.md) (mTLS y tokens ligados a certificado, RFC 8705).
- **Prioridad:** baja (extensiones; necesarias si se persigue FAPI). **Tamaño:** L. **Depende de:** EP-05 (`ClaimsMapper` para `cnf`), EP-06 (`AccessTokenPolicy`), EP-07 (`ReplayJtiGateway`). Para `tls_client_auth`, EP-11 (`ClientAuthenticator`).
- **Esquema:** A.1 `dpop-bound-access-tokens`, `tls-client-auth-subject-dn`, `tls-client-certificate-bound-access-tokens`; B.2 `_oauth_replay_jti` con `purpose=dpop`. El `cnf.jkt` viaja en `code_data`.

## Por qué juntas

Las dos son *sender-constrained tokens* y se implementan en los mismos tres puntos:

1. Al emitir, el access (y el refresh de clientes públicos) lleva un claim `cnf` (`jkt` o `x5t#S256`).
2. El vínculo se conserva desde authorize/code hasta canje y refresh ([TemporalAuthCode](../../../src/Features/OAuth/Session/Domain/TemporalAuthCode.php)).
3. Al consumir, [JwtVerifierMiddleware](../../../src/Bootstrap/Middleware/JwtVerifierMiddleware.php) exige la prueba correspondiente (cabecera DPoP o certificado del proxy).

Un único **`ProofOfPossession`** con dos implementaciones evita duplicar la lógica en `TokenController` y en el middleware.

## Plan

1. **Fase de decisión.** ¿Hay RP o resource servers que lo exijan? ¿Se dispone de terminación TLS de confianza que reenvíe el certificado (necesaria para 053)?
2. **Abstracción.** Interfaz `ProofOfPossession` (`bindingFor(request)` → `cnf`; `verify(request, cnf)`); `AccessTokenPolicy` la invoca si el token tiene `cnf`; la política bearer sigue separada.
3. **DPoP (048).** Validar `htu`, `htm`, `iat`, `jti` (anti-replay), firma y `jwk`; `DPoP-Nonce` opcional; tipo de token `DPoP` en la respuesta; `WWW-Authenticate: DPoP`.
4. **mTLS (053).** Certificado del proxy solo desde IPs de confianza (rechazar cabeceras falsificadas); `tls_client_auth` como método de `ClientAuthenticator`; `cnf.x5t#S256`; endpoints `mtls_endpoint_aliases` reales en [MtlsDiscoveryContributor](../../../src/Features/OAuth/Oidc/Infrastructure/Driven/MtlsDiscoveryContributor.php).
5. Activar en discovery `dpop_signing_alg_values_supported` y `tls_client_certificate_bound_access_tokens` solo tras pasar los tests.

## Pruebas

Prueba ausente, repetida, con otra clave o con `htu`/`htm` alterados; certificado ajeno o ausente; cabecera de proxy falsificada; refresh conservando el vínculo.

## Aceptación

Unión de 048 y 053.
