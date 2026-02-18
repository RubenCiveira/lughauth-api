# Analisis de Arquitectura: `src/Shared/` y `src/Bootstrap/`

## 1. Inventario de Componentes

### 1.1 `src/Shared/` — Namespace raiz: `Civi\Lughauth\Shared`

```
src/Shared/
├── AppConfig.php                  # Configuracion de aplicacion (env + YAML)
├── Context.php                    # Contexto de ejecucion (identidad, conexion, URLs)
├── Test.php                       # Clase de prueba/debug (residual)
├── Connector/FileStorage/         # Abstraccion de almacenamiento de ficheros
│   ├── BinaryContent.php
│   ├── FileStorageInterface.php
│   └── FileStoreKey.php
├── Event/                         # Contratos del sistema de eventos
│   ├── EventListenersRegistrarInterface.php
│   └── PublicEvent.php
├── Exception/                     # Excepciones de dominio compartidas
│   ├── ConstraintException.php
│   ├── NotEmptyChildsException.php
│   ├── NotFoundException.php
│   ├── NotUniqueException.php
│   ├── OptimistLockException.php
│   └── UnauthorizedException.php
├── Value/                         # Value objects y validacion
│   ├── Random.php
│   ├── StreamResource.php
│   ├── UploadBinaryContent.php
│   └── Validation/               # Motor de reglas de validacion (60+ reglas)
│       ├── ConstraintFail.php
│       ├── ConstraintFailList.php
│       ├── HasContraintFailList.php
│       ├── Rule.php
│       ├── RuleFail.php
│       └── Rule/                 # Reglas concretas (NotEmpty, Email, Uuid, etc.)
├── Observability/                 # Traits y helpers de observabilidad
│   ├── LoggerAwareTrait.php
│   ├── MetricAwareTrait.php
│   ├── TracerAwareTrait.php
│   ├── SpanHolder.php
│   ├── TraceContext.php
│   └── Status.php
├── Security/                      # Modelo de seguridad
│   ├── AesCypherService.php
│   ├── Allow.php
│   ├── AllowDecision.php
│   ├── Connection.php
│   ├── FieldsAccess.php
│   ├── Identity.php
│   └── Rbac/                     # Integracion RBAC con servicio Lugh
│       ├── AllowListener.php
│       ├── FieldsListener.php
│       ├── Handler.php
│       ├── LughMapper.php
│       └── Mapper.php
└── Infrastructure/                # Infraestructura del framework
    ├── Micro.php                  # Orquestador principal del microservicio
    ├── MicroConfig.php            # Feature flags
    ├── MicroPlugin.php            # Clase base de plugins
    ├── AggregatedMicroPlugin.php  # Plugin compuesto (Composite)
    ├── TemplateMapper.php         # Servir ficheros estaticos y templates
    ├── StartupProcess.php         # Interface para tareas de arranque
    ├── StartupProcessor.php       # Ejecutor ordenado de tareas de arranque
    ├── Audit/                     # Sistema de auditoria de queries SQL
    ├── Connector/FileStorage/     # Implementacion PDO de file storage
    ├── EntityAudit/               # Auditoria de entidades
    ├── EntityChangelog/            # Changelog de entidades
    ├── Event/                     # Implementacion del bus de eventos
    ├── Http/                      # Utilidades HTTP (Cookie)
    ├── Log/                       # Handlers de logging (Monolog + gzip)
    ├── LongTask/                  # Gestion de tareas de larga duracion
    ├── Management/                # Endpoints de gestion (health, metrics, config...)
    ├── Middelware/                # Middlewares HTTP (JWT, CORS, Rate, Metrics...)
    ├── MicroPlugin/               # Plugins internos (Errors, Management, Security)
    ├── Scheduler/                 # Planificador de tareas en background
    ├── Sql/                       # Helpers SQL (SqlTemplate, SqlParam)
    ├── Translation/               # i18n
    └── View/                      # Integracion con Twig
```

### 1.2 `src/Bootstrap/` — Namespace raiz: `Civi\Lughauth\Bootstrap`

