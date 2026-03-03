# Theming — Alchemize Forge Front-End Reference

## Purpose

How to work with the Alchemize Forge custom theme: SCSS architecture, token pipeline, build workflow, component integration, and the theme-driven page chrome architecture. This is the starting point for any front-end or theming work.

**Before writing any CSS**, read [CSS Strategy](../../../canonical-docs/global/css-strategy.md) for the authoritative rules on what Bootstrap utilities own vs what custom SCSS owns. Before working on layout or full-bleed sections, read [Global Layout Strategy](global-layout-strategy.md).

---

## Theme Stack

| Layer | Theme | Location | Role |
|-------|-------|----------|------|
| Base | `bootstrap_barrio` (5.5.20) | `web/themes/contrib/bootstrap_barrio` | Bootstrap 5 integration with Drupal |
| Custom theme | `alchemize_forge` | `web/themes/custom/alchemize_forge` | Custom sub-theme with Webpack, SCSS, Canvas SDCs, and custom `page.html.twig`. **Default front-end theme.** |
| Admin | `claro` (Core) | Core | Core admin theme |
| Canvas internal | `canvas_stark` | Ships with `canvas` module | Canvas editor rendering |

**Configuration:** `web/themes/custom/alchemize_forge/alchemize_forge.info.yml`

**Base theme:** `bootstrap_barrio`

**Origin:** Created from the `alchemize_forge` starter kit theme using its `scripts/create_subtheme.sh`. The starter kit (`drupal/alchemize_forge`) can be removed from `composer.json` since the custom theme is independent.

**SDC components:** All SDC components are namespaced as `sdc.alchemize_forge.*` (e.g., `sdc.alchemize_forge.heading`). When referencing components in code, content templates, or scripts, always use the `alchemize_forge` namespace.

---

## SCSS Architecture

**Location:** `web/themes/custom/alchemize_forge/scss/`

### File Responsibilities

| File | Purpose | What Goes Here |
|------|---------|----------------|
| `_tokens.scss` | **Raw palette definitions.** SCSS variables only. | Hex color values (`$brand-500: #2246FA`), scale definitions. **Never use these directly in components.** |
| `_variables.scss` | **Bootstrap variable overrides.** Maps tokens to Bootstrap's semantic vars. | `$primary`, `$secondary`, `$body-bg`, `$body-color`, feature flags, border-radius, shadow definitions. |
| `_typography.scss` | **Font families, brand type scale, Bootstrap typography overrides.** | Font families, weights, line-heights, `$type-scale` map (hero: 3.5rem, section: 2.5rem, etc.), `$display-font-sizes`, `$lead-font-size`, `$small-font-size` mapped from type scale. |
| `_semantic.scss` | **CSS custom properties.** Light/dark mode tokens on `:root`. | `--color-brand-primary`, `--color-text-inverse`, `--color-bg-surface`, shadows, transitions. |
| `_mixins.scss` | Reusable SCSS mixins. | `elevation-sm`, `elevation-md`, `transition-fast`, `transition-base`, `color-mode`. |
| `_component-base.scss` | **Variables-and-mixins-only import chain for SDC SCSS.** Emits zero CSS. | Imports tokens, variables, typography, Bootstrap internals, mixins. Safe to `@import` in every component without duplicating CSS. |
| `_typography-roles.scss` | **Typography role classes.** Semantic text identity. | `.role-heading-hero`, `.role-heading-section`, `.role-text-lead`, `.role-label`, etc. Font-size, weight, letter-spacing — NO layout. |
| `_layout-presets.scss` | **Layout preset classes.** Section/card composition. | `.preset-section-hero-dark`, `.preset-card-elevated`, etc. Background, padding, shadow, flex — NO typography sizing. |
| `_default.scss` | Import order orchestrator. | Imports `_tokens` → `_variables` → Bootstrap → `_semantic` → `_typography` → `_mixins`. **Sets the compilation pipeline.** |
| `_import.scss` | Additional imports (legacy). | Supplementary import chains. |
| `bootstrap.scss` | Main Bootstrap import. | Compiles full Bootstrap 5.3 with custom variable overrides. Produces `css/bootstrap.css`. |
| `style.scss` | **Theme-level overrides + design system layers.** | Imports `_typography-roles` and `_layout-presets`, then global Bootstrap component overrides (cards, buttons, forms), custom visual classes (`.full-bleed`), page-level custom styles. Produces `css/style.css`. |
| `global/_header.scss` | Header chrome visual styling. | `.site-header`, scrolled state, nav links, CTA button, hamburger toggle, mobile nav panel. |
| `global/_footer.scss` | Footer chrome visual styling. | `.site-footer`, dark variant, nav grid, logo, legal links, social links. |

