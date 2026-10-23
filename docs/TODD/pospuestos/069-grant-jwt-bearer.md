# 069 — Evaluar JWT bearer grant

- **Estado:** pospuesta (23-10-2026, al cerrar [EP-11](../done/EP-11-aserciones-jwt-cliente.md)); ausente, no se anuncia. Ver «Decisión». **Prioridad:** baja.
- **Evidencia:** [TokenController](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php) no gestiona `urn:ietf:params:oauth:grant-type:jwt-bearer`; el soporte de JAR en [RequestObjectValidator](../../../src/Features/OAuth/Authentication/Domain/RequestObjectValidator.php) verifica solicitudes de autorización, no assertions de grant.
- **Impacto:** no hay intercambio de assertion firmada por token según RFC 7523 §2.1.
- **Por hacer:** decidir confianza/issuer/audience/subject y prevención de replay si hay integración de servicio a servicio; no anunciar grant hasta implementarlo.
- **Aceptación:** decisión documentada; si se implementa, assertion ajena o repetida no emite tokens.
- **Origen:** análisis OAuth §5.2.

## Por qué se plantea

Hay sistemas externos que ya reciben una identidad firmada de su plataforma (por ejemplo, una carga de trabajo de CI o de nube) y podrían presentarla al servidor OAuth para obtener un access token local. El grant JWT bearer de RFC 7523 contempla ese canje; es distinto de `private_key_jwt`, que prueba la identidad de un cliente registrado al autenticarse ante un endpoint, y distinto de JAR, que firma una solicitud de autorización. La utilidad aparece cuando interesa confiar en una identidad externa concreta sin distribuir un secreto o una clave privada adicional a cada carga de trabajo. No se ha aprobado porque aún no hay una integración concreta que permita definir qué emisor, qué claims y qué permisos se aceptarían.

## Qué conseguiría implementarla

- Un sistema externo previamente autorizado podría intercambiar una aserción válida, dirigida a este servidor y de un solo uso, por un access token de corta duración con identidad local y scopes/roles limitados por una regla del tenant.
- La aceptación quedaría condicionada a la firma y claves del emisor de confianza, audiencia, caducidad y claims exactos de la carga de trabajo; no bastaría con que el JWT estuviera firmado por una plataforma pública.
- Los servidores de recursos recibirían tokens emitidos por este proveedor para identidades de sistema identificables y auditables, sin confundirlas con personas ni emitirles ID tokens o refresh tokens. Esto habilitaría integraciones sin secretos locales cuando la plataforma externa ya proporcione la identidad adecuada.

## Qué ocurre mientras siga pospuesta

`/token` no acepta `urn:ietf:params:oauth:grant-type:jwt-bearer` y discovery no lo anuncia. Una aserción externa, aunque sea válida para su emisor, no se puede canjear directamente por tokens de esta instalación. Siguen disponibles API keys y, para clientes que puedan custodiar una clave, `client_credentials` autenticado con `private_key_jwt`; esos mecanismos no realizan el canje de identidad de plataforma descrito aquí. Posponerlo evita aceptar emisores o claims sin una política de confianza y permisos definida, a cambio de no cubrir todavía el caso de cargas de trabajo sin credenciales propias.

## Detalle de implementación (solo si se aprueba)

- `src/Features/OAuth/Authentication/Application/TokenGranter/` (**nuevo** `ResolverForJwtBearer.php`): verificar assertion firmada, issuer/subject/audience, expiración y JTI de un solo uso para el grant RFC 7523.
- `src/Features/OAuth/Authentication/Application/TokenGranter/TokenGranterMediator.php` y `Infrastructure/Driver/Rest/TokenController.php`: enrutar grant solo a clientes autorizados, separar assertion de autenticación de cliente de assertion grant.
- `src/Features/OAuth/Client/Domain/ClientData.php` y `src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php`: incluir política por cliente y anunciar grant únicamente cuando sea operativo.
- `test/integration/Features/OAuth/TokenEndpointTest.php`: comprobar assertion válida, replay, firma ajena y `aud` errónea.

## Decisión

Pospuesta el 23-10-2026. El acceso de sistemas externos se hace hoy con API key, y [044](../done/044-client-auth-jwt.md) ya permite `client_credentials` con `private_key_jwt` a cualquier sistema que pueda guardar una clave privada. Este grant solo aporta algo cuando el sistema externo **ya tiene una identidad de plataforma** (CI como GitHub Actions o GitLab, Kubernetes, identidad de cargas de trabajo en la nube o el IdP de un socio) y puede presentar su token sin guardar ningún secreto. Todavía no hay un sistema concreto así.

Se reabre ante el primer sistema externo de ese tipo, o junto con [052](../052-token-exchange.md) si se aprueba token exchange, para no tener dos grants que se solapen.

### Diseño previsto para cuando se reabra

- **Emisores de confianza** (`trusted-assertion-issuer`, por tenant): `iss`, `jwks-uri` (descargado con `RemoteJwksFetcher`), algoritmos, vida máxima de la aserción, audiencia esperada y estado.
- **Reglas de aceptación** (por emisor): condiciones sobre claims, nombre del sistema local, roles y scopes que se imponen y duración del token emitido.
- **Las condiciones identifican al sistema y son obligatorias.** Un emisor público firma tokens para todo el mundo: aceptar el `iss` de GitHub sin exigir, por ejemplo, `repository` y `ref` abre la puerta a cualquier repositorio. Cada regla necesita al menos una condición de igualdad exacta sobre `sub` o un claim equivalente, nunca prefijos ni expresiones abiertas.
- **El token emitido no representa a un usuario.** Su `sub` es un identificador local y estable de la regla (p. ej. `system:<código>`), no el `sub` externo, con un `token_use` propio, igual que `client_credentials`. Cada servidor de recursos decide qué hace con las peticiones de ese sistema. Solo se emite access token, sin refresh ni ID token, con techo de scopes.
- **Anti-replay por emisor:** el `jti` es único por emisor, así que en `_oauth_replay_jti` (`purpose=jwt_bearer`) la clave debe incluir el `iss` en lugar del `client_id`.
- **Ya existe:** `ReplayPurpose::JwtBearer`, `RemoteJwksFetcher` y las reglas de claims de `JwtAssertionVerifier`, al que habría que dar un origen de claves por emisor en lugar de `ClientData`. Faltan las dos entidades (generador y migración), `ResolverForJwtBearer`, su registro en `TokenGranterMediator`, el anuncio en discovery y las pruebas de la aceptación.
- **Relacionado, independiente de esta ficha:** hoy los tokens de `client_credentials` llevan `roles: []` ([ResolverForClientCredentials](../../../src/Features/OAuth/Authentication/Application/TokenGranter/ResolverForClientCredentials.php)). Asignar roles a un cliente M2M haría de `private_key_jwt` la vía estándar para sistemas externos sin esperar a esta ficha.
