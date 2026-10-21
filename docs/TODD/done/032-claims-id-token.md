# 032 — Completar y separar claims de ID token

- **Estado:** hecho (EP-05). **Prioridad:** alta.
- **Evidencia:** [TokenController](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php) mezcla `$detail` de access con ID, `aud` incluye audiencias adicionales y no aporta `auth_time`/`acr`/`amr`/`sid`; [OidcResponseBuilder](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Services/OidcResponseBuilder.php) solo añade `at_hash` si hay token implícito.
- **Impacto:** clientes no pueden evaluar reautenticación, MFA ni logout por sesión.
- **Por hacer:** mapear claims por tipo de token; ID `aud` = RP, `auth_time` cuando aplique, `acr`/`amr` según evidencia y `sid`; `at_hash`/`c_hash` solo en respuestas que lo exijan.
- **Aceptación:** tests de claims para code/hybrid y logout; no se inventan valores de MFA.
- **Origen:** análisis OAuth §5.1/1.3 y `_2.md` claims (discrepa sobre `sid`/`amr` en Phylax: verificar, no copiar).

## Detalle de implementación

- `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php`: separar construcción de claims ID y access; ID `aud` solo RP y `auth_time` de la sesión, `acr/amr` demostrables, `sid` estable y `at_hash` cuando corresponda.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Services/OidcResponseBuilder.php`: añadir `c_hash` si code acompaña al ID token, y `at_hash` cuando el access se devuelve en el front-channel; usar la misma lógica de mapeo que el token endpoint.
- `src/Features/OAuth/Session/Domain/SessionInfo.php` y `Infrastructure/Driven/SessionStoreSqlAdapter.php`: exponer hora, método/MFA y `sid` persistidos; no inferir `auth_time` del instante de emisión.
- `src/Features/OAuth/Authentication/Application/` (**nuevo ClaimsMapper/TokenFactory**, ver 038) y `test/integration/Features/OAuth/TokenEndpointTest.php`: centralizar reglas y afirmar claims por flujo.
