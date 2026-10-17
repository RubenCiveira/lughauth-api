# EP-10 — Login de usuario: MFA y federación

- **Tareas:** [039](../039-consumir-recovery-mfa.md) (códigos de recuperación MFA). Opcionales: [040](../040-grant-mfa-no-interactivo.md) (grant MFA para clientes nativos) y [051](../051-federacion-oidc-generica.md) (proveedor OIDC genérico).
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
