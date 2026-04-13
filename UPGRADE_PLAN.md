# LughAuth — Upgrade Plan

> Revisión a fecha **2026-04-12**.
> Objetivo: completar el cumplimiento OIDC/OAuth 2.0 estándar y ampliar
> las capacidades de **Backend-as-a-Service** para dar soporte a desarrollos
> funcionales genéricos sobre esta plataforma.

---

## 1. Estado actual (resumen ejecutivo)

El proyecto implementa un servidor OIDC/OAuth 2.0 multi-tenant con:

- Authorization Code Flow + Refresh Token + Device Grant + Password Grant
- MFA TOTP, registro de usuario, recuperación de contraseña
- JWKS, `.well-known/openid-configuration`, Userinfo endpoint
- Revocación de tokens (endpoint existe, implementación parcial)
- Login delegado (Google OAuth)
- Sistema de plantillas de email (Twig/Handlebars), notificaciones SMTP
- RBAC, multi-tenant con aislamiento por `tenant_id`
- Observabilidad: OpenTelemetry, Prometheus, audit trail

**Gaps críticos identificados:**

- Scope consent tracking es un stub
- Sin introspección de tokens (RFC 7662)
- Sin PKCE (RFC 7636) — obligatorio para SPAs/apps nativas
- Sin registro dinámico de clientes (RFC 7591)
- Logout distribuido (front/back-channel) incompleto
- Sin WebAuthn/Passkeys
- Sin sistema de webhooks para eventos de autenticación
- Sin API de gestión de sesiones para el usuario final
- Sin invitaciones, organizaciones ni permisos a nivel de recurso (ABAC)
- Sin compliance GDPR (exportación y borrado de datos)

---

## 2. Roadmap por prioridad

### Nivel 1 — Correcciones de cumplimiento OIDC obligatorio

Estas tareas deben completarse para que el servidor sea plenamente conforme
con las especificaciones OIDC Core 1.0 y OAuth 2.0.

---

#### 2.1 PKCE — Proof Key for Code Exchange (RFC 7636)

**Relevancia:** Obligatorio para cualquier cliente público (SPA, app móvil, CLI).
Sin PKCE, el authorization code flow es vulnerable a intercepción de código.

**Qué implementar:**
- Parámetros `code_challenge` y `code_challenge_method` en `/authorize`
- Métodos soportados: `S256` (obligatorio), `plain` (opcional)
- Validar `code_verifier` en `/token` al intercambiar el código
- Anunciar `code_challenge_methods_supported` en `.well-known/openid-configuration`

**Archivos afectados:**
- `src/Features/Oidc/Authentication/` — paso `authorize` y `token`
- `_oauth_temporal_codes` — añadir columnas `code_challenge`, `code_challenge_method`

---

#### 2.2 Token Introspection (RFC 7662)

**Relevancia:** Permite a los resource servers validar tokens de acceso opacos
o JWT emitidos sin tener que verificar la firma localmente. Caso de uso típico:
microservicios que reciben tokens y necesitan conocer su estado.

**Endpoint a crear:**
```
POST /openid/{tenant}/introspect
Authorization: Basic <client_credentials>
Content-Type: application/x-www-form-urlencoded

token=<access_token>&token_type_hint=access_token
```

**Respuesta estándar:**
```json
{
  "active": true,
  "sub": "uuid-usuario",
  "client_id": "uuid-cliente",
  "scope": "openid email profile",
  "exp": 1234567890,
  "iat": 1234567800,
  "iss": "https://auth.example.com/openid/tenant-slug",
  "jti": "uuid-del-token"
}
```

**Archivos a crear:**
- `src/Features/Oidc/Introspection/` — nuevo sub-feature con dominio, usecase y controlador REST

---

#### 2.3 Scope Consent Tracking — completar implementación

**Estado actual:** El módulo `Oidc/Scopes/` es un stub que devuelve `[]`
y no persiste decisiones del usuario.

