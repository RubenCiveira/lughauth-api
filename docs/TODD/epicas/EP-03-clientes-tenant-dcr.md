# EP-03 — Clientes: pertenencia a tenant, DCR y metadatos de seguridad

- **Tareas:** [022](../022-clientes-aislados-por-tenant.md) (cliente por tenant), [023](../023-validar-acceso-inicial-dcr.md) (initial access token), [024](../024-limitar-grants-y-ropc.md) (grants permitidos y fin de ROPC por defecto) y [065](../065-metadatos-cliente-inoperantes.md) (metadatos de TrustedClient).
- **Prioridad:** alta. **Tamaño:** L. **Depende de:** EP-02 fase A (mismos adapters). **Desbloquea:** EP-07, EP-08, EP-10 (040), EP-11.
- **Esquema:** bloque A **ya aplicado** (`trusted-client.tenant`, `tenant-config.dcr-allowed-grant-types`, `dcr-allowed-auth-methods`, `allow-password-grant`, entidad `dcr-initial-access-token`). Falta solo la migración de datos de los clientes existentes.

## Por qué juntas

Las cuatro modifican el módulo `Features/OAuth/Client` y sobre todo los mismos tres archivos: [ClientStoreAdapter](../../../src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php), [DynamicClientAdapter](../../../src/Features/OAuth/Client/Infrastructure/Driven/DynamicClientAdapter.php) y [ClientData](../../../src/Features/OAuth/Client/Domain/ClientData.php), además de [RegisterClientUsecase](../../../src/Features/OAuth/Client/Application/Usecase/RegisterClient/RegisterClientUsecase.php) y `UpdateDynamicClientUsecase`. Los campos de modelo ya existen, así que es trabajo de conexión: cambiar `mapClientData` una sola vez con todos los metadatos evita cuatro refactorizaciones del mismo método.

## Componente común

**`ClientData` completo y tipado**, que las demás épicas solo consumen:

| Campo | Lo usa |
|---|---|
| `tenant` (null = global) | 022, logout |
| `grantTypes` (sin default `password`) | 024, TokenController |
| `responseTypes` | 033 (EP-08) |
| `requirePkce` | 034 (EP-07) |
| `requirePar` | 046 (EP-07) |
| `tokenEndpointAuthMethod`, `tokenEndpointAuthSigningAlg` | 044 (EP-11) |
| `allowAllScopes`, `isResourceServer`, flags `*LogoutSessionRequired` | 065, logout |

## Plan

1. **Metadatos (065).** Ampliar `ClientData` y `mapClientData` con todos los campos anteriores. Valores seguros por defecto: PKCE obligatorio para clientes públicos.
2. **Tenant (022).**
   - Decidir la regla para clientes globales: `tenant = null` = global, gestionable solo por root.
   - [ClientStoreGateway](../../../src/Features/OAuth/Client/Domain/Gateway/ClientStoreGateway.php): todas las consultas reciben el tenant, incluida `clientData`; filtrar con `tenant = :t OR tenant IS NULL`.
   - DCR guarda el tenant de la ruta.
   - [BackChannelLogoutDispatcher](../../../src/Features/OAuth/Authentication/Application/BackChannelLogoutDispatcher.php) y `LogoutController`: sustituir el provisional `findOneByTenantAndCode(null, code)` de la 021 por el tenant real.
   - Migración de datos con la regla de pertenencia de los clientes actuales.
3. **Grants (024).**
   - Quitar `['password']` de [ClientStoreAdapter.php:221](../../../src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php#L221); el default pasa a `authorization_code` + `refresh_token`.
   - Antes de desplegar, listar los clientes sin `grant-types-json` que hoy obtienen ROPC implícitamente y decidir caso por caso.
   - Registro y actualización DCR: validar `grant_types`, `response_types` y método de autenticación contra `tenant-config.dcr-*` y `allow-password-grant`; persistir solo metadatos validados.
   - `TokenController`: rechazar con `unauthorized_client` el grant no incluido en `ClientData::grantTypes`.
4. **Initial access token (023).**
   - Nuevo puerto `DcrInitialAccessTokenGateway` y su adaptador sobre la entidad `dcr-initial-access-token`.
   - Verificación por hash (`SecretHasher` de EP-02, o SHA-256 si EP-02 fase C no ha llegado: el token tiene entropía alta) y tenant.
   - Consumo atómico: `UPDATE … SET uses = uses + 1 WHERE token_hash = ? AND tenant = ? AND uses < max_uses AND revoked_at IS NULL AND expires_at > NOW()`.
   - Acción de creación que devuelve el token en claro una sola vez.
   - `allowed-grant-types` del token tiene prioridad sobre el tenant.
   - [DynamicClientController](../../../src/Features/OAuth/Client/Infrastructure/Driver/Rest/DynamicClientController.php) mapea el fallo a error DCR, sin confundirlo con el registration access token.

## Pruebas

- Aislamiento: un cliente del tenant A no autentica, no obtiene codes ni tokens y no recibe logout del tenant B; un cliente global sí funciona en ambos.
- DCR: credencial válida, vacía, falsa, caducada, agotada y de otro tenant; grants no permitidos y `password` sin `allow-password-grant`.
- Cada metadato de 065 cambia el resultado de su endpoint (las pruebas de PKCE, PAR y `response_type` se completan en EP-07/EP-08).

## Aceptación

Unión de 022, 023, 024 y 065. En particular: `password` nunca se obtiene por omisión ni por DCR sin permiso explícito.

## Decisiones

- Clientes globales y regla de migración (022).
- Entidad propia frente a JWT `dcr:register` para el initial access token (023): se recomienda la entidad, ya generada.
