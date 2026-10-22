# 046 — Aislar parámetros PAR y definir exigencia por cliente

- **Estado:** hecho (EP-07). **Prioridad:** alta.
- **Evidencia:** [AuthorizeHtml::buildContext](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php) fusiona `array_merge($parRequest->params, $query)` dando precedencia a query; [OpenIdConfiguration](../../../src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php) anuncia PAR obligatorio aunque no lo exige.
- **Impacto:** una query puede alterar una solicitud push autenticada.
- **Por hacer:** validar solo `request_uri` y `client_id` externo, exigir igualdad de cliente y usar exclusivamente parámetros PAR; añadir opción por cliente/tenant y sincronizar discovery.
- **Aceptación:** query conflictiva no cambia scopes, redirect, state ni challenge; cliente marcado obligatorio no puede usar authorize directo.
- **Origen:** análisis OAuth §3.6/3.3 y `_2.md` PAR.

## Detalle de implementación

- `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php`: al resolver `request_uri`, contrastar `client_id` exterior con el `ParRequest->clientId` y descartar el resto de query, en vez de `array_merge($parRequest->params, $query)`.
- `src/Features/OAuth/Par/Application/Usecase/PushAuthorizationRequest/PushAuthorizationUsecase.php` y `ResolveParRequest/ResolveParRequestUsecase.php`: asegurar pertenencia a cliente/tenant y consumo único atómico mediante `ParRequestSqlAdapter.php`.
- `src/Features/OAuth/Client/Domain/ClientData.php` y `Infrastructure/Driven/ClientStoreAdapter.php`: transportar `requirePar` por cliente/tenant; `src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php` anuncia obligatoriedad solo si aplica a todos los clientes relevantes.
- `test/integration/Features/OAuth/ParTest.php`: intentar sobreescribir redirect/scope/PKCE/client_id y repetir `request_uri`.
