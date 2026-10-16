# 050 — Valorar ES256/EdDSA y ventana de rotación

- **Estado:** no implementado; decisión de interoperabilidad. **Prioridad:** baja.
- **Evidencia:** [JoseTokenSigner](../../src/Features/OAuth/TokenSecurity/Infrastructure/Driven/JoseTokenSigner.php) solo firma RS256; publica varias claves futuras pero JWT no lleva `kid`; [OpenIdConfiguration](../../src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php) anuncia RS256.
- **Impacto:** limitación de algoritmos y resolución ambigua de claves durante rotación.
- **Por hacer:** completar antes 025/029; negociar algoritmos por cliente, generar/retirar claves con superposición suficiente y publicar exactamente lo firmado; implementar ES256/EdDSA solo si clientes lo necesitan.
- **Aceptación:** tokens antiguos verifican durante ventana, nuevos referencian clave/algoritmo anunciado.
- **Origen:** análisis OAuth §5.1/3.7.

## Detalle de implementación (solo si hay clientes que lo requieran)

- `src/Features/OAuth/TokenSecurity/Infrastructure/Driven/JoseTokenSigner.php`: añadir ES256/EdDSA a creación, firma y verificación con `kid`/algoritmo correspondiente, preservando claves antiguas hasta expirar los JWT emitidos.
- `src/Features/OAuth/TokenSecurity/Domain/KeyConfig.php`, `Domain/Gateway/TokenStoreGateway.php` y `Infrastructure/Driven/TokenStoreSqlAdapter.php`: configurar ventana dual y seleccionar clave activa por tenant/algoritmo; adaptar almacenamiento solo con nueva migración si el esquema lo exige.
- `src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php` y `src/Features/OAuth/Client/Domain/ClientData.php`: anunciar/negociar solo algoritmos implementados y permitidos al RP.
- `test/integration/Features/OAuth/DiscoveryTest.php`: validar tokens antes/durante/después de rotación contra JWKS publicado.
