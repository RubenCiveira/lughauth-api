# Cambios de modelo y de esquema — tareas 018 a 071

Inventario de los datos persistentes que exigen las tareas OIDC, separado en dos grupos:

- **A. Modelo gestionado de Access.** Entidades generadas desde `multitenant-oidc.xml`; la migración sale de la sincronización del esquema (`SynchSchemaFor<timestamp>`). Incluye el fragmento XML sugerido.
- **B. Almacenes automáticos de OIDC.** Tablas `_oauth_*` que escriben directamente los adapters SQL de `src/Features/OAuth`, sin pasar por el modelo. Requieren migración propia.

> **Estado (16-10-2026):**
> - **Bloque A aplicado.** El modelo regenerado (commit `2543dd2e`, migración `20261016125813_SynchSchemaFor20261016125813`) incluye los campos de `trusted-client`, `tenant-config` y `tenant-login-provider`, y las entidades `trusted-client-allowed-audience` y `dcr-initial-access-token`. El código OAuth todavía no los aplica: eso corresponde a cada tarea.
> - **Bloque B pendiente.**

Leyenda de la columna **Estado:**

| Estado | Significado |
|---|---|
| **Nuevo** | Hay que añadirlo |
| **Existe, sin usar** | Ya está en el modelo o en el esquema, pero el código no lo aplica; solo hace falta conectarlo |
| **Condicional** | Solo si se aprueba la tarea opcional |

## Hallazgos que condicionan el diseño

1. **`_oauth_session_grant` y `_oauth_session_token` existen y ningún adapter las usa.** Ya tienen `session`, `client_id`, `scope`, `audiences`, `jti`, `refresh_jti` y `revoked_at`. Son la base natural para:
   - las audiencias concedidas (018);
   - la familia de refresh y su rotación (019/020);
   - los RP participantes en una sesión (021).

   Se recomienda usarlas en lugar de añadir columnas JSON a `_oauth_session`.
2. **El identificador de `_oauth_session.session` es el valor de la cookie de sesión.** Publicarlo como claim `sid` en ID tokens, refresh o logout tokens filtraría la credencial a los RP. Hace falta un `sid` público independiente (019, 021, 032).
3. **`_oauth_session` no tiene tenant.** Solo tiene `issuer`, que contiene el nombre del tenant en la URL. Filtrar por tenant (019, 041) exige `tenant_id`.
4. **Existen en el modelo y no se aplican:**
   - `tenant-config.refresh-token-ttl-seconds`: `TokenController` fija `PT10H`.
   - `tenant-config.session-sso-ttl-seconds`.
   - `user-mfa-recovery-code.used-at`, que 039 no necesita duplicar.
   - `tenant-login-provider.oidc-discovery-url` y `source=OIDC`, que reducen 051.
   - `_oauth_session.auth_time` / `acr`.
   - `_oauth_session.sso_clients_json`.
5. **Un único almacén anti-replay de JTI** sirve a 044 (client assertion), 047 (request object), 048 (DPoP) y 069 (JWT bearer), en lugar de una tabla por tarea.

## A. Modelo gestionado de Access

### A.1 `trusted-client`

