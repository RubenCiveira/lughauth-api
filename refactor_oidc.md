# Refactorización arquitectural del paquete Oidc/Authentication

## Contexto

El paquete `src/Features/Oidc/` implementa un flujo OIDC completo con formularios HTML para autenticación interactiva. Tras una refactorización parcial (migración de `AuthorizedChalleges` → `ChallengesState`), el código presenta varios problemas de acoplamiento y mantenibilidad que dificultan añadir nuevos formularios (ej. verificación de scopes a un cliente) o mover datos entre capas (ej. scopes hacia persistencia).

### Problemas detectados

| Problema | Ejemplo concreto |
|----------|-----------------|
| Formularios mezclan renderizado con lógica de negocio | `LoginForm.run()` decide si renderizar HTML o autenticar según presencia de CSID |
| Indirección excesiva sin valor | `ConsentUsecase` → `ConsentGateway` → `ConsentRepository` → `ConsentAdapter` (Gateway solo reenvía) |
| Dominio importa infraestructura | `LoginGateway` importa `LoginAdapter::class`, `PublicLoginAuthResponse` inyecta `KeysManagerService` |
| God object | `PublicLogin` (300 líneas) orquesta autenticación, sesiones, registro, password, cliente — todo en una clase de infraestructura |
| Migración legacy incompleta | `AuthorizedChalleges` (mutable) sigue en todas las firmas; `ChallengesState` obliga a `toLegacy()` en cada formulario |
| Clases duplicadas | `SessionInfo` ≈ `PublicLoginSessionResponse`, `OtpMfaService` duplicado en `UserMfaAdapter` |
| Constantes mezcladas con datos | `StepInput` contiene tanto los datos de la request como las constantes `STEP_LOGIN`, `STEP_MFA`, etc. |

---

## Fase 1: Completar la migración — Eliminar `AuthorizedChalleges`

### Motivo

La refactorización hacia `ChallengesState` (inmutable, versionada, serializable) quedó a medias. Todos los formularios, el servicio `PublicLogin`, y los gateways aún reciben `AuthorizedChalleges` — un objeto mutable sin control de estado. Cada formulario llama `$input->challenges->toLegacy()` para convertir de vuelta, lo cual anula la inmutabilidad de `ChallengesState`. Mientras coexistan ambos tipos, cualquier cambio al estado de challenges requiere modificar los dos y sus puentes.

### Tareas

1. **Añadir métodos `with*()` a `ChallengesState`**
   - Fichero: `Authentication/Domain/ChallengesState.php`
   - Añadir `withUsername(string): self`, `withMfa(bool): self`, `withSession(bool): self`
   - Estos métodos devuelven nuevas instancias (inmutabilidad)
   - **Resultado**: Se puede mutar el estado de challenges sin objetos mutables

2. **Reemplazar `AuthorizedChalleges` en todas las firmas de formularios**
   - Ficheros: `LoginForm.php`, `ConsentForm.php`, `UseMfaForm.php`, `NewMfaForm.php`, `NewPassForm.php`, `RecoverPassForm.php`, `DelegateForm.php`, `RegisterUserForm.php`
   - Cada `paint()` y la lógica interna pasan a recibir/usar `ChallengesState`
   - Donde se hacía `$challenges->username = $body['username']` ahora se hace `$challenges = $challenges->withUsername($body['username'])`
   - **Resultado**: Formularios trabajan con un tipo inmutable y tipado

3. **Reemplazar `AuthorizedChalleges` en `PublicLogin`**
   - Fichero: `Authentication/Infrastructure/Driver/Html/Services/PublicLogin.php`
   - Todas las firmas (`autenticate`, `preAutenticate`, `sessionAutenticated`, `confirmPassChange`, etc.) reciben `ChallengesState`
   - **Resultado**: El servicio central ya no depende del tipo legacy

4. **Reemplazar `AuthorizedChalleges` en gateways y repositories**
   - Ficheros: `LoginGateway.php`, `LoginRepository.php`, `LoginAdapter.php`, `SessionStoreGateway.php`, `SessionStoreRepository.php`, `SessionStoreSqlAdapter.php`
   - **Resultado**: La cadena completa usa un solo tipo

5. **Actualizar `AuthorizeHtml.refresh()`**
   - Fichero: `AuthorizeHtml.php`
   - Donde construye un `AuthorizedChalleges` a mano desde la sesión, construir directamente un `ChallengesState`
   - **Resultado**: El controller no necesita conocer el tipo legacy

6. **Eliminar `AuthorizedChalleges` y los métodos puente**
   - Eliminar: `Authentication/Domain/AuthorizedChalleges.php`
   - En `ChallengesState.php`: eliminar `toLegacy()` y `fromLegacy()`
   - **Resultado**: Un solo tipo para el estado de challenges, sin puentes ni conversiones