**Qué implementar:**
- Tabla `_oauth_scope_consent` (`user_uid`, `client_uid`, `tenant_id`, `scope`, `granted_at`)
- Usecase `GrantScopeConsent` y `RevokeScopeConsent`
- Lógica en el paso `scopes-consent` del authorize flow:
  - Mostrar formulario solo cuando haya scopes nuevos no consentidos
  - Persistir decisión, no pedir nuevamente si ya fue concedida
- Endpoint `DELETE /openid/{tenant}/consent/{client_uid}` para revocar consentimiento

---

#### 2.4 Token Revocation completa (RFC 7009)

**Estado actual:** El endpoint `POST /openid/{tenant}/revoke` existe pero la
implementación puede estar incompleta (sin invalidar refresh tokens en cascada).

**Qué completar:**
- Al revocar un `access_token`: marcar el JTI como revocado en Redis/DB
- Al revocar un `refresh_token`: invalidar todos los access tokens emitidos con ese refresh
- Tabla de JTIs revocados o columna `revoked_at` en `_oauth_session`
- Anunciar `revocation_endpoint` en `.well-known`

---

#### 2.5 Logout distribuido — Front-Channel y Back-Channel (OIDC Session Management)

**Estado actual:** El logout limpia cookies localmente pero no notifica a los
clientes (RPs) que tenían sesiones activas.

**Qué implementar:**
- **Back-channel logout** (OIDC Back-Channel Logout 1.0):
  - Al hacer logout, enviar un `logout_token` JWT firmado a cada RP registrado en `access_relying_party`
  - Usar `backchannel_logout_uri` en el registro del cliente
- **Front-channel logout**:
  - Renderizar iframes con las URLs de logout de cada RP en la página de logout
  - Parámetros: `frontchannel_logout_uri`, `frontchannel_logout_session_required`
- Anunciar en `.well-known`: `backchannel_logout_supported`, `frontchannel_logout_supported`

---

#### 2.6 OpenAPI/Swagger en endpoints OIDC

**Estado actual:** Los controladores OIDC (`Oidc/Authentication/Infrastructure/Driver/Rest/`)
no tienen atributos `#[OA\...]` y no aparecen en `/management/apidoc`.

**Qué hacer:**
- Añadir anotaciones OpenAPI a todos los controladores OIDC
- Documentar request/response de `/authorize`, `/token`, `/userinfo`, `/revoke`,
  `/introspect`, `/device`, `.well-known`, `/jwks`

---

### Nivel 2 — Extensiones OAuth 2.0 de alto valor

---

#### 2.7 PKCE + PAR — Pushed Authorization Requests (RFC 9126)

**Relevancia:** PAR desplaza los parámetros del authorize al servidor antes
de redirigir al usuario, eliminando la exposición de `client_id`, `scope`,
`redirect_uri`, `code_challenge` en la URL del navegador.

**Endpoint a crear:**
```
POST /openid/{tenant}/par
Authorization: Basic <client_credentials>
→ Devuelve: { request_uri: "urn:ietf:params:oauth:request_uri:xxx", expires_in: 60 }

GET /openid/{tenant}/authorize?client_id=xxx&request_uri=urn:...
```

---

#### 2.8 Dynamic Client Registration (RFC 7591 / RFC 7592)

**Relevancia:** Permite que los RPs se registren programáticamente sin
intervención del administrador. Útil para plataformas multi-tenant donde
cada cliente configura su propia integración.

**Endpoints a crear:**
```
POST   /openid/{tenant}/register           # Registrar nuevo cliente
GET    /openid/{tenant}/register/{client}  # Leer configuración (RFC 7592)
PUT    /openid/{tenant}/register/{client}  # Actualizar cliente (RFC 7592)
DELETE /openid/{tenant}/register/{client}  # Eliminar cliente (RFC 7592)
```

**Opciones de política:** registro abierto, con token inicial (`initial_access_token`),
o solo para admins.

---

#### 2.9 Client Credentials Grant — completar soporte M2M

