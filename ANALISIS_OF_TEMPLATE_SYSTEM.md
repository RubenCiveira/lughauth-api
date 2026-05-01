# Análisis del sistema de plantillas y temas

## 1. Estado actual

### 1.1 Entidades del contexto Document

| Entidad | Propósito | Campos clave |
|---|---|---|
| `Template` | Descriptor de una plantilla funcional | `code`, `channel` (MAIL/SMS/HTML), `tenant?`, `theme?`, `enabled` |
| `TemplateVersion` | Contenido versionado de una plantilla | `template`, `subject`, `contentHtml`, `contentText`, `version` |
| `TemplateAsset` | Fichero binario de una plantilla | `template` *(FK — a añadir)*, `filename`, `type`, `content` (FileStorage), `enabled` ← `code` con prefijo convencional a eliminar |
| `TemplateVariable` | Variable con valor por defecto a nivel tenant | `code`, `tenant`, `type`, `value`, `enabled` |
| `TemplateSnippet` | Descriptor de bloque de contenido reutilizable (pie legal, aviso GDPR…) | `code`, `tenant`, `enabled` |
| `TemplateSnippetVersion` *(nuevo)* | Contenido versionado y localizable de un snippet (paralelo a TemplateVersion) | `snippet`, `locale?`, `contentHtml`, `version` |
| `Theme` | Metadato del tema visual | `tenant`, `name`, `isDefault`, `customCss` *(→ deprecar)*, `enabled` |
| `ThemeVersion` *(nuevo)* | Chrome versionado y localizable del tema (paralelo a TemplateVersion) | `theme`, `channel`, `locale?`, `contentHtml`, `version` |
| `ThemeAsset` *(nuevo)* | Fichero binario de un tema | `theme` (FK), `filename`, `type`, `content` (FileStorage), `enabled` |
| `TemplateSnippetAsset` *(nuevo)* | Fichero binario de un snippet | `snippet` (FK), `filename`, `type`, `content` (FileStorage), `enabled` |

### 1.2 Motor de renderizado existente (Document/Rendering)

`TemplateRenderUsecase` → `HandlebarsTemplateRenderAdapter`

Flujo completo:
1. Resuelve `Template` (tenant-específico primero, luego global)
2. Selecciona el `TemplateVersion` más reciente
3. Mezcla `TemplateVariable` del tenant con variables de llamada (overrides ganan)
4. Carga `TemplateSnippet` del tenant → disponibles como `{{>SNIPPET_CODE}}`
5. Carga CSS del `Theme` asociado → `RenderedAsset`
6. Delega la sustitución a `HandlebarsTemplateRenderAdapter`

Soporta:
- `{{variable}}` y `{{object.field}}` (dotted-key expansion)
- `{{{rawHtml}}}` sin escape
- `{{>SNIPPET}}` (parciales Handlebars)
- CSS inlining para formato `HTML_EMBEDDED`

### 1.3 DecorateHtml — estado actual

`DecorateHtml.getFullPage(request, title, innerContent, locale, template, tenantDomain)`:

1. Resuelve nombre de tema desde BD → fallback a 'corporate' del filesystem
2. Copia ficheros del tema al directorio `.assets/oidc/{theme}/`
3. Busca `page.{template}` en BD (tenant primero, luego global)
4. Si existe: renderiza con **Twig** (motor distinto al de Document/Rendering)
5. Si no existe: carga `{theme}/{template}.php` del filesystem y ejecuta su closure

Dependencias inyectadas: `TenantReadGateway`, `ThemeReadGateway`, `TemplateReadGateway`,
`TemplateVersionReadGateway`, `TemplateAssetReadGateway`.

---

## 2. Problemas identificados

### P1 — Dos motores de renderizado

`Document/Rendering` usa **Handlebars**; `DecorateHtml` usa **Twig**.
No hay forma de compartir lógica, variables ni tests entre ambos canales.

### P2 — DecorateHtml duplica lógica de TemplateRenderUsecase

`DecorateHtml` reimplementa a mano resolución tenant→global, selección de versión más
reciente y renderizado con variables. Todo eso ya existe en `TemplateRenderUsecase`.

### P3 — Sin soporte de internacionalización en las plantillas

`TemplateVersion` no tiene campo `locale`. No es posible registrar en BD la versión en
castellano, francés o alemán de una misma plantilla. El texto visible está acoplado al
código, no a la configuración del tenant ni del idioma del usuario.

### P4 — Assets duplicados entre page.index y page.full

Los mismos binarios de `corporate/style/` se almacenan dos veces en FileStorage: una con
código `page.index/{filename}` y otra con `page.full/{filename}`. La duplicación crece
linealmente con cada nuevo tipo de página.

