# 039 — Permitir códigos de recuperación MFA en login

- **Estado:** hecho (EP-10). **Prioridad:** media.
- **Evidencia:** [MfaAdapter](../../../src/Features/OAuth/Profile/Infrastructure/Driven/MfaAdapter.php) genera y cuenta códigos sin usar; [OAuthPlugin](../../../src/Bootstrap/Plugin/OAuthPlugin.php) expone panel de recuperación, pero el flujo de login no consume esos códigos.
- **Impacto:** el usuario con TOTP perdido no puede usar los códigos emitidos.
- **Por hacer:** paso alternativo MFA con verificación del hash y marcado de un solo uso en transacción, sin revelar si existe; enlazarlo a la sesión/challenge.
- **Aceptación:** código correcto permite una vez; repetido, de otro usuario o tenant falla.
- **Origen:** análisis OAuth §5.6/2.1 y `_2.md` recuperación MFA.

## Detalle de implementación

- `src/Features/OAuth/Profile/Domain/Gateway/MfaGateway.php` y `Infrastructure/Driven/MfaAdapter.php`: añadir `consumeRecoveryCode(user, tenant, code)` que consulte códigos no usados, verifique el hash y marque usado de forma atómica; mantener generación actual.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Forms/UseMfaForm.php`: ofrecer alternativa al TOTP y continuar `AuthenticateUser::preAuthenticate` solo tras consumo confirmado.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/OidcStepRouter.php` y `Domain/StepName.php`: si se prefiere formulario independiente, registrar nuevo paso `RecoverMfaForm.php`; si se integra en `UseMfaForm`, no hace falta crear ruta nueva.
- `test/integration/Features/OAuth/`: probar validez, uso único y concurrencia con dos intentos del mismo código.
