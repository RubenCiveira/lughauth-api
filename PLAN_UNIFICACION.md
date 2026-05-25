# Plan de Unificación: LughAuth (PHP) ↔ Phylax (Java)

## Contexto

Phylax (Java/Quarkus) y LughAuth (PHP) comparten el mismo modelo de datos subyacente en
`features/access`, `features/document` y `features/notification`. El objetivo es alinear
funcionalmente ambos proyectos, tomando Phylax como referencia canónica de diseño.

**Proyecto referencia:** `/Users/ruben.civeiraiglesia/Documents/Proyectos/Personal/phylax/phylax-api`
**Proyecto objetivo:** `/Users/ruben.civeiraiglesia/Documents/Proyectos/Personal/php-micros/apps/LughAuth/lughauth-api`

---

## FASE 0 — Renombrado estructural

El namespace de PHP usa `Oidc/` mientras Java usa `oauth/`. El protocolo que implementa es
OAuth 2.0 + OIDC, por lo que el nombre canónico del contexto delimitado debe ser `OAuth`.

### T-000 — Renombrar `Features/Oidc/` → `Features/OAuth/`

- Renombrar el directorio físico `src/Features/Oidc/` → `src/Features/OAuth/`
- Actualizar todos los namespaces PHP: `App\Features\Oidc\` → `App\Features\OAuth\`
- Actualizar todos los `use` imports en el resto del proyecto
- Actualizar el bootstrap plugin: `src/Bootstrap/Plugin/OidcPlugin.php` → `OAuthPlugin.php`
- Actualizar referencias en `composer.json` (autoload PSR-4), configuración de contenedor, rutas
- Actualizar `src/Bootstrap/Plugin/AccessPlugin.php` si referencia namespaces Oidc
- Verificar que los tests pasan tras el renombrado

**Impacto:** ~250 ficheros PHP modificados. Es el prerequisito de todo lo demás.

---

## FASE 1 — Contextos delimitados faltantes en OAuth

Java tiene dos sub-contextos dentro de `oauth/` que PHP no tiene como bounded context propio:

### T-101 — Crear bounded context `OAuth/Oidc/` (OpenID Connect Discovery)

Java tiene `oauth/oidc/` como contexto propio para el discovery document y MTLS aliases.
PHP tiene esto mezclado en `Common/` o en controladores sueltos.

**Estructura a crear:**
```
src/Features/OAuth/Oidc/
├── Domain/
│   ├── OpenIdConfiguration.php         (VO: metadata del discovery document)
│   ├── MtlsEndpointAliases.php         (VO: alias de endpoints MTLS)
│   └── Gateway/
│       └── DiscoveryContributorGateway.php  (port: interfaz para contribuidores)
└── Infrastructure/
    └── Driver/
        └── Rest/
            ├── OpenIdConfigurationController.php  (mover desde Common si existe)
            └── MtlsDiscoveryContributor.php       (nuevo: alias MTLS en discovery)
```

**Acciones:**
- Extraer `OpenIdConfigurationController` de `Common/` a este nuevo contexto
- Modelar `OpenIdConfiguration` como Value Object con todos los campos del RFC 8414
- Añadir soporte para `mtls_endpoint_aliases` en el discovery document (RFC 8705)
- Implementar `MtlsDiscoveryContributor` que inyecte los alias en el discovery

### T-102 — Crear bounded context `OAuth/Gdpr/`

Java tiene `oauth/gdpr/` con casos de uso de exportación y eliminación de datos.
PHP tiene `GdprConsentUsecase` dentro de `Consent/` pero no una gestión completa de GDPR.

**Estructura a crear:**
```
src/Features/OAuth/Gdpr/
├── Application/
│   └── Usecase/
│       ├── ExportUserData/
│       │   ├── ExportUserDataUsecase.php
│       │   ├── ExportUserDataCommand.php
│       │   └── ExportUserDataResult.php
│       └── DeleteUserData/
│           ├── DeleteUserDataUsecase.php
│           ├── DeleteUserDataCommand.php
│           └── DeleteUserDataResult.php
├── Domain/
│   ├── UserDataExport.php       (VO: datos exportados del usuario)
│   ├── Event/
│   │   ├── UserDataExportedEvent.php
│   │   └── UserDataDeletedEvent.php
│   └── Gateway/
│       ├── UserDataExportGateway.php
│       └── UserDataDeletionGateway.php
└── Infrastructure/
    ├── Driver/
    │   └── Rest/
    │       ├── GdprExportController.php    (GET /me/data-export)
    │       └── GdprDeleteController.php    (DELETE /me/account)
    └── Driven/
        ├── UserDataExportAdapter.php
        └── UserDataDeletionAdapter.php