**Estado actual:** El grant `client_credentials` puede no estar completamente
implementado para el caso máquina-a-máquina.

**Qué implementar:**
- Soporte completo en `/token` para `grant_type=client_credentials`
- Scopes específicos para M2M (no asociados a usuario)
- Tokens con `sub = client_id` en lugar de `sub = user_id`
- Política de scopes permitidos por cliente en `access_trusted_client`

---

#### 2.10 JWT Secured Authorization Request — JAR (RFC 9101)

**Relevancia:** Permite enviar parámetros de autorización como un JWT firmado
(`request` parameter), garantizando integridad y no-repudio.

**Qué implementar:**
- Parámetro `request` en `/authorize` (JWT firmado por el cliente)
- Parámetro `request_uri` que referencie un JWT externo
- Anunciar `request_object_signing_alg_values_supported` en `.well-known`

---

### Nivel 3 — Autenticación avanzada

---

#### 2.11 WebAuthn / Passkeys (FIDO2)

**Relevancia:** El estándar de autenticación sin contraseña más seguro y con
mayor adopción. Elimina ataques de phishing y relleno de credenciales.

**Qué implementar:**
- **Registration flow:**
  ```
  POST /openid/{tenant}/webauthn/register/begin   → publicKeyCredentialCreationOptions
  POST /openid/{tenant}/webauthn/register/finish  → guarda credential en DB
  ```
- **Authentication flow:**
  ```
  POST /openid/{tenant}/webauthn/authenticate/begin   → publicKeyCredentialRequestOptions
  POST /openid/{tenant}/webauthn/authenticate/finish  → verifica y crea sesión
  ```
- Tabla `access_user_webauthn_credential` (`user_uid`, `credential_id`, `public_key`, `sign_count`, `device_name`, `created_at`)
- Integrar como paso adicional en el authorize flow (paso: `webauthn`)
- Librería sugerida: `web-auth/webauthn-framework`

---

#### 2.12 Magic Links / Passwordless Email Login

**Relevancia:** Flujo de autenticación alternativo. El usuario recibe un
enlace de un solo uso por email que lo autentica directamente.

**Qué implementar:**
- Endpoint `POST /openid/{tenant}/magic-link/request` → genera código temporal y envía email
- Endpoint `GET /openid/{tenant}/magic-link/verify?token=xxx` → valida y crea sesión OIDC
- Integrar con el sistema de notificaciones existente (`Notification/Outbox`)
- Configurable por tenant en `access_tenant_config`

---

#### 2.13 Autenticación con SMS / OTP por Email como segundo factor

**Estado actual:** Solo existe TOTP (Google Authenticator).

**Qué añadir:**
- MFA por email: enviar código de 6 dígitos al email del usuario
- MFA por SMS: integración con proveedor (Twilio, AWS SNS, Vonage) vía interfaz
  `SmsProvider` intercambiable por tenant
- Tabla `access_user_mfa_method` para múltiples métodos por usuario
- UI: gestión de métodos MFA en el authorize flow

---

#### 2.14 Proveedores de login social adicionales

**Estado actual:** Solo Google OAuth está implementado.

**Qué añadir como adaptadores del `DelegateLogin` domain:**
- GitHub OAuth
- Microsoft / Azure AD (OIDC estándar)
- Apple Sign In
- SAML 2.0 genérico (para integraciones enterprise)
- Interfaz `OAuthProviderAdapter` para que nuevos proveedores sean plug-and-play

---

### Nivel 4 — API de gestión de identidad (BaaS core)

Estas APIs permiten a los desarrolladores gestionar identidades desde sus
backends sin pasar por el authorize flow OIDC.

---

#### 2.15 Management API — Sesiones activas del usuario

**Qué implementar:**
```
GET    /api/access/users/{uid}/sessions           # Listar sesiones activas
DELETE /api/access/users/{uid}/sessions/{sid}     # Revocar sesión concreta
DELETE /api/access/users/{uid}/sessions           # Revocar todas las sesiones
```

