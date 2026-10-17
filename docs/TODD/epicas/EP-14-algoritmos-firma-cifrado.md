# EP-14 — Algoritmos, firma y cifrado avanzados

- **Tareas:** [050](../050-algoritmos-rotacion-firma.md) (ES256/EdDSA y ventana de rotación), [054](../054-jarm.md) (JARM), [057](../057-jwe-id-userinfo.md) (JWE de ID token y UserInfo) y [060](../060-userinfo-firmado.md) (UserInfo firmado).
- **Prioridad:** baja (solo si hay RP o perfil FAPI que lo exija). **Tamaño:** L. **Depende de:** EP-04 (`TokenSigner` por tipo, rotación), EP-06 (UserInfo), EP-08 (`AuthorizationResponseWriter`).
- **Esquema:** B.3 `_oauth_keys_storer.alg`; A.1 `id-token-signed-response-alg`, `authorization-signed-response-alg`, `userinfo-signed-response-alg`, `id-token-encrypted-response-alg/enc` y `userinfo-encrypted-response-alg/enc`.

## Por qué juntas

Las cuatro amplían [JoseTokenSigner](../../../src/Features/OAuth/TokenSecurity/Infrastructure/Driven/JoseTokenSigner.php) y siguen el mismo patrón: el RP registra en `ClientData` qué algoritmo quiere para cada artefacto (ID token, respuesta de autorización, UserInfo), el servidor lo negocia contra lo que soporta y discovery anuncia exactamente esa lista. Implementar la **negociación por cliente** una vez sirve a las cuatro; hacerlo por separado produce cuatro versiones del mismo mapeo.

## Componentes comunes

- **`SigningProfile`** por cliente y artefacto: algoritmo de firma y, si procede, `alg`/`enc` de cifrado, con la clave pública del RP obtenida vía `RemoteJwksFetcher` (EP-07).
- `TokenSigner::sign(TokenType, claims, tenant, alg)` con selección de clave activa por tenant y algoritmo, y **`TokenEncrypter`** (JWE del JWT ya firmado).
- Nuevos `TokenType`: `AuthorizationResponse` (JARM) y `UserInfo`.

## Plan

1. **050 primero.** Columna `alg`; claves por algoritmo con ventana dual (reutiliza la rotación de EP-04); ES256 y/o EdDSA solo si algún cliente lo necesita; publicar exactamente lo que se firma.
2. **060.** UserInfo como JWT (`Content-Type: application/jwt`) con `iss`/`aud` y las mismas claims autorizadas de EP-06.
3. **057.** Cifrar después de firmar el ID token (token endpoint y front-channel) y UserInfo; validar los metadatos de cifrado en el alta DCR.
4. **054.** Nuevo modo en `AuthorizationResponseWriter` (`jwt`, `query.jwt`, `fragment.jwt`, `form_post.jwt`) con `iss`/`aud`/`exp`, éxito y error.
5. Activar en discovery cada lista de algoritmos solo tras su test.

## Pruebas

- Tokens antes, durante y después de una rotación por algoritmo.
- Un RP de prueba verifica JARM y UserInfo firmados y descifra JWE con la clave negociada.
- Algoritmo no registrado: rechazo en el alta y sin emisión.

## Aceptación

Unión de 050, 054, 057 y 060: se anuncia solo la combinación realmente usable.
