# EP-11 — Aserciones JWT de cliente

- **Tareas:** [044](044-client-auth-jwt.md) (`private_key_jwt`) y, opcional, [069](../pospuestos/069-grant-jwt-bearer.md) (grant JWT bearer RFC 7523).
- **Estado:** hecho (044). 069 [pospuesta](../pospuestos/069-grant-jwt-bearer.md) el 23-10-2026, con su diseño previsto en la ficha: no se implementa ni se anuncia.
- **Prioridad:** media (044, extensión), baja (069). **Tamaño:** M. **Depende de:** EP-02 (decisión del secreto: opción A ⇒ sin `client_secret_jwt`), EP-03 (`tokenEndpointAuthMethod`), EP-07 (`ReplayJtiGateway`, `RemoteJwksFetcher`).
- **Esquema:** A.1 `token-endpoint-auth-method` (amplía opciones) y `token-endpoint-auth-signing-alg` (nuevo); B.2 `_oauth_replay_jti` (EP-07) con `purpose` `client_assertion` y `jwt_bearer`.

## Por qué juntas

Ambas verifican una **aserción JWT firmada por un tercero** con las mismas reglas (`iss`, `sub`, `aud` exacta, `exp`, `jti` de un solo uso y firma contra el JWKS del emisor) y sobre la misma infraestructura de EP-07. Solo cambian el propósito y lo que se obtiene: autenticar al cliente (044) o un grant (069). Hay que mantenerlas separadas como pide 069: una aserción de cliente no sirve como grant ni al revés.

## Componentes comunes

- **`JwtAssertionVerifier`** (Application): parametrizado por propósito (`client_assertion` | `jwt_bearer`), audiencia esperada y JWKS de confianza.
- **`ClientAuthenticator`**: único punto de autenticación de cliente para `/token`, PAR, introspección y revocación. Absorbe los métodos actuales (Basic y body, ya con `SecretHasher` de EP-02) y añade `private_key_jwt`. Consolidar los cuatro endpoints aporta valor aunque 069 no se apruebe.

## Plan

1. Extraer el `ClientAuthenticator` con los métodos actuales, sin cambiar comportamiento, y usarlo en [TokenController](../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Rest/TokenController.php), [ParController](../../../src/Features/OAuth/Par/Infrastructure/Driver/Rest/ParController.php) y los controladores de introspección y revocación.
2. `private_key_jwt` (044): `iss = sub = client_id`, `aud` = token endpoint del tenant (o issuer), algoritmo = `token-endpoint-auth-signing-alg`, `jti` en el almacén anti-replay y método registrado obligatorio.
3. Discovery: `token_endpoint_auth_methods_supported` y los equivalentes de introspección y revocación desde capacidades (EP-01).
4. 069 (si se aprueba): `ResolverForJwtBearer` con emisores de confianza por cliente, `sub` mapeado a usuario local del tenant y `purpose=jwt_bearer`; registro condicional en `TokenGranterMediator`.

## Pruebas

Aserción válida, replay, firma ajena, `aud` errónea, método no registrado y caducada, en cada endpoint que la admita. Para 069: aserción de cliente usada como grant y viceversa.

## Aceptación

Unión de 044 y 069: la aserción válida autentica, replay o firma ajena fallan y discovery coincide con lo implementado.

## Resultado

### Decisiones tomadas

- **Solo `private_key_jwt`.** Con la opción A de EP-02 el servidor no conoce el secreto en claro, así que `client_secret_jwt` no se implementa ni se anuncia.
- **Verificador común:** [JwtAssertionVerifier](../../../src/Features/OAuth/Client/Domain/JwtAssertionVerifier.php) comprueba cualquier JWT firmado por un cliente: algoritmo asimétrico (`RS256`, `ES256`, `PS256`; nunca `none` ni HS) y el registrado si lo hay; firma contra el JWKS en línea o el `jwks_uri` (por `RemoteJwksFetcher`); `iss` (y `sub`, si se pide) igual al cliente; `aud` que nombre exactamente uno de los valores aceptados; `exp` obligatorio y como mucho a 1 h, `nbf`/`iat` no futuros (30 s de margen); y `jti` consumido en `ReplayJtiGateway` con el propósito indicado. Vive en Domain y no en Application, como proponía la épica, porque [RequestObjectValidator](../../../src/Features/OAuth/Authentication/Domain/RequestObjectValidator.php) (Domain) también lo usa. Ahora solo añade sus propias comprobaciones de claims y traduce cualquier fallo a `invalid_request_object`.
- **Un JWT firmado por el cliente sirve para un solo uso.** Guardar el `jti` por propósito no basta: si un JWT lleva los claims de los dos usos y tiene `aud` = issuer, se aceptaría una vez como JAR y otra como `private_key_jwt`, porque las entradas `request_object` y `client_assertion` son independientes. Para impedirlo hay dos capas. (1) `JwtAssertionVerifier` consume el `jti` bajo su propósito y también bajo el otro propósito firmado por el cliente (`CLIENT_SIGNED_PURPOSES`), de modo que el segundo uso encuentra el `jti` ya gastado, sea cual sea el orden. (2) Los claims son excluyentes: un request object debe llevar `response_type`, y `ClientAuthenticator` rechaza una aserción que lo lleve.
- **Un único autenticador:** [ClientAuthenticator](../../../src/Features/OAuth/Client/Application/ClientAuthenticator.php) (Application) sustituye la autenticación propia de `TokenController` (code, refresh, device y resto de grants), [ParController](../../../src/Features/OAuth/Par/Infrastructure/Driver/Rest/ParController.php), `IntrospectionController` y `LogoutController::revoke`. Las credenciales se leen con [ClientCredentials](../../../src/Features/OAuth/Client/Domain/ClientCredentials.php) (cabecera Basic o `PHP_AUTH_*`, `client_secret`, `client_assertion`, o solo `client_id`). Para conservar lo que aceptaba cada endpoint, [ClientAuthPolicy](../../../src/Features/OAuth/Client/Domain/ClientAuthPolicy.php) tiene tres casos:
  - `RegisteredMethod`: solo el método registrado, `none` incluido. Lo usan `authorization_code` y `refresh_token`.
  - `Secret`: Basic o post, sea cual sea el registrado. Lo usan los demás grants, PAR e introspección.
  - `SecretOrPublic`: lo mismo, más el `client_id` solo de un cliente con acceso público. Lo usan la revocación y el grant de device.