Permite al usuario final ver desde qué dispositivos está conectado y
revocar sesiones remotamente (funcionalidad "cerrar otras sesiones").

---

#### 2.16 Management API — Perfil de usuario ampliado

**Estado actual:** El usuario tiene `email`, `password`, `mfa_seed`, `temporal_password`.

**Qué añadir:**
- Campos de perfil: `given_name`, `family_name`, `display_name`, `phone_number`,
  `picture_url`, `locale`, `zoneinfo` (claims estándar de OIDC)
- Tabla `access_user_profile` (o columnas adicionales en `access_user`)
- Endpoint `GET /openid/{tenant}/userinfo` devuelve estos claims si el scope `profile` está concedido
- Endpoint `PUT /api/access/users/{uid}/profile` para actualizar perfil

---

#### 2.17 Sistema de Invitaciones

**Relevancia:** Feature base de cualquier BaaS. Permite invitar usuarios a
un tenant sin que tengan cuenta previa.

**Qué implementar:**
- Tabla `access_user_invitation` (`uid`, `tenant_id`, `email`, `role_uid`, `invited_by`, `expires_at`, `accepted_at`)
- Endpoints:
  ```
  POST   /api/access/invitations              # Crear y enviar invitación por email
  GET    /api/access/invitations              # Listar invitaciones pendientes
  DELETE /api/access/invitations/{uid}        # Cancelar invitación
  POST   /openid/{tenant}/invitation/accept   # Aceptar (crea usuario + sesión)
  ```
- Integrar con `Notification/Outbox` para el envío del email

---

#### 2.18 Organizaciones y Grupos jerárquicos

**Estado actual:** Existe `UserGroupMembership` y `Role` pero sin jerarquía
de organizaciones.

**Qué implementar:**
- Tabla `access_organization` (`uid`, `tenant_id`, `name`, `parent_uid`, `metadata`)
- Tabla `access_organization_member` (`org_uid`, `user_uid`, `role_uid`)
- Soporte para heredar permisos del padre de la organización
- Claim `org_id` en el JWT de acceso si el scope `organizations` está concedido
- Endpoints CRUD: `/api/access/organizations`

---

#### 2.19 Permisos a nivel de recurso — ABAC

**Estado actual:** Solo RBAC (roles planos).

**Qué implementar:**
- Tabla `access_resource_policy` (`subject_uid`, `subject_type`, `resource_type`, `resource_uid`, `action`, `effect`)
- Motor de evaluación de políticas compatible con `allow`/`deny` explicit
- Endpoint de evaluación:
  ```
  POST /api/access/policies/evaluate
  { "subject": "user:uuid", "resource": "document:uuid-doc", "action": "edit" }
  → { "allowed": true }
  ```
- Integrable con el sistema de auditoría existente

---

#### 2.20 API Keys para usuarios (Personal Access Tokens)

**Estado actual:** `access_api_key_client` existe para clientes M2M, pero
no hay API keys por usuario.

**Qué implementar:**
- Tabla `access_user_api_key` (`uid`, `user_uid`, `tenant_id`, `name`, `key_hash`, `scopes`, `last_used_at`, `expires_at`)
- El token devuelto al crear es el único momento en que se muestra el valor
- Los API keys pueden usarse en lugar de Bearer tokens en endpoints protegidos
- Endpoints:
  ```
  GET    /api/me/api-keys
  POST   /api/me/api-keys
  DELETE /api/me/api-keys/{uid}
  ```

---

### Nivel 5 — Infraestructura reactiva y extensibilidad

---

#### 2.21 Sistema de Webhooks

**Relevancia:** Permite a las aplicaciones cliente reaccionar a eventos de
autenticación en tiempo real sin polling.

**Eventos a publicar:**
- `user.created`, `user.updated`, `user.deleted`
- `user.login.success`, `user.login.failed`, `user.logout`
- `user.password.changed`, `user.mfa.enabled`, `user.mfa.disabled`
- `token.issued`, `token.revoked`
- `session.created`, `session.revoked`

