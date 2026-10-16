# 015 — Exigir redirect registrado y origen canónico

- **Estado:** corregida en código; requiere `APP_HTTP_PUBLIC_BASE_URL` en cada despliegue. **Prioridad:** crítica.
- **Evidencia:** [ClientStoreAdapter](../../src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php) exige URI registrada exacta; el wildcard localhost registrado se compara parseando la URI (esquema `http`, host `localhost`, puerto, prefijo de ruta, sin userinfo ni fragmento), lo que cierra `http://localhost:4200@evil.com`; [Context](../../src/Shared/Context.php) exige URL pública configurada y construye el issuer común, independiente de `HTTP_HOST`.
- **Impacto corregido:** un `Host` manipulado ya no cambia discovery ni el `iss` emitido; un prefijo externo no obtiene un redirect válido.
- **Operación:** configurar `APP_HTTP_PUBLIC_BASE_URL` con el origen público **y la ruta de montaje** de la API en todos los entornos. Si falta o es inválida, se rechaza la generación de URL en vez de confiar en `Host`.
- **Aceptación:** prefijos, dominios parecidos, Host falsificado y redirects no registrados son rechazados; URI legítimas siguen funcionando.
- **Origen:** análisis OAuth §3.1/0.1 y `_2.md` brecha 1.

## Detalle de implementación

- `src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php`: quitar la aceptación por prefijo de `getBaseUrl()` en `publicClientData`; comparar URI completa con las registradas y limitar la excepción de loopback a una regla explícita y segura.
- `src/Shared/Context.php` y `config/application.yaml`: base pública de configuración validada; `getIssuer()` unifica `…/oauth/openid/{tenant}` para token, authorize, magic link, invitaciones, device, DCR y logout; discovery reutiliza esa misma base.
- `test/unit/Shared/ContextUnitTest.php`, `test/unit/Features/OAuth/Client/ClientStoreAdapterUnitTest.php` y `test/integration/Features/OAuth/DiscoveryTest.php`: cubren falta de configuración, URI malformada, prefijo externo, wildcard localhost (incluidos userinfo, host parecido, puerto y esquema) y `Host` alterado.
- `src/Features/OAuth/UserInvitation/Infrastructure/Driver/Html/InvitationAcceptHtml.php`: el botón «Login now» solo enlaza al origen de un `redirect_uri` registrado para el `client_id`.
- **Pendiente menor:** test de integración del rechazo de `post_logout_redirect_uri` no registrada en `LogoutController` (usa la misma comprobación `publicClientData`).
