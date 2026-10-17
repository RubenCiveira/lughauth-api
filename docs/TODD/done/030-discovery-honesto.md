# 030 — Alinear discovery con capacidades implementadas

- **Estado:** hecho. **Prioridad:** alta.
- **Evidencia de cierre:** [OpenIdConfiguration](../../../src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php) deriva los metadatos de [OidcCapabilities](../../../src/Features/OAuth/Oidc/Domain/OidcCapabilities.php); [OAuthPlugin](../../../src/Bootstrap/Plugin/OAuthPlugin.php) registra los endpoints anunciados y conecta el contributor mTLS, que solo publica alias cuando hay URL de terminación configurada. [DiscoveryTest](../../../test/integration/Features/OAuth/DiscoveryTest.php) contrasta las rutas, incluidos los alias mTLS, y los flags; las firmas RS256, ES256 y PS256 de request objects están cubiertas en [RequestObjectValidatorUnitTest](../../../test/unit/Features/OAuth/Authentication/Domain/RequestObjectValidatorUnitTest.php).
- **Impacto corregido:** discovery ya no anuncia protocolos no implementados ni PAR obligatorio; el issuer es canónico por tenant.
- **Realizado:** retirada de anuncios falsos y flags incongruentes; capacidades centralizadas y contributors que no pueden sobrescribir campos base.
- **Aceptación:** cada endpoint/algoritmo/grant anunciado tiene implementación y test; los no disponibles no figuran.
- **Origen:** análisis OAuth §4/1.1 y `_2.md` discovery.

## Detalle de implementación

- `src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php`: metadatos derivados de las capacidades implementadas; PAR no obligatorio.
- `src/Features/OAuth/Oidc/Infrastructure/Driver/Rest/OpenIdConfigurationController.php`: issuer canónico del tenant y detección de colisiones de contributors.
- `src/Features/OAuth/Oidc/Infrastructure/Driven/MtlsDiscoveryContributor.php`: alias solo con URL mTLS configurada y rutas registradas.
- `test/integration/Features/OAuth/DiscoveryTest.php`: comprueba rutas principales y alias, issuer por tenant y flags; `test/unit/Features/OAuth/ApiDocOidcContractTest.php` comprueba las rutas documentadas en OpenAPI.
