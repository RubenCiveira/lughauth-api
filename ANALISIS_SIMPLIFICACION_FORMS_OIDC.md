# Analisis de simplificacion de forms OIDC

## Alcance
Este analisis cubre el flujo HTML de OIDC en `src/Features/Oidc/Authentication/Infrastructure/Driver/Html/`, con foco en:
- `AuthorizeHtml`
- `Forms/*`
- `Services/*`
- Entidades y objetos de dominio que viajan por el flujo (`AuthenticationRequest`, `AuthenticationResult`, `AuthorizedChalleges`, `PublicLoginAuthResponse`, `TemporalAuthCode`).

El objetivo es reducir acoplamiento y redundancia, y facilitar:
- agregar nuevos pasos del flujo OIDC
- transportar nuevos datos en request y response sin tocar muchas capas

## Inventario de componentes y dependencias

### Orquestador
`AuthorizeHtml` coordina todo el flujo:
- Extrae repetidamente datos de `query`, `cookies`, `headers`.
- Verifica cliente.
- Decide ruta (sesion previa, prompt=none, render form).
- Itera formularios para `paint` o `autenticate`.
- Maneja cookies de pre-sesion y sesion.
- Construye redirecciones y tokens.

Dependencias principales:
- `PublicLogin`, `HtmlSecurer`, `DecorateHtml`, `KeysManagerService`, `TemporalKeysGateway`.
- Coleccion de `OidcStep`.

### Formularios (steps)
`OidcStep` define:
- `step()`
- `handle()`
- `paint()`
- `autenticate()`

Formularios actuales:
- `LoginForm`
- `ConsentForm`
- `UseMfaForm`
- `NewMfaForm`
- `NewPassForm`
- `RecoverPassForm`
- `RegisterUserForm`
- `DelegateForm`

Dependencias comunes:
- `DecorateHtml`, `HtmlSecurer`, `PublicLogin`
- `MessageProvider`
- `PublicMfa`, `ConsentUsecase`, `DelegateLogin`

### Servicios de apoyo
- `PublicLogin`: aplica casos de uso y crea tokens/sesiones. Construye URLs de retorno y usa `AuthenticationRequest`, `AuthorizedChalleges`.
- `HtmlSecurer`: firma CSID, cifra campos, genera JS.
- `DecorateHtml`: selecciona theme y renderiza HTML.

### Objetos de dominio involucrados
- `AuthenticationRequest`: contiene `client`, `scope`, `redirect`, `responseType`, `audiences`.
- `AuthorizedChalleges`: estado de pasos (mfa, session, username) y encode/decode.
- `AuthenticationResult`: resultado de login y errores usados como control de flujo.
- `PublicLoginAuthResponse`: empaqueta tokens y expone `asAuthenticationResult()`.

## Flujo de datos actual (resumen)

### Entrada (query + cookies + headers)
`AuthorizeHtml` extrae en varios metodos:
- `tenant`, `response_type`, `client_id`, `state`, `redirect_uri`, `scope`, `nonce`, `audience`, `prompt`
- `AUTH_SESSION_ID_*` y `PRE_SESSION_ID`
- `accept-language`

Estos mismos datos se vuelven a pasar explicitamente a:
- `cisdPage()`
- `redirectToLogin()`
- `redirectOk()`
- `formAuthorize()`
- `paint()`

### Estado intermedio
- `AuthorizedChalleges` se actualiza en formularios y se almacena en cookie `PRE_SESSION_ID`.
- `AuthenticationRequest` se reconstruye varias veces (en authorize, refresh, formAuthorize).

### Resultados
- `PublicLoginAuthResponse` genera tokens y retorna `AuthenticationResult` en algunos flujos.
- `TemporalAuthCode` encapsula la response al flujo `code`.

## Problemas detectados

1) **Duplicacion de parseo y propagacion de variables**
   - Mismos campos del query se extraen y reenvian en multiples funciones.
   - Cualquier campo nuevo obliga a modificar `AuthorizeHtml` y varios helpers.

2) **Acoplamiento entre forms y detalles del orquestador**
   - Los forms reciben parametros sueltos (`tenant`, `issuer`, `csid`, `state`, `nonce`) y esperan que el orquestador los compute.
   - Los steps se resuelven por string (`step`) en multiples lugares.

3) **Uso de `AuthenticationResult` como control de flujo**
   - `handle()` depende de errores del dominio para decidir que form renderizar.
   - Esto mezcla errores de autenticacion con control de UI.

4) **`AuthorizedChalleges` es una bolsa mutable que viaja por todas partes**
   - Se actualiza en forms y se serializa en cookie.
   - Agregar campos implica modificar encode/decode y todos los sitios donde se leen/escriben.

5) **Errores y estados viajan en varias capas con representaciones distintas**
   - `AuthenticationResult`, `LoginException`, `UnauthorizedException`.
   - Algunas rutas usan mensajes string, otras errores tipados.

6) **Responsabilidades mezcladas**
   - `AuthorizeHtml` hace parsing, validacion, flujo, y render.
   - `PublicLogin` construye URLs de retorno con query manual y parametros duplicados.

