# EP-11 — Aserciones JWT de cliente

- **Tareas:** [044](../044-client-auth-jwt.md) (`private_key_jwt`) y, opcional, [069](../069-grant-jwt-bearer.md) (grant JWT bearer RFC 7523).
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
