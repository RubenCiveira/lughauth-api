# 055 — Evaluar autenticación back-channel CIBA

- **Estado:** no implementado; opcional. **Prioridad:** baja.
- **Evidencia:** [OpenIdConfiguration](../../src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php) anuncia `/backchannel` y modos `poll/ping`; [OAuthPlugin](../../src/Bootstrap/Plugin/OAuthPlugin.php) no registra ruta CIBA.
- **Impacto:** clientes que crean en discovery reciben ruta inexistente.
- **Por hacer:** retirar anuncios en 030; si hay caso de uso, implementar solicitud autenticada, consentimiento fuera de banda, expiración, polling/ping y tests OIDC CIBA.
- **Aceptación:** no hay endpoint fantasma; si se añade, solicitud/canje vinculan usuario y cliente.
- **Origen:** análisis OAuth §4/fase 4.

## Detalle de implementación (solo si se aprueba)

- `src/Features/OAuth/Ciba/` (**módulo nuevo Domain/Application/Infrastructure**): modelar solicitud autenticada, consentimiento fuera de banda, `auth_req_id`, expiración y resultado de `poll`/`ping`, con gateway persistente.
- `src/Bootstrap/Plugin/OAuthPlugin.php`: registrar endpoint `/backchannel` y vincular el canje en `/token` solo tras completar el flujo.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php` y `src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php`: validar cliente/grant/intervalo, emitir tokens y anunciar únicamente delivery modes implementados; mientras no exista, retirar endpoint/flags CIBA.
- `test/integration/Features/OAuth/`: comprobar pendiente, consentimiento, expiración, replay y otro tenant.
