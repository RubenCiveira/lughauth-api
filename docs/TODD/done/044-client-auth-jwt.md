# 044 — Añadir autenticación de cliente JWT

- **Estado:** hecho en [EP-11](EP-11-aserciones-jwt-cliente.md) (solo `private_key_jwt`; `client_secret_jwt` descartado por la opción A de 063). **Prioridad:** media.
- **Evidencia:** [ClientStoreAdapter](../../../src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php) verifica secreto en claro; [RequestObjectValidator](../../../src/Features/OAuth/Authentication/Domain/RequestObjectValidator.php) ya obtiene JWKS de cliente para JAR, pero `/token` solo acepta Basic/body.
- **Impacto:** clientes sin secreto compartido no pueden usar `private_key_jwt`/`client_secret_jwt`.
- **Por hacer:** validar assertion firmada, `iss=sub=client_id`, audiencia exacta de token endpoint, `exp`/`jti` anti-replay y método registrado; aplicar también a PAR/introspection/revocation cuando se anuncie.
- **Aceptación:** assertion válida autentica al cliente; replay o firma ajena falla; discovery coincide.
- **Dependencia con 063:** `client_secret_jwt` (HS256) exige que el servidor conozca el secreto en claro, lo que choca con guardarlo como hash. Recomendación: implementar solo `private_key_jwt` y añadir `client_secret_jwt` únicamente si se adopta la opción B de 063 (cifrado reversible solo para esos clientes).
- **Origen:** análisis OAuth §5.3/3.1.

## Detalle de implementación (solo si se aprueba)

- `src/Features/OAuth/Client/Application/` (**nuevo verificador de client assertions**) y `Domain/ClientData.php`: validar `iss=sub=client_id`, `aud`, tiempo/JTI y método registrado; reutilizar carga JWKS mediante un puerto compartido, no llamar directamente al validador JAR desde el controller.
- `src/Features/OAuth/Authentication/Domain/RequestObjectValidator.php`: extraer su lógica de obtención/verificación de JWKS a un servicio de infraestructura reutilizable (coordinado con 047), sin mezclar semántica de JAR y client assertion.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php`, `src/Features/OAuth/Par/Infrastructure/Driver/Rest/ParController.php` y controladores de introspección/revocación: aplicar la autenticación común para cada método admitido.
- `src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php` y `test/integration/Features/OAuth/`: anunciar métodos soportados y cubrir firma, replay y audiencia de cada endpoint.
