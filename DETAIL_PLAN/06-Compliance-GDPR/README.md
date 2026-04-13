# 06 — Compliance y Privacidad GDPR/CCPA (P3)

## Objetivo

Implementar los derechos de los usuarios establecidos por el RGPD (Reglamento General
de Protección de Datos) y regulaciones similares, permitiendo que LughAuth sea
usado en productos que operan en la UE u otras jurisdicciones con regulación estricta.

## Tareas

| Archivo | Descripción | GDPR Article | Prioridad |
|---------|-------------|-------------|-----------|
| [06-01-Exportacion-Datos.md](06-01-Exportacion-Datos.md) | Derecho de acceso y portabilidad | Art. 15, 20 | P3 |
| [06-02-Derecho-Al-Olvido.md](06-02-Derecho-Al-Olvido.md) | Derecho de supresión | Art. 17 | P3 |
| [06-03-Gestion-Consentimientos.md](06-03-Gestion-Consentimientos.md) | Gestión de consentimientos de procesamiento | Art. 7 | P3 |

## Dependencias

```
06-01 Exportación ──→ usa Notification/Outbox para enviar zip por email
                  ──→ usa _long_tasks existente para proceso async
06-02 Olvido ────────→ prerequisito: 06-01 para inventario de datos a borrar
06-03 Consentimientos → usa access_user_accepted_termns_of_use como modelo
```
