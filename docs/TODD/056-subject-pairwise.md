# 056 — Evaluar identificadores subject pairwise

- **Estado:** no implementado; opcional. **Prioridad:** baja.
- **Evidencia:** [OpenIdConfiguration](../../src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php) anuncia `pairwise`, pero [TokenController](../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php) usa el mismo `$auth->id` como `sub` para clientes.
- **Impacto:** RP confían en aislamiento entre sectores que no existe.
- **Por hacer:** retirar anuncio o introducir identificador estable por usuario/sector (`sector_identifier_uri`) y mantener consistencia UserInfo/ID/access/logout.
- **Aceptación:** clientes de sectores distintos reciben `sub` distinto para el mismo usuario.
- **Origen:** análisis OAuth §4/fase 4.

## Detalle de implementación (solo si se aprueba)

- `src/Features/OAuth/Client/Domain/ClientData.php` y `src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php`: transportar `subject_type` y `sector_identifier_uri` validados; ajustar DCR en `DynamicClientRequest.php`/`DynamicClientAdapter.php` si pueden registrarse.
- `src/Features/OAuth/Authentication/Application/` (**nuevo servicio de subject pairwise**): derivar/persistir identificador estable por usuario+sector con secreto seguro y política de rotación.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php`, `UserInfoController.php` y `src/Features/OAuth/Authentication/Domain/LogoutToken.php`: usar el mismo `sub` sectorial para RP en ID/access/UserInfo/logout.
- `src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php` y `test/integration/Features/OAuth/`: anunciar `pairwise` solo tras probar estabilidad y separación entre sectores.
