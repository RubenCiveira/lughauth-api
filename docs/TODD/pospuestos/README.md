# Tareas pospuestas

Tareas que una épica ya cerrada dejó sin implementar porque falta un caso de uso o la aprobación de producto. No están descartadas (las descartadas, como 040 y 051, siguen en `done/` con su decisión): cada ficha indica cuándo se reabriría. Al reabrir una, se devuelve a `docs/TODD/` o se asigna a una épica, y hay que actualizar sus enlaces.

| Tarea | Épica de origen | Motivo | Se reabre |
|---|---|---|---|
| [041 API administrativa de sesiones](041-admin-sesiones-usuario.md) | [EP-09](../done/EP-09-sesion-sso-y-logout.md) | Opcional sin requerimiento; existe el autoservicio `/api/me/sessions` | Si soporte o administración necesita listar o revocar sesiones ajenas |
| [062 Session Management iframe](062-session-management-iframe.md) | [EP-09](../done/EP-09-sesion-sso-y-logout.md) | Opcional sin aprobar | Si algún RP depende de `check_session_iframe` |
| [067 Hints y step-up ACR](067-hints-y-acr-step-up.md) | [EP-09](../done/EP-09-sesion-sso-y-logout.md) | Opcional sin aprobar | Si se necesita `acr_values` o step-up a MFA |
| [069 Grant JWT bearer](069-grant-jwt-bearer.md) | [EP-11](../done/EP-11-aserciones-jwt-cliente.md) | Los sistemas externos usan API key o `private_key_jwt` (044) | Con el primer sistema externo con identidad de plataforma, o junto con 052 |
