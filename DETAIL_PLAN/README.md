# DETAIL PLAN — LughAuth Upgrade

> Documentación de implementación detallada para el plan de desarrollo de LughAuth.
> Ver [UPGRADE_PLAN.md](../UPGRADE_PLAN.md) para el resumen ejecutivo y tabla de priorización.

---

## Estructura

```
DETAIL_PLAN/
├── 01-Cumplimiento-OIDC-Obligatorio/   P0/P1 — Conformidad con specs OIDC/OAuth 2.0
├── 02-Extensiones-OAuth2/               P1/P2 — Extensiones del protocolo
├── 03-Autenticacion-Avanzada/           P2/P3 — WebAuthn, Magic Links, MFA adicional
├── 04-BaaS-Core/                        P1/P2 — APIs de gestión de identidad
├── 05-Infraestructura-Reactiva/         P2/P3 — Webhooks, SSE, Feature Flags
├── 06-Compliance-GDPR/                  P3    — Exportación, olvido, consentimientos
└── 07-Developer-Experience/             P0/P4 — Refactoring, Admin API, SDKs
```

---

## Índice completo de tareas

### 01 — Cumplimiento OIDC Obligatorio

| ID | Tarea | Prioridad | Esfuerzo |
|----|-------|-----------|----------|
| [01-01](01-Cumplimiento-OIDC-Obligatorio/01-01-PKCE.md) | PKCE — RFC 7636 | P0 | Medio |
| [01-02](01-Cumplimiento-OIDC-Obligatorio/01-02-Token-Introspection.md) | Token Introspection — RFC 7662 | P1 | Bajo-Medio |
| [01-03](01-Cumplimiento-OIDC-Obligatorio/01-03-Scope-Consent-Tracking.md) | Scope Consent Tracking | P0 | Medio |
| [01-04](01-Cumplimiento-OIDC-Obligatorio/01-04-Token-Revocation.md) | Token Revocation completa — RFC 7009 | P0 | Bajo-Medio |
| [01-05](01-Cumplimiento-OIDC-Obligatorio/01-05-Logout-Distribuido.md) | Logout distribuido (back/front-channel) | P1 | Medio |
| [01-06](01-Cumplimiento-OIDC-Obligatorio/01-06-OpenAPI-OIDC.md) | Anotaciones OpenAPI en controladores OIDC | P1 | Bajo |

### 02 — Extensiones OAuth 2.0

| ID | Tarea | Prioridad | Esfuerzo |
|----|-------|-----------|----------|
| [02-01](02-Extensiones-OAuth2/02-01-PAR.md) | PAR — Pushed Authorization Requests — RFC 9126 | P2 | Medio |
| [02-02](02-Extensiones-OAuth2/02-02-Dynamic-Client-Registration.md) | Dynamic Client Registration — RFC 7591/7592 | P2 | Medio |
| [02-03](02-Extensiones-OAuth2/02-03-Client-Credentials-M2M.md) | Client Credentials Grant M2M completo | P1 | Bajo |
| [02-04](02-Extensiones-OAuth2/02-04-JAR.md) | JAR — JWT Secured Auth Requests — RFC 9101 | P4 | Medio |

### 03 — Autenticación Avanzada

| ID | Tarea | Prioridad | Esfuerzo |
|----|-------|-----------|----------|
| [03-01](03-Autenticacion-Avanzada/03-01-WebAuthn-Passkeys.md) | WebAuthn / Passkeys — FIDO2 | P2 | Alto |
| [03-02](03-Autenticacion-Avanzada/03-02-Magic-Links.md) | Magic Links / Passwordless Email | P2 | Bajo-Medio |
| [03-03](03-Autenticacion-Avanzada/03-03-MFA-SMS-Email.md) | MFA por SMS y Email OTP | P3 | Medio |
| [03-04](03-Autenticacion-Avanzada/03-04-Social-Login-Adicionales.md) | Proveedores sociales: GitHub, Microsoft, Apple, SAML | P3 | Medio |