### P5 — TemplateAsset ligado al tenant inicial pero Template es global

Las plantillas `page.index` y `page.full` se instalan sin tenant (globales). Sus
`TemplateAsset` se crean con `tenant = $createdTenant` (el del install). Para cualquier
otro tenant los assets por defecto son invisibles: la página se renderiza sin CSS ni imágenes.

### P6 — Theme.customCss infrautilizado

`TemplateRenderUsecase` carga `customCss` solo para plantillas con `template.theme`
asignado. Las plantillas `page.*` no tienen `theme` asignado, por lo que el CSS del tema
no se aplica. La personalización por tenant requiere editar código.

### P7 — TemplateSnippet carece de versionado y soporte de locale

`TemplateSnippet` es la abstracción correcta para **bloques de contenido reutilizables**
(pie de condiciones legales, aviso GDPR, dirección fiscal, enlace de baja…) que deben
poder incrustarse tanto en plantillas funcionales como en el chrome del tema mediante la
sintaxis nativa de Handlebars `{{>snippet_code}}`, ya implementada en
`HandlebarsTemplateRenderAdapter`.

Sin embargo, en su forma actual tiene carencias estructurales:

- **Sin versionado de contenido**: `contentHtml` está directamente en la entidad. Un cambio
  de texto sobreescribe el historial sin posibilidad de rollback ni de publicación controlada.
- **Sin locale**: no hay forma de tener el aviso legal en castellano, francés y alemán dentro
  del mismo snippet; habría que duplicar la entidad con distintos códigos.
- **Rol mezclado con chrome**: actualmente se usa también para parciales de layout
  (`{{>page.head}}`, `{{>page.header}}`), lo que mezcla "contenido reutilizable" con
  "chrome visual del tema". El chrome debe migrar a `ThemeVersion` (§3.3).

La solución (§3.5) introduce `TemplateSnippetVersion` manteniendo el concepto de snippet
pero añadiéndole el mismo modelo de versionado + locale que `TemplateVersion` y `ThemeVersion`.

### P8 — Acoplamiento de DecorateHtml al contexto Document

`DecorateHtml` vive en `Features/Oidc/Theme/Application/` pero importa directamente
gateways de Template, TemplateVersion y TemplateAsset. El renderizado de páginas HTML
debe ser responsabilidad del contexto Document/Rendering, no de Oidc.

### P10 — TemplateAsset usa convenciones de nombre en lugar de referencias explícitas

`TemplateAsset.code` codifica la relación con la plantilla mediante una convención de
prefijo (`page.index/logo.png`, `page.full/corporate.css`). Esto es una **relación implícita
por string**, no una foreign key:

- El gateway no puede filtrar por plantilla propietaria sin parsear el código.
- Renombrar o eliminar una `Template` no invalida automáticamente sus assets.
- No hay integridad referencial: un asset puede existir sin plantilla, o con un código
  que no corresponde a ninguna plantilla real.
- Duplicar la relación en el nombre impide reutilizar el mismo fichero binario entre versiones
  de la misma plantilla sin duplicar el registro.

El mismo problema afectaría a `ThemeAsset` y a `TemplateSnippetAsset` si se diseñasen
siguiendo el mismo patrón. La solución (§3.8) es usar FK explícitas en los tres tipos.

### P9 — Cada plantilla contiene su propio chrome visual completo

Cada `TemplateVersion.contentHtml` incluye el HTML completo: estructura, cabecera, footer,
banners, estilos y contenido funcional. Consecuencias:

- Cambiar el logo o el footer de todos los emails del tenant implica editar N plantillas.
- Inconsistencias visuales cuando una plantilla se actualiza y otra no.
- No hay forma de aplicar un look & feel transversal por tenant sin clonar plantillas.
- La separación entre _what_ (qué comunica la plantilla) y _how_ (cómo se presenta) no existe.

---

## 3. Arquitectura propuesta

### 3.1 Principios guía

> 1. **Un único motor** (Handlebars) para todos los canales.
> 2. **Separación estricta de responsabilidades**: `Template` define el contenido funcional;
>    `Theme` define el chrome visual y sus assets.
> 3. **El chrome es del tema, no de la plantilla.** Una plantilla funcional no debe saber
>    en qué envoltorio visual se va a incrustar.
> 4. `TemplateSnippet` se mantiene para bloques de contenido reutilizables; el chrome visual migra a `ThemeVersion`.

### 3.2 Migración del motor: Twig → Handlebars

Todas las plantillas `page.*` pasan a usar sintaxis Handlebars:

