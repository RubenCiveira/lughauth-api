# 033 — Corregir hybrid y validar response_type por cliente

- **Estado:** hecho (EP-08; implicit retirado). **Prioridad:** media.
- **Evidencia:** [OidcResponseBuilder](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Services/OidcResponseBuilder.php) añade ID token a la query si existe code y no calcula `c_hash`; [ClientStoreAdapter](../../../src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php) no traslada `responseTypesJson` a la validación del flujo.
- **Impacto:** exposición de ID token en URL y respuesta no conforme; tipos no autorizados para el RP.
- **Por hacer:** seleccionar modo por response_type y modos permitidos; fragment para híbrido, `c_hash`, restringir al catálogo del cliente y revisar implicit (o retirarlo).
- **Aceptación:** `code id_token` usa fragment con `c_hash`; tipo prohibido devuelve error sin token.
- **Origen:** análisis OAuth §5.1/1.4 y `_2.md` respuestas.

## Detalle de implementación

- `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/Services/OidcResponseBuilder.php`: emitir code + ID/access en fragment (o modo permitido), construir una sola respuesta y calcular hashes correspondientes, sin tokens en query.
- `src/Features/OAuth/Authentication/Domain/OidcFlowContext.php` y `src/Features/OAuth/Authentication/Infrastructure/Driver/Html/AuthorizeHtml.php`: normalizar `response_type`, validar combinación permitida antes de mostrar login y rechazar la no registrada para el cliente.
- `src/Features/OAuth/Client/Domain/ClientData.php` y `Infrastructure/Driven/ClientStoreAdapter.php`: trasladar `responseTypesJson` de TrustedClient al snapshot del cliente.
- `test/integration/Features/OAuth/`: probar `code id_token` con RP registrado, `c_hash`, fragment correcto y tipo no autorizado.
