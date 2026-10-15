# 059 — Evaluar resource indicators

- **Estado:** ausente; opcional. **Prioridad:** baja.
- **Evidencia:** [TokenController](../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php) acepta `audience` libre; [OidcFlowContext](../../src/Features/OAuth/Authentication/Domain/OidcFlowContext.php) también acepta audience, sin parámetro `resource` de RFC 8707.
- **Impacto:** falta un mecanismo estándar de selección restringida de resource server.
- **Por hacer:** tras 018, decidir catálogo de recursos autorizados por cliente e implementar `resource` en authorize/token con reducción de audiencias, no ampliación en refresh.
- **Aceptación:** solo se emite token para recurso registrado y permitido.
- **Origen:** análisis OAuth §5.4/fase 4.

## Detalle de implementación (solo si se aprueba)

- `src/Features/OAuth/Authentication/Domain/OidcFlowContext.php` y `AuthenticationRequest.php`: leer `resource` RFC 8707, normalizar URI y comprobar pertenencia al catálogo del cliente.
- `src/Features/OAuth/Client/Domain/ClientData.php` y `Infrastructure/Driven/ClientStoreAdapter.php`: mapear resource servers permitidos, coordinado con 018.
- `src/Features/OAuth/Session/Domain/TemporalAuthCode.php` y `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php`: conservar recurso desde authorize/code y restringir `aud` de access/refresh sin permitir otro resource.
- `test/integration/Features/OAuth/TokenEndpointTest.php`: resource permitido, desconocido y ampliación en refresh.
