# 04 — BaaS Core (P1/P2)

## Objetivo

Implementar las APIs de gestión de identidad que permiten a los desarrolladores
construir aplicaciones completas sobre LughAuth como Backend-as-a-Service,
sin tener que construir estas funcionalidades desde cero.

## Tareas

| Archivo | Descripción | Prioridad |
|---------|-------------|-----------|
| [04-01-Sesiones-Activas.md](04-01-Sesiones-Activas.md) | API para gestionar sesiones activas del usuario | P1 |
| [04-02-Perfil-Usuario.md](04-02-Perfil-Usuario.md) | Perfil extendido con claims OIDC estándar | P1 |
| [04-03-Invitaciones.md](04-03-Invitaciones.md) | Sistema de invitaciones a tenant | P2 |
| [04-04-Organizaciones.md](04-04-Organizaciones.md) | Organizaciones y grupos jerárquicos | P3 |
| [04-05-ABAC.md](04-05-ABAC.md) | Permisos a nivel de recurso (ABAC) | P3 |
| [04-06-Personal-Access-Tokens.md](04-06-Personal-Access-Tokens.md) | API Keys por usuario (PAT) | P2 |

## Dependencias

```
04-01 Sesiones ──→ requiere 01-04 Token Revocation (revoked_at en sesiones)
04-02 Perfil ────→ independiente (amplía access_user existente)
04-03 Invitaciones → requiere Notification/Outbox (ya existe)
04-04 Organizaciones → independiente (nueva tabla)
04-05 ABAC ──────→ puede construirse sobre access_role existente
04-06 PAT ───────→ independiente
```