- **`private_key_jwt` (044):** se acepta en todos los endpoints, pero solo de un cliente registrado con ese método. Ese cliente tampoco puede autenticarse con secreto ni como público, sea cual sea la política. Reglas: `client_assertion_type` = `urn:ietf:params:oauth:client-assertion-type:jwt-bearer`; `iss` = `sub` = cliente (si llega `client_id`, debe coincidir; si no, el cliente se toma del `iss`); algoritmo = `token-endpoint-auth-signing-alg` (cualquiera de los tres si está vacío); `aud` = issuer del tenant, su token endpoint o el endpoint llamado (RFC 7523 §3, RFC 9126 §2), como cadena o dentro de una lista; `jti` de un solo uso con `purpose=client_assertion`. Los rechazos dan `invalid_client` y se registran (aviso con el motivo) sin dar detalles al cliente.
- **Un solo método por petición** (RFC 6749 §2.3): mezclar Basic, `client_secret` y aserción, o un `client_id` distinto del de Basic, da `invalid_client` en todos los endpoints.
- **Discovery:** `private_key_jwt` en `token_endpoint_auth_methods_supported`, `introspection_endpoint_auth_methods_supported` y `revocation_endpoint_auth_methods_supported`, con `*_auth_signing_alg_values_supported` = `RS256`, `ES256`, `PS256` (`OidcCapabilities::endpointAuthSigningAlgs`). PAR no tiene campo propio y se rige por los del token endpoint (RFC 9126). El grant `jwt-bearer` (069) no se anuncia.

### Cambios de comportamiento

- Un request object JAR sin `response_type` se rechaza (`invalid_request_object`). Antes se aceptaba y fallaba más adelante.
- PAR sin `client_id` ni credenciales responde `invalid_client` (401) en lugar de `invalid_request` (400), porque la autenticación ocurre antes de validar los parámetros.
- Los grants distintos de code y refresh ya no combinan el usuario de Basic con el `client_secret` del cuerpo, y un `client_id` del cuerpo distinto del de Basic se rechaza (antes ganaba Basic).
- El grant de device con `client_id` y `client_secret` en el cuerpo verifica el secreto. Antes lo ignoraba y trataba al cliente como público.
- Las cabeceras `Authorization` con otro esquema (por ejemplo `Bearer`) se ignoran como credencial de cliente. Antes, en `/token` daban `invalid_client`.
- `PushAuthorizationUsecase` recibe el cliente ya autenticado (`PushAuthorizationParams::$client`) en lugar del par id/secreto.

### Qué cambió

- Sin cambios de esquema: el modelo ya tenía `private_key_jwt` en `token-endpoint-auth-method`, `token-endpoint-auth-signing-alg`, `jwks-uri`/`jwks-json` y el `purpose` `client_assertion` de `_oauth_replay_jti`.
- Pruebas: [ClientAssertionTest](../../../test/integration/Features/OAuth/ClientAssertionTest.php) recorre token, PAR, introspección y revocación con una aserción válida, repetida, firmada con otra clave, dirigida a otro tenant, caducada, de un cliente no registrado para `private_key_jwt`, el secreto de un cliente `private_key_jwt` y la aserción mezclada con secreto. Cubre también la reutilización cruzada (un request object usado después como aserción y al revés, y una aserción que lleva `response_type`), los claims (`sub`, `jti`, `exp`, `iat`, `aud`), el `client_id` distinto del `iss`, el tipo de aserción, el algoritmo registrado, las audiencias aceptadas, el canje de un code y el grant de device. Además: `ClientCredentialsUnitTest` (detección del método), `ClientAuthenticatorUnitTest` (políticas), `DiscoveryTest` (métodos y algoritmos anunciados, sin `client_secret_jwt` ni `jwt-bearer`), y `RequestObjectValidatorUnitTest`, `ParUnitTest` y `ParTest` adaptados.

### Pendiente fuera de esta épica

- **DCR no admite `private_key_jwt`:** necesitaría aceptar `jwks`/`jwks_uri` y `token_endpoint_auth_signing_alg` en el registro, y `DcrMetadataPolicy` no lo permite todavía. Hoy estos clientes se dan de alta por la API de administración.
- **Políticas laxas heredadas:** `Secret` acepta Basic o post aunque el cliente registró el otro. Exigir el método registrado en todos los endpoints es un cambio de una línea en `ClientAuthenticator`, pero afecta a clientes existentes.
- La caché del JWKS remoto (5 minutos) no se refresca ante un `kid` desconocido; afecta igual a JAR y a las aserciones (ya anotado en EP-07).
- **069 (grant JWT bearer):** [pospuesta](../pospuestos/069-grant-jwt-bearer.md). La ficha recoge el diseño previsto (emisores de confianza por tenant, reglas de claims con roles y `sub` del sistema, anti-replay por emisor) y cuándo se reabre.