| Tarea | Campo | Estado | Uso | XML sugerido |
|---|---|---|---|---|
| 018, 059 | `allowed-audiences` (subentidad) | Nuevo | Audiencias o recursos que el cliente puede pedir; se cruzan con la petición en authorize y en todos los grants. Su valor es el `code` de un `trusted-client` (resource server) o de un `relying-party`, o una URI de recurso (059) | `<property name="allowed-audiences" type="tns:trusted-client-allowed-audience[]" reverse="client" />` más la entidad del bloque A.6 |
| 022 | `tenant` | Nuevo | Pertenencia al tenant; `null` = cliente global (solo root) | `<property name="tenant" type="tns:tenant" description="Owner tenant. Null means a global client, manageable only by root." />` |
| 024 | `grant-types-json` | Existe; cambia el default | Quitar `password` como valor por defecto (hoy se aplica en `ClientStoreAdapter`, no en el modelo) | `<property name="grant-types-json" type="xs:blob" default='["authorization_code","refresh_token"]' />` |
| 033, 065 | `response-types-json` | Existe, sin usar | Validar `response_type` en authorize | — |
| 034, 065 | `require-pkce` | Existe, sin usar | Exigir PKCE a clientes confidenciales | — |
| 046 | `require-pushed-authorization-requests` | Nuevo | Obliga a usar PAR | `<property name="require-pushed-authorization-requests" type="xs:boolean" required="true" default="false" />` |
| 044 | `token-endpoint-auth-method` | Existe; amplía opciones | Añadir `none` (ya en uso) y `private_key_jwt`; `tls_client_auth` con 053 | `<property name="token-endpoint-auth-method" type="xs:string" required="true" default="client_secret_basic"><option>client_secret_basic</option><option>client_secret_post</option><option>private_key_jwt</option><option>none</option></property>` |
| 044 | `token-endpoint-auth-signing-alg` | Nuevo | Algoritmo permitido para la client assertion | `<property name="token-endpoint-auth-signing-alg" type="xs:string" default="RS256" />` |
| 044, 047 | `jwks-uri`, `jwks-json` | Existe | Ya se usan para JAR; se reutilizan para la client assertion | — |
| 048 | `dpop-bound-access-tokens` | Condicional | Exige DPoP al cliente | `<property name="dpop-bound-access-tokens" type="xs:boolean" required="true" default="false" />` |
| 050 | `id-token-signed-response-alg` | Condicional | Algoritmo de firma del ID token por RP | `<property name="id-token-signed-response-alg" type="xs:string" default="RS256"><option>RS256</option><option>ES256</option><option>EdDSA</option></property>` |
| 053 | `tls-client-auth-subject-dn`, `tls-client-certificate-bound-access-tokens` | Condicional | mTLS (RFC 8705) | `<property name="tls-client-auth-subject-dn" type="xs:string" />` `<property name="tls-client-certificate-bound-access-tokens" type="xs:boolean" required="true" default="false" />` |
| 054 | `authorization-signed-response-alg` | Condicional | JARM | `<property name="authorization-signed-response-alg" type="xs:string" />` |
| 055 | `backchannel-token-delivery-mode`, `backchannel-client-notification-endpoint` | Condicional | CIBA | `<property name="backchannel-token-delivery-mode" type="xs:string"><option>poll</option><option>ping</option></property>` `<property name="backchannel-client-notification-endpoint" type="xs:string" />` |
| 056 | `subject-type`, `sector-identifier-uri` | Condicional | `sub` pairwise por sector | `<property name="subject-type" type="xs:string" required="true" default="public"><option>public</option><option>pairwise</option></property>` `<property name="sector-identifier-uri" type="xs:string" />` |
| 057 | `id-token-encrypted-response-alg/enc`, `userinfo-encrypted-response-alg/enc` | Condicional | JWE | `<property name="id-token-encrypted-response-alg" type="xs:string" />` `<property name="id-token-encrypted-response-enc" type="xs:string" />` `<property name="userinfo-encrypted-response-alg" type="xs:string" />` `<property name="userinfo-encrypted-response-enc" type="xs:string" />` |
| 058 | `authorization-details-types` | Condicional | Tipos RAR permitidos | `<property name="authorization-details-types" type="xs:blob" />` |
| 060 | `userinfo-signed-response-alg` | Condicional | UserInfo como JWT firmado | `<property name="userinfo-signed-response-alg" type="xs:string" />` |
| 063 | `secret-oauth` | Existe; cambia el almacenamiento | HMAC con *pepper* en lugar de AES reversible. **Requiere soporte en el generador** de un nuevo valor de `private-content` | `<property name="secret-oauth" type="xs:string" private-content="hash" input-type="password" />` |
| 063 | `registration-access` | Existe; cambia el almacenamiento | Registration access token de DCR (RFC 7592): guardar hash, no el valor | `<property name="registration-access" type="xs:string" private-content="hash" />` |
| 067 | `default-acr-values`, `default-max-age` | Condicional (pendiente: 067 no los aplica todavía) | Metadatos de registro OIDC para step-up por defecto | `<property name="default-acr-values" type="xs:string" />` `<property name="default-max-age" type="xs:integer" />` |

> **Al generar `token-endpoint-auth-method`:** el enum resultante usa valores con guiones (`client-secret-basic`), y los nombres del protocolo (RFC 7591) son con guion bajo. `ClientStoreAdapter` y `DynamicClientAdapter` hacen la traducción; cualquier consumidor nuevo debe pasar por ellos.

### A.2 `tenant-config`

