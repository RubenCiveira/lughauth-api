# 040 — Evaluar e implementar grant MFA para clientes nativos

- **Estado:** descartada (22-10-2026, EP-10); no se implementa. **Prioridad:** baja. **Dependencia:** 024.
- **Evidencia:** [TokenController](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php) soporta code, refresh, device, password y client_credentials; no hay estrategia `mfa_token` + `mfa_code`.
- **Impacto:** clientes nativos que usen ROPC no disponen de continuación MFA, pero OAuth 2.1 desaconseja ROPC.
- **Por hacer:** decidir si el caso de uso justifica el grant frente a code+PKCE/device; si sí, challenge de vida corta ligado a cliente/usuario, OTP de un solo uso, límites de intento y anuncio condicional.
- **Aceptación:** decisión documentada; si se implementa, no se emiten tokens finales antes de completar MFA.
- **Origen:** análisis OAuth §5.2/2.2.

## Detalle de implementación (solo si se aprueba)

- `src/Features/OAuth/Authentication/Application/TokenGranter/` (**nuevo** `ResolverForMfa.php`): implementar `TokenGranterStrategy` para un challenge `mfa_token` ligado a cliente, tenant y usuario; verificar OTP/recovery y consumirlo una vez.
- `src/Features/OAuth/Authentication/Application/TokenGranter/TokenGranterMediator.php`: registrar la estrategia únicamente cuando el grant esté habilitado; `src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php` debe emitir tokens solo tras MFA completado.
- `src/Features/OAuth/Client/Domain/ClientData.php` y `src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php`: transportar grant autorizado; `src/Features/OAuth/Oidc/Domain/OpenIdConfiguration.php` lo anuncia solo si funciona.
- `test/integration/Features/OAuth/TokenEndpointTest.php`: challenge inválido, de otro cliente, agotado o reutilizado no produce access token.

## Decisión

Descartada el 22-10-2026. No hay ningún cliente que no pueda usar el navegador del sistema con code + PKCE, ni un dispositivo sin navegador que no cubra el device flow. Ambos pasan por las pantallas de login, donde el MFA ya funciona con TOTP y con códigos de recuperación (039). Un grant `mfa_token` solo serviría para seguir usando ROPC, que OAuth 2.1 desaconseja porque la aplicación ve la contraseña y no puede ofrecer passkeys ni login federado.

Consecuencia: un cliente que use el grant `password` con un usuario con MFA no puede completar el login por `/token`; debe pasar a code + PKCE o al device flow. Se reabriría solo ante un cliente concreto que no pueda abrir un navegador.