### Resultado de la fase

Un único Value Object inmutable (`ChallengesState`) gestiona el progreso de los challenges en todo el flujo. Añadir un nuevo challenge (ej. `scopeVerified`) es añadir un campo + un `with*()`, sin tocar conversiones legacy.

---

## Fase 2: Eliminar la capa Gateway redundante

### Motivo

El patrón actual tiene 4 capas para cada operación de dominio:

```
Usecase → Gateway (class concreta) → Repository (interface) → Adapter (class concreta)
```

La clase Gateway no añade ninguna lógica: solo reenvía cada llamada al Repository. Peor aún, las Gateways viven en `Domain/Gateway/` pero importan directamente las clases Adapter de `Infrastructure/` (ej. `LoginGateway` importa `LoginAdapter::class` para resolverlo del contenedor), violando la regla de dependencia del Clean Architecture. Esto también significa que no se puede sustituir el adapter sin modificar la Gateway.

### Tareas

1. **Hacer que los Usecases inyecten directamente la interfaz Repository**
   - Ficheros a modificar:
     - `User/Application/Usecase/LoginUsecase.php` → inyectar `LoginRepository` en vez de `LoginGateway`
     - `User/Application/Usecase/ConsentUsecase.php` → inyectar `ConsentRepository`
     - `User/Application/Usecase/ChangePasswordUsecase.php` → inyectar `ChangePasswordRepository`
     - `User/Application/Usecase/RegisterUserUsecase.php` → inyectar `RegisterUserRepository`
     - `Mfa/Application/Usecase/UserMfa.php` → inyectar `UserMfaRepository`
   - **Resultado**: Los usecases dependen solo de abstracciones del dominio

2. **Actualizar los consumidores directos de Gateways**
   - `PublicLogin.php` usa `SessionStoreGateway`, `ClientStoreGateway` → pasar a usar `SessionStoreRepository`, `ClientStoreRepository`
   - `HtmlSecurer.php` usa `TemporalKeysGateway` → pasar a `TemporalKeysRepository`
   - `AuthorizeHtml.php` usa `TemporalKeysGateway` → pasar a `TemporalKeysRepository`
   - `KeysManagerService.php` usa `TokenStoreGateway` → pasar a `TokenStoreRepository`
   - `DelegateLogin.php` (Application) usa `DelegateLoginGateway` → pasar a `DelegateLoginRepository`
   - **Resultado**: Ningún consumidor depende de clases concretas intermedias

3. **Registrar las interfaces en el contenedor DI**
   - En la configuración del contenedor: mapear cada `*Repository` interface → su `*Adapter` class
   - **Resultado**: La resolución de dependencias es explícita y configurable

4. **Eliminar todas las clases Gateway**
   - Eliminar: `LoginGateway.php`, `ConsentGateway.php`, `ChangePasswordGateway.php`, `RegisterUserGateway.php`, `UserMfaGateway.php`, `ClientStoreGateway.php`, `ApiKeyStoreGateway.php`, `DelegateLoginGateway.php`, `SessionStoreGateway.php`, `TemporalKeysGateway.php`, `TokenStoreGateway.php`
   - **Resultado**: 11 clases menos, cero violaciones domain → infrastructure

### Resultado de la fase

La cadena pasa a ser `Usecase → Repository (interface) → Adapter`. Se eliminan 11 clases que solo reenviaban llamadas, y el dominio ya no importa infraestructura. Sustituir un adapter (ej. para tests o cambiar de BD) es solo registrar otra implementación en el contenedor.

---

## Fase 3: Separar renderizado de autenticación en los formularios

### Motivo

Cada formulario (`LoginForm`, `ConsentForm`, etc.) implementa `OidcStep.run()` con una estructura bifurcada:

```php
if ($csid !== null) {
    // lógica de negocio: autenticar, cambiar password, verificar MFA...
    return StepResult::proceed($auth);
}
// lógica de presentación: construir HTML
return StepResult::render($response);
```

Esto significa que cada formulario tiene dos responsabilidades distintas en un solo método. Añadir un nuevo formulario obliga a mezclar ambas desde el principio. Además la firma de `OidcStep.run()` pasa 5 parámetros porque necesita cubrir ambos caminos.

### Tareas

1. **Crear dos interfaces separadas**
   - Crear `Forms/StepRenderer.php`:
     ```php
     interface StepRenderer {
         function render(StepInput $input, ResponseInterface $response, ?AuthenticationResult $error): ResponseInterface;
     }
     ```
   - Crear `Forms/StepAuthenticator.php`:
     ```php
     interface StepAuthenticator {
         function authenticate(StepInput $input): PublicLoginAuthResponse;
     }
     ```
   - **Resultado**: Contratos claros y separados para cada responsabilidad