| Tarea | Campo | Estado | Uso | XML sugerido |
|---|---|---|---|---|
| 019, 020 | `refresh-token-ttl-seconds` | Existe, sin usar | Vida de cada refresh; sustituye el `PT10H` fijo | — |
| 020 | `refresh-token-max-lifetime-seconds` | Nuevo | Vida absoluta de la familia; la rotación no la extiende | `<property name="refresh-token-max-lifetime-seconds" type="xs:integer" required="true" default="7776000" description="Absolute lifetime of a refresh token family; rotation never extends it." />` |
| 031 | `session-sso-ttl-seconds` | Existe, sin usar | Antigüedad máxima de la sesión SSO | — |
| 024 | `dcr-allowed-grant-types` | Nuevo | Grants que puede pedir un cliente DCR | `<property name="dcr-allowed-grant-types" type="xs:blob" default='["authorization_code","refresh_token"]' />` |
| 024 | `dcr-allowed-auth-methods` | Nuevo | Métodos de autenticación admitidos en DCR | `<property name="dcr-allowed-auth-methods" type="xs:blob" default='["client_secret_basic","private_key_jwt","none"]' />` |
| 024 | `allow-password-grant` | Nuevo | Permiso explícito de ROPC a nivel de tenant | `<property name="allow-password-grant" type="xs:boolean" required="true" default="false" />` |
| 046 | `require-pushed-authorization-requests` | Nuevo | PAR obligatorio para todo el tenant (si es `true`, se anuncia en discovery) | `<property name="require-pushed-authorization-requests" type="xs:boolean" required="true" default="false" />` |
| 067 | `acr-values-supported` | Condicional (no usado: 067 toma los valores de `SECURITY_ACR_*`, globales) | Niveles ACR publicables y su correspondencia con el `acr` numérico de la sesión | `<property name="acr-values-supported" type="xs:blob" description="JSON map of published ACR values to session level, e.g. {&quot;urn:lugh:acr:pwd&quot;:1,&quot;urn:lugh:acr:mfa&quot;:2}" />` |

### A.3 `tenant-login-provider` (051, condicional)

| Campo | Estado | Uso | XML sugerido |
|---|---|---|---|
| `source=OIDC`, `oidc-discovery-url` | Existe | Identifican el proveedor genérico | — |
| `public-key` / `private-key` | Existe | Hoy se usan como client id y secret | — |
| `oidc-scopes` | Nuevo | Scopes que se piden al IdP | `<property name="oidc-scopes" type="xs:string" default="openid email profile" />` |
| `oidc-claim-mapping` | Nuevo | Correspondencia entre claims externos y atributos locales | `<property name="oidc-claim-mapping" type="xs:blob" />` |
| `account-link-policy` | Nuevo | Cómo enlazar una identidad externa a un usuario local | `<property name="account-link-policy" type="xs:string" required="true" default="verified-email"><option>none</option><option>verified-email</option><option>manual</option></property>` |

### A.4 `user` (071)

| Campo | Estado | Uso | XML sugerido |
|---|---|---|---|
| `password` | Existe; cambia el almacenamiento | argon2id (`password_hash`) en lugar de AES. **Requiere soporte en el generador**, distinto del HMAC de 063 | `<property name="password" type="xs:string" required="true" private-content="password-hash" input-type="password" />` |

### A.5 Entidad nueva `dcr-initial-access-token` (023)

Un token que el admin de tenant emite y que se muestra **una sola vez**. Necesita una acción de creación propia que devuelva el valor en claro; el CRUD generado solo lista y revoca.

> **Alternativa sin entidad nueva:** usar como *initial access token* un access token JWT con scope `dcr:register`, emitido por `client_credentials` a un cliente de alta de clientes. Aprovecha la caducidad y la revocación existentes, pero no limita el número de usos.

```xml
<entity name="dcr-initial-access-token" id="uid" generator="uuid" display="description" group="access">
    <property name="uid" type="xs:string" unique="true" required="true" />
    <property name="tenant" type="tns:tenant" required="true" />
    <property name="description" type="xs:string" />
    <property name="token-hash" type="xs:string" required="true" unique="true" private-content="hash" />
    <property name="expires-at" type="xs:datetime" required="true" />
    <property name="max-uses" type="xs:integer" required="true" default="1" />
    <property name="uses" type="xs:integer" required="true" default="0" calculated="true" />
    <property name="allowed-grant-types" type="xs:blob" description="Overrides tenant-config.dcr-allowed-grant-types when set" />
    <property name="revoked-at" type="xs:datetime" />
    <facade type="crud" path="access/dcr-initial-access-tokens">
        <allow for="all" name="#tenant-admin" match="hasRole('admin')" />
    </facade>
</entity>
```

### A.6 Entidad nueva `trusted-client-allowed-audience` (018/059)

