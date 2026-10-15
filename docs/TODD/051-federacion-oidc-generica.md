# 051 — Proveedor federado OIDC genérico

- **Estado:** ausente; extensión opcional. **Prioridad:** baja.
- **Evidencia:** [DelegateLogin](../../src/Features/OAuth/DelegateLogin/Application/DelegateLogin.php) y proveedores específicos bajo `Infrastructure/Provider` cubren Google/Microsoft/Apple/GitHub y SAML, no un proveedor OIDC parametrizable.
- **Impacto:** nuevas integraciones requieren código por proveedor.
- **Por hacer:** proveedor por tenant con discovery/JWKS de IdP de confianza, PKCE/state/nonce, mapeo de identidades y política de enlaces; probar con un IdP externo real.
- **Aceptación:** se configura IdP estándar sin añadir clase específica y no acepta claims sin validar firma/issuer/audience.
- **Origen:** análisis OAuth §5.6/3.8.

## Detalle de implementación (solo si se aprueba)

- `src/Features/OAuth/DelegateLogin/Infrastructure/Provider/` (**nuevo** `GenericOidcProvider.php`): descubrimiento de IdP confiable, code+PKCE, intercambio y validación de ID token con JWKS/issuer/aud/nonce.
- `src/Features/OAuth/DelegateLogin/Domain/Gateway/DelegateLoginGateway.php` y `Infrastructure/Driven/DelegateLoginAdapter.php`: cargar configuración por tenant y mapear identidad externa a usuario local sin enlace automático inseguro.
- `src/Features/OAuth/DelegateLogin/Application/DelegateLogin.php` y `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Forms/DelegateForm.php`: registrar proveedor configurable y reutilizar callback/finalización sin saltar state/consentimiento.
- `test/integration/Features/OAuth/`: probar discovery/JWKS válidos y rechazo de callback ajeno, nonce erróneo o firma de otro IdP.