**Qué implementar:**
- Tabla `access_webhook_endpoint` (`uid`, `tenant_id`, `url`, `secret`, `events[]`, `enabled`)
- Dispatcher conectado al `EventDispatcher` de Symfony existente
- Entrega con reintentos exponenciales (tabla `access_webhook_delivery`)
- Firma de payload: `X-LughAuth-Signature: sha256=<HMAC>`
- Endpoints de gestión:
  ```
  GET/POST   /api/access/webhooks
  PUT/DELETE /api/access/webhooks/{uid}
  GET        /api/access/webhooks/{uid}/deliveries
  POST       /api/access/webhooks/{uid}/test
  ```

---

#### 2.22 Event Streaming — SSE / WebSocket para eventos en tiempo real

**Relevancia:** Para dashboards de administración y aplicaciones reactivas.

**Qué implementar:**
- Endpoint SSE: `GET /api/stream/events?topics=user.login,session.created`
- Autenticado con Bearer token con scope `stream:events`
- Backend: usar el `_output_queue_pending_events` como fuente
- Alternativa: integración con AMQP existente haciendo fan-out por tenant

---

#### 2.23 Feature Flags por Tenant

**Relevancia:** Permite habilitar/deshabilitar funcionalidades por tenant
sin despliegues. Útil para rollouts graduales y A/B testing.

**Qué implementar:**
- Tabla `access_feature_flag` (`uid`, `tenant_id`, `key`, `enabled`, `rollout_percentage`, `conditions_json`)
- SDK endpoint: `GET /api/flags?keys=webauthn,magic-link` (autenticado)
- Integración con `TenantConfig` existente para flags de sistema
- Admin UI: gestión de flags por tenant

---

#### 2.24 Audit Log API pública

**Estado actual:** El audit trail existe internamente (`_audit_action`, `_audit_change`)
pero no hay API para que las aplicaciones cliente lo consulten.

**Qué implementar:**
```
GET /api/access/audit-log?actor_uid=xxx&from=2026-01-01&event_type=user.login
```

- Filtros: `actor_uid`, `resource_type`, `resource_uid`, `event_type`, `from`, `to`
- Paginación cursor-based
- Respuesta con `actor`, `action`, `resource`, `changes`, `ip`, `timestamp`
- Retención configurable por tenant

---

### Nivel 6 — Cumplimiento y privacidad (GDPR / CCPA)

---

#### 2.25 Derecho de acceso y exportación de datos (GDPR Art. 15 / Art. 20)

**Qué implementar:**
- Endpoint `POST /api/me/data-export` → genera archivo ZIP con todos los datos del usuario
- Exportación incluye: perfil, sesiones, consentimientos, API keys, audit log personal
- Entrega asíncrona: usa `_long_tasks` existente + notificación por email al completar

---

#### 2.26 Derecho al olvido (GDPR Art. 17)

**Qué implementar:**
- Endpoint `DELETE /api/me/account` → solicitar eliminación
- Flujo: verificación por email → borrado en cascada (usuario, sesiones, tokens, consentimientos)
- Para datos requeridos por auditoría: anonimización en lugar de borrado
- Log de eliminaciones en tabla separada `access_deletion_request`

---

#### 2.27 Gestión de consentimientos (GDPR Art. 7)

**Estado actual:** `access_user_accepted_termns_of_use` rastrea aceptación de T&C
pero no gestiona consentimientos de procesamiento de datos.

**Qué implementar:**
- Tabla `access_user_consent` (`user_uid`, `purpose`, `granted`, `version`, `granted_at`, `revoked_at`)
- Propósitos: `marketing`, `analytics`, `third_party_sharing`, etc.
- Endpoints:
  ```
  GET  /api/me/consents
  PUT  /api/me/consents/{purpose}
  ```
- Integración en el registro de usuario (paso adicional en authorize flow)

