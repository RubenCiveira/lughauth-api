# 041 — Evaluar API administrativa de sesiones de usuario

- **Estado:** no implementado; decisión de producto. **Prioridad:** baja.
- **Evidencia:** [OAuthPlugin](../../src/Bootstrap/Plugin/OAuthPlugin.php) ofrece autoservicio `/api/me/sessions`, pero no ruta de administración de sesiones de otro usuario.
- **Impacto:** soporte/administración no puede listar ni revocar sesiones ajenas por API específica.
- **Por hacer:** definir autorización, tenant y auditoría para listar/revocar sesiones por uid, reutilizando [SessionStoreGateway](../../src/Features/OAuth/Session/Domain/Gateway/SessionStoreGateway.php); implementar solo si hay requerimiento.
- **Aceptación:** decisión registrada; si se añade, administrador autorizado opera solo en su tenant y usuario normal recibe denegación.
- **Origen:** análisis OAuth §5.5/2.3 y `_2.md` administración.

## Detalle de implementación (solo si se aprueba)

- `src/Features/OAuth/Profile/Infrastructure/Driver/Rest/` (**nuevo** `AdminUserSessionController.php`): endpoints para listar y revocar sesiones de `{userUid}` bajo una ruta administrativa por tenant, con OpenAPI y denegación explícita.
- `src/Features/OAuth/Profile/Domain/Gateway/SessionsGateway.php` y `Infrastructure/Driven/SessionsAdapter.php`: añadir consultas/borrados restringidos por usuario **y tenant/issuer**, sin reutilizar `revoke(sessionId)` a ciegas.
- `src/Bootstrap/Plugin/OAuthPlugin.php` y `config/guard.yaml`: registrar rutas y autorización de administrador; complementar con comprobación del caso de uso, no solo del guard HTTP.
- `test/integration/Features/OAuth/`: probar administrador autorizado, usuario ordinario, sesión ajena y tenant cruzado.