> **Design system architecture:** These files form an 8-layer design system. See `canonical-docs/global/component-strategy.md` for the complete layer diagram, type scale reference, and role/preset class catalog.

### Token Pipeline

Colors flow through three layers:

```
_tokens.scss (raw hex)
    ↓ SCSS variables ($brand-500, $neutral-0, $dark-navy)
_variables.scss (Bootstrap mapping)
    ↓ Maps tokens to Bootstrap vars ($primary: $brand-500)
_semantic.scss (CSS custom properties)
    ↓ Exposes tokens as --color-* vars on :root
Components consume var(--color-*) or $token-name
```

**SCSS files** (style.scss, _header.scss, _footer.scss) may use raw token variables (`$brand-500`, `$neutral-0`, `$dark-navy`) because they go through the SCSS compiler which resolves them at build time.

**Code components** (canvas-components/*/index.css) are standalone CSS — they MUST use CSS custom properties (`var(--color-brand-primary)`) because they have no access to the SCSS compiler.

### Token Usage Rules

1. **Never hardcode hex values in SCSS.** Always reference a token from `_tokens.scss` (e.g., `$neutral-0` not `#ffffff`, `$brand-500` not `#2246FA`).
2. **Never hardcode hex values in code component CSS.** Use `var(--color-*)` custom properties from `_semantic.scss`.
3. **rgba() with tokens:** In SCSS, use `rgba($neutral-0, 0.15)` — the compiler resolves it. In code component CSS, use `rgba(255, 255, 255, 0.15)` with a comment identifying the token (CSS can't do `rgba(var(--hex), opacity)`).
4. **New colors:** Add to `_tokens.scss` first, then map in `_variables.scss` (if Bootstrap needs it) and/or `_semantic.scss` (if code components need it). Never add colors directly to `style.scss`.

### Available CSS Custom Properties

From `_semantic.scss` — these are what code components consume:

| Property | Maps To | Purpose |
|----------|---------|---------|
| `--color-bg-page` | `$neutral-0` | Page background |
| `--color-bg-surface` | `$neutral-0` | Card/surface background |
| `--color-bg-elevated` | `$neutral-50` | Elevated surface (slightly off-white) |
| `--color-bg-muted` | `$neutral-100` | Muted background |
| `--color-text-primary` | `$neutral-800` | Primary body text |
| `--color-text-secondary` | `$neutral-600` | Secondary text |
| `--color-text-muted` | `$neutral-400` | Muted/placeholder text |
| `--color-text-inverse` | `$neutral-0` | White text (on dark backgrounds) |
| `--color-border-default` | `$neutral-200` | Default borders |
| `--color-border-subtle` | `$neutral-100` | Subtle borders |
| `--color-border-strong` | `$neutral-300` | Strong borders |
| `--color-brand-primary` | `$brand-500` | Primary brand blue |
| `--color-brand-hover` | `$brand-700` | Brand hover state |
| `--color-brand-muted` | `$brand-100` | Muted brand tint |
| `--color-brand-contrast` | `$neutral-0` | Text on brand background |
| `--color-dark-navy` | `$dark-navy` | Dark navy (#000D4D) |
| `--color-hero-gradient-mid` | `$hero-gradient-mid` | Hero gradient middle (#001EB2) |
| `--color-footer-bg` | `$dark-navy` | Footer background |
| `--color-footer-text` | `$neutral-300` | Footer text |
| `--color-section-alt` | `$section-alt-bg` | Alternate section background |
| `--color-cta` | `$brand-500` | CTA color |
| `--color-cta-hover` | `$brand-700` | CTA hover |
| `--color-hero-overlay` | `rgba(0,13,77,0.5)` | Hero gradient overlay |
| `--color-light-blue-bg` | `$light-blue-bg` | Light blue section background |
| `--shadow-sm/md/lg` | — | Elevation shadows |
| `--transition-fast/base/slow` | — | Animation timing |

---

## Build Workflow

SCSS must be compiled to CSS via Webpack. The compiled CSS files (`css/bootstrap.css` and `css/style.css`) are what Drupal loads.

**Recommended: Use `ddev theme-build`** — a single command that handles the full pipeline (npm install → copy Bootstrap JS → webpack compile). Run from **anywhere** in the project:

```bash
# Production build (minified) — before commits and deployment
ddev theme-build

# Development build (source maps, not minified) — for debugging
ddev theme-build --dev

# Watch mode (auto-rebuild on SCSS changes) — during active development
ddev theme-build --watch

# Clean compiled CSS before building
ddev theme-build --clean

# CI mode: skip npm install if node_modules already exists
ddev theme-build --ci
```

**What gets compiled:**
- `scss/bootstrap.scss` → `css/bootstrap.css` (full Bootstrap + custom variables)
- `scss/style.scss` → `css/style.css` (theme-specific overrides + custom classes)

**After any SCSS change**, rebuild and clear Drupal's cache:
```bash
ddev theme-build && ddev drush cr
```

**Manual npm commands (alternative):** You can also run individual npm scripts inside the theme directory — see `development-workflow.md` for the full command reference.

---

## Adding Custom CSS Classes

For visual classes used in Canvas components via `custom_class` props (backgrounds, color schemes, section treatments):

1. Add the CSS to `scss/style.scss` — this compiles to `css/style.css`
2. Use token variables from `_tokens.scss` — never hardcode hex
3. Only include **visual** properties (backgrounds, colors, borders, shadows, typography). Layout properties (padding, margin, flex, display, width) belong on Bootstrap utility classes via component props.
4. Rebuild with `ddev theme-build && ddev drush cr`
5. The class is now available in any Canvas component's `custom_class` prop

**Example — dark section background:**
```scss
// In scss/style.scss — SCSS tokens resolve at build time
.my-dark-section {
  background-color: $dark-navy;
  color: $neutral-0;

  h2 {
    color: $neutral-0;
  }

  p {
    color: rgba($neutral-0, 0.85);
  }
}
```

Then apply in Canvas:
```
Wrapper (custom_class: "my-dark-section", padding_y: py-5, flex_enabled: true)
```

**CSS authoring rules:** See [CSS Strategy](../../../canonical-docs/global/css-strategy.md) for the complete rules on what goes in SCSS vs Bootstrap utilities.

---

## Design Direction Patterns

When implementing a design direction (e.g., dark theme, brand-specific aesthetics), use **per-section styling**:

- Write a custom SCSS class for the section's visual properties (background, text colors, borders)
- Apply layout via Canvas component props (which output Bootstrap utilities)
- Keep the global theme neutral (`$body-bg: $neutral-0`) and let individual Canvas sections control their own styling

This approach allows mixing light and dark sections on the same page without fighting global styles.

**Background images:** Place assets in `web/themes/custom/alchemize_forge/images/`, then reference via CSS:
```scss
.bg-hero {
  background-image: url('../images/hero-bg.jpg');
  background-size: cover;
  background-position: center;
}
```

---

## Canvas SDC Components

**Location:** `web/themes/custom/alchemize_forge/components/`

Bootstrap Forge provides 12 SDC components that Canvas discovers as `sdc.alchemize_forge.*`:

`accordion`, `accordion-container`, `blockquote`, `button`, `card`, `column`, `heading`, `image`, `link`, `paragraph`, `row`, `wrapper`

Components inherit Bootstrap 5 classes and the theme's compiled SCSS. Changes to `_tokens.scss` / `_variables.scss` affect all components after rebuild.

**The Wrapper component** is the primary layout building block. It outputs Bootstrap utility classes based on its props (padding, margin, flex, width, height) and accepts `custom_class` for visual SCSS classes. See [Global Layout Strategy](global-layout-strategy.md) for the Wrapper prop reference and full-bleed section patterns.

**Key Wrapper prop: `width_class`** — defaults to `w-100`. Set to `none` for full-bleed sections to avoid the `w-100 !important` conflict. See [CSS Strategy](../../../canonical-docs/global/css-strategy.md) for details.

**Full component catalog with props and slots:** See `canvas/canvas-sdc-components.md`

---

## Canvas Code Components

**Location:** `canvas-components/` (project root)

Code components are Preact/React islands rendered via `<canvas-island>`. They have their own standalone CSS in `index.css`.

**CSS rules for code components:**
1. Use `var(--color-*)` CSS custom properties for all brand/theme colors
2. Use BEM naming (`block__element--modifier`)
3. Code components manage their own layout (no Bootstrap utilities apply)
4. No Tailwind, no utility-first CSS, no `cn()` or `twMerge()`

See the "Code Component CSS" section in [CSS Strategy](../../../canonical-docs/global/css-strategy.md) for complete rules.

---

## Libraries

**Configuration:** `web/themes/custom/alchemize_forge/alchemize_forge.libraries.yml`

**Library:** `global-styling`

| Asset | Files |
|-------|-------|
| JavaScript | `js/bootstrap.bundle.min.js` (BS 5.3 + Popper v2), `js/barrio.js`, `js/custom.js`, `js/header-scroll.js` |
| CSS | `css/bootstrap.css`, `css/style.css` |
| Dependencies | `core/jquery`, `core/drupal` |

**Library override:** Bootstrap Barrio's `global-styling` is disabled (`libraries-override: bootstrap_barrio/global-styling: false`) to prevent conflicts.

**JavaScript notes:**
- `bootstrap.bundle.min.js` — Bootstrap 5.3 bundle includes Popper v2. No separate `popper.min.js` needed.
- `barrio.js` — Uses jQuery for scroll detection and dropdown toggle. This is why `core/jquery` remains a dependency.
- `header-scroll.js` — Adds `site-header--scrolled` class to the header on scroll for transparent-to-solid header effect.
- `custom.js` — Drupal behaviors placeholder. Add project-specific JS here using the `Drupal.behaviors` pattern.

**Upgrading Bootstrap JS:**
```bash
# Update bootstrap version in package.json, then:
ddev theme-build && ddev drush cr
```
The build pipeline automatically copies `bootstrap.bundle.min.js` from `node_modules/` to `js/`.

---

## Theme Regions

**Defined in:** `web/themes/custom/alchemize_forge/alchemize_forge.info.yml`

**23 regions total.** Canvas creates PageRegion config entities for 22 of them (`content` is excluded — Canvas never manages the content region).

See `canvas/canvas-page-regions.md` for which regions are populated with components and the full region map.

---

## Image Styles

**Location:** `config/<site>/image.style.*.yml`

| Style | Dimensions | Used By |
|-------|------------|---------|
| `wide` | 1090px width, WebP | Article default view mode |
| `medium` | 220×220px, WebP | Article teaser view mode |
| `thumbnail` | 100×100px, WebP | General thumbnails |
| `large` | 480×480px, WebP | General large images |
| `media_library` | — | Media library widget |
| `canvas_parametrized_width` | — | Canvas components |
| `canvas_avatar` | — | Canvas avatar components |

All styles convert to WebP format. Create new image styles before view displays or components reference them.

---

## Theme Settings Reference

**Configuration:** `config/<site>/alchemize_forge.settings.yml`

Bootstrap Forge theme settings are managed at `/admin/appearance/settings/alchemize_forge`. Key categories:

### Container

- **Container type:** `container` (fixed max-width) or `container-fluid` (full width)
- **Current setting:** `container` (fixed max-width ~1140px). Full-bleed sections break out using the CSS breakout pattern.
- See [Global Layout Strategy](global-layout-strategy.md) for the container strategy and full-bleed pattern

### Navbar

| Setting | Options | Notes |
|---------|---------|-------|
| Toggle breakpoint | `navbar-toggleable-{sm,md,lg,xl}` | When hamburger menu appears |
| Color scheme | `navbar-light` / `navbar-dark` | Text color in navbar |
| Background | `bg-primary`, `bg-dark`, `bg-light`, etc. | Bootstrap background class |

### Sidebars

- Position: `both`, `left`, `right`, or `none`
- Width: Set in Bootstrap grid columns (1-12)
- Collapse: Enable/disable responsive sidebar collapse

### Other settings

| Setting | Purpose |
|---------|---------|
| Messages widget | `default` or `toasts` (auto-dismiss notifications) |
| Checkbox style | Standard or `switch` (Bootstrap toggle) |
| Table style | `table-striped`, `table-hover`, etc. |
| Bootstrap source | CDN or local |
| Bootstrap Icons | Enable/disable icon library |

After changing settings: `ddev drush cex -y` to capture in config

---

## Related Documentation

| Document | Location | What It Covers |
|----------|----------|----------------|
| **[CSS Strategy](../../../canonical-docs/global/css-strategy.md)** | `canonical-docs/global/` | **Read before writing any CSS.** Bootstrap utility ownership vs custom SCSS, full-bleed pattern, code component CSS rules, token usage constraints, quick reference table. |
| **[Global Layout Strategy](global-layout-strategy.md)** | `drupal/docs/theming/` | Container strategy, page template structure, full-bleed breakout pattern, Wrapper component prop reference. |
| **[Architecture](../../../canonical-docs/global/architecture.md)** | `canonical-docs/global/` | System blueprint, rendering model (theme owns chrome, Canvas owns content), module landscape. |
| **[Canvas SDC Components](../canvas/canvas-sdc-components.md)** | `drupal/docs/canvas/` | Full component catalog with props, slots, and enum values. |
| **[Canvas Build Guide](../canvas/canvas-build-guide.md)** | `drupal/docs/canvas/` | Design-to-component methodology for building Canvas pages. |

---

## Change Surface

| What | Where |
|------|-------|
| Theme info | `web/themes/custom/alchemize_forge/alchemize_forge.info.yml` |
| Theme libraries | `web/themes/custom/alchemize_forge/alchemize_forge.libraries.yml` |
| Token definitions | `web/themes/custom/alchemize_forge/scss/_tokens.scss` |
| Bootstrap overrides | `web/themes/custom/alchemize_forge/scss/_variables.scss` |
| CSS custom properties | `web/themes/custom/alchemize_forge/scss/_semantic.scss` |
| Custom styles | `web/themes/custom/alchemize_forge/scss/style.scss` |
| Header styles | `web/themes/custom/alchemize_forge/scss/components/_header.scss` |
| Footer styles | `web/themes/custom/alchemize_forge/scss/components/_footer.scss` |
| Compiled CSS | `web/themes/custom/alchemize_forge/css/*.css` (**never edit directly**) |
| Image assets | `web/themes/custom/alchemize_forge/images/` |
| SDC components | `web/themes/custom/alchemize_forge/components/*/` |
| Code component CSS | `canvas-components/*/index.css` |
| Theme settings | `config/<site>/alchemize_forge.settings.yml` |
| Image styles | `config/<site>/image.style.*.yml` |

---

## Constraints

- **SCSS compilation required:** SCSS changes have no effect until compiled via `ddev theme-build`.
- **Token pipeline is law:** All colors trace to `_tokens.scss`. No hardcoded hex in SCSS or code component CSS.
- **CSS ownership split:** Bootstrap utilities own layout/spacing. Custom SCSS owns visual styling. Never compete. See [CSS Strategy](../../../canonical-docs/global/css-strategy.md).
- **Never use `!important` in custom SCSS.** If you need it, you're fighting a Bootstrap utility — fix the component props instead.
- **Canvas formats locked:** `canvas_html_inline` and `canvas_html_block` are Canvas-managed — don't modify them. See `configuration/text-formats.md`.
- **SDC deduplication:** Alchemize Forge's SDCs override `canvas_bootstrap` module SDCs with the same machine names. Do not create new SDCs with names that collide. See `canvas/canvas-bootstrap-integration.md`.
- **Theme-driven chrome:** The theme's `page.html.twig` renders page chrome (header, menu, footer) via `drupal_block()` from Twig Tweak. Canvas manages only the content area. See `global/architecture.md` → Rendering Model.
- **Never edit `css/*.css` directly.** Always edit SCSS and rebuild.

## Failure Modes

- **Missing SCSS compilation:** CSS won't update. Run `ddev theme-build`.
- **Hardcoded hex drifts from tokens:** If someone adds a hardcoded `#2246FA` instead of `$brand-500`, the value won't update if the brand color changes.
- **`w-100` kills full-bleed:** If a full-bleed Wrapper doesn't have `width_class: none`, Bootstrap's `width: 100% !important` overrides the breakout's `width: 100vw`. Section renders contained instead of edge-to-edge.
- **Custom SCSS overridden by utilities:** Any custom class that sets padding, margin, width, display, or flex on an element that also has Bootstrap utilities will be silently overridden (utilities use `!important`).
- **Missing image style:** Images won't display if a view display or component references a non-existent style.
- **Library conflicts:** If Bootstrap Barrio's `global-styling` isn't disabled, CSS conflicts will occur.
- **Canvas component not found:** If an SDC is removed or renamed, Canvas pages using it will break.
- **Code component hex drift:** Code component CSS with hardcoded hex won't update when `_tokens.scss` changes. Use `var(--color-*)` custom properties.