### 04 — BaaS Core

| ID | Tarea | Prioridad | Esfuerzo |
|----|-------|-----------|----------|
| [04-01](04-BaaS-Core/04-01-Sesiones-Activas.md) | API de sesiones activas del usuario | P1 | Bajo-Medio |
| [04-02](04-BaaS-Core/04-02-Perfil-Usuario.md) | Perfil extendido con claims OIDC estándar | P1 | Bajo-Medio |
| [04-03](04-BaaS-Core/04-03-Invitaciones.md) | Sistema de invitaciones a tenant | P2 | Medio |
| [04-04](04-BaaS-Core/04-04-Organizaciones.md) | Organizaciones y grupos jerárquicos | P3 | Alto |
| [04-05](04-BaaS-Core/04-05-ABAC.md) | Permisos a nivel de recurso (ABAC) | P3 | Alto |
| [04-06](04-BaaS-Core/04-06-Personal-Access-Tokens.md) | Personal Access Tokens (PAT) | P2 | Medio |

### 05 — Infraestructura Reactiva

| ID | Tarea | Prioridad | Esfuerzo |
|----|-------|-----------|----------|
| [05-01](05-Infraestructura-Reactiva/05-01-Webhooks.md) | Sistema de Webhooks | P2 | Medio-Alto |
| [05-02](05-Infraestructura-Reactiva/05-02-Event-Streaming.md) | SSE / Event Streaming | P4 | Alto |
| [05-03](05-Infraestructura-Reactiva/05-03-Feature-Flags.md) | Feature Flags por tenant | P3 | Medio |
| [05-04](05-Infraestructura-Reactiva/05-04-Audit-Log-API.md) | Audit Log API pública | P3 | Bajo-Medio |

### 06 — Compliance GDPR

| ID | Tarea | Prioridad | Esfuerzo |
|----|-------|-----------|----------|
| [06-01](06-Compliance-GDPR/06-01-Exportacion-Datos.md) | Exportación de datos (Art. 15/20) | P3 | Medio |
| [06-02](06-Compliance-GDPR/06-02-Derecho-Al-Olvido.md) | Derecho al olvido (Art. 17) | P3 | Medio |
| [06-03](06-Compliance-GDPR/06-03-Gestion-Consentimientos.md) | Gestión de consentimientos (Art. 7) | P3 | Medio |

### 07 — Developer Experience

| ID | Tarea | Prioridad | Esfuerzo |
|----|-------|-----------|----------|
| [07-01](07-Developer-Experience/07-01-Refactoring-Deuda-Tecnica.md) | Refactoring y deuda técnica | P0 | Medio |
| [07-02](07-Developer-Experience/07-02-Admin-Dashboard-API.md) | Admin Dashboard API | P4 | Medio-Alto |
| [07-03](07-Developer-Experience/07-03-SDKs.md) | SDKs cliente (JS/TS y PHP) | P4 | Alto |

---

## Orden de implementación recomendado

### Sprint 1 — Fundamentos (P0)
1. [07-01](07-Developer-Experience/07-01-Refactoring-Deuda-Tecnica.md) Refactoring deuda técnica
2. [01-01](01-Cumplimiento-OIDC-Obligatorio/01-01-PKCE.md) PKCE
3. [01-03](01-Cumplimiento-OIDC-Obligatorio/01-03-Scope-Consent-Tracking.md) Scope Consent Tracking
4. [01-04](01-Cumplimiento-OIDC-Obligatorio/01-04-Token-Revocation.md) Token Revocation

### Sprint 2 — OIDC Completo (P1)
5. [01-02](01-Cumplimiento-OIDC-Obligatorio/01-02-Token-Introspection.md) Token Introspection
6. [01-05](01-Cumplimiento-OIDC-Obligatorio/01-05-Logout-Distribuido.md) Logout Distribuido
7. [01-06](01-Cumplimiento-OIDC-Obligatorio/01-06-OpenAPI-OIDC.md) OpenAPI OIDC
8. [02-03](02-Extensiones-OAuth2/02-03-Client-Credentials-M2M.md) Client Credentials M2M
9. [04-01](04-BaaS-Core/04-01-Sesiones-Activas.md) Sesiones Activas
10. [04-02](04-BaaS-Core/04-02-Perfil-Usuario.md) Perfil Usuario

