# 051 — Proveedor federado OIDC genérico

- **Estado:** descartada (22-10-2026, EP-10); no se implementa. **Prioridad:** baja.
- **Evidencia:** [DelegateLogin](../../../src/Features/OAuth/DelegateLogin/Application/DelegateLogin.php) y proveedores específicos bajo `Infrastructure/Provider` cubren Google/Microsoft/Apple/GitHub y SAML, no un proveedor OIDC parametrizable.
- **Impacto:** nuevas integraciones requieren código por proveedor.
- **Por hacer:** proveedor por tenant con discovery/JWKS de IdP de confianza, PKCE/state/nonce, mapeo de identidades y política de enlaces; probar con un IdP externo real.
- **Aceptación:** se configura IdP estándar sin añadir clase específica y no acepta claims sin validar firma/issuer/audience.
- **Origen:** análisis OAuth §5.6/3.8.

## Detalle de implementación (solo si se aprueba)

- `src/Features/OAuth/DelegateLogin/Infrastructure/Provider/` (**nuevo** `GenericOidcProvider.php`): descubrimiento de IdP confiable, code+PKCE, intercambio y validación de ID token con JWKS/issuer/aud/nonce.
- `src/Features/OAuth/DelegateLogin/Domain/Gateway/DelegateLoginGateway.php` y `Infrastructure/Driven/DelegateLoginAdapter.php`: cargar configuración por tenant y mapear identidad externa a usuario local sin enlace automático inseguro.
- `src/Features/OAuth/DelegateLogin/Application/DelegateLogin.php` y `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Forms/DelegateForm.php`: registrar proveedor configurable y reutilizar callback/finalización sin saltar state/consentimiento.
- `test/integration/Features/OAuth/`: probar discovery/JWKS válidos y rechazo de callback ajeno, nonce erróneo o firma de otro IdP.

## Decisión

Descartada el 22-10-2026. No hay demanda de IdPs OIDC configurables por tenant (SSO corporativo con Okta, Entra, Keycloak…), que es lo que este proveedor resolvería. Para SSO corporativo sigue disponible SAML. Las redes sociales que se plantearon (Facebook, X) no implementan OpenID Connect en el login web, así que el genérico no las cubriría: cada una necesitaría su propia clase en `Infrastructure/Provider`, como GitHub.

Consecuencia: las columnas `oidc_discovery_url`, `oidc_scopes`, `oidc_claim_mapping` y `account_link_policy` de `tenant-login-provider` siguen sin uso por un flujo genérico. Se reabriría ante un cliente que necesite conectar su propio IdP OIDC.
