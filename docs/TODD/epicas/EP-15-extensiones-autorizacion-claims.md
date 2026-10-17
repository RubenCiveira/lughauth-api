# EP-15 — Extensiones de autorización y claims

- **Tareas:** [056](../056-subject-pairwise.md) (`sub` pairwise), [058](../058-rar.md) (Rich Authorization Requests), [059](../059-resource-indicators.md) (resource indicators) y [061](../061-claims-parameter.md) (parámetro `claims`).
- **Prioridad:** baja. **Tamaño:** L (por partes: 059 y 061 M, 058 L, 056 M). **Depende de:** EP-05 (`ClaimsMapper`), EP-07 (resolver de parámetros). 059 se apoya en 018 (`allowed-audiences`, hecho).
- **Esquema:** sin tablas nuevas. `authorization_details`, `claims` y `resource` van en `_oauth_temporal_codes.code_data` y en el token; pairwise se deriva con `HMAC(secreto, sector || user_uid)`. A.1: `subject-type`, `sector-identifier-uri`, `authorization-details-types`.

## Por qué juntas

Las cuatro siguen el mismo recorrido de datos:

1. Un **parámetro nuevo** en `/authorize` (o `/token`) que se parsea y valida en [OidcFlowContext](../../../src/Features/OAuth/Authentication/Domain/OidcFlowContext.php) y [AuthenticationRequest](../../../src/Features/OAuth/Authentication/Domain/AuthenticationRequest.php).
2. Se **persiste** en el code y en la familia de refresh sin poder ampliarse.
3. Se **aplica** en el `ClaimsMapper` al emitir ID, access y UserInfo.

Diseñar una vez la extensión del contexto y del `code_data` (con su regla de «no ampliar en refresh») sirve a las cuatro.

## Componente común

**`AuthorizationGrantDetails`**: objeto inmutable que viaja de la solicitud al code y al refresh, con `resources`, `authorizationDetails` y `requestedClaims`. El `ClaimsMapper` lo recibe junto con el scope. En refresh solo admite reducciones.

## Plan (cada paso es independiente tras crear el objeto común)

1. **059 (más útil y más pequeño).** `resource` RFC 8707 en authorize y token; normalizar la URI; comprobar contra `allowed-audiences` (018); `aud` del access = recursos pedidos ∩ permitidos; sin ampliación en refresh.
2. **061.** Parsear `claims` (`id_token`/`userinfo`), esenciales y valores; el mapper entrega solo las claims consentidas y disponibles; `claims_parameter_supported: true` tras los tests.
3. **056.** `SubjectResolver` (`public` | `pairwise` por `sector_identifier_uri` validado); el mismo `sub` sectorial en ID, access, UserInfo y logout token; validar en DCR.
4. **058.** `authorization_details` tipados con tipos permitidos por tenant y cliente; consentimiento granular en `ScopesConsentAdapter`; claim en el access token.

## Pruebas

- 059: recurso permitido, desconocido y ampliación en refresh.
- 061: claim opcional, esencial no disponible y parámetro malformado.
- 056: dos sectores dan `sub` distintos y estables para el mismo usuario.
- 058: detalle ajeno o no consentido nunca aparece en el token.

## Aceptación

Las de cada tarea aprobada; sin aprobación, sus anuncios siguen retirados (EP-01).
