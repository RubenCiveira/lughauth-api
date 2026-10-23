# EP-10 — Login de usuario: MFA y federación

- **Tareas:** [039](039-consumir-recovery-mfa.md) (códigos de recuperación MFA). Opcionales: [040](040-grant-mfa-no-interactivo.md) (grant MFA para clientes nativos, **descartada**) y [051](051-federacion-oidc-generica.md) (proveedor OIDC genérico, **descartada**).
- **Prioridad:** media (039), baja (opcionales). **Tamaño:** S (039) + M por opcional. **Depende de:** nada para 039; EP-03 (024) para 040; EP-07 (`RemoteJwksFetcher`) para 051.
- **Esquema:** 039 sin cambios (`user-mfa-recovery-code.used-at` existe). 040: `_oauth_mfa_challenge` (B.4). 051: `tenant-login-provider.oidc-scopes`, `oidc-claim-mapping` y `account-link-policy` (A.3).

## Por qué juntas

Las tres son pasos de **autenticación del usuario** previos a la emisión de tokens, y las tres alimentan el `amr` real que EP-05 publica (`pwd`, `otp`, federado). 039 y 040 comparten la verificación de un factor MFA de un solo uso. 039 y 051 añaden pasos al mismo [OidcStepRouter](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/OidcStepRouter.php).

## Componente común

`MfaGateway::consumeRecoveryCode(user, tenant, code)` en [MfaAdapter](../../../src/Features/OAuth/Profile/Infrastructure/Driven/MfaAdapter.php): verifica el hash entre los códigos no usados y lo marca con `UPDATE … SET used_at = NOW() WHERE uid = ? AND used_at IS NULL`, comprobando que `rowCount() === 1`. La verificación TOTP actual y este método forman un `MfaVerifier` reutilizable por 040.

## Plan

1. **039.** `consumeRecoveryCode`; alternativa en [UseMfaForm](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Forms/UseMfaForm.php), o un paso `RecoverMfaForm` si se prefiere separado; continuar `AuthenticateUser::preAuthenticate` solo tras el consumo confirmado; mensajes que no revelen si el código existe; registrar `amr` correspondiente.
2. **040 (si se aprueba).** Documentar primero por qué code+PKCE o device no bastan. Después, `ResolverForMfa` con un challenge de vida corta en `_oauth_mfa_challenge`, ligado a cliente, tenant y usuario, con límite de intentos y `MfaVerifier`; registrarlo en `TokenGranterMediator` solo si está habilitado, y anunciarlo.
3. **051 (si se aprueba).** `GenericOidcProvider` con discovery y JWKS del IdP vía `RemoteJwksFetcher` (EP-07), code+PKCE, `state` y `nonce`, validación de `iss`/`aud`; configuración por `tenant-login-provider` (`source=OIDC`, `oidc-discovery-url`) y enlace de cuenta según `account-link-policy`, nunca automático sin email verificado.

## Pruebas

- 039: código correcto una vez; repetido, de otro usuario o de otro tenant falla; dos intentos concurrentes del mismo código, uno solo gana.
- 040: challenge inválido, de otro cliente, agotado o reutilizado no da token.
- 051: callback ajeno, nonce erróneo o firma de otro IdP rechazados.

## Aceptación

La de 039, más las de 040 y 051 si se aprueban.

## Implementación

### 039: códigos de recuperación en el login

