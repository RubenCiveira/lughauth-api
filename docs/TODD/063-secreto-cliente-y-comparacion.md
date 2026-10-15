# 063 — Endurecer almacenamiento y comparación del secreto cliente

- **Estado:** pendiente. **Prioridad:** alta (063a), media (063b). **Dependencias:** 071 (clave AES). **Relacionada:** 044 (`client_secret_jwt` necesita el secreto en claro).
- **Evidencia:**
  - [ClientStoreAdapter::clientData](../../src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php) descifra el secreto (`getPlainSecretOauth`) y lo compara con `!==`, que no es tiempo constante.
  - [TrustedClientSecretOauthVO](../../src/Features/Access/TrustedClient/Domain/ValueObject/TrustedClientSecretOauthVO.php) guarda `cyphered://` + AES (`encryptForAll`), y [DynamicClientAdapter](../../src/Features/OAuth/Client/Infrastructure/Driven/DynamicClientAdapter.php) hace lo mismo con las altas DCR.
  - La clave de `encryptForAll` es por defecto la constante `'clave-cifrado'` de [AesCypherService](../../src/Shared/Security/AesCypherService.php), y no hay definición DI que la sustituya (ver 071). Hoy el cifrado equivale a **ofuscación**: quien tenga la BD y el código fuente obtiene todos los secretos.
  - [TrustedClientRetrieveController](../../src/Features/Access/TrustedClient/Infrastructure/Driver/Rest/TrustedClientRetrieveController.php) ya enmascara el secreto (`******`) en la API de lectura.
- **Impacto:** si se filtra la BD, se exponen todos los secretos de cliente, y la comparación no constante abre la puerta a un ataque de temporización.

## Modelo de amenaza y por qué hash

- Lo que se protege es la **preimagen**, no la resistencia a colisiones. Para suplantar a un cliente hay que encontrar un valor `x` con `H(x) = hash guardado`. Las colisiones (dos entradas cualesquiera con el mismo hash) no sirven contra un hash ya fijado.
- Los secretos de cliente los genera el servidor con ≥ 256 bits de entropía, así que no los alcanzan los diccionarios ni la fuerza bruta. Basta un **HMAC-SHA256 con *pepper*** (clave del servidor guardada fuera de la BD), que es rápido en `/token`, `/introspect` y `/revoke`.
- `password_hash` (bcrypt/argon2) es lento a propósito para contraseñas humanas, que tienen poca entropía. Con secretos aleatorios no aporta seguridad y se paga en cada autenticación. Úsese solo si quedan secretos legados elegidos a mano que no se puedan rotar.
- **Límite:** con el secreto guardado como hash el servidor no puede verificar `client_secret_jwt`, porque HS256 usa el secreto como clave de firma (OIDC Core §9). Ver decisión.

## 063a — Comparación constante y no exposición (inmediato, S)

- **Por hacer:** sustituir `!==` por `hash_equals` en `ClientStoreAdapter::clientData`. Comprobar que el secreto no se devuelve en ninguna respuesta (Retrieve, List, Update, Create, DCR GET/PUT) salvo una vez en el alta o la rotación.
- **Aceptación:** test que verifica la autenticación válida e inválida pasando por `hash_equals`; ninguna respuesta de lectura contiene el secreto.

## 063b — Almacenamiento no reversible (decisión)

- **Por hacer:** según la decisión, guardar `hmac-sha256:<pepper-id>:<hex>` en lugar de `cyphered://…`, con un *pepper* versionado leído de configuración segura (no de la BD ni del código). Migrar los secretos existentes: descifrarlos con la clave actual y re-hashearlos en una migración de datos; o, si se cambia la clave antes (071), obligar a rotarlos.
- **Aceptación:** la BD no contiene ningún secreto recuperable; los clientes existentes siguen autenticando tras la migración; rotar el *pepper* está documentado.

## Decisión de diseño

| Opción | Métodos de auth soportados | Riesgo si se filtra la BD |
|---|---|---|
| A. HMAC-SHA256 + *pepper* para todos | `client_secret_basic`/`post`, `private_key_jwt` | Ninguno sin el *pepper* |
| B. Mixto: hash por defecto y cifrado reversible solo para los clientes con `token_endpoint_auth_method=client_secret_jwt` | Todos, incluido `client_secret_jwt` | Solo esos clientes, y solo si también se filtra la clave AES |
| C. Cifrado reversible para todos, con la clave fuera de la BD y del código (KMS o secreto montado) | Todos | Todos, si se filtran a la vez la BD y la clave |

**Recomendación:** A, y en 044 implementar solo `private_key_jwt`, que es asimétrico, no necesita secreto compartido y es lo que piden FAPI y los perfiles modernos. Pasar a B solo si aparece un RP que exija `client_secret_jwt`. C únicamente como paso transitorio mientras se migra, y nunca con la clave por defecto actual.

## Detalle de implementación

- `src/Features/OAuth/Client/Infrastructure/Driven/ClientStoreAdapter.php`: (063a) `hash_equals`. (063b) verificar con HMAC + *pepper*, aceptar el formato `cyphered://` solo durante la ventana de migración y re-hashear al primer login correcto.
- `src/Features/OAuth/Client/Infrastructure/Driven/DynamicClientAdapter.php` y `src/Features/OAuth/Client/Application/Usecase/RegisterClient/RegisterClientResult.php`: guardar el hash en altas y rotaciones DCR y devolver el secreto en claro solo en esa respuesta.
- **Nuevo** `src/Shared/Security/SecretHasher.php` (o puerto en `Client/Domain/Gateway`): `hash(secret)`, `verify(secret, stored)` y `needsRehash(stored)`, con *pepper* versionado desde configuración.
- `src/Features/Access/TrustedClient/Domain/ValueObject/TrustedClientSecretOauthVO.php`: hoy es generado (propiedad `secret-oauth`, `private-content="true"` → AES `cyphered://`). Hace falta un atributo de modelo que genere hash (p. ej. `private-content="hash"`) y regenerar; en el modelo vigente, no en la copia antigua de la raíz (ver 001).
- `migrations/mysql/schema/1.0.0/` (**nueva migración**): ajustar el tamaño de columna si hace falta, más una migración de datos que re-hashee los secretos existentes.
- `test/integration/Features/OAuth/`: autenticación válida e inválida, formato legado durante la ventana de migración, secreto no expuesto en lecturas.

- **Origen:** análisis OAuth §3.6.
