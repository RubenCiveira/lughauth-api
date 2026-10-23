# 062 — Decidir soporte real de Session Management iframe

- **Estado:** pospuesta (opcional de [EP-09](../done/EP-09-sesion-sso-y-logout.md), cerrada sin aprobarla); no implementada ni anunciada actualmente. **Prioridad:** baja.
- **Evidencia:** [OpenIdConfiguration](../../../src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php) no publica `check_session_iframe`; [OAuthPlugin](../../../src/Bootstrap/Plugin/OAuthPlugin.php) dirige `/check-session` a refresh silencioso de `AuthorizeHtml`, no a un iframe de Session Management.
- **Impacto:** los RP que exijan el mecanismo OIDC de comprobación de sesión mediante iframe no pueden integrarlo con este proveedor.
- **Por hacer:** implementar iframe/postMessage y `session_state` conforme, con restricciones de origen; publicar `check_session_iframe` solo cuando funcione.
- **Aceptación:** un RP de prueba recibe comportamiento conforme y discovery anuncia la ruta solo tras implementarla.
- **Origen:** análisis OAuth §4/§5.5.

## Por qué se plantea

OIDC Session Management permite que una aplicación cliente (RP) compruebe desde el navegador si cambió la sesión del proveedor de identidad (OP), por ejemplo al cerrar sesión o cambiar de cuenta en otra pestaña. El RP consulta mediante `postMessage` un iframe del OP usando el `session_state` obtenido en la respuesta de autorización; si detecta un cambio, puede volver a comprobar el estado de autenticación y actualizar su propia sesión. `/check-session` en este proyecto tiene otra finalidad: continúa un flujo de autorización con refresh silencioso; su nombre no lo convierte en el protocolo de iframe. Se pospuso porque no hay un RP aprobado que requiera ese mecanismo adicional.

## Qué conseguiría implementarla

- Los RP compatibles podrían detectar cambios de la sesión SSO en el navegador y reaccionar sin esperar a que el usuario navegue de nuevo por `/authorize` o a que caduque su sesión local.
- El proveedor expondría un `check_session_iframe` real en discovery y emitiría un `session_state` coherente con la sesión y el origen del cliente; el iframe respondería a los mensajes sin revelar identificadores de sesión ni cookies al RP.
- Se podría comprobar el comportamiento ante sesión activa, cierre y cambio de usuario, manteniendo separados el nuevo endpoint y el flujo actual de `/check-session`.

## Qué ocurre mientras siga pospuesta

Discovery no anuncia `check_session_iframe`, por lo que un RP no debe asumir que puede usar esta modalidad de detección de cambios. El login, el SSO, el logout y el refresh silencioso disponibles siguen siendo mecanismos distintos; no sustituyen el intercambio `postMessage`/`session_state`. Un RP que dependa específicamente de Session Management necesitará adaptar su integración o esperar a que se apruebe esta tarea. La ausencia del iframe no significa por sí sola que las sesiones del servidor nunca expiren o que el logout deje de funcionar.

## Detalle de implementación (solo si se aprueba)

- `src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php`: mantener ausente `check_session_iframe` hasta registrar un iframe real; publicarlo entonces.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/` (**nuevo** `CheckSessionIframe.php`): servir HTML/JS del protocolo Session Management con `postMessage`, validación de origen y comprobación de `session_state` sin exponer cookies a otros orígenes.
- `src/Bootstrap/Plugin/OAuthPlugin.php`: registrar nueva ruta de iframe separada de `/check-session`, que ahora realiza refresh silencioso; añadir `session_state` en `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Services/OidcResponseBuilder.php`.
- `test/integration/Features/OAuth/`: probar mensajes desde origen autorizado/no autorizado y cambios de sesión.
