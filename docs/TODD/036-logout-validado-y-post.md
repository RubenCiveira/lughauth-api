# 036 — Validar logout antes de destruir sesión y añadir POST

- **Estado:** pendiente. **Prioridad:** alta.
- **Evidencia:** [LogoutController::logout](../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/LogoutController.php) elimina sesión antes de validar `client_id`/redirect; [OAuthPlugin](../../src/Bootstrap/Plugin/OAuthPlugin.php) registra solo GET.
- **Impacto:** logout inducido por terceros o solicitud inválida destruye sesión; falta flujo de confirmación.
- **Por hacer:** validar `id_token_hint`, cliente y post-logout URI antes de revocar; añadir POST y confirmación/mitigación CSRF cuando no hay hint válido; unir a destinatarios filtrados de 021.
- **Aceptación:** GET inválido no cierra sesión; POST/confirmación válida sí y redirige a URI registrada.
- **Origen:** análisis OAuth §3.6/1.7 y `_2.md` logout.

## Detalle de implementación

- `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/LogoutController.php`: verificar sesión, cliente, ID token hint y URI post-logout antes de `removeSession`; dar confirmación/CSRF si no hay hint fiable, y compartir lógica entre GET/POST.
- `src/Bootstrap/Plugin/OAuthPlugin.php`: añadir POST `/logout` y asociarlo al handler correspondiente; conservar GET para inicio del flujo.
- `src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php`: usar una comprobación diferenciada de `post_logout_redirect_uri` registrada, no permitir el atajo por URL base.
- `test/integration/Features/OAuth/`: petición inválida mantiene sesión; confirmación/POST válido la elimina y solo redirige a URI permitida.
