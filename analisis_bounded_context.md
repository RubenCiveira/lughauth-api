# Analisis del Bounded Context: Access/User

## Resumen

El bounded context `src/Features/Access/User/` implementa la gestion CRUD + operaciones de estado (accept, reject, enable, disable, unlock) sobre usuarios. Es codigo **autogenerado** con arquitectura hexagonal bien estructurada: 148 ficheros PHP, ~10.000 lineas, 14 Value Objects, 14 eventos de dominio y 10 operaciones de negocio.

---

## 1. Revision Psalm (nivel 4)

### Resultados: 124 errores, 315 avisos informativos

| Tipo | Cantidad | Severidad | Descripcion |
|------|----------|-----------|-------------|
| `NullableReturnStatement` | 24 | error | Metodos que retornan null sin declararlo en el tipo |
| `InvalidArgument` | 17 | error | Argumentos con tipo incompatible |
| `InvalidNullableReturnType` | 15 | error | Tipo de retorno no permite null pero el metodo puede retornar null |
| `TooManyArguments` | 10 | error | Constructores llamados con argumentos extra |
| `RedundantCondition` | 9 | error | Condiciones que siempre son true/false |
| `InvalidReturnType` | 6 | error | Tipo de retorno incorrecto |
| `InvalidReturnStatement` | 6 | error | Sentencia return no coincide con tipo declarado |
| `MethodSignatureMismatch` | 6 | error | Firma de metodo no coincide con interfaz/parent |
| `TypeDoesNotContainNull` | 5 | error | Check de null sobre tipo que nunca es null |
| `InvalidDocblock` | 5 | error | PHPDoc incorrecto |
| `ParamNameMismatch` | 4 | error | Nombre de parametro difiere del padre |
| `RiskyTruthyFalsyComparison` | 99 | info | Comparaciones truthy/falsy sobre tipos ambiguos |
| `MissingReturnType` | 31 | info | Metodos sin tipo de retorno |
| `PossiblyNullArgument` | 36 | info | Argumento posiblemente null |
| `MissingParamType` | 17 | info | Parametro sin tipo |
| `MissingClassConstType` | 18 | info | Constante de clase sin tipo declarado |
| `MissingClosureParamType` | 15 | info | Closure sin tipo en parametro |
| `MissingConstructor` | 16 | info | Propiedades sin inicializar en constructor |

### Tareas para correccion Psalm

#### 1.1 Corregir errores criticos (124 errores)

**1.1.1 `TooManyArguments` (10)**
Los usecases de operaciones de estado (Accept, Reject, Disable, Enable, Unlock) pasan `$ref` como segundo argumento a `AllowDecision`, pero el constructor solo espera 1.

- Ficheros: `UserAcceptUsecase.php`, `UserRejectUsecase.php`, `UserDisableUsecase.php`, `UserEnableUsecase.php`, `UserUnlockUsecase.php` (metodos `allow*` y ejecucion)
- Accion: Verificar si `AllowDecision` deberia aceptar un segundo parametro (el UserRef) o si sobra

**1.1.2 `NullableReturnStatement` + `InvalidNullableReturnType` (39)**
Los Holder traits devuelven `?VO` pero los metodos `getXOrDefault()` no declaran nullable el retorno correctamente, o los metodos `build()` de `UserAttributes` asume non-null.

- Ficheros: 14 Holder traits + `UserAttributes.php` + Result/Params classes
- Accion: Declarar correctamente tipos nullable donde corresponda; usar asserts donde la logica garantice non-null

**1.1.3 `InvalidArgument` (17)**
Principalmente en llamadas a VOs donde se pasa un tipo union que psalm no puede verificar completamente.

- Accion: Revisar llamadas y añadir narrowing donde sea necesario

**1.1.4 `MethodSignatureMismatch` (6)**
Firmas de metodos en adapters/implementations que no coinciden con la interfaz.

- Accion: Alinear firmas exactamente con la interfaz

**1.1.5 `RedundantCondition` (9)**
Checks de null sobre valores que nunca son null.

- Accion: Eliminar condiciones redundantes

**1.1.6 `InvalidIterator` (1) en `UserVisibilityService.php:89`**
`checkVisibility()` acepta `\Iterator|string|UserRef` pero hace `foreach ($value)` que falla si `$value` es string.

- Accion: Reordenar los checks (string primero) o usar type narrowing explicito:
  ```php
  if (is_string($value)) {
      return !!$this->retrieveVisibleForUpdate(new UserRef($value));
  } elseif ($value instanceof UserRef) {
      return !!$this->retrieveVisibleForUpdate($value);
  } else {
      // Iterator case
  }
  ```

#### 1.2 Corregir avisos informativos prioritarios

