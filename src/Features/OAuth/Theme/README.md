# Theme Bounded Context

Provides the multi-theme HTML rendering system for all authorization UI pages.

## Responsibility

This context owns **visual theming** for the server-rendered authorization interface. It decorates bare HTML content with a full page layout — header, footer, CSS, and JavaScript — according to the active tenant theme. Themes are pluggable: adding a new theme requires only a directory with `full.php` and `index.php` layout files.

## Application Services

- **`DecorateHtml`** — Wraps a content fragment with the active theme layout. Accepts raw HTML and returns a fully-decorated page ready for browser rendering.

## Available Themes

| Theme | Description |
|---|---|
| `blue` | Classic blue corporate layout |
| `corporate` | Neutral corporate design |
| `dark-theme` | Dark mode layout |
| `floating-card` | Card-centered, floating layout |
| `light-corporate` | Light variant of the corporate theme |
| `modern-minimal` | Clean, minimal modern design |

Each theme directory contains:
- `full.php` — Full-page layout (used for standalone auth pages).
- `index.php` — Index/portal layout (used for the authorization landing page).

## Key Invariants

- Themes are **tenant-configured**: each tenant selects a theme; the `DecorateHtml` service resolves the correct theme at render time.
- Theme files are **pure PHP templates** with no business logic — only layout, asset inclusion, and slot rendering.
- Adding a new theme has **zero impact** on other bounded contexts — the only contract is the directory structure and the two layout files.

## Interactions with Other Contexts

```
Theme ──decorates HTML for──> Authentication  (all authorization UI pages)
      ──decorates HTML for──> MagicLink       (verification page)
      ──decorates HTML for──> Device          (user verification page)
      ──decorates HTML for──> UserInvitation  (acceptance page)
      ──decorates HTML for──> Profile         (self-service dashboard)
      ──installed by──>       Common          (InstallCorporateTheme use case)
```