Sigue el patrón de `trusted-client-allowed-redirect`.

```xml
<entity name="trusted-client-allowed-audience" id="uid" generator="uuid"
    display="audience" group="access" union="true">
    <property name="uid" type="xs:string" unique="true" required="true" />
    <property name="client" type="tns:trusted-client" required="true" />
    <property name="audience" type="xs:string" required="true"
        description="Code of a trusted-client resource server or relying-party, or an absolute resource URI (RFC 8707)." />
    <index properties="client,audience" unique="true" />
</entity>
```

> `allowed-audiences` cubre la cuestión de 018 sobre los **roles**. `LoginAdapter` carga roles y grupos por cada audiencia pedida, de modo que limitar las audiencias a esta lista también limita qué roles entran en el token.

> **Defectos del generador detectados al añadir esta segunda colección hija** (corregidos a mano en el código generado; hay que llevarlos a la plantilla):
> 1. Las lecturas de la segunda colección acumulaban las columnas de la primera (`select "uid", "client", "url", "version", "uid", … from "access_trusted_client_allowed_audience"`).
> 2. `insert<Colección>` no devolvía el `uid` generado. Se corrige con `new <Ítem>(<UidVO>::from($uid), null, …)->getOrDefault($child)`.
> 3. Los controladores leían `$item['campo'] ? …` sin `??`, lo que da *Undefined array key* si falta la clave.

## B. Almacenes automáticos de OIDC (`_oauth_*`, migración propia)

Todos los `INSERT` actuales sobre estas tablas listan sus columnas explícitamente. Por eso se pueden añadir columnas que admitan `NULL` o tengan valor por defecto sin tocar el código. Una columna `NOT NULL` sin valor por defecto en una tabla donde hoy se inserta (`_oauth_session`) rompe el `INSERT` hasta que el código la escriba.

### B.1 Sesión y familia de refresh

| Tarea | Tabla | Columna o índice | Tipo | Estado | Motivo |
|---|---|---|---|---|---|
| 019, 021, 032 | `_oauth_session` | `sid` | `VARCHAR(64) NULL` + `UNIQUE` (pasa a `NOT NULL` cuando el código lo escriba) | Nuevo | Identificador público y estable de la sesión para los claims `sid` y el logout. **No** reutilizar `session`, que es el valor de la cookie |
| 019, 041 | `_oauth_session` | `tenant_id` | `VARCHAR(36) NULL` + índice `(user_uid, tenant_id)` (rellenable desde `issuer`) | Nuevo | Filtrar sesiones por tenant; hoy solo existe `issuer` |
| 032, 067 | `_oauth_session` | `amr` | `VARCHAR(128) NULL` (lista separada por espacios) | Nuevo | Métodos de autenticación reales (`pwd`, `otp`, `hwk`…) |
| 067 | `_oauth_session` | `acr` | `TINYINT`, sin uso | Existe; sin uso tras 067 | 067 deriva el nivel ACR del `amr` de la sesión y lo nombra con los valores configurados (`SECURITY_ACR_*`), sin migración; la columna puede retirarse más adelante |
| 031, 032 | `_oauth_session` | `auth_time` | — | Existe; hay que leerlo | Ya se escribe; `loadSession` no lo selecciona |
| 021 | `_oauth_session` | `sso_clients_json` | — | Existe, sin usar → **eliminar** | Se sustituye por las filas de `_oauth_session_grant` |
| 018, 020, 021 | `_oauth_session_grant` | (tabla) | — | Existe, sin usar | Una fila por sesión y cliente = **familia de refresh** con las audiencias y el scope concedidos; también sirve como lista de RP participantes para el logout |
| 020 | `_oauth_session_grant` | `tenant_id` | `VARCHAR(36) NOT NULL` | Nuevo | Aislamiento por tenant |
| 020 | `_oauth_session_grant` | `absolute_expires_at` | `DATETIME NOT NULL` | Nuevo | Fin de la familia; la rotación no lo mueve |
| 020 | `_oauth_session_grant` | índice `(session, client_id)` | `UNIQUE` (con `revoked_at IS NULL` a nivel de lógica) | Nuevo | Un grant vivo por sesión y cliente |
| 020 | `_oauth_session_token` | (tabla) | — | Existe, sin usar | Una fila por refresh emitido |
| 020 | `_oauth_session_token` | `consumed_at` | `DATETIME NULL` | Nuevo | Consumo atómico (`UPDATE … WHERE consumed_at IS NULL`); una segunda presentación = replay → revocar el grant |
| 020 | `_oauth_session_token` | `replaced_by_jti` | `VARCHAR(36) NULL` | Nuevo | Trazabilidad de la cadena de rotación |
| 020 | `_oauth_session_token` | índice `UNIQUE (refresh_jti)` | — | Nuevo | Búsqueda y consumo por el JTI del refresh |

