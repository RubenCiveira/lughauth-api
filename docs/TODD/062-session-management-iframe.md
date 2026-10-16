# 062 — Decidir soporte real de Session Management iframe

- **Estado:** anunciado sin implementación equivalente. **Prioridad:** baja.
- **Evidencia:** [OpenIdConfiguration](../../src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php) declara `check_session_iframe`; [OAuthPlugin](../../src/Bootstrap/Plugin/OAuthPlugin.php) dirige `/check-session` a refresh silencioso de `AuthorizeHtml`, no a un iframe de Session Management.
- **Impacto:** RP de sesión OIDC fallan en comprobación de cambios de sesión.
- **Por hacer:** retirar `check_session_iframe` del discovery o implementar iframe/postMessage y `session_state` conforme, con restricciones de origen.
- **Aceptación:** un RP de prueba recibe comportamiento conforme o no ve el metadato.
- **Origen:** análisis OAuth §4/§5.5.

## Detalle de implementación (solo si se aprueba)

- `src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php`: retirar `check_session_iframe` hasta registrar un iframe real.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/` (**nuevo** `CheckSessionIframe.php`): servir HTML/JS del protocolo Session Management con `postMessage`, validación de origen y comprobación de `session_state` sin exponer cookies a otros orígenes.
- `src/Bootstrap/Plugin/OAuthPlugin.php`: registrar nueva ruta de iframe separada de `/check-session`, que ahora realiza refresh silencioso; añadir `session_state` en `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Services/OidcResponseBuilder.php`.
- `test/integration/Features/OAuth/`: probar mensajes desde origen autorizado/no autorizado y cambios de sesión.