```
src/Bootstrap/
├── Install.php                    # Instalacion y compilacion (migraciones, DI, OpenAPI)
├── Security/                      # (directorio vacio)
└── Plugin/
    ├── AccessPlugin.php           # Agrega 10 plugins de control de acceso
    ├── MultiTenantPlugin.php      # Orquesta multi-tenancy + eventos OIDC
    ├── OidcPlugin.php             # Rutas OpenID Connect / OAuth 2.0
    └── TestPlanPlugin.php         # Plugin de plan de tests
```

---

## 2. Analisis Arquitectonico

### 2.1 Patron General

El proyecto sigue una arquitectura de **microservicio modular basada en plugins** sobre Slim Framework + PHP-DI. La clase `Micro` actua como compositor central que:
1. Construye el contenedor DI
2. Registra middlewares condicionalmente segun `MicroConfig`
3. Itera plugins para registrar rutas, eventos, schedulers y management endpoints
4. Ejecuta tareas de startup con bloqueo de ficheros
5. Arranca la app HTTP o el scheduler CRON

### 2.2 Separacion de Responsabilidades

| Capa | Ubicacion | Rol |
|------|-----------|-----|
| **Domain Shared** | `Shared/Value/`, `Shared/Exception/`, `Shared/Event/`, `Shared/Security/` (modelos) | Contratos y value objects reutilizables |
| **Application Shared** | `Shared/Observability/`, `Shared/Connector/` | Traits y abstracciones de aplicacion |
| **Infrastructure Framework** | `Shared/Infrastructure/` | Framework del microservicio (Micro, plugins, middlewares, SQL, etc.) |
| **Bootstrap** | `Bootstrap/` | Composicion especifica de la aplicacion LughAuth |

### 2.3 Diagrama de Dependencias (simplificado)

```
Bootstrap/Plugin/* ──────► Shared/Infrastructure/MicroPlugin
Bootstrap/Install  ──────► Shared/AppConfig
                           Shared/Infrastructure/Management/Migration/Phix

Features/*         ──────► Shared/* (consumidores)

Shared/Infrastructure/Micro ──► Shared/AppConfig
                                Shared/Context
                                Shared/Infrastructure/* (todos los subsistemas)
```

---

## 3. Hallazgos y Recomendaciones

### 3.1 Errores Ortograficos en Nombres de Paquetes y Variables

| Actual | Correcto | Ubicacion | Impacto |
|--------|----------|-----------|---------|
| `Middelware` | `Middleware` | `Shared/Infrastructure/Middelware/` | **ALTO** — Namespace completo con typo, afecta a todos los imports |
| `depenencies` | `dependencies` | `Micro.php:140` (parametro constructor) | MEDIO — Variable privada |
| `withTelementry` | `withTelemetry` | `Micro.php:347` (metodo privado) | BAJO — Metodo privado |
| `dispacher` | `dispatcher` | `Micro.php:516` (propiedad de EventBus) | MEDIO — Referenciado en definiciones DI |
| `anonimous` | `anonymous` | `Identity.php:32,42`, `Context.php:32` | **ALTO** — API publica usada en todo el proyecto |
| `deseiralize` | `deserialize` | `JwtVerifierMiddleware.php:158` | BAJO — Metodo privado |
| `clousure` | `closure` | `SqlTemplate.php:96,110,124,135` | BAJO — Parametro local |
| `NotEmptyChildsException` | `NotEmptyChildrenException` | `Exception/NotEmptyChildsException.php` | MEDIO — Nombre de clase publica |
| `HasContraintFailList` | `HasConstraintFailList` | `Value/Validation/HasContraintFailList.php` | MEDIO — Nombre de trait |
| `AesCypherService` | `AesCipherService` | `Security/AesCypherService.php` | MEDIO — Nombre de clase publica |
| `enviroment` | `environment` | `Context.php:80` | BAJO — Clave de config interna |

**Recomendacion**: Corregir progresivamente con alias de backwards-compatibility o en una migracion coordinada. Priorizar `Middelware` → `Middleware` y `anonimous` → `anonymous` por ser los de mayor superficie de impacto.

---

### 3.2 Arquitectura de Codigo

#### 3.2.1 `Micro.php` es un God Class (644 lineas)