```

---

## FASE 2 — Casos de uso faltantes en contextos existentes

### T-201 — Token granters como estrategias explícitas (`OAuth/Authentication/`)

Java implementa el token endpoint con 6 granters concretos usando el patrón Strategy.
Verificar si PHP los tiene todos y si están bien encapsulados:

- `ClientCredentialsGranter` — grant_type=client_credentials
- `PasswordGranter` — grant_type=password (ROPC)
- `RefreshGranter` — grant_type=refresh_token
- `MfaGranter` — grant_type=mfa (custom)
- `DeviceCodeGranter` — grant_type=urn:ietf:params:oauth:grant-type:device_code
- `DelegatedAccessGranter` — grant_type para federated login

**Acciones:**
- Auditar `TokenController` en PHP: ¿delega en granters individuales o tiene lógica monolítica?
- Si es monolítico, extraer cada grant type a su propia clase Granter implementando una interfaz `TokenGranter`
- Registrar los granters en el contenedor DI con autodescubrimiento por `grant_type`

### T-202 — Back-channel logout (`OAuth/Authentication/`)

Java tiene `BackChannelLogoutDispatcher` que notifica a los Relying Parties cuando el usuario
cierra sesión (RFC 7009 + draft OIDC back-channel logout).

**Acciones:**
- Verificar si PHP tiene back-channel logout implementado
- Si no existe: crear `BackChannelLogoutDispatcher` que:
  - Obtiene la lista de RPs con sesiones activas del usuario
  - Construye un `logout_token` JWT firmado para cada RP
  - Dispara HTTP POST al `backchannel_logout_uri` de cada RP
  - Maneja fallos de entrega (reintentos, outbox pattern)
- Añadir soporte para `backchannel_logout_uri` en el modelo `RelyingParty`

### T-203 — `SessionManager` como servicio de aplicación (`OAuth/Authentication/`)

Java tiene `SessionManager` como servicio de orquestación de sesión de usuario en la capa de
aplicación, separado de la lógica de autenticación.

**Acciones:**
- Verificar si PHP tiene este servicio o está acoplado en controladores
- Si no existe: extraer la gestión del ciclo de vida de sesión a `SessionManager`:
  - Crear sesión tras autenticación exitosa
  - Renovar sesión (sliding window)
  - Invalidar sesión (logout)
  - Consultar sesiones activas del usuario

### T-204 — `ActiveUserFindService` (`OAuth/User/`)

Java tiene `ActiveUserFindService` en infrastructure como servicio de localización de usuario
activo en el contexto de una petición.

**Acciones:**
- Verificar si PHP tiene equivalente
- Si no: crear `ActiveUserFindService` que resuelve el usuario autenticado desde el token JWT
  de la petición entrante, usable en casos de uso que necesiten el principal actual

### T-205 — Consentimiento secuencial multi-RP (`OAuth/Consent/`)

Java implementa ADR-001: cuando un usuario tiene sesión activa y un nuevo RP solicita
autorización, se muestran los consents pendientes de forma secuencial (un RP a la vez).

**Acciones:**
- Verificar si PHP implementa el flujo de consent secuencial
- Si no: modelar `PendingConsentQueue` como parte de `ChallengesState`
- Añadir `ConsentRequiredException` y `ClientScopeConsentRequiredException` al dominio
- El `StepRouter` debe detectar consents pendientes y enrutar al step correcto antes de emitir código de autorización

---

## FASE 3 — Modelo de dominio: elementos faltantes

### T-301 — Jerarquía de excepciones de autenticación (`OAuth/Authentication/Domain/Exception/`)

Java tiene 8 excepciones de dominio específicas para cada tipo de fallo de autenticación.
Verificar y completar en PHP:

| Excepción Java | Significado | ¿Existe en PHP? |
|---|---|---|
| `ConsentRequiredException` | Falta consentimiento de RP | Verificar |
| `ClientScopeConsentRequiredException` | Falta consentimiento de scope | Verificar |
| `MfaRequiredException` | MFA requerido pero no presentado | Verificar |
| `NewMfaRequiredException` | MFA debe configurarse (primera vez) | Verificar |
| `NewPasswordRequiredException` | Cambio de contraseña obligatorio | Verificar |
| `NotAllowedAccessUserException` | Usuario sin permiso para ese cliente | Verificar |
| `UnknownUserException` | Usuario no encontrado | Verificar |
| `WrongCredentialsException` | Credenciales inválidas | Verificar |

**Acciones:** Auditar las excepciones existentes en `Authentication/Domain/` y crear las faltantes.
Asegurarse de que el flujo `revolve()` captura cada tipo y enruta al step correcto.

### T-302 — `PkceChallenge` Value Object (`OAuth/Authentication/Domain/`)

Java tiene `PkceChallenge` como VO que encapsula `code_challenge` + `code_challenge_method`
y la verificación del `code_verifier` en el token endpoint.

**Acciones:**
- Verificar si PHP tiene soporte PKCE (RFC 7636)
- Si no: crear `PkceChallenge` VO con métodos:
  - `fromRequest(string $challenge, string $method): self`
  - `verify(string $codeVerifier): bool`
  - Soporte para `S256` y `plain` (desaconsejado pero requerido por spec)
- Integrar verificación en `AuthorizationRequest` y en el granter de `authorization_code`

### T-303 — Eventos de dominio de autenticación (`OAuth/Authentication/Domain/Event/`)

Java tiene un modelo de eventos ricos para el flujo OAuth. Verificar y completar en PHP:

| Evento Java | Cuándo se dispara |
|---|---|
| `LoginSucceededEvent` | Autenticación completada con éxito |
| `LoginFailedEvent` | Credenciales inválidas |
| `UserLocked` | Usuario bloqueado por intentos fallidos |
| `UserUnlocked` | Bloqueo levantado |
| `OidcEvent` | Evento base genérico OIDC |

**Acciones:** Verificar eventos existentes y añadir los que falten. Asegurarse de que se
persisten/disparan desde `AuthenticateUser` o el caso de uso equivalente.

### T-304 — `AuthenticationData` como Value Object rico (`OAuth/Authentication/Domain/`)

Java tiene `AuthenticationData` como VO que encapsula el resultado positivo de autenticación:
`uid`, `roles`, `scopes`, `claims`, `tenantId`. Es la salida del proceso de autenticación
antes de emitir tokens.

**Acciones:**
- Verificar si PHP tiene equivalente o si los datos se pasan de forma ad-hoc
- Si no: crear `AuthenticationData` VO y usarlo como tipo de retorno de `AuthenticateUser`

### T-305 — `OpenIdConfiguration` VO completo (`OAuth/Oidc/Domain/`)

El discovery document debe incluir todos los campos del RFC 8414 y extensiones OIDC:

- Campos básicos: `issuer`, `authorization_endpoint`, `token_endpoint`, `userinfo_endpoint`, etc.
- Campos de capacidades: `response_types_supported`, `grant_types_supported`, `scopes_supported`
- PKCE: `code_challenge_methods_supported`
- MTLS: `mtls_endpoint_aliases`
- PAR: `pushed_authorization_request_endpoint`, `require_pushed_authorization_requests`
- Device: `device_authorization_endpoint`
- Back-channel logout: `backchannel_logout_supported`, `backchannel_logout_session_supported`
- WebAuthn: ningún campo estándar específico pero sí ACR values

---

## FASE 4 — Capa de infraestructura: componentes faltantes

### T-401 — `OidcStepRouter` / `StepOutcomeHandler` (`OAuth/Authentication/Infrastructure/`)

Java separa la responsabilidad de enrutar entre steps en clases dedicadas:
- `OidcStepRouter`: decide qué step mostrar según el estado de challenges
- `StepOutcomeHandler`: procesa el resultado de cada step y avanza el flujo
- `OidcResponseBuilder`: construye la respuesta OAuth (redirect con code, error, etc.)
- `OidcUrlBuilder`: construye URLs del flujo OIDC

**Acciones:**
- Verificar si PHP tiene esta separación o si está acoplada en controladores HTML
- Si está acoplada: extraer a clases dedicadas siguiendo la misma separación de responsabilidades

### T-402 — `SecureHtmlBuilder` (`OAuth/Authentication/Infrastructure/Driver/Html/`)

Java tiene `SecureHtmlBuilder` que añade automáticamente tokens CSRF y campos cifrados a
todos los formularios HTML del flujo OAuth.

**Acciones:**
- Verificar si PHP tiene protección CSRF en los formularios del flujo
- Si no: crear `SecureHtmlBuilder` o equivalente que:
  - Genera y valida tokens CSRF para cada formulario
  - Opcionalmente cifra campos sensibles antes de enviarlos al cliente

### T-403 — Step handlers como clases dedicadas (`OAuth/Authentication/Infrastructure/Driver/Html/Step/`)

Java tiene 11 step handlers, uno por tipo de challenge. Verificar si PHP los tiene todos:

| Step Java | Propósito |
|---|---|
| `LoginStep` | Formulario de login con usuario/contraseña |
| `MfaStep` | Formulario de código MFA |
| `MagicLinkStep` | Pantalla de espera de magic link |
| `DelegatedStep` | Redirección a proveedor externo |
| `RegistrationStep` | Formulario de registro de nuevo usuario |
| `ConsentStep` | Pantalla de consentimiento de RP |
| `ScopeConsentStep` | Pantalla de consentimiento de scopes |
| `NewMfaStep` | Configuración inicial de MFA |
| `NewPassStep` | Formulario de cambio de contraseña obligatorio |
| `RecoverMfaStep` | Recuperación de MFA con código de recovery |
| `RecoverStep` | Recuperación de cuenta (contraseña olvidada) |

**Acciones:** Auditar los step handlers en `Authentication/Infrastructure/Driver/Html/` y crear los que falten.

### T-404 — `MtlsDiscoveryContributor` (`OAuth/Oidc/Infrastructure/`)

Implementar el contribuidor que añade `mtls_endpoint_aliases` al discovery document,
exponiendo los mismos endpoints bajo URLs con validación de certificado de cliente (mTLS).

### T-405 — `OAuthCleanupScheduler` (`OAuth/Session/Infrastructure/`)

Java tiene un scheduler que limpia periódicamente tokens expirados, sesiones caducadas y
códigos temporales. Verificar si PHP tiene equivalente en `public/cron/index.php` y si
cubre todos los recursos a limpiar:

- Sesiones OAuth expiradas
- Authorization codes usados/expirados
- Refresh tokens revocados/expirados
- Device authorization requests expirados
- PAR requests expirados
- Magic links expirados
- Temporal auth codes expirados

### T-406 — `DevicesAccessController` (gestión de dispositivos autorizados) (`OAuth/Session/`)

Java tiene `DevicesAccessController` para que el usuario gestione los dispositivos que tienen
sesiones activas (listar y revocar por dispositivo).

**Acciones:**
- Verificar si PHP tiene endpoints para gestión de dispositivos/sesiones activas
- Si no: crear `UserSessionController` equivalente con:
  - `GET /me/sessions` — listar sesiones activas del usuario
  - `DELETE /me/sessions/{sessionId}` — revocar una sesión concreta
  - `DELETE /me/sessions` — revocar todas las sesiones (logout global)

---

## FASE 5 — Limpieza y alineación de `Common/`

### T-501 — Revisar y vaciar `OAuth/Common/`

Java no tiene un sub-contexto `Common/` dentro de `oauth/`. Las responsabilidades que
actualmente viven en `Common/` deben redistribuirse:

**Acciones:**
- Inventariar todo lo que está en `src/Features/OAuth/Common/` (tras renombrado)
- Mover cada elemento al contexto al que pertenece semánticamente:
  - Controllers de discovery → `OAuth/Oidc/`
  - Helpers de respuesta OAuth → `OAuth/Authentication/Infrastructure/`
  - Utilidades de URL → `OAuth/Authentication/Infrastructure/`
  - Casos de uso de instalación → mantener en `Common/` o mover a contexto propio `OAuth/Install/`
- Si `Common/` queda vacío, eliminarlo

---

## FASE 6 — Alineación de `Access/` con Java

La feature `Access/` en PHP tiene los mismos 21 sub-dominios que Java, pero hay que verificar
que la implementación de las políticas de autorización (Allow/Filter) esté completa.

### T-601 — Auditar que todos los sub-dominios de `Access/` tienen policies completas

Java implementa autorización en la capa de aplicación. Verificar en PHP que cada sub-dominio
tiene:
- Policies de `Allow` para cada operación (Create, Read, Update, Delete, List, Enable, Disable)
- Policies de `Filter` para limitar resultados según el rol del actor
- Visibilidad de campos según rol (`NonVisibleFields`, `NonEditableFields`)

**Sub-dominios a revisar:** User, Tenant, Role, TenantConfig, TenantLoginProvider, TenantTermsOfUse,
ApiKeyClient, ConsentPurpose, RelyingParty, TrustedClient, UserProfile, UserRoleAssignament,
UserGroupMembership, UserPersonalApiKey, UserMfaRecoveryCode, UserWebauthnCredential,
UserConsentedScopes, UserConsentPurposes, UserAcceptedTermnsOfUse, UserAccessTemporalCode, UserInvitation.

### T-602 — Verificar dominio de `User` con fórmulas calculadas completas

Java tiene 7 fórmulas calculadas para el agregado User. Verificar que PHP tiene las equivalentes:
- `EnabledCalculator`
- `EmailVerifiedCalculator`
- `BlockedUntilCalculator`
- `ApproveCalculator`
- `ProviderCalculator`
- `SecondFactorSeedCalculator`
- `WellcomeAtCalculator` (fecha de primer login)

### T-603 — Eventos de dominio de `User` en `Access/`

Java tiene 13 eventos de dominio para el agregado User. Verificar en PHP:

| Evento | Propósito |
|---|---|
| `UserCreateEvent` | Usuario creado |
| `UserUpdateEvent` | Datos de usuario actualizados |
| `UserDeleteEvent` | Usuario eliminado |
| `UserBlockEvent` | Usuario bloqueado |
| `UserUnlockEvent` | Usuario desbloqueado |
| `UserEnableEvent` | Usuario habilitado |
| `UserDisableEvent` | Usuario deshabilitado |
| `UserVerifyEmailEvent` | Email verificado |
| `UserVerifyEvent` | Usuario verificado (admin) |
| `UserAcceptEvent` | Usuario aceptado (flujo de aprobación) |
| `UserRejectEvent` | Usuario rechazado |
| `UserChangePasswordEvent` | Contraseña cambiada |
| `UserSetMfaSeedEvent` | Seed MFA configurado |

---

## FASE 7 — Pruebas y verificación

### T-701 — Tests de integración del flujo OAuth completo

Asegurar cobertura de los flows principales:
- Authorization Code + PKCE
- Client Credentials
- Device Authorization (RFC 8628)
- PAR → Authorization Code (RFC 9126)
- Magic Link
- Delegated Login (OAuth2 externo)
- MFA challenge en authorization code flow
- Back-channel logout

### T-702 — Tests de discovery document

Verificar que `GET /.well-known/openid-configuration` devuelve todos los campos requeridos
por RFC 8414 y las extensiones implementadas.

### T-703 — Tests de PKCE

Verificar que el flujo authorization code rechaza peticiones sin PKCE cuando el cliente lo
requiere, y que verifica correctamente el `code_verifier` en el token endpoint.

---

## Orden de ejecución recomendado

```
T-000  (renombrado) → PRERREQUISITO ABSOLUTO
  ↓
T-101, T-102  (bounded contexts nuevos, paralelo)
  ↓
T-201..205  (casos de uso, se pueden paralelizar por contexto)
  ↓
T-301..305  (modelo de dominio, paralelo)
  ↓
T-401..406  (infraestructura, paralelo por contexto)
  ↓
T-501  (limpieza Common)
  ↓
T-601..603  (auditoría Access, paralelo por sub-dominio)
  ↓
T-701..703  (tests de verificación)
```

---

## Referencias

- Java: `phylax-api/src/main/java/net/civeira/phylax/features/oauth/`
- Java: `phylax-api/docs/oauth/` (arquitectura y ADRs)
- RFC 7636 — PKCE
- RFC 8628 — Device Authorization Grant
- RFC 9126 — Pushed Authorization Requests
- RFC 8705 — OAuth 2.0 mTLS Client Authentication
- RFC 8414 — OAuth 2.0 Authorization Server Metadata
- OpenID Connect Back-Channel Logout 1.0
