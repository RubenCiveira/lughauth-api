# EP-12 — Grants y flujos por decisión de producto

- **Tareas:** [043](../043-decidir-grant-delegated.md) (grant `delegated`), [052](../052-token-exchange.md) (token exchange RFC 8693) y [055](../055-ciba.md) (CIBA).
- **Prioridad:** baja. **Tamaño:** decisión (S) y después M (052) o L (055). **Depende de:** EP-05 (`TokenFactory`) y EP-06 (validación del `subject_token`). Los anuncios ya los retiró EP-01.
- **Esquema:** 055 `_oauth_ciba_request` (B.4); 043 y 052 sin cambios.

## Por qué juntas

Las tres están **sin caso de uso aprobado** y comparten el mismo patrón de implementación: una estrategia nueva en `TokenGranterMediator`, permiso por cliente en `ClientData`, emisión vía `TokenFactory` y anuncio condicional. 043 recomienda resolverse con 052, así que decidirlas por separado duplicaría análisis.

## Fase 1 — decisión (un documento, una reunión)

Para cada flujo: actor y sujeto, quién lo autoriza, audiencia y alcance máximo, auditoría, y alternativa estándar existente.

- **043 + 052:** si hay delegación o impersonación entre servicios, adoptar RFC 8693 (052) y cerrar 043 como «cubierta por 052». Si no la hay, descartar ambas.
- **055:** solo si existe un canal fuera de banda real (app móvil, push) para el consentimiento.

El resultado se registra en las fichas 043, 052 y 055 («Decisiones tomadas»).

## Fase 2 — implementación (solo lo aprobado)

1. **052.** `ResolverForTokenExchange`: valida `subject_token` y `actor_token` con `AccessTokenPolicy` (EP-06); tipos admitidos; scope y audiencia ⊆ los del `subject_token` y los permitidos al cliente; emite el claim `act`. Errores y campos de respuesta RFC 8693 (`issued_token_type`).
2. **055.** Módulo `Features/OAuth/Ciba` (Domain, Application, Infrastructure): `/backchannel` autenticado con `ClientAuthenticator` (EP-11 si existe), `auth_req_id`, expiración, `poll` (y `ping` si se aprueba), con `slow_down` por intervalo; el canje en `/token` solo tras el consentimiento.
3. Registro condicional en `TokenGranterMediator` y [OAuthPlugin](../../../src/Bootstrap/Plugin/OAuthPlugin.php); activar capacidades en discovery.

## Pruebas

- 052: impersonación no autorizada, tenant cruzado, actor no permitido y escalada de scope.
- 055: pendiente, consentido, denegado, expirado, replay y otro tenant.

## Aceptación

Decisión documentada para las tres. Si se implementan, las de 052 y 055.