La clase `Micro` concentra demasiadas responsabilidades:
- Construccion del contenedor DI (`build()`)
- Configuracion de cada subsistema (`withCache`, `withDatabase`, `withLogging`... 10 metodos `with*`)
- Registro de middlewares
- Registro de management endpoints
- Ciclo de vida de startup
- Ejecucion HTTP y CRON
- Supervisor de background
- Clase `InjectResourceAttrsProcessor` definida en el mismo fichero

**Recomendacion**: Extraer responsabilidades en clases dedicadas:

```
Infrastructure/
├── Micro.php                          # Solo orquestacion y run()
├── MicroBuilder.php                   # build() + with*() → ServiceProviders
├── ServiceProvider/
│   ├── CacheServiceProvider.php
│   ├── DatabaseServiceProvider.php
│   ├── LoggingServiceProvider.php
│   ├── TelemetryServiceProvider.php
│   ├── MetricsServiceProvider.php
│   ├── RateLimitServiceProvider.php
│   ├── HttpClientServiceProvider.php
│   ├── EventBusServiceProvider.php
│   ├── LockServiceProvider.php
│   └── AuditServiceProvider.php
└── Telemetry/
    └── InjectResourceAttrsProcessor.php  # Clase propia
```

#### 3.2.2 `MicroConfig` no permite personalizacion

El constructor de `MicroConfig` hardcodea todos los flags a `true` sin permitir parametros:

```php
public function __construct()
{
    $this->withRate = true;
    // ...
}
```

**Recomendacion**: Aceptar parametros con valores por defecto:

```php
public function __construct(
    public readonly bool $withRate = true,
    public readonly bool $withMetrics = true,
    public readonly bool $withTelemetry = true,
    public readonly bool $withManagement = true,
    public readonly bool $withAudit = true,
) {}
```

#### 3.2.3 `AppConfig::$develop` siempre es `true`

En `AppConfig.php:28`:
```php
$this->develop = true;
```

No se lee de configuracion ni de entorno. Esto significa que en produccion se exponen stack traces y se activa el modo debug de Slim.

**Recomendacion**: Leer de entorno:
```php
$this->develop = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
```

#### 3.2.4 Clase `Test.php` residual

`Shared/Test.php` es una clase de prueba que no deberia estar en el codigo de produccion.

**Recomendacion**: Eliminar o mover a `tests/`.

---

### 3.3 Seguridad

#### 3.3.1 Clave de cifrado por defecto insegura

En `AesCypherService.php:54`:
```php
$this->cipherKey = $clave ?? 'clave-cifrado';
```

Si no se proporciona una clave, se usa un literal hardcodeado predecible. Esto anula toda la seguridad del cifrado AES-256-GCM.

**Recomendacion CRITICA**: Lanzar excepcion si no se proporciona clave, o leer de configuracion obligatoria:
```php
$this->cipherKey = $clave ?? throw new \RuntimeException('Cipher key must be configured');
```

#### 3.3.2 JWT desde query string y cookie sin proteccion CSRF

En `JwtVerifierMiddleware.php:71-80`:
```php
} elseif (isset($_COOKIE['Authorization']) && 'GET' == $request->getMethod()) {
    $this->verifyAuth($_COOKIE['Authorization'], Identity::AUTH_SCOPE_READ);
} elseif (isset($_GET['Authorization']) && 'GET' == $request->getMethod()) {
```

- Tokens JWT en query string quedan en logs de servidores, proxies e historial del navegador.
- Tokens en cookies sin verificacion CSRF permiten ataques cross-site.

**Recomendacion**:
- Eliminar soporte de JWT por query string o marcarlo como deprecado con logging de warning.
- Implementar verificacion de origen (header `Origin`/`Referer`) o token CSRF para cookies.

#### 3.3.3 Caching de tokens JWT invalidos

En `JwtVerifierMiddleware.php:107-116`, los tokens invalidos se cachean con su error:
```php
$this->cache->set($cache_key, json_encode([null, null, $fail]));
```

Pero la cache key es el propio token completo sin TTL explicito. Esto puede:
1. Llenar la cache con tokens invalidos (ataque DoS)
2. Mantener tokens expirados como "conocidos" indefinidamente

**Recomendacion**: Establecer TTL corto para tokens invalidos y limitar el tamano de la cache key (usar hash del token).

