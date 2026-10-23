# 041 — Evaluar API administrativa de sesiones de usuario

- **Estado:** pospuesta (opcional de [EP-09](../done/EP-09-sesion-sso-y-logout.md), cerrada sin aprobarla); no implementado, decisión de producto. **Prioridad:** baja.
- **Evidencia:** [OAuthPlugin](../../../src/Bootstrap/Plugin/OAuthPlugin.php) ofrece autoservicio `/api/me/sessions`, pero no ruta de administración de sesiones de otro usuario.
- **Impacto:** soporte/administración no puede listar ni revocar sesiones ajenas por API específica.
- **Por hacer:** definir autorización, tenant y auditoría para listar/revocar sesiones por uid, reutilizando [SessionStoreGateway](../../../src/Features/OAuth/Session/Domain/Gateway/SessionStoreGateway.php); implementar solo si hay requerimiento.
- **Aceptación:** decisión registrada; si se añade, administrador autorizado opera solo en su tenant y usuario normal recibe denegación.
- **Origen:** análisis OAuth §5.5/2.3 y `_2.md` administración.

## Por qué se plantea

El autoservicio `/api/me/sessions` permite que una persona consulte y cierre **sus propias** sesiones, pero presupone que puede autenticarse y actuar por sí misma. No resuelve incidencias en las que soporte debe contener una cuenta comprometida, cerrar sesiones tras una baja o atender a una persona que ha perdido el acceso a sus dispositivos. Esas operaciones afectan a otra identidad y requieren una autorización distinta de la del autoservicio. La tarea está pospuesta porque no hay todavía un caso de uso administrativo aprobado que justifique exponer esa capacidad.

## Qué conseguiría implementarla

- Un operador autorizado podría localizar las sesiones activas de un usuario dentro de un tenant y revocar una sesión concreta o todas las que proceda, sin hacerse pasar por el titular ni conocer sus cookies.
- La operación quedaría delimitada por identidad, tenant/issuer y permisos administrativos, con registro auditable de quién actuó, sobre qué usuario y por qué. Esto permitiría responder y reconstruir una intervención de soporte.
- El cierre sería verificable para las sesiones gestionadas por el almacén; habría que definir expresamente el efecto sobre tokens ya emitidos y sobre las sesiones de otros clientes, para no prometer una revocación inmediata que la API no garantice.

## Qué ocurre mientras siga pospuesta

La persona usuaria puede seguir gestionando sus sesiones mediante `/api/me/sessions` y los flujos de logout existentes. Soporte y administración, en cambio, no disponen de un endpoint específico para consultar o revocar sesiones **de otra persona**: las incidencias que exijan intervención centralizada necesitan otro procedimiento operativo. Esto no implica que cualquier usuario pueda acceder a sesiones ajenas; precisamente falta diseñar y autorizar esa operación.

## Detalle de implementación (solo si se aprueba)

- `src/Features/OAuth/Profile/Infrastructure/Driver/Rest/` (**nuevo** `AdminUserSessionController.php`): endpoints para listar y revocar sesiones de `{userUid}` bajo una ruta administrativa por tenant, con OpenAPI y denegación explícita.
- `src/Features/OAuth/Profile/Domain/Gateway/SessionsGateway.php` y `Infrastructure/Driven/SessionsAdapter.php`: añadir consultas/borrados restringidos por usuario **y tenant/issuer**, sin reutilizar `revoke(sessionId)` a ciegas.
- `src/Bootstrap/Plugin/OAuthPlugin.php` y `config/guard.yaml`: registrar rutas y autorización de administrador; complementar con comprobación del caso de uso, no solo del guard HTTP.
- `test/integration/Features/OAuth/`: probar administrador autorizado, usuario ordinario, sesión ajena y tenant cruzado.
