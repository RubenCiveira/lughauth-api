# 071 — Clave de cifrado fija en código y contraseñas de usuario reversibles

- **Estado:** pendiente. **Prioridad:** crítica.
- **Evidencia:**
  - [AesCypherService::__construct](../../src/Shared/Security/AesCypherService.php) usa `$clave ?? 'clave-cifrado'`. No hay ninguna definición DI que le pase una clave, y PHP-DI lo autoconstruye con el valor por defecto. Es la clave de `encryptForAll`/`decryptForAll` para todo el sistema.
  - Las contraseñas de usuario se guardan con ese cifrado reversible: [UserPasswordVO](../../src/Features/Access/User/Domain/ValueObject/UserPasswordVO.php) usa `cyphered://` + `encryptForAll`. Además, [LoginAdapter.php:205](../../src/Features/OAuth/User/Infrastructure/Driven/LoginAdapter.php#L205) **descifra** la contraseña y la compara con `!==`, y [PasswordAdapter](../../src/Features/OAuth/Profile/Infrastructure/Driven/PasswordAdapter.php) hace lo mismo al cambiarla.
  - La misma clave protege el secreto de cliente (063), la semilla TOTP ([UserMfaAdapter](../../src/Features/OAuth/Mfa/Infrastructure/Driven/UserMfaAdapter.php)) y la contraseña SMTP ([SmtpOutboundConfigPasswordVO](../../src/Features/Notification/SmtpOutboundConfig/Domain/ValueObject/SmtpOutboundConfigPasswordVO.php)).
- **Impacto:** quien obtenga una copia de la BD y el código fuente (o conozca la constante) recupera **en claro** todas las contraseñas de usuario. Las contraseñas humanas se reutilizan en otros servicios, así que el daño trasciende a LughAuth. La comparación `!==` permite además ataques de temporización.
- **Origen:** revisión de 063 (no estaba en los análisis previos).

## Por hacer

1. **Clave fuera del código (S, inmediato):** registrar `AesCypherService` en DI con una clave leída de configuración segura (variable de entorno o secreto montado). Fallar al arrancar si falta o si vale `'clave-cifrado'`. Hace falta re-cifrar los datos existentes con la nueva clave (comando de migración que descifre con la antigua y cifre con la nueva); la semilla TOTP y la contraseña SMTP **deben** seguir siendo reversibles.
2. **Contraseñas de usuario con `password_hash` (M):** aquí sí corresponde un hash lento (argon2id o bcrypt), porque son secretos humanos de baja entropía. Verificar con `password_verify` y aplicar `password_needs_rehash`.
   - Migración: re-hashear en bloque descifrando con la clave actual (es posible precisamente porque hoy es reversible), o re-hashear perezosamente en el siguiente login correcto. **Recomendación:** en bloque, para no dejar contraseñas recuperables en las cuentas inactivas.
   - Revisar cualquier flujo que hoy dependa de recuperar la contraseña en claro (p. ej. `InstallUsecase`, `RegisterUserAdapter`, `InvitationAcceptUsecase`, `DelegateLoginAdapter`), que deberá dejar de hacerlo.
3. **Generador:** la propiedad de contraseña del modelo `user` debe generar hash y no `cyphered://`. Es el mismo atributo de modelo que propone 063 (p. ej. `private-content="hash"`).

## Aceptación

- Arrancar sin clave configurada falla; la constante no puede usarse en producción.
- La BD no contiene ninguna contraseña de usuario recuperable; los usuarios existentes inician sesión tras la migración.
- Login, cambio de contraseña, invitación y registro funcionan con `password_verify`; test de contraseña correcta, incorrecta y re-hash.

## Detalle de implementación

- `src/Shared/Security/AesCypherService.php` y el plugin de `Shared/Security`: eliminar el valor por defecto y registrar la definición DI con la clave de configuración. **Nuevo** comando de rotación de clave que re-cifre todos los campos `cyphered://`.
- `src/Features/Access/User/Domain/ValueObject/UserPasswordVO.php` (generado): formato de hash en lugar de AES, regenerando desde el modelo vigente.
- `src/Features/OAuth/User/Infrastructure/Driven/LoginAdapter.php`, `ChangePasswordAdapter.php`, `RegisterUserAdapter.php`, `src/Features/OAuth/Profile/Infrastructure/Driven/PasswordAdapter.php`, `src/Features/OAuth/UserInvitation/Application/Usecase/Accept/InvitationAcceptUsecase.php` y `src/Features/OAuth/Common/Application/Usecase/InstallUsecase.php`: `password_hash`/`password_verify` en lugar de `getPlainPassword` + `!==`.
- `migrations/mysql/schema/1.0.0/` (**nueva migración**) y un comando de datos: re-hashear las contraseñas existentes.
- `test/`: cubrir login, cambio de contraseña, rotación de la clave AES y arranque sin clave.