2. **Refactorizar cada formulario para implementar ambas interfaces**
   - Cada formulario (8 ficheros) implementa `StepRenderer` y `StepAuthenticator`
   - El método `paint()` actual se renombra a `render()` y recibe `StepInput` directamente (no 8 parámetros sueltos)
   - La lógica de autenticación que estaba dentro de `run()` (bloque `if ($csid)`) se mueve a `authenticate()`
   - El método `autenticate()` legacy (con firma de 8 parámetros) se elimina
   - **Resultado**: Cada formulario tiene dos métodos con responsabilidad única y firmas simples

3. **Actualizar `OidcStepRouter` para orquestar el dispatch**
   - `OidcStepRouter.run()` pasa a decidir:
     ```php
     $form = $this->resolve($step, $error);
     if ($csid !== null) {
         $auth = $form->authenticate($input);
         return StepResult::proceed($auth);
     }
     $response = $form->render($input, $response, $error);
     return StepResult::render($response);
     ```
   - **Resultado**: La decisión render/authenticate se toma una sola vez en el router, no en cada formulario

4. **Eliminar `OidcStep.php`**
   - Ya no se necesita la interfaz unificada
   - **Resultado**: Las interfaces reflejan responsabilidades reales

### Resultado de la fase

Añadir un nuevo formulario (ej. verificación de scopes) significa implementar `StepRenderer` para el HTML y `StepAuthenticator` para la lógica, registrarlo en el router, y listo. No hay que replicar el patrón `if ($csid) {...} else {...}` ni preocuparse de pasar parámetros irrelevantes.

---

## Fase 4: Extraer `PublicLogin` como Application Services

### Motivo

`PublicLogin` vive en `Infrastructure/Driver/Html/Services/` pero es lógica de aplicación pura: orquesta usecases, gestiona sesiones, crea tokens. Está acoplada al driver HTML aunque su lógica la necesita también el `TokenController` (REST) y potencialmente cualquier otro driver. Además es un "God object" con ~300 líneas y responsabilidades dispares: autenticación, sesiones, registro, cambio de password, validación de clientes.

### Tareas

1. **Crear `Authentication/Application/AuthenticateUser.php`**
   - Mover aquí: `autenticate()`, `autenticateWithState()`, `preAutenticate()`, `sessionAutenticated()`, `saveIt()`
   - Este servicio orquesta: validar credenciales → crear sesión → construir response
   - Dependencias: `LoginRepository`, `SessionStoreRepository`, `KeysManagerService`
   - **Resultado**: La orquestación de autenticación vive en Application, accesible desde cualquier driver

2. **Crear `Authentication/Application/SessionManager.php`**
   - Mover aquí: `loadSession()`, `removeSesion()`
   - Dependencias: `SessionStoreRepository`
   - **Resultado**: Gestión de sesiones como servicio independiente

3. **Dejar operaciones de password/registro en sus Usecases existentes**
   - `askPassChange()`, `confirmPassChange()`, `validateForcePassChange()` → los formularios inyectan `ChangePasswordUsecase` directamente
   - `askRegisterUser()`, `confirmRegisterUser()` → los formularios inyectan `RegisterUserUsecase` directamente
   - `allowUserRegister()`, `allowUserRecoverPassword()`, `getRegisterConsent()` → los formularios consultan el usecase directamente
   - `publicClientData()` → se inyecta `ClientStoreRepository` donde se necesite
   - **Resultado**: Cada formulario inyecta solo lo que necesita, sin pasar por un intermediario

4. **Eliminar `Infrastructure/Driver/Html/Services/PublicLogin.php`**
   - **Resultado**: Desaparece el God object; cada servicio tiene una responsabilidad clara

5. **Actualizar formularios y controllers**
   - Los formularios inyectan `AuthenticateUser` para la autenticación
   - `AuthorizeHtml` inyecta `SessionManager` + `AuthenticateUser`
   - `LogoutController` inyecta `SessionManager`
   - **Resultado**: Cada consumidor declara sus dependencias explícitamente

### Resultado de la fase

La lógica de orquestación vive en Application layer, reutilizable por HTML y REST drivers. Cada nuevo formulario inyecta solo los servicios que necesita. El controlador `AuthorizeHtml` se simplifica significativamente.

---

## Fase 5: Desacoplar `PublicLoginAuthResponse` de `KeysManagerService`

### Motivo

`PublicLoginAuthResponse` vive en `User/Domain/` pero contiene una referencia a `KeysManagerService` (infraestructura de firma JWT). Esto significa que el dominio sabe cómo firmar tokens, cuando debería solo transportar los datos de claims. Además contiene `generateHash()` duplicado en `PublicLogin`.

### Tareas