#### 3.3.4 Verificacion de `nbf` invertida

En `JwtVerifierMiddleware.php:109`:
```php
} elseif (($now - $nbf) > 1000) {
    $fail = 'The provided JWT is not ready for use.';
```

Si `now - nbf > 1000` (token emitido hace mas de 1000 segundos), lo rechaza. Esto no es la semantica correcta de `nbf` (Not Before). La comprobacion deberia ser `now < nbf` para rechazar tokens que aun no son validos.

**Recomendacion CRITICA**: Corregir la logica:
```php
if ($now < $nbf) {
    $fail = 'The provided JWT is not ready for use.';
}
```

#### 3.3.5 `exec("rm -rf ...")` en Install.php

En `Install.php:49`:
```php
exec("rm -rf " . escapeshellarg($cacheDir));
```

Aunque usa `escapeshellarg`, el uso de `exec` con `rm -rf` es arriesgado. Si `realpath` devuelve una ruta inesperada, las consecuencias son graves.

**Recomendacion**: Usar funciones PHP nativas para borrado recursivo o la clase `Filesystem` de Symfony.

#### 3.3.6 Valores de telemetria hardcodeados

En `Micro.php:369-375`:
```php
'service.name'        => 'phylax-logs',
'service.namespace'   => 'backoffice',
'service.version'     => '1.4.2',
```

Estos valores estan hardcodeados en lugar de leerse de configuracion, contradiciendose con `Context::getInstanceData()` que si lee de config.

**Recomendacion**: Usar `Context::getInstanceData()` o la configuracion directamente.

#### 3.3.7 Permisos de directorios

Multiples llamadas a `mkdir($dir, 0777, true)` crean directorios con permisos abiertos a todos.

**Recomendacion**: Usar `0755` o `0750` como permisos por defecto.

---

### 3.4 Calidad y Mantenibilidad

#### 3.4.1 Acceso directo a superglobales

`$_SERVER`, `$_ENV`, `$_COOKIE`, `$_GET` se acceden directamente en multiples clases:
- `Micro.php:193` — `$_SERVER['SCRIPT_NAME']`
- `Context.php:52-63` — `$_SERVER['HTTPS']`, `$_SERVER['HTTP_HOST']`, etc.
- `Connection.php:28-48` — `$_SERVER['HTTP_ACCEPT_LANGUAGE']`, `$_SERVER['REMOTE_ADDR']`, etc.
- `JwtVerifierMiddleware.php:71-79` — `$_COOKIE`, `$_GET`

Esto dificulta el testing unitario y viola el principio de inyeccion de dependencias del propio framework.

**Recomendacion**: Encapsular el acceso a superglobales en un `ServerEnvironment` inyectable o usar exclusivamente el `ServerRequestInterface` de PSR-7 que ya esta disponible.

#### 3.4.2 Comentarios en espanol mezclados con ingles

El codigo mezcla comentarios y mensajes en espanol e ingles:
- `// Opcional: definir base path si tu app no esta en "/"`
- `// Middleware para parsear json`
- `$fail = 'The provided JWT is not valid.'`

**Recomendacion**: Unificar todo a ingles para consistencia y colaboracion internacional.

#### 3.4.3 Duplicacion entre `query` y `queryForUpdate` / `findOne` y `findOneForUpdate`

En `SqlTemplate.php`, los metodos `query` y `queryForUpdate` son identicos, al igual que `findOne` y `findOneForUpdate`, y `exists` y `existsForUpdate`.

**Recomendacion**: Unificar o agregar el `FOR UPDATE` real a la query SQL en las variantes `*ForUpdate`.

#### 3.4.4 Metodo `execute()` retorna tipos mixtos

En `SqlTemplate.php:87`:
```php
return $result ? $stmt->rowCount() > 0 : 0;
```

Declara retorno `bool` pero retorna `0` (int) en caso de fallo.

**Recomendacion**: Retornar siempre `bool`:
```php
return $result && $stmt->rowCount() > 0;
```

#### 3.4.5 Error handler que hace echo

En `Micro.php:223-226`:
```php
$this->errorHandler->setErrorHandler(Exception::class, function (...) {
    echo $exception->getTraceAsString();
    return new Response();
});
```

