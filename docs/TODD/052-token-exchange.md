# 052 — Decidir token exchange entre servicios

- **Estado:** ausente; extensión opcional. **Prioridad:** baja. **Relacionado:** 043.
- **Evidencia:** [TokenController](../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php) no implementa RFC 8693; la federación `DelegateLogin` no es exchange de tokens.
- **Impacto:** no hay delegación/impersonación estándar entre servicios.
- **Por hacer:** definir actor/subject, permisos, audiencia y límites de impersonación; implementar grant, validación y auditoría solo tras aprobar caso de uso.
- **Aceptación:** token intercambiado indica actor y alcance acotado; sin autorización no se emite.
- **Origen:** análisis OAuth §5.2/3.9.

## Detalle de implementación (solo si se aprueba)

- `src/Features/OAuth/Authentication/Application/TokenGranter/` (**nuevo** `ResolverForTokenExchange.php`): implementar validación RFC 8693 de `subject_token`, `actor_token`, tipo solicitado, alcance y audiencias autorizadas.
- `src/Features/OAuth/Authentication/Application/TokenGranter/TokenGranterMediator.php` y `Infrastructure/Driver/Rest/TokenController.php`: enrutar grant y devolver errores OAuth y campos de respuesta del exchange.
- `src/Features/OAuth/Client/Domain/ClientData.php`, `src/Features/OAuth/TokenSecurity/Domain/Gateway/TokenSigner.php` y `src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php`: permitir grant por cliente, validar tipo de entrada y emitir claims `act`/audiencia acotados; anunciar solo si funciona.
- `test/integration/Features/OAuth/TokenEndpointTest.php`: negar impersonación, tenant cruzado, actor no autorizado y escalada de scope.