**1.2.1 `MissingReturnType` (31)**
Metodos `with()`, `reject()`, `deny()`, `unset()`, `applyPreVisibilityFilter()` sin tipo de retorno.

- Accion: Añadir `: void`, `: static`, `: self` o el tipo correcto segun el caso

**1.2.2 `MissingParamType` (17)**
Parametro `$field` en `unset()` sin tipo.

- Accion: Tipar como `string`

**1.2.3 `RiskyTruthyFalsyComparison` (99)**
Las clases Result/Params usan `if ($this->name)` para comprobar si un campo fue asignado, pero el tipo es `VO|string|null` y un string vacio seria falsy.

- Accion: Cambiar a `if ($this->name !== null)` o usar el flag `_nameAssigned`

**1.2.4 `MissingClassConstType` (18)**
Las constantes `UNSETS` no tienen tipo declarado.

- Accion: Declarar `private const array UNSETS = [...]` (PHP 8.3+)

**1.2.5 `MissingClosureParamType` (15)**
Closures en `UserVisibilityService` y `UserSlide` sin tipo en parametro.

- Accion: Tipar `fn (User $item) => ...`

---

## 2. Problemas de calidad detectados

### 2.1 Bug logico en policies `IsAuthenticated*Allow`

**Severidad: CRITICA**

En `IsAuthenticatedCreateAllow.php:29` (y probablemente en las 10 policies):

```php
if (! !$userContext->anonimous) {
    $proposal->deny('Disabled if not IsAuthenticatedCreate');
}
```

La doble negacion `! !` es equivalente a `(bool)`. Si `$userContext->anonimous` es `true` (usuario anonimo), la condicion es `true` y deniega correctamente. **Funciona pero es confuso**. Deberia ser:

```php
if ($userContext->anonimous) {
    $proposal->deny('Disabled if not IsAuthenticatedCreate');
}
```

- Ficheros afectados: 10 policies en `Application/Policy/Allow/*/IsAuthenticated*Allow.php`
- Accion: Simplificar la doble negacion a una comparacion directa

### 2.2 Typos en nombres de clases y propiedades

| Typo | Correcto | Ubicacion | Impacto |
|------|----------|-----------|---------|
| `Wellcome` | `Welcome` | VO, Accessor, Holder, Formula, columna BD, API | Alto (afecta API publica y BD) |
| `Accesor` | `Accessor` | Directorio + 13 traits | Medio (afecta namespace) |
| `chypered` | `ciphered` | UserPasswordVO:87, UserSecondFactorSeedVO | Bajo (solo mensaje excepcion) |
| `anonimous` | `anonymous` | Context (shared) | Bajo (solo propiedad interna) |
| `enttiy` | `entity` | UserPdoConnector:417 (span name) | Bajo (solo tracing) |
| `hidratation` | `hydration` | UserVisibilityService:282 | Bajo (solo log) |
| `Unknow` | `Unknown` | UserVisibilityService:242 | Bajo (solo excepcion) |

**Nota:** Los typos `Wellcome` y `Accesor` afectan la API publica REST, la base de datos y los namespaces PHP. Corregirlos requiere migracion de BD y versionado de API. Valorar si se corrigen en una migracion mayor o se mantienen por retrocompatibilidad.

### 2.3 `UserPasswordVO.value()` expone los ultimos 2 caracteres

```php
public function value(): string
{
    return "****" . substr($this->password, -2);
}
```

El valor almacenado es `cyphered://[encrypted_data]`. Los ultimos 2 caracteres del texto cifrado no revelan la password real, pero exponer fragmentos del ciphertext no es buena practica. Deberia ser simplemente `return "******"`.

### 2.4 `UserPdoConnector` — clase demasiado larga (443 lineas)

Es la clase mas larga del contexto. Mezcla:
- Queries CRUD
- Logica de filtrado y paginacion
- Mapping SQL → entidad
- Verificacion de duplicados
- Locking optimista

Accion: Considerar extraer `UserQueryBuilder` para la logica de filtros (metodo `filter()`, ~100 lineas) y `UserRowMapper` para el mapping.

### 2.5 Transacciones gestionadas en controllers (Infrastructure)

Los REST controllers hacen `$this->db->beginTransaction()` / `commit()` / `close()`. Esto es una responsabilidad de Application layer.

Accion: Crear un decorator transaccional o middleware que envuelva las llamadas a usecases. No es urgente pero es una violacion arquitectural.

### 2.6 `InvalidOperand` en `UserVisibilityService:242`

```php
throw new NotFoundException("Unknow Tenant " . $attributes->getTenant());
```

`getTenant()` retorna `?TenantRef` que no es stringable. Deberia ser:

