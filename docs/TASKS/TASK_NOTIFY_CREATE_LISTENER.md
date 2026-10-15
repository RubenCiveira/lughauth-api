# TASK — Listener `NotifyCreate` vacío, con import roto y destinatario personal

> Origen: [ANALISIS_CALIDAD_CODIGO_MAESTROS.md](../ANALISIS_CALIDAD_CODIGO_MAESTROS.md), revisión de clases importadas inexistentes.
> Prioridad: media (por el punto relacionado de `NotifyLogin`) · Esfuerzo: S–M

## Problema

[NotifyCreate.php](../../src/Features/OAuth/User/Application/Listener/NotifyCreate.php) está registrado para `UserCreateEvent` en [MultiTenantPlugin.php:34](../../src/Bootstrap/Plugin/MultiTenantPlugin.php#L34), pero:

1. **Import roto**: `use Civi\Lughauth\Features\Notification\Message\Application\Usecase\SendMessage;` apunta a una clase que no existe (en `Notification/Message/Application/Usecase` solo hay `Create/Delete/List/Retrieve/Update`). No rompe en ejecución, porque un `use` sin uso no dispara el autoload, pero confunde y lo señala el análisis estático.
2. **Cuerpo vacío**: `__invoke()` es todo un bloque comentado. Se instancia el listener e inyecta `EnqueueNotificationUsecase` en cada alta de usuario para no hacer nada.
3. **Código comentado obsoleto**: usa `new Message(...)` y `$this->sender->send()`, API que ya no existe (hoy se usa `EnqueueNotificationUsecase::enqueue(EnqueueNotificationCommand)`), con nombre y correo personales escritos a mano.
4. **Docblock desfasado**: dice que la dependencia es `SendMessage` y que se mantiene «para no romper el contenedor», cuando el constructor ya recibe `EnqueueNotificationUsecase`.

### Relacionado (más grave): `NotifyLogin`

[NotifyLogin.php](../../src/Features/OAuth/User/Application/Listener/NotifyLogin.php), registrado para `AuthenticationResult` en el mismo plugin, **sí envía**: en cada login válido de cualquier usuario y de cualquier tenant encola un correo urgente a `rubenciveira@gmail.com`, una dirección fija en el código (la lectura de `oidc.notify.login.to` está comentada). Esto filtra a un buzón personal el nombre, el email, la IP y la hora de todos los inicios de sesión. Conviene resolverlo en esta misma tarea porque el patrón es el mismo.

## Solución propuesta

Decidir el propósito de la notificación de alta:

- **A — Aviso al administrador** (lo que parece querer el código comentado):
  - destinatario configurable, p. ej. `oidc.notify.create.to` en `AppConfig`, con valor por defecto vacío; si está vacío, no hacer nada;
  - plantilla `user.create` (instalarla desde `Common/Application/Usecase/Install`, como las demás);
  - implementar `__invoke()` siguiendo `NotifyLogin`: `EnqueueNotificationCommand(templateCode: 'user.create', channel: MAIL, recipient, tenant, variables: ['user' => [...]])` dentro de un `try/catch` que registre el error en el log sin propagarlo.
- **B — Bienvenida al usuario**: ya existen `TenantConfig::welcomeEmail` / `registeredEmail`. Usar esa configuración por tenant y enviar al email del usuario creado.
- **C — Sin notificación por ahora**: borrar `NotifyCreate` y su registro en `MultiTenantPlugin`. El generador u otro listener lo añadirán cuando haga falta.

En todos los casos:

- [ ] Quitar el `use ...\SendMessage;` y el bloque comentado, y corregir el docblock.
- [ ] En `NotifyLogin`: sustituir la dirección fija por `oidc.notify.login.to` (por defecto vacío, es decir, desactivado) y no encolar si está vacío.

## Criterios de aceptación

- [ ] Ningún correo personal escrito en `src/` (`grep -rn "rubenciveira" src` sin resultados).
- [ ] Con la configuración vacía, crear un usuario o hacer login no encola notificaciones.
- [ ] Con la configuración rellena (opción A/B), se encola un mensaje con la plantilla correcta y el tenant del usuario.
- [ ] Un error al encolar no impide el alta del usuario ni el login (solo log).
- [ ] Tests unitarios de los dos listeners con un mock de `EnqueueNotificationUsecase`.
- [ ] El chequeo de imports no da `SendMessage` como clase inexistente.
