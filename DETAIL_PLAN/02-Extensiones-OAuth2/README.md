# 02 — Extensiones OAuth 2.0 de Alto Valor (P2)

## Objetivo

Implementar extensiones del protocolo OAuth 2.0 que mejoran significativamente
la seguridad y la experiencia de integración para clientes avanzados.

## Tareas

| Archivo | Descripción | Prioridad |
|---------|-------------|-----------|
| [02-01-PAR.md](02-01-PAR.md) | Pushed Authorization Requests (RFC 9126) | P2 |
| [02-02-Dynamic-Client-Registration.md](02-02-Dynamic-Client-Registration.md) | Registro dinámico de clientes (RFC 7591/7592) | P2 |
| [02-03-Client-Credentials-M2M.md](02-03-Client-Credentials-M2M.md) | Client Credentials Grant completo para M2M | P1 |
| [02-04-JAR.md](02-04-JAR.md) | JWT Secured Authorization Requests (RFC 9101) | P4 |

## Dependencias

```
01-01 PKCE ──────────────────────→ 02-01 PAR (PAR incluye PKCE en la request empujada)
02-01 PAR ───────────────────────→ (prerreq. recomendado para 02-04 JAR)
02-02 Dynamic Registration ──────→ (independiente)
02-03 Client Credentials ────────→ (independiente, no depende de los anteriores)
```
