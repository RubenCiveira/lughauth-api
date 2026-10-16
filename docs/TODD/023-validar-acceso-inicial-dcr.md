# 023 — Validar de verdad el initial access token de DCR

- **Estado:** pendiente. **Prioridad:** alta.
- **Evidencia:** [RegisterClientUsecase::requireInitialAccessToken](../../src/Features/OAuth/Client/Application/Usecase/RegisterClient/RegisterClientUsecase.php) solo exige texto no vacío cuando la política lo requiere.
- **Impacto:** cualquier cadena da acceso al registro si el tenant usa `token-required`.
- **Por hacer:** emitir/verificar token o credencial ligada a tenant y política, expiración y permisos; almacenar hash si procede y cubrir revocación/replay.
- **Aceptación:** cadena arbitraria, caducada o de otro tenant no permite registrar.
- **Origen:** análisis OAuth §3.6/0.7 y `_2.md` DCR.

## Detalle de implementación

- `src/Features/OAuth/Client/Application/Usecase/RegisterClient/RegisterClientUsecase.php`: sustituir `requireInitialAccessToken` por verificación de una credencial real y política `token-required` vinculada al tenant.
- `src/Features/OAuth/Client/Domain/Gateway/` (**nuevo puerto**) y `src/Features/OAuth/Client/Infrastructure/Driven/` (**nuevo adaptador**): emitir/verificar hash, expiración, alcance, tenant y, si corresponde, consumo único del initial access token; reutilizar infraestructura existente solo tras comprobar sus garantías.
- `src/Features/OAuth/Client/Infrastructure/Driver/Rest/DynamicClientController.php`: conservar `extractBearerToken()` (ya lee la cabecera), trasladar al caso de uso el token y mapear una validación fallida a error DCR/OAuth; no confundir initial access token con registration access token.
- `test/integration/Features/OAuth/`: añadir registro con credencial válida, vacía, falsa, caducada y de otro tenant.