---

### Nivel 7 — Developer Experience

---

#### 2.28 Admin Dashboard API

**Relevancia:** Endpoints de administración que permiten construir un panel
de control completo para gestionar tenants, usuarios y configuración.

**Endpoints a organizar bajo `/api/admin/`:**
```
GET  /api/admin/tenants/{uid}/stats           # Usuarios activos, sesiones, eventos/día
GET  /api/admin/tenants/{uid}/users           # Búsqueda avanzada de usuarios
POST /api/admin/tenants/{uid}/users/{uid}/impersonate  # Impersonación de usuario (con audit)
GET  /api/admin/system/health-extended        # Estado detallado de todos los subsistemas
GET  /api/admin/system/rate-limits            # Estadísticas de rate limiting por tenant
```

---

#### 2.29 SDKs cliente

**Qué crear:**
- **JavaScript/TypeScript SDK** (`@lughauth/client`):
  - `signIn()`, `signOut()`, `getSession()`, `getToken()`
  - Adaptadores: React hooks, Vue composables
  - Compatible con PKCE por defecto
- **PHP SDK** (`lughauth/php-sdk`):
  - Cliente para consumir el Management API desde backends PHP
  - Verificación de JWT con caching de JWKS
- **OpenAPI Client Generator**: publicar spec en `/management/apidoc` y generar
  SDKs automáticamente con `openapi-generator`

---

#### 2.30 Refactoring pendiente (deuda técnica)

Estas tareas son prerequisito para el trabajo de los niveles anteriores:

| Tarea | Impacto | Referencia |
|-------|---------|------------|
| Renombrar `autenticate` → `authenticate` en ~40 métodos/tests | Calidad / DX | `refactor_oidc.md` |
| Eliminar `AuthorizedChalleges` legacy y conversiones `->toLegacy()` | Mantenibilidad | `refactor_oidc.md` |
| Colapsar Gateway → Repository (eliminar capa redundante) | Complejidad | `refactor_oidc.md` |
| Corregir `UserMfa.storeSeed()` orden de parámetros | Corrección | `refactor_oidc.md` |
| Añadir `jti` a todos los JWT emitidos (prerequisito para revocación) | Seguridad | RFC 7519 |
| Implementar cleanup job para `_oauth_temporal_codes` expirados | Performance | DB bloat |
| Cleanup de `_oauth_session` (sesiones expiradas) | Performance | DB bloat |

---

## 3. Tabla de priorización

| # | Feature | RFC / Spec | Impacto | Esfuerzo | Prioridad |
|---|---------|-----------|---------|----------|-----------|
| 2.30 | Refactoring deuda técnica | — | Alto | Medio | P0 |
| 2.1 | PKCE | RFC 7636 | Crítico | Bajo | P0 |
| 2.3 | Scope Consent Tracking | OIDC Core | Alto | Medio | P0 |
| 2.4 | Token Revocation completa | RFC 7009 | Alto | Bajo | P0 |
| 2.2 | Token Introspection | RFC 7662 | Alto | Bajo | P1 |
| 2.6 | OpenAPI en endpoints OIDC | — | Medio | Bajo | P1 |
| 2.5 | Logout distribuido (back/front-channel) | OIDC Logout | Alto | Medio | P1 |
| 2.15 | Sesiones activas del usuario | — | Alto | Bajo | P1 |
| 2.16 | Perfil de usuario ampliado | OIDC Claims | Alto | Bajo | P1 |
| 2.9 | Client Credentials Grant M2M | RFC 6749 | Alto | Bajo | P1 |
| 2.17 | Sistema de Invitaciones | — | Alto | Medio | P2 |
| 2.20 | Personal Access Tokens | — | Alto | Medio | P2 |
| 2.21 | Webhooks | — | Alto | Medio | P2 |
| 2.11 | WebAuthn / Passkeys | FIDO2 / W3C | Alto | Alto | P2 |
| 2.12 | Magic Links | — | Medio | Bajo | P2 |
| 2.7 | PAR | RFC 9126 | Medio | Medio | P2 |
| 2.8 | Dynamic Client Registration | RFC 7591 | Medio | Medio | P3 |
| 2.13 | MFA por SMS/Email | — | Medio | Medio | P3 |
| 2.14 | Proveedores sociales adicionales | — | Medio | Medio | P3 |
| 2.18 | Organizaciones jerárquicas | — | Alto | Alto | P3 |
| 2.19 | ABAC (permisos por recurso) | — | Alto | Alto | P3 |
| 2.24 | Audit Log API pública | — | Medio | Bajo | P3 |
| 2.23 | Feature Flags | — | Medio | Medio | P3 |
| 2.25 | GDPR — Exportación de datos | GDPR Art.20 | Medio | Medio | P3 |
| 2.26 | GDPR — Derecho al olvido | GDPR Art.17 | Medio | Medio | P3 |
| 2.27 | Gestión de consentimientos | GDPR Art.7 | Medio | Medio | P3 |
| 2.10 | JAR — JWT Secured Auth Request | RFC 9101 | Bajo | Medio | P4 |
| 2.22 | Event Streaming SSE | — | Bajo | Alto | P4 |
| 2.28 | Admin Dashboard API | — | Medio | Alto | P4 |
| 2.29 | SDKs cliente | — | Alto | Alto | P4 |

