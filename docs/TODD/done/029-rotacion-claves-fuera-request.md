# 029 — Sacar generación de claves de las peticiones

- **Estado:** hecho (EP-04). **Prioridad:** media.
- **Evidencia:** [JoseTokenSigner](../../src/Features/OAuth/TokenSecurity/Infrastructure/Driven/JoseTokenSigner.php) puede generar RSA-4096 durante sign/JWKS, hace `echo` de errores OpenSSL e invalida claves de caché globales sin tenant.
- **Impacto:** latencia/bloqueo en petición, fugas en respuesta y cache cruzada entre tenants.
- **Por hacer:** job/comando de rotación con ventana dual, aprovisionamiento seguro, fallo controlado sin salida HTTP y caché JWKS por tenant; evitar portar el fallo de clave privada en `kid` advertido para Phylax.
- **Aceptación:** firmar no genera RSA y cada tenant publica/invalida solo su JWKS.
- **Origen:** análisis OAuth §3.6/0.11 y `_2.md` precaución.

## Detalle de implementación

- `src/Features/OAuth/TokenSecurity/Infrastructure/Driven/JoseTokenSigner.php`: separar `checkNewNeeded/createKeyPair` de `sign/keysAsJwks`, eliminar el `echo` y usar claves de caché dependientes del tenant al invalidar JWKS.
- `src/Features/OAuth/TokenSecurity/Domain/Gateway/TokenStoreGateway.php` y `Infrastructure/Driven/TokenStoreSqlAdapter.php`: mantener transición de clave activa/futura y publicación durante la vida máxima de tokens emitidos.
- `src/Features/OAuth/Session/Application/OAuthCleanupScheduler.php` o **nuevo job/comando** de TokenSecurity: preparar y rotar claves por tenant antes de la petición; registrar fallo recuperable si no hay clave activa.
- `test/integration/Features/OAuth/DiscoveryTest.php`: comprobar aislamiento de JWKS entre tenants y verificación con clave antigua durante ventana.