```php
throw new NotFoundException("Unknown Tenant " . ($attributes->getTenant()?->uid() ?? 'null'));
```

---

## 3. Problemas de seguridad

### 3.1 Sin rate limiting visible para operaciones de escritura

Los controllers REST no tienen rate limiting. Esto permite:
- Fuerza bruta sobre creacion de usuarios
- DoS por creacion masiva

Accion: Verificar si hay middleware de rate limiting a nivel de framework. Si no, añadir.

### 3.2 Encryption key management no visible

`AesCypherService` encripta passwords y MFA seeds, pero no se ve como se gestiona la rotacion de claves ni donde se almacena la master key.

Accion: Documentar o verificar que la clave AES se gestiona correctamente (variable de entorno, no hardcodeada, con rotacion).

### 3.3 Password validation insuficiente

`UserPasswordVO` solo valida longitud maxima (250 chars). No hay validacion de:
- Longitud minima
- Complejidad (mayusculas, numeros, caracteres especiales)
- Passwords comunes (diccionario)

Accion: Añadir reglas de complejidad en `UserPasswordVO::rules()` para plain text (antes de cifrar).

---

## 4. Tests unitarios — cobertura actual y tests faltantes

### 4.1 Cobertura actual (25 tests existentes)

| Capa | Tests existentes | Clases sin test |
|------|-----------------|-----------------|
| Domain/Entity | User, UserAttributes, UserRef | - |
| Domain/ValueObject | 14/14 VOs | - |
| Domain/Event | 0/14 eventos | Todos |
| Domain/Formula | 0/6 calculators | Todos |
| Domain/Gateway | 0/6 (interfaces/DTOs) | UserFilter, UserCursor |
| Application/Usecase | 8 Result/Params DTOs | 10 Usecases, 10 Checks, 10 AllowDecisions |
| Application/Policy | 0/12 | Todas las policies |
| Application/Visibility | 0/7 | UserVisibilityService + 6 eventos |
| Infrastructure/Connector | 0/1 | UserPdoConnector |
| Infrastructure/Adapter | 0/2 | Read/Write adapters |
| Infrastructure/Controller | 0/13 | Todos los controllers |
| Infrastructure/Batch | 0/7 | Reader + 6 task processors |

### 4.2 Tests unitarios a crear

#### Alta prioridad (logica de negocio)

**4.2.1 `UserCreateUsecaseTest`**
- Test: crea usuario correctamente con todos los campos
- Test: deniega si allowCreate retorna denied
- Test: ejecuta check y enrich events
- Test: aplica visibility (copyWithFixed, copyWithHidden)
- Test: lanza excepcion si check rechaza
- Mock: EventDispatcher, UserVisibilityService, UserWriteGateway

**4.2.2 `UserUpdateUsecaseTest`**
- Test: actualiza campos y genera UserUpdateEvent
- Test: respeta campos calculados (no sobreescribe wellcomeAt, etc.)
- Test: deniega si no autorizado
- Test: lanza NotFoundException si uid no existe
- Mock: EventDispatcher, UserVisibilityService, UserWriteGateway

**4.2.3 `UserAcceptUsecaseTest` / `UserRejectUsecaseTest`**
- Test: cambia approve a ACCEPTED/REJECTED
- Test: genera evento correcto (UserAcceptEvent/UserRejectEvent)
- Test: deniega si no autorizado

**4.2.4 `UserDisableUsecaseTest` / `UserEnableUsecaseTest` / `UserUnlockUsecaseTest`**
- Test: cambia estado enabled/blockedUntil
- Test: genera evento correcto
- Test: deniega si no autorizado

**4.2.5 `UserDeleteUsecaseTest`**
- Test: elimina y genera UserDeleteEvent
- Test: deniega si no autorizado
- Test: lanza si tiene dependencias (NotEmptyChildsException)

**4.2.6 `UserListUsecaseTest` / `UserRetrieveUsecaseTest`**
- Test: lista con filtros y paginacion
- Test: aplica visibility (solo muestra visibles)
- Test: retrieve retorna null si no visible

#### Media prioridad (policies y visibilidad)

**4.2.7 `IsAuthenticatedCreateAllowTest` (y las 9 variantes)**
- Test: permite si usuario autenticado
- Test: deniega si usuario anonimo
- Test: respeta la logica de la doble negacion actual

**4.2.8 `FixTenantExcludingRootTest`**
- Test: añade 'tenant' a campos no editables si no es root
- Test: root puede editar tenant

**4.2.9 `TenantAccesibleTest`**
- Test: restringe filtro al tenant del usuario
- Test: root admin ve todos los tenants
- Test: lanza UnauthorizedException si falta claim tenant

