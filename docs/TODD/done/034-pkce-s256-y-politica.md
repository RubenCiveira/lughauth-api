# 034 — Restringir PKCE a S256 y aplicar política del cliente

- **Estado:** hecho (EP-07). **Prioridad:** media.
- **Evidencia:** [PkceChallenge](../../../src/Features/OAuth/Authentication/Domain/ValueObject/PkceChallenge.php) permite `plain` por defecto; [AuthorizeHtml::requirePkce](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php) exige challenge en general; el atributo `requirePkce` del TrustedClient no llega a [ClientData](../../../src/Features/OAuth/Client/Domain/ClientData.php).
- **Impacto:** PKCE débil y configuración del cliente ignorada.
- **Por hacer:** exigir S256 para nuevos flujos, actualizar discovery y decidir política de transición/obligatoriedad para clientes confidenciales según requisitos OAuth actuales.
- **Aceptación:** `plain` rechazado en authorize y code exchange; clientes con política PKCE reciben validación coherente.
- **Origen:** análisis OAuth §5.1/1.5.

## Detalle de implementación

- `src/Features/OAuth/Authentication/Domain/ValueObject/PkceChallenge.php`: rechazar `plain` y ausencia de método cuando haya challenge; validar longitud y alfabeto del verifier/challenge S256.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php` y `Infrastructure/Driver/Rest/TokenController.php`: aplicar la misma política al crear y canjear code; exigir PKCE al cliente que lo requiera.
- `src/Features/OAuth/Client/Domain/ClientData.php` y `Infrastructure/Driven/ClientStoreAdapter.php`: mapear `requirePkce` del TrustedClient, con valor seguro para clientes públicos.
- `src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php` y `test/integration/Features/OAuth/TokenEndpointTest.php`: anunciar únicamente S256 y comprobar rechazo de `plain`/downgrade.