### B.2 Anti-replay compartido (044, 047, 048, 069)

| Tarea | Tabla | Columna | Tipo | Motivo |
|---|---|---|---|---|
| 044, 047, 048, 069 | `_oauth_replay_jti` (**nueva**) | `jti` | `VARCHAR(255) NOT NULL` | Identificador de la aserción, el request object o la prueba DPoP |
| | | `tenant_id` | `VARCHAR(36) NOT NULL` | Aislamiento |
| | | `client_id` | `VARCHAR(250) NOT NULL` | El JTI es único por emisor |
| | | `purpose` | `ENUM('client_assertion','request_object','dpop','jwt_bearer') NOT NULL` | Evitar colisiones entre usos |
| | | `expires_at` | `DATETIME NOT NULL` + índice | Limpieza por `OAuthCleanupScheduler` |
| | | PK `(tenant_id, client_id, purpose, jti)` | — | `INSERT` falla si ya existe, lo que da un consumo atómico sin `SELECT` previo |

### B.3 Claves de firma

| Tarea | Tabla | Columna | Tipo | Estado | Motivo |
|---|---|---|---|---|---|
| 025 | `_oauth_keys_storer` | `keyid` | — | Existe | Basta con escribirlo en la cabecera `kid` |
| 050 | `_oauth_keys_storer` | `alg` | `VARCHAR(16) NOT NULL DEFAULT 'RS256'` | Condicional | Varias claves activas por algoritmo |
| 029, 050 | `_oauth_keys_storer` | índice `(tenant, since, expiration)` | — | Nuevo | Hoy se consulta por esas columnas sin índice |

### B.4 Flujos condicionales

| Tarea | Tabla | Columnas | Motivo |
|---|---|---|---|
| 040 | `_oauth_mfa_challenge` (**nueva**) | `mfa_token_hash VARCHAR(64) PK`, `tenant_id`, `client_id`, `user_uid`, `attempts INT DEFAULT 0`, `expires_at`, `consumed_at NULL` | Challenge de vida corta del grant MFA |
| 055 | `_oauth_ciba_request` (**nueva**) | `auth_req_id VARCHAR(64) PK`, `tenant_id`, `client_id`, `user_uid`, `scope`, `binding_message`, `status ENUM('pending','approved','denied','expired')`, `interval_sec`, `last_poll_at`, `expires_at`, `auth_data` | Estado de la autenticación back-channel |

### B.5 Sin cambios de esquema

| Tarea | Por qué no hace falta |
|---|---|
| 017 | Ya hecho (`uq_oauth_temporal_codes_code`) |
| 028 | `_oauth_revoked_jti` ya tiene `tenant_id`, `token_type` e índices |
| 039 | `user-mfa-recovery-code.used-at` ya existe |
| 046 | `_oauth_par_request` ya tiene `client_id`, `tenant_id` y `used_at` |
| 048, 058, 061, 070 | `cnf.jkt`, `authorization_details`, `claims` y `nonce` van dentro de `_oauth_temporal_codes.code_data` (JSON) y del propio token |
| 056 | El `sub` pairwise se deriva de forma determinista con `HMAC(secreto, sector \|\| user_uid)`; no necesita tabla |
| 025, 026, 027, 029–038, 042, 045, 049, 062, 064, 066, 068 | Solo cambian lógica, configuración o documentación |

## Orden recomendado de migraciones

1. **Seguridad, P0 (sin opcionales):**
   - Modelo (**hecho**): `trusted-client.allowed-audiences` + entidad, `trusted-client.tenant`, `tenant-config.dcr-*`, `allow-password-grant` y `refresh-token-max-lifetime-seconds`.
   - Esquema (pendiente): `_oauth_session.sid` / `tenant_id`, columnas de `_oauth_session_grant` y `_oauth_session_token`, y `_oauth_replay_jti`.
2. **Hash de secretos (063/071):** requiere primero el soporte del generador para `private-content="hash"` / `"password-hash"`, más una migración de datos.
3. **Conformidad OIDC:** `_oauth_session.amr`, revisión de `acr`, `require-pushed-authorization-requests`.
4. **Opcionales:** solo al aprobar cada tarea condicional.
