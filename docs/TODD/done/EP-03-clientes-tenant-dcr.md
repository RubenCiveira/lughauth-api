# EP-03 — Clientes: pertenencia a tenant, DCR y metadatos de seguridad

- **Tareas:** [022](022-clientes-aislados-por-tenant.md) (cliente por tenant), [023](023-validar-acceso-inicial-dcr.md) (initial access token), [024](024-limitar-grants-y-ropc.md) (grants permitidos y fin de ROPC por defecto) y [065](065-metadatos-cliente-inoperantes.md) (metadatos de TrustedClient).
- **Estado:** hecho. **Prioridad:** alta. **Tamaño:** L. **Depende de:** EP-02 fase A (mismos adapters). **Desbloquea:** EP-07, EP-08, EP-10 (040), EP-11.
- **Esquema:** bloque A ya aplicado (`trusted-client.tenant`, `tenant-config.dcr-allowed-grant-types`, `dcr-allowed-auth-methods`, `allow-password-grant`, entidad `dcr-initial-access-token`). Sin migración de datos: no hay entornos desplegados con clientes previos.

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

## Resultado

### Decisiones tomadas

- **Clientes globales:** `tenant = null` = global, visible desde todos los tenants y gestionable solo por root (políticas ya generadas de `trusted-client`). Si un tenant tiene un cliente con el mismo `code` que uno global, gana el del tenant. Un cliente de otro tenant nunca se devuelve.
- **Migración (022):** no se necesita, porque no hay entornos existentes. Un cliente creado por un administrador nace global salvo que root le asigne tenant; uno creado por DCR nace con el tenant de la ruta.
- **Initial access token (023):** entidad propia `dcr-initial-access-token`, digest con `SecretDigester` (EP-02). Se emite con `POST /api/access/dcr-initial-access-tokens/~/issue`, que devuelve el token en claro una sola vez. Solo emite un admin: un admin de tenant siempre para su propio tenant (si indica otro se rechaza); root debe indicar el tenant, que tiene que existir. La decisión la toma `IssueInitialAccessTokenUsecase` y la persistencia va directa al repositorio de comandos, porque el caso de uso generado no sirve (ver «Pendiente»). El CRUD generado sigue sirviendo para listar y revocar (`revokedAt`).
- **Grants (024):** sin `grant-types-json` el default es `authorization_code` + `refresh_token`. En DCR un grant debe estar en `dcr-allowed-grant-types` (o en `allowed-grant-types` del token, que tiene prioridad) y `password` además exige `allow-password-grant`. En la actualización DCR se conservan los grants que el cliente ya tenía (pudieron venir de un token), salvo `password`.
- **Métodos de autenticación DCR:** se cruzan los del tenant con los que el token endpoint sabe verificar (`client_secret_basic`, `client_secret_post`, `none`); `private_key_jwt` queda fuera hasta EP-11. Un cliente `none` no puede pedir `client_credentials` ni `password`.
- **`response_types`:** cada parte necesita su grant (`code`/`none` → `authorization_code`; `token`/`id_token` → `implicit`). Lista vacía permitida (cliente M2M).
- **`refresh_token`:** el token endpoint rechaza con `unauthorized_client` el grant `refresh_token` si el cliente no lo tiene y deja de emitir refresh tokens a esos clientes. `DynamicClientRequest` mantiene el default RFC 7591 (`["authorization_code"]`): un cliente DCR que quiera refresh debe pedirlo.
- **Nombre del campo:** `ClientData::$grants` se mantiene (no se renombra a `grantTypes`) para no tocar sus consumidores; se añade `allowsGrant()`.

### Qué cambió

- `ClientStoreGateway`: todas las consultas reciben el tenant (nombre de la ruta). Lookup en [TrustedClientFinder](../../../src/Features/OAuth/Client/Infrastructure/Driven/TrustedClientFinder.php), reutilizado por `LogoutController` (participantes) y `LoginAdapter` (roles y grupos por audiencia).
- `ClientData` incluye `tenant`, `responseTypes`, `requirePkce`, `requirePar`, `tokenEndpointAuthSigningAlg`, `allowAllScopes`, `isResourceServer` y los flags `*LogoutSessionRequired`; `requiresPkce()` es siempre cierto para clientes públicos y el canje de code lo aplica también a confidenciales con `require-pkce`.
- DCR: [DcrMetadataPolicy](../../../src/Features/OAuth/Client/Domain/DcrMetadataPolicy.php), [DcrRegistrationRules](../../../src/Features/OAuth/Client/Domain/DcrRegistrationRules.php), puerto [DcrInitialAccessTokenGateway](../../../src/Features/OAuth/Client/Domain/Gateway/DcrInitialAccessTokenGateway.php) y adaptador SQL con consumo atómico (`SELECT … FOR UPDATE` + `UPDATE … uses < max_uses`); la validación de metadatos ocurre antes de gastar el uso. Los clientes DCR se crean con el tenant de la ruta y solo se gestionan desde él.
- El fallo del initial access token devuelve `401 invalid_token` con descripción propia, distinta de la del registration access token.
- Los clientes DCR se crean habilitados. Antes nacían deshabilitados sin que nadie lo notara: `TrustedClient::create()` calcula `enabled = false` e ignoraba el `enabled(true)` del alta.
- [DcrRegistrationFlowTest](../../../test/integration/Features/OAuth/DcrRegistrationFlowTest.php) cubre el flujo completo sobre MySQL con los controladores reales: emisión por admin de tenant y por root, registro DCR, consumo único, gestión RFC 7592 solo desde el tenant propietario y aislamiento en `ClientStoreAdapter`.

### Pendiente fuera de esta épica

- `responseTypes` en authorize (EP-08), `requirePar` (EP-07), `tokenEndpointAuthSigningAlg` (EP-11). `allowAllScopes` e `isResourceServer` quedan expuestos en `ClientData`, pero sus consumidores actuales siguen leyendo `TrustedClient`.
- El CRUD generado de `dcr-initial-access-token` aún acepta un `tokenHash` en claro elegido por el admin; conviene ocultarlo en el modelo para que la única vía sea `~/issue`.
- **Fallo del generador** (afecta a todas las entidades con tenant, no solo a esta): los `TenantFormula` generados no se registran en ningún plugin, y además `copyWithFixed` borra `tenant` para quien no es root (lo marca `FixTenantExcludingRoot`) después del *enrich*. Por eso un admin de tenant no puede crear registros por el CRUD genérico. Hay que corregirlo en la plantilla del generador.

