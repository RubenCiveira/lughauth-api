# 060 — Evaluar respuesta UserInfo firmada

- **Estado:** no implementada; opcional. **Prioridad:** baja.
- **Evidencia:** [OpenIdConfiguration](../../src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php) anuncia `userinfo_signing_alg_values_supported`; [UserInfoController](../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/UserInfoController.php) solo devuelve JSON.
- **Impacto:** cliente que solicita JWT firmado recibe otro formato.
- **Por hacer:** retirar anuncio en 030; si se exige, negociar `userinfo_signed_response_alg`, firmar respuesta con `kid` y validar interoperabilidad.
- **Aceptación:** formato anunciado coincide con el entregado.
- **Origen:** análisis OAuth §4/fase 4.

## Detalle de implementación (solo si se aprueba)

- `src/Features/OAuth/Client/Domain/ClientData.php` y `Infrastructure/Driven/ClientStoreAdapter.php`: mapear `userinfo_signed_response_alg` por RP y asegurar que el access token identifica ese RP.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/UserInfoController.php`: reutilizar las claims autorizadas de 035 y, si se negoció JWT, firmarlas con audiencia e issuer apropiados y `Content-Type: application/jwt`.
- `src/Features/OAuth/TokenSecurity/Domain/Gateway/TokenSigner.php` y `src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php`: firmar con `kid` y anunciar solo algoritmos probados.
- `test/integration/Features/OAuth/`: comparar respuesta JSON y JWT firmado para el mismo usuario/scope.
