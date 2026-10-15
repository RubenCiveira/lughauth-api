# 015 — Exigir redirect registrado y origen canónico

- **Estado:** pendiente. **Prioridad:** crítica.
- **Evidencia:** [ClientStoreAdapter](../../src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php) acepta `str_starts_with(getBaseUrl(), $redirectUrl)` antes de mirar las URI registradas; [Context](../../src/Shared/Context.php) deriva la base de `HTTP_HOST`.
- **Impacto:** `/authorize`, magic link y post-logout pueden aceptar un destino ajeno; `iss`/discovery pueden depender de Host.
- **Por hacer:** exigir coincidencia exacta con registro; permitir solo la excepción loopback definida y delimitada por origen/puerto; configurar issuer/base pública de confianza por entorno/tenant, reutilizarla en todos los flujos.
- **Aceptación:** prefijos, dominios parecidos, Host falsificado y redirects no registrados son rechazados; URI legítimas siguen funcionando.
- **Origen:** análisis OAuth §3.1/0.1 y `_2.md` brecha 1.

## Detalle de implementación

- `src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php`: quitar la aceptación por prefijo de `getBaseUrl()` en `publicClientData`; comparar URI completa con las registradas y limitar la excepción de loopback a una regla explícita y segura.
- `src/Shared/Context.php` y configuración en `config/`: obtener la URL pública/issuer de una configuración confiable en lugar de `HTTP_HOST`; determinar política por tenant sin cambiar el issuer entre peticiones.
- `src/Features/OAuth/Oidc/Infrastructure/Driver/Rest/OpenIdConfigurationController.php`, `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php` y `src/Features/OAuth/Authentication/Domain/OidcFlowContext.php`: consumir el mismo origen canónico al construir discovery, `iss` y URLs; revisar también los consumidores de `getBaseUrl()` en logout y magic link.
- `test/integration/Features/OAuth/`: añadir casos de URI registrada, dominio prefijo, redirect externo, Host inyectado y enlace post-logout; actualizar los stubs de cliente solo si su contrato cambia.
