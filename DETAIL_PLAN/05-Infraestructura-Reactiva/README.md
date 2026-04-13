# 05 — Infraestructura Reactiva (P2/P3)

## Objetivo

Añadir capacidades de notificación en tiempo real y extensibilidad que permiten
a las aplicaciones cliente reaccionar a eventos de autenticación sin polling.

## Tareas

| Archivo | Descripción | Prioridad |
|---------|-------------|-----------|
| [05-01-Webhooks.md](05-01-Webhooks.md) | Sistema de webhooks para eventos de auth | P2 |
| [05-02-Event-Streaming.md](05-02-Event-Streaming.md) | SSE para eventos en tiempo real | P4 |
| [05-03-Feature-Flags.md](05-03-Feature-Flags.md) | Feature flags por tenant | P3 |
| [05-04-Audit-Log-API.md](05-04-Audit-Log-API.md) | API pública de audit log | P3 |

## Dependencias

```
05-01 Webhooks ──→ usa Symfony EventDispatcher existente
05-02 SSE ───────→ requiere 05-01 (o AMQP existente como fuente)
05-03 Flags ─────→ independiente (usa access_tenant_config como modelo)
05-04 Audit ─────→ usa _audit_action existente
```
