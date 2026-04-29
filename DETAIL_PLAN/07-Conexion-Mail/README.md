# 07 — Developer Experience (P0/P4)

## Objetivo

Mejorar la calidad del código base (deuda técnica crítica) y añadir herramientas
para los desarrolladores que integran LughAuth en sus aplicaciones.

## Tareas

| Archivo | Descripción | Prioridad |
|---------|-------------|-----------|
| [07-01-Refactoring-Deuda-Tecnica.md](07-01-Refactoring-Deuda-Tecnica.md) | Typos, legacy classes, capas redundantes | P0 |
| [07-02-Admin-Dashboard-API.md](07-02-Admin-Dashboard-API.md) | APIs para panel de administración | P4 |
| [07-03-SDKs.md](07-03-SDKs.md) | SDKs cliente JavaScript/TypeScript y PHP | P4 |

## Dependencias

```
07-01 Refactoring ──→ prerequisito para todo el trabajo nuevo (P0)
07-02 Admin API ────→ depende de 04-01 (sesiones), 05-04 (audit log)
07-03 SDKs ─────────→ depende de 01-06 (OpenAPI completo)
```