| Twig | Handlebars |
|---|---|
| `{{ variable }}` | `{{variable}}` |
| `{{ innerContent }}` | `{{{innerContent}}}` (sin escape — es HTML) |
| `{{ assets_path }}/logo.png` | `{{theme_assets_path}}/logo.png` |

### 3.3 Theme como meta-plantilla

#### 3.3.1 Concepto

El `Theme` pasa de ser un contenedor de `customCss` a ser la **definición completa del
tema visual**: incluye el contenido HTML base (el chrome) y los assets binarios que
necesita. Su semántica es "todo lo que define la apariencia visual de un tenant".

El renderizado se convierte en **dos fases**:

```
Fase 1 — Plantilla funcional
  TemplateRenderUsecase.render(code, channel, tenant, locale, variables)
  → RenderedTemplate { htmlContent (solo el bloque funcional), subject, … }

Fase 2 — Envoltorio del tema
  ThemeRenderService.wrap(theme, renderedTemplate, locale)
  → string (HTML completo listo para servir)
```

La plantilla funcional **nunca incluye** `<html>`, `<head>`, header ni footer. Solo el
bloque de contenido que varía por caso de uso. El tema aporta todo lo demás.

#### 3.3.2 ThemeVersion — contenido versionado y localizable del tema

`Theme` sigue el mismo patrón que `Template`: es el descriptor/metadato del tema, y una
nueva entidad `ThemeVersion` alberga el contenido versionado y localizable. Se elimina
`customCss` de `Theme` (pasa a `ThemeAsset(code='theme.css')`).

| Entidad | Analogía | Rol |
|---|---|---|
| `Template` | — | Descriptor de plantilla funcional |
| `TemplateVersion` | — | Contenido versionado + locale de la plantilla |
| `Theme` | ≈ `Template` | Descriptor del tema visual |
| `ThemeVersion` | ≈ `TemplateVersion` | Contenido versionado + locale del chrome |

**Campos de `ThemeVersion`:**

| Campo | Tipo | Descripción |
|---|---|---|
| `uid` | string | Identificador |
| `theme` | ThemeRef | Tema al que pertenece |
| `channel` | enum | `MAIL`, `HTML` — canal al que se aplica el chrome |
| `locale` | `string?` | Locale del chrome (`null` = default) |
| `contentHtml` | string | Plantilla Handlebars del chrome con slots fijos |
| `version` | int | Contador de revisión — el más alto por `(channel, locale)` es el activo |

Un tema puede tener múltiples `ThemeVersion` activos simultáneamente:

```
ThemeVersion(channel=HTML, locale=null,   version=2) → chrome HTML en inglés (default)
ThemeVersion(channel=HTML, locale='es',   version=1) → chrome HTML en castellano
ThemeVersion(channel=MAIL, locale=null,   version=3) → chrome email en inglés (default)
ThemeVersion(channel=MAIL, locale='es',   version=1) → chrome email en castellano
```

La resolución aplica el mismo algoritmo BCP 47 que `TemplateVersion` (§3.4.2):
`exact locale → language prefix → null (default) → any`.

**Theme queda como metadato limpio:**

| Campo | Tipo |
|---|---|
| `uid` | string |
| `tenant` | TenantRef |
| `name` | string |
| `isDefault` | bool |
| `enabled` | bool |
| `version` | int (lock optimista) |

`customCss` se elimina — su valor existente se migra a `ThemeAsset(code='theme.css')`.

#### 3.3.3 Contrato de slots en ThemeVersion.contentHtml

Placeholders fijos que el chrome del tema debe exponer:

| Placeholder | Obligatorio | Descripción |
|---|---|---|
| `{{{slot_content}}}` | Sí | HTML del contenido funcional ya renderizado |
| `{{subject}}` | No | Asunto de la plantilla (útil para `<title>` o preheader en email) |
| `{{locale}}` | No | Locale negociado |
| `{{theme_assets_path}}` | No | URL pública a los assets del tema |
| `{{tenant_name}}` | No | Nombre del tenant |
| `{{tenant_domain}}` | No | Dominio del tenant |
| `{{title}}` | No | Título de página (canal HTML) |
| `{{body_class}}` | No | Clase CSS del `<body>` (permite variantes dentro del mismo tema) |

Ejemplo de `ThemeVersion(channel=HTML, locale=null)` para el tema 'corporate':

```handlebars
<!DOCTYPE html>
<html lang="{{locale}}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{title}}</title>
    <link rel="icon" href="{{theme_assets_path}}/favicon.png">
    <link rel="stylesheet" href="{{theme_assets_path}}/corporate.css">
</head>
<body class="{{body_class}}">
    <header class="app-header">
        <img src="{{theme_assets_path}}/logo.png" alt="{{tenant_name}}">
    </header>
    <main>
        {{{slot_content}}}
    </main>
</body>
</html>
```