**4.2.10 `UserVisibilityServiceTest`**
- Test: listVisibles aplica pre-filter y post-filter
- Test: retrieveVisible retorna null si post-filter rechaza
- Test: fieldsToFix incluye campos calculados + campos no editables
- Test: fieldsToHide delega a UserCollectNonVisibleFields
- Test: copyWithFixed elimina campos fijados de UserAttributes
- Test: copyWithHidden elimina campos ocultos
- Test: checkVisibility verifica existencia via retrieveVisibleForUpdate
- Mock: EventDispatcher, UserReadGateway, UserWriteGateway, TenantVisibilityService

#### Baja prioridad (eventos, formulas, infraestructura)

**4.2.11 Tests de eventos (14)**
- Verificar que `eventType()`, `schemaVersion()`, `payload()` retornan valores correctos
- Verificar que `original()` retorna el estado previo en eventos de update

**4.2.12 Tests de Formula calculators (6)**
- Verificar defaults cuando no hay original (create)
- Verificar que preservan valor del original (update)

**4.2.13 Tests de `UserFilter`**
- Verificar inmutabilidad (clone en setters)
- Verificar cada filtro individualmente
- Verificar combinacion de filtros

**4.2.14 Tests de `UserCursor`**
- Verificar paginacion (limit, sinceUid, sinceName)
- Verificar metodo `next()`

---

## 5. Mejoras de eficiencia

### 5.1 `UserVisibilityService.checkVisibility()` con Iterator

Cuando recibe un `Iterator`, extrae todos los UIDs, crea un filtro, y ejecuta un `count`. Si el iterator es grande, esto carga todos los IDs en memoria.

Accion: Considerar un approach de batching si se espera iteradores grandes.

### 5.2 `UserPdoConnector.filter()` — concatenacion de SQL

Construye el SQL concatenando strings. Esto es correcto (usa SqlParam para valores) pero fragil para mantenimiento.

Accion: No es urgente, pero considerar un query builder si se añaden mas filtros.

### 5.3 Observability excesiva

Casi todos los metodos (incluidos helpers privados como `mapper`, `filter`, `theQuery`) crean spans de tracing. Esto genera muchos spans por operacion y puede impactar rendimiento en produccion.

Accion: Reducir spans a metodos publicos de alto nivel. Los metodos privados internos no necesitan span individual.

---

## 6. Resumen de tareas priorizadas

### Inmediatas (bugs y seguridad)

| # | Tarea | Ficheros | Impacto |
|---|-------|----------|---------|
| 1 | Corregir `InvalidOperand` en `UserVisibilityService:242` | 1 fichero | Bug en runtime |
| 2 | Corregir `InvalidIterator` en `UserVisibilityService:89` | 1 fichero | Bug en runtime |
| 3 | Corregir `TooManyArguments` en 5 usecases | 5 ficheros | Bug en runtime |
| 4 | Simplificar doble negacion `! !` en policies | 10 ficheros | Claridad |
| 5 | Eliminar exposicion de ciphertext en `UserPasswordVO.value()` | 1 fichero | Seguridad |

### Corto plazo (calidad Psalm)

| # | Tarea | Ficheros | Impacto |
|---|-------|----------|---------|
| 6 | Añadir return types faltantes | ~31 metodos | Psalm clean |
| 7 | Añadir param types faltantes | ~17 parametros | Psalm clean |
| 8 | Corregir `NullableReturnStatement` + `InvalidNullableReturnType` | ~39 sitios | Psalm clean |
| 9 | Declarar tipos en constantes `UNSETS` | ~18 constantes | Psalm clean |
| 10 | Cambiar comparaciones truthy a `!== null` | ~99 sitios | Psalm clean |

### Medio plazo (tests)

| # | Tarea | Tests nuevos |
|---|-------|-------------|
| 11 | Tests de 10 Usecases | ~10 ficheros, ~50 tests |
| 12 | Tests de 10+2 policies | ~12 ficheros, ~30 tests |
| 13 | Test de UserVisibilityService | 1 fichero, ~15 tests |
| 14 | Tests de eventos y formulas | ~20 ficheros, ~40 tests |
| 15 | Tests de UserFilter y UserCursor | 2 ficheros, ~15 tests |

### Largo plazo (refactoring)

| # | Tarea | Impacto |
|---|-------|---------|
| 16 | Extraer `UserQueryBuilder` de `UserPdoConnector` | Mantenibilidad |
| 17 | Mover transacciones de controllers a Application | Arquitectura |
| 18 | Añadir validacion de complejidad de password | Seguridad |
| 19 | Reducir spans de observabilidad en metodos privados | Rendimiento |
| 20 | Corregir typos `Wellcome`/`Accesor` (requiere migracion BD) | Consistencia |
