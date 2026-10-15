# 054 — Evaluar respuestas de autorización firmadas (JARM)

- **Estado:** no implementado; opcional. **Prioridad:** baja.
- **Evidencia:** [OpenIdConfiguration](../../src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php) anuncia algoritmos de firma/cifrado de autorización; [OidcResponseBuilder](../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Services/OidcResponseBuilder.php) solo emite parámetros directos.
- **Impacto:** RP JARM intentaría verificar un JWT inexistente.
- **Por hacer:** retirar anuncio en 030; si se exige FAPI, diseñar response JWT firmada por tenant con `iss/aud/exp` y response_mode negociado.
- **Aceptación:** RP verifica firma y claims para éxito y error; sin implementación no se anuncia.
- **Origen:** análisis OAuth §4/fase 4.

## Detalle de implementación (solo si se aprueba)

- `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Services/OidcResponseBuilder.php`: encapsular éxito/error en JWT de respuesta con `iss/aud/exp`, `state` y code, firmado por tenant y entregado en response_mode JARM permitido.
- `src/Features/OAuth/Authentication/Domain/OidcFlowContext.php` y `src/Features/OAuth/Client/Domain/ClientData.php`: negociar modo/algoritmo permitido por RP; rechazar lo no registrado.
- `src/Features/OAuth/TokenSecurity/Domain/Gateway/TokenSigner.php` y `src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php`: firma de propósito JARM y anuncios reales; mientras no exista, retirar algoritmos `authorization_*`.
- `test/integration/Features/OAuth/`: RP ficticio verifica firma, audience y expiración tanto en éxito como en error.