- **Consumo:** `MfaGateway::consumeRecoveryCode(user, tenant, code)` en [MfaAdapter](../../../src/Features/OAuth/Profile/Infrastructure/Driven/MfaAdapter.php). Resuelve el usuario por nombre o uid dentro del tenant (`UserLoaderAdapter::checkUserOrSubject`, que rechaza el de otro tenant), compara el hash con los códigos sin usar del usuario y marca el que coincide con `UserMfaRecoveryCode::consume()` (evento `user-mfa-recovery-code.consume` y changelog). El código se normaliza: mayúsculas y sin espacios ni guiones.
- **Un solo ganador:** en lugar de un `UPDATE … WHERE used_at IS NULL` propio, se reutiliza el `update` generado, que ya filtra por `version` (bloqueo optimista). De dos intentos con el mismo código, el segundo en escribir recibe `OptimistLockException` y devuelve `false`. Si la petición va dentro de una transacción, el `SELECT … FOR UPDATE` de `listForUpdate` además serializa los intentos.
- **Formulario:** [UseMfaForm](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Forms/UseMfaForm.php) añade, dentro del mismo paso `mfa` (sin ruta nueva), un desplegable con el campo `recovery_code` y su propia firma CSRF (`rsign`). Si llega `recovery_code`, se consume el código y no se comprueba el TOTP. Solo tras el consumo confirmado se llama a `AuthenticateUser::preAuthenticate`. Un TOTP o un código erróneo, gastado o ajeno da el mismo `wrong_auth_code`, cuyo texto pasa a «código de verificación» en vez de «código otp».
- **`amr`:** `ChallengesState` guarda `mfaMethod`, el valor RFC 8176 del segundo factor, serializado como `mfa_method` (los estados anteriores, sin esa clave, se leen como `null`). [AuthenticateUser](../../../src/Features/OAuth/Authentication/Application/AuthenticateUser.php) publica `pwd` si la contraseña se comprobó en este flujo, también cuando el MFA viene después, más `otp` + `mfa` tras un TOTP (`UseMfaForm` y el alta en `NewMfaForm`) o solo `mfa` tras un código de recuperación, que no tiene valor propio en RFC 8176.
- **Generación de códigos reparada:** `MfaAdapter::generateRecoveryCodes` no asignaba `uid`, y `UserMfaRecoveryCode::create` fallaba con `ConstraintException`. Ahora usa un UUID v4, como `ProfileAdapter`. Sin esto, el panel de perfil no podía emitir los códigos que este paso consume.
- **`MfaVerifier` no se extrae:** solo lo necesitaría 040, que se ha descartado.

### Identificación previa a los pasos posteriores al login (hallazgo de la revisión)

- **Fallo:** con una contraseña errónea, `LoginForm` dejaba el `username` tecleado en el estado de pre-sesión, y [OidcStepRouter](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/OidcStepRouter.php) acepta un `step` explícito. Bastaba un POST con `step=mfa` y un código válido, o con `step=scopes-consent` (o `consent`) sin nada pendiente, para que `preAuthenticate` abriera sesión como ese usuario: en el caso del consentimiento, **sin ningún factor**. Con `step=build-mfa`, `NewMfaForm` guardaba además la semilla TOTP del atacante en la cuenta. Y con la contraseña correcta, enviar directamente un paso posterior se saltaba el MFA y el cambio de contraseña temporal, porque `fillPreAuthenticated` no los volvía a comprobar. Los fallos de consentimiento y de alta MFA eran anteriores a esta épica.
- **Evidencia en el estado:** `ChallengesState::identifiedBy` ([IdentificationMethod](../../../src/Features/OAuth/Authentication/Domain/IdentificationMethod.php), serializado como `identified_by`) registra cómo se demostró la identidad: contraseña, WebAuthn, federado, recuperación de contraseña, registro verificado o sesión existente. Solo lo fija el paso que comprueba esa prueba: `AuthenticateUser::authenticate` (contraseña correcta, aunque quede un paso pendiente), `WebAuthnLoginForm`, `DelegateForm`, `RecoverPassForm`, `RegisterUserForm` y `/check-session`. `withUsername` descarta la identificación y el MFA reunidos hasta entonces, porque declarar otro usuario no prueba nada.
- **Dónde se exige:** `AuthenticateUser::preAuthenticate` rechaza un estado sin identificar con `WRONG_CREDENTIAL`, que lleva al login. `UseMfaForm` y `NewMfaForm` lo comprueban antes de mirar ningún código, para no gastar códigos de recuperación, no servir de oráculo del TOTP y no guardar semillas. Si la identificación fue por contraseña, [LoginAdapter](../../../src/Features/OAuth/User/Infrastructure/Driven/LoginAdapter.php)`::fillPreAuthenticated` vuelve a pedir el cambio de contraseña temporal y, mientras no haya MFA, la configuración o la verificación MFA, igual que `validatedUserData`. Los demás métodos de identificación conservan su comportamiento: no se les exige MFA.
- **Alta MFA solo pendiente (segunda revisión):** `step=build-mfa` sigue aceptándose como paso explícito, y `UserMfaAdapter::storeSeed` sustituye la semilla que haya. Con la contraseña de una cuenta con MFA activo se podía guardar una semilla nueva y superar así el MFA anterior. [NewMfaForm](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Forms/NewMfaForm.php) exige ahora, antes de mostrar la semilla y antes de guardarla, que el usuario esté identificado y que `LoginGateway::mfaEnrolmentPending` sea cierto. Ese método aplica la misma regla que el login (`checkMfaConfigurationRequired`): sin semilla, y con MFA activado en el usuario o forzado en el tenant. Si no, la respuesta es `WRONG_CREDENTIAL` y el login. Cambiar la semilla de una cuenta con MFA queda para el perfil (`MfaGateway::enable`).
- **Límite de intentos MFA:** un TOTP o un código de recuperación erróneos cuentan como un intento fallido de inicio de sesión (`LoginGateway::markSecondFactorFailed`), con el mismo contador y bloqueo (6 h al superar 3 fallos) que una contraseña errónea. Un usuario bloqueado recibe el mismo «usuario desconocido» con un código bueno o malo. Completar el login tras el MFA pone el contador a cero (`markLoginOk`), cosa que antes nunca ocurría para los usuarios con MFA.
- **Cambio de comportamiento:** los estados de pre-sesión guardados antes de desplegar no tienen `identified_by`, así que un login a medias en ese momento vuelve a pedir la contraseña.

