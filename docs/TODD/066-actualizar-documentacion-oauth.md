# 066 — Actualizar documentación de arquitectura OAuth

- **Estado:** pendiente. **Prioridad:** baja.
- **Evidencia:** [docs/OAuth/README.md](../OAuth/README.md) y [00-overview.md](../OAuth/00-overview.md) hablan de `Features/Oidc`; las rutas actuales están en [OAuthPlugin](../../src/Bootstrap/Plugin/OAuthPlugin.php) bajo `Features/OAuth`.
- **Impacto:** nuevos desarrollos pueden seguir diagramas/rutas/acoplamientos desfasados.
- **Por hacer:** actualizar rutas, estructura, contratos realmente implementados y límites de `Access`; revisar afirmaciones de conformidad tras 030.
- **Aceptación:** enlaces y ejemplos de los documentos OAuth apuntan a código y rutas actuales.
- **Origen:** análisis OAuth §5.8.

## Detalle de implementación

- `docs/OAuth/README.md` y `docs/OAuth/00-overview.md`: sustituir rutas `Features/Oidc` por estructura real `src/Features/OAuth` y reconocer dependencias con `Access`.
- `docs/OAuth/03-authorization-code-flow.md`, `04-token-grants.md`, `05-device-and-par-flows.md` y `08-session-and-key-management.md`: contrastar cada flujo/claim/endpoint con `src/Bootstrap/Plugin/OAuthPlugin.php` y con los cambios aceptados de 015–065.
- `docs/OAuth/` (resto de guías): corregir referencias, ejemplos y afirmaciones de conformidad tras 030; no documentar extensiones opcionales como operativas hasta cerrar su tarea.