La plantilla funcional (`page.index`, `page.full`, `mail.user.recovery`…) solo aporta
lo que va dentro de `{{{slot_content}}}`, sin etiquetas de página ni chrome.

#### 3.3.4 ThemeAsset — assets binarios del tema

Ver §3.8 para el modelo unificado de assets (TemplateAsset, ThemeAsset, TemplateSnippetAsset).

#### 3.3.5 Separación de responsabilidades entre ThemeVersion y TemplateSnippet

`ThemeVersion` y `TemplateSnippet` (ver §3.5) coexisten con roles distintos:

| Criterio | `ThemeVersion.contentHtml` | `TemplateSnippetVersion.contentHtml` |
|---|---|---|
| **Propósito** | Chrome visual completo (HTML skeleton, header, footer) | Bloque de contenido reutilizable (aviso legal, GDPR…) |
| **Referenciado desde** | `ThemeRenderService` directamente | `{{>snippet_code}}` dentro de plantillas o del propio chrome |
| **Propietario** | El tema (`ThemeRef`) | El tenant o global (`tenant?`) |
| **Locale** | Sí — `ThemeVersion.locale` | Sí — `TemplateSnippetVersion.locale` |

Los snippets que actualmente hacen de chrome (`{{>page.head}}`, `{{>page.header}}`…)
se **migran al `ThemeVersion`** correspondiente. Los snippets de contenido reutilizable
(pie legal, aviso de cookies…) se mantienen como `TemplateSnippet` + `TemplateSnippetVersion`.

### 3.4 Modelo unificado de assets: FK explícita al propietario

Los tres tipos de asset binario del sistema (plantilla, tema, snippet) siguen el mismo
patrón estructural: **referencia explícita a su entidad propietaria** + nombre de fichero
simple. No hay convenciones de nombre que codifiquen la relación.

#### 3.4.1 Estructura común

| Campo | Tipo | Descripción |
|---|---|---|
| `uid` | string | Identificador |
| `{owner}` | `TemplateRef` / `ThemeRef` / `TemplateSnippetRef` | FK explícita al propietario |
| `filename` | string | Nombre del fichero sin prefijos, e.g. `logo.png`, `corporate.css` |
| `type` | string | MIME type |
| `content` | string | Clave en FileStorage |
| `enabled` | bool | Activo/inactivo |
| `version` | int | Lock optimista |

El campo actual `TemplateAsset.code` (que codificaba `{template}/{filename}`) se **divide**
en una FK al propietario (`template: TemplateRef`) y un nombre simple (`filename: string`).

#### 3.4.2 Las tres entidades de asset

**`TemplateAsset`** — assets de una plantilla concreta (imagen de fondo específica de login, etc.)

```
template : TemplateRef   → FK a la Template propietaria
filename : string        → e.g. "background.webp"
```

URL pública: `.assets/templates/{templateUid}/{filename}`
El gateway filtra por `TemplateRef` directamente.

**`ThemeAsset`** — assets del tema visual (logo, CSS base, favicon, imágenes corporativas)

```
theme    : ThemeRef      → FK al Theme propietario
filename : string        → e.g. "logo.png", "corporate.css"
```

URL pública: `.assets/themes/{themeUid}/{filename}`
El gateway filtra por `ThemeRef` directamente.

**`TemplateSnippetAsset`** — assets de un snippet reutilizable (imagen en pie legal, sello, etc.)

```
snippet  : TemplateSnippetRef  → FK al TemplateSnippet propietario
filename : string              → e.g. "seal.png"
```

URL pública: `.assets/snippets/{snippetUid}/{filename}`
El gateway filtra por `TemplateSnippetRef` directamente.

#### 3.4.3 Variables de assets en las plantillas

Cada servicio de render resuelve y publica los assets de su propietario antes de renderizar,
e inyecta la URL base como variable Handlebars:

| Variable | Disponible en | Apunta a |
|---|---|---|
| `{{theme_assets_path}}` | `ThemeVersion`, `TemplateVersion`, `TemplateSnippetVersion` | `.assets/themes/{themeUid}/` |
| `{{template_assets_path}}` | `TemplateVersion` | `.assets/templates/{templateUid}/` |
| `{{snippet_assets_path}}` | `TemplateSnippetVersion` | `.assets/snippets/{snippetUid}/` |

Los assets del tema están disponibles en todos los niveles porque el tema es global al render.
Los assets de plantilla y snippet solo están disponibles en su propio contexto de render.

#### 3.4.4 Beneficios del FK explícito

