# EP-02 — Secretos y criptografía en reposo

- **Tareas:** [071](../071-clave-aes-y-contrasenas-usuario.md) (clave AES fija y contraseñas reversibles) y [063](../063-secreto-cliente-y-comparacion.md) (secreto de cliente).
- **Prioridad:** crítica. **Tamaño:** L. **Depende de:** nada. **Desbloquea:** EP-03 (hash del initial access token de DCR) y EP-11 (método de autenticación de cliente).
- **Esquema:** A.1 (`secret-oauth`, `registration-access` con `private-content="hash"`), A.4 (`user.password` con `private-content="password-hash"`) y migraciones de datos. Ver [MODELO_Y_ESQUEMA](../MODELO_Y_ESQUEMA_018-071.md).

## Por qué juntas

- La misma clave de [AesCypherService](../../../src/Shared/Security/AesCypherService.php) (`'clave-cifrado'`) protege contraseñas de usuario, secretos de cliente, semillas TOTP y la contraseña SMTP.
- Ambas necesitan **la misma mejora del generador**: nuevos valores de `private-content` (`hash` para HMAC con *pepper*, `password-hash` para argon2id), en el modelo vigente (ver 001).
- Ambas sustituyen `getPlain…() + !==` por verificación en tiempo constante y necesitan una migración de datos que descifre con la clave actual.

## Orden clave: re-hashear antes de rotar la clave

Mientras la clave antigua siga descifrando, contraseñas y secretos de cliente se pueden convertir a hash sin pedir nada a usuarios ni a clientes. Si se rota primero, habría que re-cifrarlos para luego descartarlos. Por eso:

1. **Fase A — inmediata (S, un PR).**
   - 063a: `hash_equals` en [ClientStoreAdapter.php:53](../../../src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php#L53); comprobar que ningún endpoint de lectura (Retrieve, List, Update, DCR GET/PUT) devuelve el secreto.
   - 071: `hash_equals` provisional en [LoginAdapter.php:205](../../../src/Features/OAuth/User/Infrastructure/Driven/LoginAdapter.php#L205) y [PasswordAdapter](../../../src/Features/OAuth/Profile/Infrastructure/Driven/PasswordAdapter.php).
   - Registrar `AesCypherService` en DI con clave leída de configuración segura. En esta fase se permite que la clave configurada sea igual a la antigua para no romper datos; se registra un aviso si lo es.
2. **Fase B — generador.** `private-content="hash"` y `"password-hash"`; regenerar `TrustedClientSecretOauthVO`, el VO de `registration-access` y `UserPasswordVO`. Llevar a la plantilla, no al código generado.
3. **Fase C — servicios.** Nuevo puerto en `Shared/Security` con dos implementaciones:
   - `SecretHasher` (HMAC-SHA256, *pepper* versionado `hmac-sha256:<pepper-id>:<hex>`), para secretos de cliente, registration access token y el initial access token de EP-03.
   - `PasswordHasher` (`password_hash` argon2id, `password_verify`, `password_needs_rehash`).
4. **Fase D — flujos.**
   - Usuarios: `LoginAdapter`, `ChangePasswordAdapter`, `RegisterUserAdapter`, `PasswordAdapter`, `InvitationAcceptUsecase`, `InstallUsecase`, `DelegateLoginAdapter`. Revisar cualquier código que recupere la contraseña en claro.
   - Clientes: `ClientStoreAdapter` verifica con `SecretHasher` y acepta `cyphered://` solo durante la ventana de migración; `DynamicClientAdapter` guarda el hash; [RegisterClientResult](../../../src/Features/OAuth/Client/Application/Usecase/RegisterClient/RegisterClientResult.php) devuelve el secreto en claro solo en el alta o la rotación.
5. **Fase E — migración de datos.** Comando que descifra con la clave actual y re-hashea **en bloque** contraseñas y secretos de cliente; nueva migración en `migrations/mysql/schema/1.0.0/` si cambia el tamaño de columna.
6. **Fase F — rotación de la clave AES.** Comando que re-cifra los campos que siguen siendo reversibles (semilla TOTP de [UserMfaAdapter](../../../src/Features/OAuth/Mfa/Infrastructure/Driven/UserMfaAdapter.php), contraseña SMTP). Después se activa el fallo de arranque si la clave falta o vale `'clave-cifrado'`.

## Decisiones

- Secreto de cliente: **opción A** de 063 (HMAC + *pepper* para todos). Implica que EP-11 solo implemente `private_key_jwt`.
- Re-hash de contraseñas **en bloque** (071), para no dejar contraseñas recuperables en cuentas inactivas.
- Dónde viven la clave AES y el *pepper* (variable de entorno o secreto montado) y cómo se versiona el *pepper*.

## Pruebas

- Login, cambio de contraseña, invitación, registro e instalación con `password_verify`; contraseña correcta, incorrecta y re-hash.
- Cliente: autenticación válida/inválida, formato legado durante la ventana, secreto ausente en lecturas.
- Arranque sin clave o con la constante: falla. Rotación de clave: TOTP y SMTP siguen funcionando.

## Aceptación

Unión de 063 y 071: la BD no contiene contraseñas ni secretos de cliente recuperables, los usuarios y clientes existentes siguen autenticando y la constante no puede usarse en producción.

## Conflictos

`ClientStoreAdapter` y `DynamicClientAdapter` también los modifica EP-03. Fusionar la fase A antes de empezar EP-03 y las fases B–E después, o asignar ambas épicas al mismo responsable.
