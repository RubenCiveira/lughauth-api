# 026 — Verificar issuer, audience y tenant en los consumidores

- **Estado:** parcialmente cubierto por configuración; pendiente garantía local. **Prioridad:** crítica.
- **Evidencia:** [JoseTokenSigner::verifyTokenPayload](../../src/Features/OAuth/TokenSecurity/Infrastructure/Driven/JoseTokenSigner.php) solo valida firma/tiempos; [JwtVerifierMiddleware::verifyToken](../../src/Bootstrap/Middleware/JwtVerifierMiddleware.php) comprueba issuer/aud solo si `security.jwt.verify.*` está configurado. Desde `7343131f` exige que el token contenga **todas** las audiencias configuradas, no al menos una. Además, toma `tenant` del claim con `'main'` por defecto.
- **Impacto:** firmas válidas de otro contexto pueden aceptarse en refresh, UserInfo o recursos con configuración permisiva.
- **Por hacer:** especificar issuer/audience requeridos por ruta y tipo, validar tenant del path y consumidor, cubrir caché de verificaciones y rechazo de tokens de otro tenant. Decidir si `audiences` significa «todas» (comportamiento actual) o «al menos una de las aceptadas» (semántica habitual de RFC 7519 §4.1.3 para un resource server con varios identificadores). Un token sin claim `tenant` no debe caer en `main`.
- **Aceptación:** token con `iss`, `aud` o tenant incorrectos falla en cada consumidor incluso sin configuración global opcional.
- **Origen:** análisis OAuth §3.6/0.8 y `_2.md` línea base.

## Detalle de implementación

- `src/Features/OAuth/TokenSecurity/Domain/Gateway/TokenSigner.php` y `Infrastructure/Driven/JoseTokenSigner.php`: exponer validación parametrizada por issuer/audience/propósito del consumidor; la verificación de firma/tiempo por sí sola no basta.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php`, `UserInfoController.php` e `IntrospectionController.php`: pasar issuer canónico, tenant y audiencia esperada al verificar refresh, access e ID según ruta.
- `src/Bootstrap/Middleware/JwtVerifierMiddleware.php`: exigir los valores efectivos donde corresponda (sin depender solo de `security.jwt.verify.*` opcional), rechazar tokens sin `tenant` en lugar de usar `'main'` y evitar que una identidad cacheada salte validaciones de tenant/revocación.
- `test/integration/Features/OAuth/`: cubrir `iss`/`aud` ausente, ajeno y token válido de otro tenant tanto en ruta HTTP como en consumo local.
