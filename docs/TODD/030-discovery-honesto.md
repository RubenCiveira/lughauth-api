# 030 — Alinear discovery con capacidades implementadas

- **Estado:** pendiente. **Prioridad:** alta.
- **Evidencia:** [OpenIdConfiguration](../../src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php) anuncia CIBA, `form_post`, `pairwise`, JWE, JARM, implicit, UserInfo firmado, claims, iframe y PAR obligatorio; [OAuthPlugin](../../src/Bootstrap/Plugin/OAuthPlugin.php) no registra varios endpoints anunciados.
- **Impacto:** clientes OIDC intentan protocolos que el servidor no soporta.
- **Por hacer:** retirar anuncios falsos y flags incongruentes (incluido `require_pushed_authorization_requests`) o hacerlos condicionales por capacidad; aprovechar `DiscoveryContributorGateway` con contratos de endpoint y tenant.
- **Aceptación:** cada endpoint/algoritmo/grant anunciado tiene implementación y test; los no disponibles no figuran.
- **Origen:** análisis OAuth §4/1.1 y `_2.md` discovery.

## Detalle de implementación

- `src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php`: retirar campos/capacidades falsos y calcular grants, modos y flags a partir de funcionalidad habilitada; anunciar PAR como no obligatorio mientras 046 no lo exija.
- `src/Features/OAuth/Oidc/Infrastructure/Driver/Rest/OpenIdConfigurationController.php`: construir el documento desde issuer canónico y aplicar contributors reales; verificar colisiones y metadatos específicos del tenant.
- `src/Features/OAuth/Oidc/Domain/Gateway/DiscoveryContributorGateway.php` y `Infrastructure/Driven/MtlsDiscoveryContributor.php`: conectar contribuciones solo si el endpoint implementa el método anunciado; crear nuevos contributors únicamente para áreas que tengan rutas reales.
- `test/integration/Features/OAuth/DiscoveryTest.php` y `test/unit/Features/OAuth/ApiDocOidcContractTest.php`: contrastar cada endpoint y algoritmo anunciado con el registro de `src/Bootstrap/Plugin/OAuthPlugin.php`.