## Plan de refactor propuesto

### Objetivo
Crear un modelo de flujo unificado que:
- Centralice parsing y validacion de input.
- Encapsule datos comunes en una estructura inmutable.
- Permita agregar pasos y campos sin tocar multiples capas.

### Fase 1: Contexto unico para el flujo

Introducir un `OidcFlowContext` (DTO inmutable) con:
- `tenant`, `clientId`, `redirect`, `scope`, `responseType`, `state`, `nonce`, `audiences`, `prompt`
- `locale`
- `baseUrl`, `issuer`
- `cookies` relevantes (`sessionId`, `preSessionId`)

Responsabilidades:
- Se construye una sola vez por request.
- Se pasa a forms, servicios y builders de URL.

Ejemplo de firma:
```php
final class OidcFlowContext {
    public function __construct(
        public readonly string $tenant,
        public readonly string $clientId,
        public readonly string $redirect,
        public readonly string $scope,
        public readonly string $responseType,
        public readonly string $state,
        public readonly string $nonce,
        public readonly array $audiences,
        public readonly string $prompt,
        public readonly string $locale,
        public readonly string $baseUrl,
        public readonly string $issuer,
        public readonly ?string $sessionId,
        public readonly ?string $preSessionId,
    ) {}
}
```

### Fase 2: Input y Output tipado por step

Definir un contrato unico para steps con un `StepInput` y `StepResult`:
- `StepInput`: contiene `OidcFlowContext`, `AuthenticationRequest`, `AuthorizedChallenges`, `body`, `request`.
- `StepResult`: `Render(view)` o `Proceed(authResponse)`.

Esto elimina la necesidad de que cada form reciba parametros sueltos.

### Fase 3: Simplificar `AuthorizedChalleges`

Reemplazar con una estructura inmutable y versionada:
- `ChallengesState` (inmutable) con `withMfa`, `username`, `session`, y `extra` (array).
- Serializacion en `KeysManagerService` usando schema version.

Ventaja:
- Agregar campos nuevos sin romper decode.
- Menos capas modificadas.

### Fase 4: Router de pasos

Crear un `OidcStepRouter` que:
- Mapea `step` a `OidcStep` con constantes en `OidcStepRouter`.
- Tiene reglas de fallback (por ejemplo, `handle()` basado en error, o paso explicitado en query).

`AuthorizeHtml` se reduce a:
1) construir `OidcFlowContext`
2) validar cliente
3) cargar session/challenges
4) delegar a `StepRouter`

### Fase 5: Unificar construccion de URLs de retorno

Crear `OidcUrlBuilder` para:
- URL de authorize
- URL de refresh
- URL para `askRegisterUser` / `askPassChange`

Esto elimina concatenaciones repetidas y reduce errores al agregar parametros nuevos.

## Beneficios esperados

- Agregar un nuevo campo en el flujo (por ejemplo `ui_locales` o `login_hint`) se hace en un solo lugar (`OidcFlowContext`).
- Agregar un nuevo step requiere:
  - Implementar `AuthorizationStep`.
  - Registrar en `OidcStepRouter`.
- Menos dependencias cruzadas entre forms y servicios.
- Mayor testabilidad: cada step puede probarse con `StepInput` fijo.

## Cambios concretos sugeridos

### 1) Nuevo contexto de flujo
- Archivo: `src/Features/Oidc/Authentication/Domain/OidcFlowContext.php`
- Constructor recibe `ServerRequestInterface` y `tenant` para extraer query/cookies/headers.

### 2) Refactor `AuthorizeHtml`
- Extraer `buildContext()`.
- Eliminar todos los parametros duplicados en metodos privados.
- Usar `context->issuer` y `context->baseUrl`.

### 3) Refactor `OidcStep`
De:
```php
public function autenticate(AuthenticationRequest $request, string $tenant, string $issuer, string $csid, string $state, string $nonce, AuthorizedChalleges $challenges, mixed $body): PublicLoginAuthResponse;
```
A:
```php
public function handle(StepInput $input): StepResult;
```

### 4) Adaptar servicios para aceptar `OidcFlowContext`
- `PublicLogin::askRegisterUser` / `askPassChange` deben usar `OidcFlowContext` y `AuthenticationRequest`.

### 5) Sustituir `AuthorizedChalleges`
- Nueva clase `ChallengesState` con `toArray()` / `fromArray()` y version.
- Mantener un adaptador temporal durante la migracion.

## Plan de migracion

1) Introducir `OidcFlowContext`, `StepInput`, `StepResult` sin reemplazar codigo existente.
2) Implementar `OidcStepRouter` y migrar un step piloto (por ejemplo `LoginForm`).
3) Migrar el resto de forms progresivamente.
4) Simplificar `AuthorizeHtml` y eliminar metodos duplicados.
5) Reemplazar `AuthorizedChalleges` con `ChallengesState`.
6) Remover rutas de compatibilidad.

## Plan de tareas concretas