`echo` en un error handler rompe la respuesta HTTP (mezcla salida directa con PSR-7). Ademas, expone stack traces al cliente.

**Recomendacion**: Escribir la traza en el logger y devolver una respuesta JSON apropiada.

#### 3.4.6 Metodo `Install::info()` vacio

`Install.php:95-97`: Metodo vacio sin implementacion ni documentacion.

**Recomendacion**: Eliminar o implementar.

#### 3.4.7 Codigo comentado en `Install::compileDi()`

Grandes bloques de codigo comentado en `Install.php:127-143` y `Install.php:159-161`.

**Recomendacion**: Eliminar codigo muerto. El historial de git conserva las versiones anteriores.

---

### 3.5 Rendimiento

#### 3.5.1 Contenedor DI no compilado

En `Micro::build()`, si no existe el fichero cache de definiciones, se construye el contenedor desde cero en cada request. PHP-DI soporta compilacion a fichero PHP para produccion.

**Recomendacion**: Habilitar `$builder->enableCompilation()` para produccion.

#### 3.5.2 JWKS sin TTL de renovacion adecuado

En `JwtVerifierMiddleware::getJwks()`, la cache de JWKS usa `PT1H` como TTL. Si las claves rotan, hay un gap de hasta 1 hora.

**Recomendacion**: Implementar un mecanismo de invalidacion anticipada o reducir el TTL con cache de fallback.

#### 3.5.3 TemplateMapper comprime en memoria

`TemplateMapper::asStatic()` lee todo el fichero en memoria y lo comprime con gzip para cada peticion.

**Recomendacion**: Pre-comprimir ficheros estaticos (`.gz` junto al original) o delegar la compresion al web server (nginx/Apache).

#### 3.5.4 `InjectResourceAttrsProcessor` se ejecuta por cada span

`Context::getInstanceData()` se llama en cada `onStart()`, recalculando datos que no cambian entre requests.

**Recomendacion**: Cachear el resultado de `getInstanceData()` en una propiedad del processor.

#### 3.5.5 PBKDF2 con 100.000 iteraciones por cada operacion

`AesCypherService` ejecuta PBKDF2 con 100K iteraciones en cada encrypt/decrypt. Si se encadena en un loop, puede ser un cuello de botella.

**Recomendacion**: Cachear la clave derivada cuando se usa la misma password repetidamente, o considerar Argon2id para operaciones no compatibles con Java.

#### 3.5.6 HTTP/1.0 hardcodeado en el cliente HTTP

En `Micro.php:535`:
```php
'http_version' => '1.0',
```

HTTP/1.0 no soporta keep-alive ni chunked transfer encoding, forzando una nueva conexion TCP por cada request.

**Recomendacion**: Usar `'1.1'` o `'2.0'`.

---

### 3.6 Sugerencias de Renombrado de Paquetes

El namespace actual `Civi\Lughauth\Shared` mezcla tres niveles de abstraccion:

1. **Framework de microservicios** (reutilizable entre proyectos)
2. **Utilidades compartidas de dominio** (especificas de LughAuth)
3. **Codigo de bootstrap** (especifico de la aplicacion)

#### Propuesta de reestructuracion de namespaces:

```
# ANTES                                    # DESPUES
Civi\Lughauth\Shared\Infrastructure\       Civi\Micro\                        # Framework
  Micro.php                                  Micro.php
  MicroConfig.php                            MicroConfig.php
  MicroPlugin.php                            Plugin\MicroPlugin.php
  AggregatedMicroPlugin.php                  Plugin\AggregatedPlugin.php
  Middleware\*                               Middleware\*  (corregir typo!)
  Sql\*                                      Database\*
  Log\*                                      Logging\*
  Scheduler\*                                Scheduler\*
  Management\*                               Management\*
  LongTask\*                                 Task\*
  View\*                                     View\*
  Event\*                                    Event\*
  Audit\*                                    Audit\*

Civi\Lughauth\Shared\                      Civi\Lughauth\Shared\              # Dominio compartido
  AppConfig.php                              Config\AppConfig.php
  Context.php                                Context\ApplicationContext.php
  Security\*                                 Security\*
  Connector\*                                Connector\*
  Event\*                                    Event\*
  Exception\*                                Exception\*
  Value\*                                    Value\*
  Observability\*                            Observability\*

Civi\Lughauth\Bootstrap\                   Civi\Lughauth\Bootstrap\           # Sin cambios
  Install.php
  Plugin\*
```