- **Integridad referencial**: eliminar una `Template` puede validar o limpiar sus `TemplateAsset` en cascada.
- **Filtrado sin parseo**: `assetGateway->listByTemplate($templateRef)` sin `LIKE 'page.index/%'`.
- **Sin convenciones frágiles**: renombrar el `code` de una plantilla no rompe los assets.
- **Reutilización**: el mismo binario en FileStorage puede referenciarse desde versiones distintas sin duplicar el `TemplateAsset`.
- **Gestión en admin consistente**: cada entidad tiene su pestaña "Assets" en su propio panel.

### 3.6 Internacionalización: campo `locale` en TemplateVersion

#### 3.4.1 Nuevo campo `locale`

Se añade `locale?: string` (nullable) a `TemplateVersion`:

| `locale` | Significado |
|---|---|
| `null` | Versión por defecto — fallback para cualquier idioma no traducido |
| `'es'` | Castellano |
| `'fr'` | Francés |
| `'de'` | Alemán |
| `'es-ES'` | Español de España (variante específica de región) |

Cada `Template` puede tener **múltiples versiones simultáneamente activas**, una por locale.
El campo `version` (contador) es independiente por locale.

#### 3.4.2 Algoritmo de resolución de versión (BCP 47)

`TemplateRenderUsecase.latestVersion(template, locale)`:

```
1. Exact locale:   versión más reciente con locale = $locale    (ej. 'es-ES')
2. Language:       versión más reciente con locale = prefijo     (ej. 'es')
3. Default:        versión más reciente con locale = null
4. Any:            si no hay default explícito, cualquier versión (compatibilidad)
```

El mismo algoritmo se aplica a `ThemeLayout` (canal + locale).

#### 3.4.3 Coexistencia con TemplateVariable

| Mecanismo | Cuándo usarlo |
|---|---|
| `TemplateVersion` con `locale` | Texto de UI que varía estructuralmente entre idiomas (plurales, orden). Editable por el operador desde admin. |
| `TemplateVariable` del tenant | Overrides de branding por tenant (nombre de empresa, eslogan). Se aplican encima de la versión localizada. |

Jerarquía de precedencia (mayor a menor):
1. Variables de llamada del caller (`title`, `innerContent`…)
2. `TemplateVariable` del tenant
3. `TemplateVersion` del locale solicitado (con negociación BCP 47)

#### 3.4.4 `TemplateRenderInput` — añadir `locale`

```php
new TemplateRenderInput(
    code:         'page.index',
    channel:      TemplateChannelOptions::HTML,
    tenant:       $tenantRef,
    locale:       'es',           // NUEVO
    variables:    ['title' => 'Mi empresa', 'innerContent' => $html],
    outputFormat: TemplateOutputFormat::HTML,
)
```

### 3.7 TemplateSnippet + TemplateSnippetVersion — bloques reutilizables versionados

#### 3.5.1 El modelo correcto: paralelo a Template/TemplateVersion

`TemplateSnippet` pasa a ser un descriptor ligero, y `TemplateSnippetVersion` alberga
el contenido versionado y localizable, completando el patrón uniforme del sistema:

```
Theme           + ThemeVersion           → chrome visual del tenant
Template        + TemplateVersion        → contenido funcional de cada caso de uso
TemplateSnippet + TemplateSnippetVersion → bloque reutilizable de contenido
```

**Campos de `TemplateSnippet` (limpiado):**

| Campo | Tipo | Descripción |
|---|---|---|
| `uid` | string | Identificador |
| `code` | string | Código de referencia (`legal.footer`, `gdpr.notice`, `company.address`…) |
| `tenant` | TenantRef? | Propietario (`null` = snippet global disponible para todos los tenants) |
| `enabled` | bool | Activo/inactivo |
| `version` | int | Lock optimista |

`contentHtml` se elimina de `TemplateSnippet` y pasa a `TemplateSnippetVersion`.

**Campos de `TemplateSnippetVersion` (nueva entidad):**

| Campo | Tipo | Descripción |
|---|---|---|
| `uid` | string | Identificador |
| `snippet` | TemplateSnippetRef | Snippet al que pertenece |
| `locale` | string? | Locale (`null` = default/fallback) |
| `contentHtml` | string | HTML del bloque (puede usar `{{variables}}` y `{{>otros_snippets}}`) |
| `version` | int | Contador — el más alto por `(snippet, locale)` es el activo |

La resolución aplica el mismo algoritmo BCP 47 que `TemplateVersion` y `ThemeVersion`.

#### 3.5.2 Inclusión mediante `{{>snippet_code}}`