---

## 4. Dependencias entre features

```
PKCE (2.1) ──────────────────────────────┐
                                          ├──→ PAR (2.7)
Refactoring (2.30) ──→ Introspection (2.2)│
                   └──→ Token Revocation (2.4) ──→ Logout distribuido (2.5)
                                                ──→ Sesiones activas (2.15)

Perfil ampliado (2.16) ──→ Exportación GDPR (2.25)
Invitaciones (2.17) ──────────────────────────────┐
                                                    ├──→ Organizaciones (2.18)
Personal Access Tokens (2.20) ────────────────────┘
                                                    └──→ ABAC (2.19)

Scope Consent (2.3) ──→ Gestión de consentimientos (2.27)
Webhooks (2.21) ──────→ Event Streaming (2.22)
```

---

## 5. Notas de arquitectura

### Sobre la adición de endpoints OIDC

Todos los nuevos endpoints OIDC deben:
1. Anunciarse en `GET /openid/{tenant}/.well-known/openid-configuration`
2. Respetar el aislamiento de tenant en la URL (`/openid/{tenant}/...`)
3. Seguir el patrón existente de `Oidc/{Feature}/Infrastructure/Driver/Rest/`
4. Incluir anotaciones `#[OA\...]` desde el primer commit
5. Tener cobertura de tests antes de merge

### Sobre la base de datos

- Todas las tablas nuevas deben incluirse como migraciones Phinx bajo `migrations/mysql/`
- Usar `ramsey/uuid` para PKs, consistente con el esquema actual
- Índices en columnas de búsqueda frecuente (`tenant_id`, `user_uid`, `created_at`)
- Las tablas de tokens/códigos temporales deben tener `expires_at` indexado para cleanup jobs

### Sobre la seguridad

- Cualquier endpoint nuevo que maneje credenciales debe estar bajo rate limiting
- Los tokens nuevos (magic links, invitaciones) deben usar el sistema existente
  de `_oauth_temporal_codes` o un equivalente con TTL corto
- Personal Access Tokens: almacenar solo el hash SHA-256, nunca el valor en claro
- Webhooks: firmar con HMAC-SHA256, documentar verificación en SDK

### Sobre la extensibilidad

- Los proveedores de MFA (SMS, email) y de login social deben implementar
  interfaces del dominio (`MfaProvider`, `OAuthProviderAdapter`) para mantener
  el bajo acoplamiento del hexágono actual
- Los SDKs deben generarse desde la spec OpenAPI para garantizar consistencia

---

*Documento generado el 2026-04-12. Revisión recomendada cada sprint.*