#### Cambios de nombre puntuales:

| Actual | Propuesto | Razon |
|--------|-----------|-------|
| `Middelware/` | `Middleware/` | Correccion ortografica |
| `Infrastructure/Sql/` | `Infrastructure/Database/` o `Persistence/` | Mas descriptivo, permite incluir no-SQL |
| `Infrastructure/Log/` | `Infrastructure/Logging/` | Consistencia con convention PSR |
| `Infrastructure/LongTask/` | `Infrastructure/Task/` o `BackgroundTask/` | Mas conciso |
| `MicroPlugin/` (bajo Infrastructure) | `Plugin/Internal/` | Clarifica que son plugins internos del framework |
| `Connector/FileStorage/` | `Storage/` | Simplificacion |
| `Management/Collector/` | `Management/Telemetry/` | Mas descriptivo |
| `EntityAudit/` + `EntityChangelog/` | `ChangeTracking/` | Unificar conceptos relacionados |

---

### 3.7 Problemas de Acoplamiento Bootstrap ↔ Shared

El contrato indica que **Bootstrap no debe ser llamado desde ningun sitio** (solo punto de entrada). Sin embargo:

1. `Install.php` importa `Shared\AppConfig` y `Shared\Infrastructure\Management\Migration\Phix` — esto es **correcto**, Bootstrap depende de Shared.
2. No se ha encontrado ninguna dependencia inversa (Shared → Bootstrap) — **correcto**.
3. Los plugins de `Bootstrap/Plugin/` extienden clases de `Shared/Infrastructure/` — **correcto**, es composicion.

**Estado: La frontera de dependencias se respeta.**

Sin embargo, el directorio `Bootstrap/Security/` esta vacio. Deberia eliminarse o documentarse su proposito futuro.

---

## 4. Resumen de Prioridades

### Critico (corregir inmediatamente)
1. `AppConfig::$develop` siempre `true` — expone debug en produccion
2. `AesCypherService` con clave por defecto `'clave-cifrado'` — cifrado inutil
3. Verificacion `nbf` invertida en `JwtVerifierMiddleware` — logica de seguridad rota
4. `echo` en error handler de `Micro.php` — fuga de stack traces

### Alto (planificar en proximo sprint)
5. Renombrar `Middelware/` → `Middleware/` (namespace con typo)
6. Renombrar `anonimous` → `anonymous` (API publica)
7. Eliminar JWT via query string o documentar sus riesgos
8. Usar permisos `0755` en lugar de `0777` para directorios

### Medio (deuda tecnica)
9. Refactorizar `Micro.php` en ServiceProviders
10. Parametrizar `MicroConfig` con constructor con argumentos
11. Eliminar `Test.php`
12. Desacoplar acceso a superglobales (`$_SERVER`, `$_COOKIE`, `$_GET`)
13. Valores de telemetria hardcodeados en `Micro::withTelementry()`
14. Unificar idioma de comentarios a ingles
15. Eliminar codigo muerto en `Install.php`

### Bajo (mejora continua)
16. Compilar contenedor DI para produccion
17. Pre-comprimir assets estaticos
18. Cachear `getInstanceData()` en el span processor
19. Actualizar `http_version` de `'1.0'` a `'1.1'`
20. Corregir typos menores (`depenencies`, `clousure`, `deseiralize`, etc.)
21. Unificar/diferenciar metodos `*ForUpdate` en `SqlTemplate`

---

## 5. Metricas del Analisis

| Metrica | Valor |
|---------|-------|
| Ficheros PHP en `Shared/` | ~120+ |
| Ficheros PHP en `Bootstrap/` | 5 |
| Lineas en `Micro.php` | 644 |
| Errores ortograficos en APIs publicas | 6 |
| Hallazgos de seguridad criticos | 4 |
| Hallazgos de rendimiento | 6 |
| Clases/ficheros muertos o residuales | 2 (`Test.php`, `Bootstrap/Security/`) |