Handlebars soporta parciales nativamente con `{{>nombre}}`. El `HandlebarsTemplateRenderAdapter`
ya implementa este mecanismo (parámetro `snippets` en `TemplateRenderRequest`). Los snippets
resueltos se pasan como mapa `['legal.footer' => '<p>...</p>']` y son accesibles desde
cualquier plantilla o chrome.

Los snippets pueden usarse en **cualquier nivel del render**:
- Dentro de `TemplateVersion.contentHtml`: `{{>legal.footer}}`
- Dentro de `ThemeVersion.contentHtml` (chrome): `{{>gdpr.notice}}`
- Anidados: un snippet puede incluir otro snippet (`{{>company.address}}` dentro de `legal.footer`)

#### 3.5.3 Orden de resolución en el render

```
1. Resolver TemplateSnippetVersion para cada snippet del tenant
   (tenant-específico primero, luego global; BCP 47 para locale)

2. TemplateRenderUsecase renderiza TemplateVersion.contentHtml
   con los snippets como partials + variables del tenant

3. ThemeRenderService renderiza ThemeVersion.contentHtml
   con {{{slot_content}}} = resultado del paso 2
   Los mismos snippets están disponibles también en el chrome
```

#### 3.5.4 Casos de uso típicos de TemplateSnippetVersion

| Código | Contenido | Usado en |
|---|---|---|
| `legal.footer` | Texto de condiciones legales + enlaces | Emails, pie de páginas |
| `gdpr.notice` | Aviso de tratamiento de datos | Emails de registro, formularios |
| `company.address` | Dirección fiscal de la empresa | Emails, facturas |
| `unsubscribe.link` | Enlace de baja de comunicaciones | Todos los emails de marketing |
| `cookie.banner` | Texto del aviso de cookies | Páginas web |

Todos ellos son traducibles (via `TemplateSnippetVersion.locale`) y personalizables por
tenant (snippet con `tenant` asignado sobreescribe el global de mismo `code`).

### 3.8 ThemeRenderService — segunda fase del renderizado

Ubicación propuesta: `Features/Document/Rendering/Application/ThemeRenderService.php`

Responsabilidades:
1. Resolver el `Theme` activo del tenant (o el tema global por defecto).
2. Resolver el `ThemeLayout` para el canal y locale dados (negociación BCP 47).
3. Publicar los `ThemeAsset` del tema al directorio público (`.assets/themes/{uid}/`).
4. Renderizar el `ThemeLayout.contentHtml` con Handlebars inyectando:
   - `{{{slot_content}}}` = HTML ya renderizado por `TemplateRenderUsecase`
   - `{{theme_assets_path}}`, `{{subject}}`, `{{locale}}`, `{{title}}`, `{{tenant_name}}`, etc.
5. Devolver el HTML final.

```php
class ThemeRenderService
{
    public function wrap(
        RenderedTemplate $content,
        ?TenantRef $tenant,
        TemplateChannelOptions $channel,
        string $locale,
        array $extraVars = [],  // title, body_class…
    ): string;
}
```

Si no existe `ThemeLayout` para el canal, el servicio devuelve `$content->htmlContent`
directamente (comportamiento actual — sin envoltorio).

### 3.9 PageRenderService — orquestador para páginas HTML

Ubicación: `Features/Document/Rendering/Application/PageRenderService.php`

Combina `TemplateRenderUsecase` + `ThemeRenderService` para el canal HTML:

```php
class PageRenderService
{
    public function render(
        string $templateCode,    // 'page.index', 'page.full'…
        ?TenantRef $tenant,
        string $innerContent,
        string $title,
        string $locale,
    ): ?string;                  // null si no existe plantilla en BD
}
```

Flujo:
1. `TemplateRenderUsecase.render(code, HTML, tenant, locale, ['innerContent' => $innerContent])`
2. Si no hay resultado → retorna `null` (DecorateHtml aplica fallback del filesystem)
3. `ThemeRenderService.wrap(rendered, tenant, HTML, locale, ['title' => $title])`
4. Retorna el HTML completo

### 3.10 DecorateHtml como thin wrapper

Tras la migración, `DecorateHtml` queda reducido a:

```php
public function getFullPage(
    RequestInterface $request,
    string $title,
    string $innerContent,
    string $locale,
    string $template = 'index',
    ?string $tenantDomain = null,
): string {
    $tenantRef = $this->resolveTenantRef($tenantDomain);

    $html = $this->pageRenderer->render(
        templateCode: "page.{$template}",
        tenant:       $tenantRef,
        innerContent: $innerContent,
        title:        $title,
        locale:       $locale,
    );

    if ($html !== null) {
        return $html;
    }

    // Fallback filesystem (comportamiento actual, sin cambios)
    $usedTheme = $this->resolveThemeName($tenantDomain);
    $srcDir    = __DIR__ . "/../Themes/{$usedTheme}/";
    $this->dumpTheme($srcDir, $targetDir);
    $callback  = require $srcDir . "/{$template}.php";
    return $callback($theme, $title, $innerContent, $locale);
}
```

