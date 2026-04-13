# 01 — Cumplimiento OIDC Obligatorio (P0/P1)

## Objetivo

Completar los requisitos que el servidor OIDC/OAuth 2.0 debe implementar para ser
plenamente conforme con las especificaciones de referencia y seguro para uso en
producción con cualquier tipo de cliente (SPA, app nativa, microservicio).

## Especificaciones cubiertas

| Spec | Título | RFC/Link |
|------|--------|---------|
| OIDC Core 1.0 | OpenID Connect Core | openid.net/specs/openid-connect-core-1_0.html |
| RFC 7636 | PKCE for Public Clients | tools.ietf.org/html/rfc7636 |
| RFC 7662 | Token Introspection | tools.ietf.org/html/rfc7662 |
| RFC 7009 | Token Revocation | tools.ietf.org/html/rfc7009 |
| OIDC Session | Session Management & Logout | openid.net/specs/openid-connect-session-1_0.html |
| OIDC Back-Channel Logout | Back-Channel Logout 1.0 | openid.net/specs/openid-connect-backchannel-1_0.html |

## Tareas

| Archivo | Descripción | Prioridad |
|---------|-------------|-----------|
| [01-01-PKCE.md](01-01-PKCE.md) | Proof Key for Code Exchange | P0 |
| [01-02-Token-Introspection.md](01-02-Token-Introspection.md) | Endpoint de introspección de tokens | P1 |
| [01-03-Scope-Consent-Tracking.md](01-03-Scope-Consent-Tracking.md) | Completar tracking de consentimiento de scopes | P0 |
| [01-04-Token-Revocation.md](01-04-Token-Revocation.md) | Revocación completa con cascada | P0 |
| [01-05-Logout-Distribuido.md](01-05-Logout-Distribuido.md) | Back-channel y front-channel logout | P1 |
| [01-06-OpenAPI-OIDC.md](01-06-OpenAPI-OIDC.md) | Anotaciones OpenAPI en controladores OIDC | P1 |

## Dependencias

```
01-01 PKCE ──────────────────────────────→ (prerequisito para 02-01 PAR)
01-04 Token Revocation ──────────────────→ (prerequisito para 01-05 Logout Distribuido)
                      └─────────────────→ (prerequisito para 04-01 Sesiones Activas)
01-02 Introspection → añadir jti a tokens (deuda técnica previa)
```

## Estado actual

- `src/Features/Oidc/Authentication/` — authorize + token flow existe, sin PKCE
- `src/Features/Oidc/Session/` — sesiones en DB, sin jti indexado
- `src/Features/Oidc/Scopes/` — stub que retorna `[]`, sin persistencia
- `POST /openid/{tenant}/revoke` — endpoint existe, revocación incompleta
- `GET /openid/{tenant}/logout` — limpia cookies locales, sin notificar RPs