### Pruebas

- `PasswordStepBypassUnitTest`: con `LoginForm` y `AuthenticateUser` reales, contraseña errónea, estado guardado y leído como en la pre-sesión, y después `step=mfa` con TOTP válido, con código de recuperación válido, o `step=scopes-consent` sin nada pendiente: no se abre sesión. Sin las guardas, los tres casos abren sesión para la víctima.
  También: contraseña correcta de una cuenta con MFA activo seguida de `step=build-mfa` con semilla propia y OTP válido: no se guarda la semilla ni se abre sesión. Sin la comprobación de alta pendiente, falla.
- `LoginAdapterPendingStepsUnitTest`: tras un login con contraseña, el MFA y la contraseña temporal siguen exigiéndose al continuar desde un paso posterior; el alta está pendiente solo sin semilla.
- `MfaRecoveryCodeConsumeTest` (MySQL): un código vale una vez; se acepta en minúsculas o agrupado; otro usuario no puede usarlo, y su intento no lo gasta; el usuario buscado en otro tenant no existe; con dos conexiones, el rival que consume entre la lectura y la escritura gana y el otro intento falla; un código recién generado se consume.
- `UseMfaFormStepUnitTest`: el TOTP registra `otp`; el código de recuperación se consume sin mirar el TOTP; TOTP o código rechazados dan el mismo error, cuentan el fallo y no autentican; sin identificación no se comprueba ningún código; el formulario muestra el campo de recuperación. `NewMfaFormStepUnitTest`: sin identificación, o con MFA ya activo, no se muestra ni se guarda una semilla.
- `AuthenticateUserAmrUnitTest`: `amr` con contraseña y TOTP, con contraseña y código de recuperación, solo con contraseña y con WebAuthn; `preAuthenticate` rechaza un estado sin identificar; `withUsername` descarta las pruebas; `identified_by` y `mfa_method` sobreviven a la serialización.

### Pendiente fuera de esta épica

- **040:** descartada; ver la decisión en su ficha.
- **051:** descartada; ver la decisión en su ficha. Al revisarla aparecieron fallos graves del login federado existente, corregidos en [072](072-seguridad-login-federado.md).
- **`amr` de otros métodos:** WebAuthn, federado y enlace mágico siguen sin publicar `amr` (`hwk`, `user`…). Solo `pwd` tiene evidencia registrada.
- **Coste de un código de recuperación erróneo:** cada intento verifica hasta diez hashes argon2id; el bloqueo tras 3 fallos lo acota.
- **`count` generado inválido en MySQL/MariaDB (resuelto):** los conectores PDO generados construían `SELECT count("tabla".*)`, que MariaDB rechaza. Corregido a `count(*)` en los 34 conectores y en el generador (`PhpPdoConnector.java`), para que la próxima regeneración no lo deshaga. `MfaRecoveryCodeConsumeTest` vuelve a comprobar `countUnusedRecoveryCodes`.
- **`BadRequestException` inexistente (resuelto):** `MfaAdapter::enable` lanzaba una clase que no existe; `ProfileHtml` atrapaba el `Error` y mostraba al usuario el mensaje interno de PHP con un 422. Ahora lanza `ConstraintException` (`wrong_otp`, 422 en la API) y el perfil muestra un mensaje traducido (`profile.mfa.wrongOtp`, o `profile.mfa.error` para cualquier otro fallo). `MfaAdapterEnableUnitTest` lo cubre.