### Tarea 1: Crear DTO de contexto unificado
- **Objetivo**: centralizar query/cookies/headers y calcular `baseUrl`/`issuer` una sola vez.
- **Acciones**:
  - Crear `OidcFlowContext` en `src/Features/Oidc/Authentication/Domain/`.
  - Agregar factorias: `fromRequest(ServerRequestInterface $request, string $tenant, Context $context)`.
  - Incluir `audiences` normalizado y `prompt` por defecto.
- **Criterio de fin**: `AuthorizeHtml` puede obtener todos los campos desde el contexto sin parseo duplicado.

### Tarea 2: Definir contrato de steps con input/output tipado
- **Objetivo**: evitar firmas largas y mover datos adicionales sin tocar cada form.
- **Acciones**:
  - Crear `StepInput` y `StepResult` en `Domain` o `Infrastructure/Driver/Html`.
  - Incluir `AuthenticationRequest`, `ChallengesState`, `body`, `request`, `context`.
  - Añadir resultado `RenderResponse` vs `ProceedAuth`.
- **Criterio de fin**: un step nuevo puede implementarse con una sola firma.

### Tarea 3: Implementar router de steps
- **Objetivo**: resolver step de forma declarativa y desacoplada.
- **Acciones**:
  - Crear `OidcStepRouter` con registro de steps.
  - Estrategia de seleccion: `step` explicito, luego `handle()` por error.
  - Fallback configurable (login por defecto).
- **Criterio de fin**: `AuthorizeHtml` solo delega y no conoce detalles de steps.

### Tarea 4: Migrar `LoginForm` a la nueva firma (piloto)
- **Objetivo**: validar el modelo sin tocar todo el flujo.
- **Acciones**:
  - Adaptar `LoginForm` para recibir `StepInput`.
  - Ajustar `AuthorizeHtml`/router para usar el nuevo contrato solo en este step.
  - Mantener un adaptador temporal para el resto de forms.
- **Criterio de fin**: login funciona con el nuevo pipeline sin romper el resto.

### Tarea 5: Sustituir `AuthorizedChalleges` por `ChallengesState`
- **Objetivo**: eliminar mutabilidad y facilitar extensiones.
- **Acciones**:
  - Crear `ChallengesState` inmutable con `extra` y versionado.
  - Añadir adaptador `fromLegacy(AuthorizedChalleges)`.
  - Actualizar `PublicLogin` y forms migrados para usar `ChallengesState`.
- **Criterio de fin**: nuevos campos se serializan sin tocar multiples capas.

### Tarea 6: Unificar construccion de URLs OIDC
- **Objetivo**: remover concatenaciones repetidas.
- **Acciones**:
  - Crear `OidcUrlBuilder` con metodos `authorizeUrl`, `recoverPassUrl`, `registerUserUrl`.
  - Migrar `PublicLogin` a usar el builder.
- **Criterio de fin**: todos los enlaces OIDC salen del builder.

### Tarea 7: Migrar el resto de forms
- **Objetivo**: completar la adopcion del nuevo contrato.
- **Acciones**:
  - Migrar en orden: `ConsentForm`, `UseMfaForm`, `NewMfaForm`, `RecoverPassForm`, `NewPassForm`, `RegisterUserForm`, `DelegateForm`.
  - Eliminar adaptadores temporales al final.
- **Criterio de fin**: todas las clases implementan el contrato nuevo.

### Tarea 8: Simplificar `AuthorizeHtml`
- **Objetivo**: dejarlo como orquestador minimal.
- **Acciones**:
  - Extraer `buildContext()` y `buildAuthRequest()`.
  - Remover metodos auxiliares con firmas largas (usar contexto + builder).
  - Centralizar cookies en un helper.
- **Criterio de fin**: `AuthorizeHtml` no pasa strings sueltos a forms.

### Tarea 9: Tests y compatibilidad
- **Objetivo**: asegurar que los flujos principales no regresan.
- **Acciones**:
  - Tests unitarios para `OidcFlowContext`, `OidcUrlBuilder`, `OidcStepRouter`.
  - Tests por step con `StepInput` fijo.
  - Tests de integracion para login, mfa, consent, recover, register.
- **Criterio de fin**: cobertura de flujos principales y regresion estable.

## Riesgos y mitigaciones

- Riesgo: errores de compatibilidad en cookies de pre-sesion.
  - Mitigacion: mantener `decode` compatible con la version anterior.

- Riesgo: cambios en orden de steps.
  - Mitigacion: tests de regresion para cada step con input fijo.

- Riesgo: nuevas firmas en varios forms.
  - Mitigacion: migracion incremental por step.

## Tests recomendados

- Unit tests para `OidcFlowContext` y `OidcUrlBuilder`.
- Tests de cada step con `StepInput` fijo.
- Tests de integracion sobre `AuthorizeHtml` para:
  - login simple
  - mfa
  - consent
  - recover password
  - register user

## Resumen ejecutivo

El flujo actual mezcla parsing de entrada, control de UI, y logica de autenticacion en varias capas que duplican datos y estados. La solucion es introducir un contexto unico de flujo, un router de steps y un contrato tipado de input/output para formularios, reduciendo la propagacion manual de parametros y haciendo trivial agregar nuevos datos o pasos.
