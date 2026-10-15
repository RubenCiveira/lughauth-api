# TASK — `MfaAdapter` lanza una excepción que no existe

> Origen: [ANALISIS_CALIDAD_CODIGO_MAESTROS.md](../ANALISIS_CALIDAD_CODIGO_MAESTROS.md), revisión de clases importadas inexistentes.
> Prioridad: media · Esfuerzo: S

## Problema

[MfaAdapter.php:111](../../src/Features/OAuth/Profile/Infrastructure/Driven/MfaAdapter.php#L111) lanza `new BadRequestException('Invalid OTP code')` cuando el usuario activa el MFA desde el perfil con un OTP incorrecto. La clase `Civi\Lughauth\Shared\Exception\BadRequestException` **no existe**; en `src/Shared/Exception/` solo hay `ConstraintException`, `NotEmptyChildsException`, `NotFoundException`, `NotUniqueException`, `OptimistLockException` y `UnauthorizedException`.

## Comportamiento actual

1. `POST /oauth/openid/{tenant}/me/mfa` con un OTP erróneo.
2. `MfaAdapter::enable()` ejecuta `new BadRequestException(...)` y PHP lanza `Error: Class "Civi\Lughauth\Shared\Exception\BadRequestException" not found`.
3. `ProfileHtml::saveMfa()` captura `Throwable` y repinta el panel con **`$ex->getMessage()`** y estado 422.

Resultado: el usuario ve el texto `Class "Civi\Lughauth\Shared\Exception\BadRequestException" not found` en lugar de «código incorrecto». No es un fatal error, pero muestra detalles internos y el mensaje no se puede traducir. El span se marca con una excepción de tipo `Error`.

## Solución propuesta

Elegir una opción:

- **A (recomendada)**: usar la excepción de validación existente, sin añadir tipos nuevos:
  ```php
  throw ConstraintException::ofError('invalid-otp', ['otp'], [null]);
  ```
  y en `ProfileHtml::saveMfa()` mostrar un texto traducido (`profile.mfa.invalid_code`) cuando la excepción sea `ConstraintException`, en lugar de `$ex->getMessage()`.
- **B**: crear `src/Shared/Exception/BadRequestException.php` (extends `\RuntimeException`, mismo estilo que `NotFoundException`) y mapearla a 400/422 en el manejador de errores HTTP.

En cualquier caso, `ProfileHtml::saveMfa()` **no debería volcar `$ex->getMessage()` de un `Throwable` genérico** en la página: solo los mensajes de excepciones de validación conocidas, y un texto genérico para el resto.

## Criterios de aceptación

- [ ] Con un OTP incorrecto, el panel MFA muestra un mensaje traducido y la respuesta es 422.
- [ ] Con un OTP correcto, el MFA queda activado (sin regresión).
- [ ] Ningún error interno (nombres de clase, SQL…) aparece en el HTML del perfil.
- [ ] Test unitario de `MfaAdapter::enable()` para el OTP inválido (con mock de `UserMfaGateway::verifyNewOpt` devolviendo `false`).
- [ ] El chequeo de imports no da `BadRequestException` como clase inexistente.

## Comprobación

```bash
# Imports a clases Civi\Lughauth inexistentes (debe salir 0 para este fichero)
grep -rn "BadRequestException" src --include=*.php
```