### Sprint 3 — BaaS Features (P2)
11. [03-02](03-Autenticacion-Avanzada/03-02-Magic-Links.md) Magic Links
12. [04-03](04-BaaS-Core/04-03-Invitaciones.md) Invitaciones
13. [04-06](04-BaaS-Core/04-06-Personal-Access-Tokens.md) Personal Access Tokens
14. [05-01](05-Infraestructura-Reactiva/05-01-Webhooks.md) Webhooks
15. [02-01](02-Extensiones-OAuth2/02-01-PAR.md) PAR
16. [02-02](02-Extensiones-OAuth2/02-02-Dynamic-Client-Registration.md) Dynamic Client Registration
17. [03-01](03-Autenticacion-Avanzada/03-01-WebAuthn-Passkeys.md) WebAuthn

### Sprint 4 — Extensiones (P3)
18. [04-04](04-BaaS-Core/04-04-Organizaciones.md) Organizaciones
19. [04-05](04-BaaS-Core/04-05-ABAC.md) ABAC
20. [03-03](03-Autenticacion-Avanzada/03-03-MFA-SMS-Email.md) MFA SMS/Email
21. [03-04](03-Autenticacion-Avanzada/03-04-Social-Login-Adicionales.md) Social Login adicionales
22. [05-03](05-Infraestructura-Reactiva/05-03-Feature-Flags.md) Feature Flags
23. [05-04](05-Infraestructura-Reactiva/05-04-Audit-Log-API.md) Audit Log API
24. [06-01](06-Compliance-GDPR/06-01-Exportacion-Datos.md) Exportación GDPR
25. [06-02](06-Compliance-GDPR/06-02-Derecho-Al-Olvido.md) Derecho al Olvido
26. [06-03](06-Compliance-GDPR/06-03-Gestion-Consentimientos.md) Gestión Consentimientos

### Sprint 5 — Pulido (P4)
27. [02-04](02-Extensiones-OAuth2/02-04-JAR.md) JAR
28. [05-02](05-Infraestructura-Reactiva/05-02-Event-Streaming.md) SSE
29. [07-02](07-Developer-Experience/07-02-Admin-Dashboard-API.md) Admin Dashboard API
30. [07-03](07-Developer-Experience/07-03-SDKs.md) SDKs

---

## Convenciones de los documentos de tarea

Cada documento incluye:
- **Contexto** — por qué es necesario y estado actual
- **Qué implementar** — spec del endpoint / comportamiento esperado
- **Dónde y cómo hacer los cambios** — archivos específicos con rutas y código de ejemplo
- **Tests a incluir** — casos de test unitarios e integración

### Rutas de referencia frecuentes

| Área | Ruta |
|------|------|
| Features OIDC | `src/Features/Oidc/` |
| Features Access | `src/Features/Access/` |
| Bootstrap/Middleware | `src/Bootstrap/Middleware/` |
| Tests OIDC | `test/Features/Oidc/` |
| Tests Access | `test/Features/Access/` |
| Migraciones | `migrations/mysql/schema/` |
| Discovery endpoint | `src/Features/Oidc/Common/Infrastructure/Driver/Rest/OpenIdConfigurationController.php` |
| Token signing | `src/Features/Oidc/Key/Infrastructure/Driven/JoseTokenSigner.php` |
| Token granter | `src/Features/Oidc/Authentication/Application/TokenGranter/TokenGranterMediator.php` |
| Session store | `src/Features/Oidc/Session/Infrastructure/Driven/SessionStoreSqlAdapter.php` |
| Plugin DI patrón | `src/Features/Access/TrustedClient/Infrastructure/Driver/TrustedClientPlugin.php` |
