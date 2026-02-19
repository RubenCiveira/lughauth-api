# Revisión del endpoint de revoke y confirmación del flujo de login

## Contexto

El endpoint `POST /oauth/openid/{tenant}/revoke` (`LogoutController.revoke()`) es invocado por clientes REST (aplicaciones frontend, SPAs, backends) para invalidar una sesión. A diferencia de `logout` (que redirige al navegador del usuario), `revoke` es una llamada API programática. Sin embargo, el endpoint actual emite cabeceras `Set-Cookie` en la respuesta, lo cual:

1. No tiene sentido en un contexto API (el cliente que invoca revoke no es el navegador que posee las cookies)
2. Puede generar cookies espurias en el cliente que hace la llamada
3. No respeta la separación entre el canal HTML (navegador) y el canal REST (API)

Además, hay varias inconsistencias en el controller que deben revisarse.

---

## Problema 1: `revoke` emite `Set-Cookie` innecesarias

### Situación actual

```php
// LogoutController.php:26-29
public function revoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
{
    $session = $request->getCookieParams();
    return $this->destroy($response, $session['AUTH_SESSION_ID'] ?? '');
}

// LogoutController.php:32-41
private function destroy(ResponseInterface $response, string $session): ResponseInterface
{
    $this->sessionStore->deleteSession($session);
    $authCookie = new Cookie(name: 'AUTH_SESSION_ID');
    $preCookie = new Cookie(name: 'PRE_SESSION_ID');
    return $preCookie->remove($authCookie->remove($response));
}
```

El método `destroy()` es compartido entre `logout` y `revoke`. En ambos casos añade cabeceras `Set-Cookie` para borrar `AUTH_SESSION_ID` y `PRE_SESSION_ID`. Esto es correcto para `logout` (que devuelve un redirect 302 al navegador del usuario), pero incorrecto para `revoke` (que responde a un cliente API).

### Tarea

- Separar la lógica de `revoke` para que solo elimine la sesión en base de datos, sin emitir `Set-Cookie`
- `revoke` debe recibir el identificador de sesión como parámetro del body (token o session_id), no de las cookies del request — un cliente API no envía cookies del navegador del usuario
- Devolver un 200 con cuerpo vacío o un JSON mínimo de confirmación

### Resultado esperado

El endpoint `revoke` invalida la sesión servidor sin emitir ninguna cabecera `Set-Cookie`. Los clientes API que lo invocan no reciben cookies espurias.

---

## Problema 2: Nombres de cookie sin tenant en `LogoutController`

### Situación actual

`LogoutController.destroy()` borra las cookies `AUTH_SESSION_ID` y `PRE_SESSION_ID` sin path ni sufijo de tenant:

```php
$authCookie = new Cookie(name: 'AUTH_SESSION_ID');
```

Pero `AuthorizeHtml` crea las cookies con el nombre `AUTH_SESSION_ID_<TENANT>` y el path `/oauth/openid/<tenant>/`:

```php
$cookie = new Cookie(
    name: 'AUTH_SESSION_ID_' . strtoupper($tenant),
    path: $base . '/openid/' . $tenant . '/',
    ...
);
```

Esto significa que `logout` no está borrando las cookies correctas — el nombre no coincide (`AUTH_SESSION_ID` vs `AUTH_SESSION_ID_MAIN`) y el path no coincide (`/` vs `/oauth/openid/main/`). El navegador mantiene cookies que no podrá borrar.

### Tarea

- En `logout`, usar el `{tenant}` del path para construir el nombre correcto: `AUTH_SESSION_ID_<TENANT>`
- Establecer el `path` correcto en la cookie de borrado para que coincida con la de creación
- Borrar también `PRE_SESSION_ID` con el path correcto

### Resultado esperado

`logout` borra exactamente las cookies que `AuthorizeHtml` creó, usando el mismo nombre y path.

---

## Problema 3: `revoke` lee sesión de cookies en vez del body

### Situación actual

```php
public function revoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
{
    $session = $request->getCookieParams();
    return $this->destroy($response, $session['AUTH_SESSION_ID'] ?? '');
}
```

El revoke espera encontrar la sesión en las cookies del request. Pero quien invoca `POST /revoke` es un cliente API (backend, SPA via fetch), no el navegador con la sesión del usuario. El cliente API no tiene esas cookies.

Según la especificación OIDC, el `revocation_endpoint` debería recibir el token a revocar en el body del POST.

### Tarea

- Cambiar `revoke` para leer el identificador de sesión (o el refresh token) del body del POST, no de las cookies
- Validar que el cliente que invoca tiene autorización para revocar (ej. verificar client credentials o el propio token)
- No depender de cookies en absoluto para este endpoint

### Resultado esperado

El endpoint revoke funciona como un endpoint API estándar: recibe el token a revocar en el body y responde sin estado ni cookies.

---

## Problema 4: `logout` no valida `post_logout_redirect_uri`

### Situación actual

```php
public function logout(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
{
    $redirect = $request->getQueryParams();
    return $this->destroy(...)->withStatus(302)->withHeader('Location', $redirect['post_logout_redirect_uri']);
}
```

Se redirige a cualquier URL que venga en el query param sin verificar que sea una URI registrada para el cliente. Esto es un open redirect.

### Tarea

- Validar que `post_logout_redirect_uri` coincide con una URI registrada para el cliente (o el tenant)
- Si no coincide o no se proporciona, redirigir a una página por defecto o devolver error
- Considerar aceptar `id_token_hint` para identificar al usuario y `client_id` para validar el redirect

### Resultado esperado

`logout` solo redirige a URIs autorizadas, eliminando el vector de open redirect.

---

## Tarea de confirmación: verificar el flujo completo de login

Tras corregir los problemas anteriores, verificar manualmente que el flujo completo sigue funcionando:

1. **Login inicial**: `GET /authorize` → muestra formulario → `POST /authorize` con credenciales → redirect a `redirect_uri` con code → cookie `AUTH_SESSION_ID_<TENANT>` establecida correctamente
2. **Re-login con sesión activa**: `GET /authorize` con cookie → página CSID → `POST /check-session` → redirect con code (sesión reutilizada)
3. **Token exchange**: `POST /token` con code → devuelve access_token + id_token + refresh_token
4. **Refresh**: `POST /token` con refresh_token → devuelve nuevos tokens
5. **Logout**: `GET /logout?post_logout_redirect_uri=<uri>` → sesión eliminada en BD, cookies borradas, redirect a URI validada
6. **Revoke desde API**: `POST /revoke` con token en body → sesión eliminada en BD, respuesta 200 sin `Set-Cookie`
7. **Post-logout**: `GET /authorize` → ya no tiene sesión → muestra formulario de login

---

## Ficheros implicados

| Fichero | Cambio |
|---------|--------|
| `Authentication/Infrastructure/Driver/Rest/LogoutController.php` | Refactorizar `revoke()` y `logout()`, separar lógica |
| `Shared/Infrastructure/Http/Cookie.php` | Sin cambios (verificar que `remove()` funciona correctamente) |
| `Bootstrap/Plugin/OidcPlugin.php` | Sin cambios (rutas correctas) |
| `Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php` | Referencia para verificar nombres y paths de cookies |