Sin imports de gateways de Document. Sin Twig. Sin lógica de resolución de versiones.

---

## 4. Plan de migración

### Fase 1 — Corrección de regresiones inmediatas

1. **F1-1** `InstallIndexPageTemplate` e `InstallFullPageTemplate`: crear `TemplateAsset`
   sin tenant (global). Así los assets son accesibles para cualquier tenant.

2. **F1-2** `DecorateHtml.dumpDbTemplateAssets()`: dos pasadas de lookup —
   primero con `tenantRef`, luego sin él — tenant gana si hay colisión de filename.

### Fase 2 — Locale en TemplateVersion

3. **F2-1** Añadir campo `locale?: string` a `TemplateVersion` (migración BD + dominio +
   gateways). Las versiones existentes quedan con `locale = null` (default).

4. **F2-2** Añadir `locale?: string` a `TemplateRenderInput`.

5. **F2-3** Actualizar `TemplateRenderUsecase.latestVersion()` con negociación BCP 47.

6. **F2-4** Crear versiones localizadas (`es`, `fr`, `de`…) en los Install usecases de
   plantillas de página y de email. El texto de UI viaja dentro de la versión localizada.

### Fase 3 — ThemeVersion, ThemeAsset y ThemeRenderService

7. **F3-1** Crear entidad `ThemeAsset` (dominio + gateways + PDO + FileStorage + REST).
   Migrar los assets de `corporate/style/` a `ThemeAsset` del tema 'corporate'.
   Migrar `Theme.customCss` a `ThemeAsset(code='theme.css')` y eliminar el campo.

8. **F3-2** Crear entidad `ThemeVersion` (dominio + gateways + PDO + REST).
   Campos: `theme`, `channel`, `locale?`, `contentHtml`, `version`.
   Resolución activa: el `version` más alto por `(theme, channel, locale)`.

9. **F3-3** Crear `ThemeRenderService` en `Features/Document/Rendering/Application/`.
   Resuelve `ThemeVersion` con negociación BCP 47, publica `ThemeAsset` al disco y
   renderiza el chrome con Handlebars inyectando los slots fijos.

10. **F3-4** Instalar `ThemeVersion` HTML y MAIL (con sus locales) para el tema
    'corporate' en `InstallUsecase`. Las plantillas funcionales pasan a contener
    solo el bloque de contenido, sin chrome.

### Fase 4 — Motor unificado y PageRenderService

13. **F4-1** Crear `PageRenderService` que combina `TemplateRenderUsecase` + `ThemeRenderService`.

14. **F4-2** Migrar `page.index` y `page.full` a Handlebars. El contenido HTML de cada
    versión ya NO incluye `<html>`, `<head>` ni chrome — solo el bloque funcional interno.

15. **F4-3** Refactorizar `DecorateHtml` para delegar en `PageRenderService`.
    Eliminar todos los imports de Document gateways de `DecorateHtml`.

### Fase 5 — TemplateSnippetVersion y migración de snippets de chrome

16. **F5-1** Crear entidad `TemplateSnippetVersion` (dominio + gateways + PDO + REST).
    Campos: `snippet`, `locale?`, `contentHtml`, `version`.
    Eliminar `contentHtml` de `TemplateSnippet` (migrar valor existente a una
    `TemplateSnippetVersion` con `locale = null`).

17. **F5-2** Actualizar `TemplateRenderUsecase.loadSnippets()` para resolver
    `TemplateSnippetVersion` con negociación BCP 47 (igual que `latestVersion()`).
    Los snippets se pasan como partials tanto a la plantilla funcional como al chrome.

18. **F5-3** Identificar los snippets que actualmente hacen de chrome
    (`{{>page.head}}`, `{{>page.header}}`…) y migrar su contenido al `ThemeVersion`
    correspondiente. Eliminar esos snippets una vez migrados.

19. **F5-4** Crear snippets de contenido reutilizable por defecto (sin tenant):
    `legal.footer`, `gdpr.notice`, `company.address`, `unsubscribe.link`.
    Instalarlos con su `TemplateSnippetVersion` en inglés y los locales necesarios.

### Fase 6 — Migrar plantillas de email al esquema funcional

20. **F6-1** Crear `ThemeVersion(channel=MAIL, locale=null)` para el tema 'corporate'
    con el chrome estándar de email (cabecera con logo, `{{>legal.footer}}`, CSS inline
    via `HTML_EMBEDDED`).

