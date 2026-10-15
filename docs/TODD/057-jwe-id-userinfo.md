# 057 — Evaluar cifrado de ID token y UserInfo

- **Estado:** no implementado; opcional. **Prioridad:** baja.
- **Evidencia:** [OpenIdConfiguration](../../src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php) anuncia algoritmos JWE; [JoseTokenSigner](../../src/Features/OAuth/TokenSecurity/Infrastructure/Driven/JoseTokenSigner.php) solo firma JWS y [UserInfoController](../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/UserInfoController.php) devuelve JSON.
- **Impacto:** RP esperan contenido cifrado que no se produce.
- **Por hacer:** retirar anuncios hasta decidir requisitos; si se adopta, negociar claves/algoritmos por cliente, cifrar tras firma y probar descifrado por RP.
- **Aceptación:** se anuncia solo la combinación de algoritmos realmente usable.
- **Origen:** análisis OAuth §4/fase 4.

## Detalle de implementación (solo si se aprueba)

- `src/Features/OAuth/TokenSecurity/Domain/Gateway/TokenSigner.php` y `Infrastructure/Driven/JoseTokenSigner.php`: agregar operación de cifrado JWE del JWT firmado usando clave pública del RP, con algoritmos permitidos.
- `src/Features/OAuth/Client/Domain/ClientData.php` y `Infrastructure/Driven/ClientStoreAdapter.php`: transportar claves y preferencias `id_token_encrypted_response_*`/`userinfo_encrypted_response_*` del cliente; validar también alta DCR si se ofrece.
- `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php`, `Infrastructure/Driver/Html/Services/OidcResponseBuilder.php` y `Infrastructure/Driver/Rest/UserInfoController.php`: firmar y después cifrar las respuestas solicitadas.
- `src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php` y `test/integration/Features/OAuth/`: retirar anuncios JWE hasta que un RP pueda descifrar tokens con clave/algoritmo negociados.