1. **Convertir `PublicLoginAuthResponse` en un VO puro**
   - Fichero: `User/Domain/PublicLoginAuthResponse.php`
   - Quitar: constructor parameter `KeysManagerService`, métodos `withIdToken()`, `withAccessToken()`, `generateHash()`
   - Dejar: `authData`, `idData`, `sessionId`, `sessionExpiration`, `authExpiration`, `tenant` como propiedades públicas readonly
   - Mantener `asAuthenticationResult()` (es una transformación de datos, no usa infra)
   - **Resultado**: VO puro sin dependencias de infraestructura

2. **Mover la firma JWT al punto de uso**
   - En `AuthorizeHtml.redirectOk()`: usar `KeysManagerService` directamente para firmar los tokens con los datos del VO
   - En `TokenController`: igual, firmar en el controller
   - Extraer `generateHash()` como función estática en un util o inline donde se use
   - **Resultado**: La firma JWT es una responsabilidad del driver (infraestructura), no del dominio

3. **Actualizar `AuthenticateUser.saveIt()`** (creado en Fase 4)
   - Ya no pasa `KeysManagerService` al construir `PublicLoginAuthResponse`
   - Solo construye el VO con los datos de claims
   - **Resultado**: La capa Application no acopla domain a infra

### Resultado de la fase

`PublicLoginAuthResponse` es un VO transportable y testeable sin mock de servicios. La firma JWT queda como responsabilidad exclusiva de la capa de infraestructura (drivers).

---

## Fase 6: Limpiar duplicados y código legacy

### Motivo

Quedan artefactos de la evolución del código: clases duplicadas, código sin usar, bugs pendientes, y constantes mal ubicadas.

### Tareas

1. **Eliminar `PublicLoginSessionResponse`**
   - Fichero a eliminar: `Authentication/Infrastructure/Driver/Html/Entities/PublicLoginSessionResponse.php`
   - Usar `Session/Domain/SessionInfo` en su lugar (tienen los mismos 5 campos)
   - Actualizar `PublicLogin.loadSession()` (o su sucesor `SessionManager`) para devolver `SessionInfo`
   - **Resultado**: Un solo tipo para datos de sesión

2. **Eliminar `OtpMfaService`**
   - Fichero a eliminar: `Mfa/Domain/OtpMfaService.php`
   - No se usa en ningún sitio; la lógica está directamente en `UserMfaAdapter`
   - **Resultado**: Código muerto eliminado

3. **Extraer constantes de step a un enum `StepName`**
   - Crear: `Authentication/Domain/StepName.php` como `enum StepName: string`
   - Mover: `STEP_LOGIN`, `STEP_CONSENT`, `STEP_MFA`, etc. desde `StepInput`
   - Actualizar: `OidcStepRouter`, formularios, y cualquier referencia
   - **Resultado**: `StepInput` es solo datos de request; los nombres de step son un tipo seguro

4. **Corregir bug en `UserMfa.storeSeed()`**
   - Fichero: `Mfa/Application/Usecase/UserMfa.php`
   - El orden de parámetros es `storeSeed(seed, tenant, username)` pero Gateway espera `(tenant, username, seed)`
   - Corregir el orden en la llamada
   - **Resultado**: Bug corregido

5. **Eliminar `MfaDescriptor.php`**
   - Fichero: `Mfa/Domain/MfaDescriptor.php`
   - No se usa en ningún sitio del codebase
   - **Resultado**: Código muerto eliminado

### Resultado de la fase

Codebase limpio sin duplicados, sin código muerto, con tipos bien ubicados y un bug corregido.

---

## Resumen de impacto

| Métrica | Antes | Después |
|---------|-------|---------|
| Clases Gateway redundantes | 11 | 0 |
| Tipos duplicados para challenges | 2 (`AuthorizedChalleges` + `ChallengesState`) | 1 (`ChallengesState`) |
| Tipos duplicados para sesión | 2 (`SessionInfo` + `PublicLoginSessionResponse`) | 1 (`SessionInfo`) |
| Responsabilidades de `PublicLogin` | ~6 | 0 (eliminada, repartida en servicios cohesivos) |
| Violaciones domain → infrastructure | 12+ (Gateways + `PublicLoginAuthResponse`) | 0 |
| Esfuerzo para añadir un formulario nuevo | Copiar patrón `if/else` + inyectar God object + usar tipo legacy | Implementar 2 interfaces + registrar en router |

## Orden de ejecución

Las fases se ejecutan secuencialmente porque cada una reduce acoplamiento que la siguiente necesita:

1. **Fase 1** → elimina el tipo legacy que aparece en todas las firmas
2. **Fase 2** → simplifica la cadena de dependencias (menos clases que tocar en fases posteriores)
3. **Fase 3** → separa responsabilidades en formularios (prerequisito para reubicar la lógica)
4. **Fase 4** → extrae la orquestación de `PublicLogin` a Application layer
5. **Fase 5** → desacopla el VO de dominio de infraestructura
6. **Fase 6** → limpieza final de artefactos residuales