21. **F6-2** Refactorizar `InstallLoginTemplate`, `InstallPasswordRecoverTemplate` y demás
    plantillas de email para que solo contengan el bloque funcional (sin cabecera/footer).
    El chrome lo aporta `ThemeVersion`.

22. **F6-3** `ThemeRenderService` aplica CSS inlining (`HTML_EMBEDDED`) automáticamente
    para el canal MAIL, usando los `ThemeAsset` CSS del tema.

---

## 5. Resumen de decisiones

| Decisión | Opción elegida | Alternativa descartada |
|---|---|---|
| Motor de renderizado | Handlebars para todos los canales | Twig (solo en DecorateHtml) |
| Chrome visual | `ThemeVersion` (canal + locale) + `ThemeRenderService` | Campos directos en `Theme` (no versionable ni localizable) |
| Consistencia de modelo | `Theme`/`ThemeVersion` paralelo a `Template`/`TemplateVersion` | `ThemeLayout` con nombre distinto al patrón establecido |
| Assets del tema | `ThemeAsset` (entidad con ThemeRef) | `TemplateAsset` con prefijo de código |
| TemplateSnippet | **Refactorizado** + `TemplateSnippetVersion` | Rol depurado: solo bloques de contenido reutilizable; chrome migra a `ThemeVersion` |
| Internacionalización del chrome | `ThemeVersion.locale` + negociación BCP 47 | Campos `mailBaseHtml`/`htmlBaseHtml` directos en `Theme` sin locale |
| Internacionalización del contenido | `TemplateVersion.locale` + negociación BCP 47 | Variables `ui.*` inyectadas desde YAML |
| Override de texto por tenant | `TemplateVariable` encima de la versión localizada | Solo versiones por tenant |
| CSS personalizado | `ThemeAsset(code='theme.css')` reemplaza `Theme.customCss` | Campo `customCss` en Theme |
| Fallback páginas sin BD | Filesystem PHP theme (comportamiento actual) | Eliminarlo |

---

## 6. Impacto en entidades existentes

| Entidad | Estado | Cambio necesario |
|---|---|---|
| `Template` | Sin cambios | Ninguno — soporta HTML channel ya |
| `TemplateVersion` | **Modificar** | Añadir campo `locale?: string` (BD + dominio + gateways) |
| `TemplateAsset` | **Refactorizar** | Eliminar `code` con prefijo convencional; añadir `template: TemplateRef` + `filename: string`; migración BD + dominio + gateways |
| `ThemeAsset` | **Nueva entidad** | Dominio + gateways (read/write) + PDO + FileStorage + REST |
| `TemplateSnippetAsset` | **Nueva entidad** | Dominio + gateways (read/write) + PDO + FileStorage + REST |
| `TemplateVariable` | Sin cambios | Ninguno |
| `TemplateSnippet` | **Refactorizar** | Eliminar `contentHtml` del descriptor; mantener como metadato (`code`, `tenant`, `enabled`) |
| `TemplateSnippetVersion` | **Nueva entidad** | Dominio + gateways (read/write) + PDO + REST. Campos: `snippet`, `locale?`, `contentHtml`, `version` |
| `Theme` | **Limpiar** | Eliminar `customCss` (→ `ThemeAsset`); queda como metadato puro |
| `ThemeVersion` | **Nueva entidad** | Dominio + gateways (read/write) + PDO + REST. Campos: `theme`, `channel`, `locale?`, `contentHtml`, `version` |
| `TemplateRenderInput` | **Modificar** | Añadir `locale?: string` |
| `TemplateRenderUsecase` | **Modificar** | Actualizar `latestVersion()` con BCP 47; eliminar `loadSnippets()` (Fase 5) |
| `ThemeRenderService` | **Nuevo servicio** | Publica `ThemeAsset`, resuelve `ThemeVersion` con BCP 47, renderiza el chrome, inlining CSS para MAIL |
| `PageRenderService` | **Nuevo servicio** | Orquestador: `TemplateRenderUsecase` + `ThemeRenderService` |
| `DecorateHtml` | **Simplificar** | Thin wrapper sobre `PageRenderService`; eliminar imports de Document |
| `InstallUsecase` | **Ampliar** | Añadir instalación de `ThemeAsset` y `ThemeVersion` HTML/MAIL (con locales) del tema 'corporate' |
| `InstallIndexPageTemplate` | **Refactorizar** | Contenido HTML → solo bloque funcional; añadir versiones localizadas |
| `InstallFullPageTemplate` | **Refactorizar** | Ídem |
| `Install*Template` (emails) | **Refactorizar** (Fase 6) | Contenido solo funcional; chrome pasa a `ThemeVersion(channel=MAIL)` |
