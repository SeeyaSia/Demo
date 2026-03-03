# CSS Strategy

## Purpose

Defines the rules for how CSS is authored in this project. Specifically: what Bootstrap utility classes own, what custom SCSS owns, how the design system layers interact, and how to style new sections or components. Read this before writing any CSS, creating any SCSS class, or configuring Canvas component props.

For the full 8-layer design system architecture and preset reference, see `component-strategy.md`.

---

## The Rule

**Bootstrap utility classes own layout and spacing. Custom SCSS owns visual styling. Presets bridge both — SCSS-defined classes applied through Twig preset maps.**

These three systems have clear boundaries. Bootstrap utilities use `!important`; custom SCSS and preset classes use normal specificity. Individual Bootstrap utility overrides (from Canvas editor props) reliably win over preset defaults.

---

## SCSS File Responsibilities

The theme's SCSS is organized into distinct layers. Each file has a single responsibility.

| File | Layer | Emits CSS? | What Goes Here |
|------|-------|-----------|---------------|
| `_tokens.scss` | 1 | No | Raw hex values only (`$brand-*`, `$neutral-*`, `$state-*`). Never used directly in components. |
| `_variables.scss` | 2 | No | Bootstrap variable overrides mapping tokens to semantic names (`$primary: $brand-500`). |
| `_typography.scss` | 2 | No | Font families, weights, line-heights, `$type-scale` map, Bootstrap typography overrides (`$display-font-sizes`, `$lead-font-size`). |
| `_component-base.scss` | — | **No** | Variables-and-mixins-only import chain for SDC component SCSS. Imports tokens, variables, typography, Bootstrap internals, and mixins — but emits zero CSS. Safe to `@import` in every component. |
| `_semantic.scss` | 4 | Yes | CSS custom properties for light/dark mode (`--color-bg-page`, `--shadow-sm`, `--transition-fast`). |
| `_mixins.scss` | — | No | Reusable mixins: `elevation-sm/md/lg`, `transition-fast/base`. |
| `_typography-roles.scss` | 3 | Yes | Typography role classes (`.role-heading-hero`, `.role-text-lead`, `.role-label`). Font-size, weight, letter-spacing — NO layout. |
| `_layout-presets.scss` | 5 | Yes | Layout preset classes (`.preset-section-hero-dark`, `.preset-card-elevated`). Background, padding, shadow, flex — NO typography sizing. |
| `style.scss` | 6 | Yes | Imports layers 3+5, plus global Bootstrap component overrides (`.card`, `.btn-primary`, `body`, `a`, etc.) and chrome (header, footer). |
| `global/_header.scss` | 6 | Yes | Header chrome visual styling. |
| `global/_footer.scss` | 6 | Yes | Footer chrome visual styling. |
| `components/*.scss` | 7 | Yes (per-component) | BEM-named SDC component styles. Import `component-base` only. |

**Never edit `css/*.css` directly.** Always edit SCSS and rebuild with `npm run build` (from theme directory) or `ddev theme-build`.

---

## What Bootstrap Utilities Own

Use Bootstrap utility classes (via SDC component props or `custom_class`) for:

| Category | Examples | Applied Via |
|----------|----------|-------------|
| **Layout structure** | `container`, `container-fluid`, `row`, `col-*` | Wrapper `container_type`, Row, Column components |
| **Sizing** | `w-100`, `w-auto`, `vw-100`, `h-100` | Wrapper `width_class`, `height_class` |
| **Spacing** | `p-*`, `m-*`, `px-*`, `py-*`, `gap-*` | Wrapper padding/margin props |
| **Flexbox** | `d-flex`, `flex-column`, `justify-content-*`, `align-items-*` | Wrapper `flex_enabled` + flex props |
| **Display** | `d-block`, `d-none`, `d-lg-flex` | `custom_class` |
| **Text alignment** | `text-center`, `text-start`, `text-end` | `custom_class` or Heading `alignment` prop |

**Why:** Bootstrap compiles all utilities with `!important`. They are designed to be the final word on these properties. Fighting them with custom CSS is a losing battle.

## What Custom SCSS Owns

Write custom SCSS for:

| Category | Examples |
|----------|----------|
| **Backgrounds** | Colors, gradients, images (`background-color`, `background-image`) |
| **Typography treatments** | Section-specific font sizes, weights, line-heights, letter-spacing |
| **Color schemes** | Text/link colors for dark-on-light or light-on-dark contexts |
| **Borders & dividers** | `border-bottom`, `border-color`, decorative lines |
| **Shadows & elevation** | `box-shadow`, custom elevation levels |
| **Transitions & animations** | `transition`, `transform`, keyframes |
| **Pseudo-elements** | `::before`, `::after` decorative content |
| **Component-specific patterns** | Multi-property visual treatments Bootstrap has no utility for |

## What Preset Classes Own

Preset classes (layers 3 and 5) bridge Bootstrap utilities and custom SCSS. They bundle design decisions into single semantic classes defined in SCSS using brand tokens.

| Class type | What they set | What they don't set |
|-----------|--------------|-------------------|
| **Typography roles** (`.role-*`) | Font-size, weight, letter-spacing, line-height, identity-linked color | Layout, padding, background |
| **Layout presets** (`.preset-*`) | Background, padding, shadow, flex, inherited text color | Font-size, font-weight, letter-spacing |

**Specificity model:** Preset/role classes use normal specificity. Bootstrap utility overrides (set by editors via Canvas props) use `!important` and reliably win. This is the desired behavior — presets provide brand defaults, utilities provide editorial overrides.

---

## The Full-Bleed Pattern

The page template wraps Canvas content in a Bootstrap `.container` (max-width ~1140px). Sections that need edge-to-edge backgrounds use the **CSS breakout pattern**:

```scss
// In style.scss
.full-bleed {
  width: 100vw;
  position: relative;
  left: 50%;
  right: 50%;
  margin-left: -50vw;
  margin-right: -50vw;
}
```

This is an exception to the "Bootstrap owns sizing" rule because Bootstrap has no breakout utility. It is a multi-property layout pattern.

**Critical:** When using `full-bleed` on a Wrapper, set `width_class: none` in the component props. The default `w-100` outputs `width: 100% !important` which overrides the breakout's `width: 100vw`.

### Full-bleed section pattern (correct):

```
Wrapper (html_tag: section, custom_class: "full-bleed", preset: hero-dark, width_class: none)
  +-- Wrapper (container_type: container)
        +-- Heading (preset: hero)
        +-- Paragraph (preset: lead)
        +-- Button
```

- Outer Wrapper: `full-bleed` for breakout + preset for visual treatment. **width_class: none**.
- Inner Wrapper: `container` to constrain content.

---

## How to Style a New Section

### Option A: Use a Preset (Preferred)

If a section matches an existing preset pattern, use it directly:

```
Wrapper (preset: hero-dark)
  └─ Heading (preset: hero)
  └─ Paragraph (preset: lead)
```

No SCSS needed. The preset classes handle all visual styling. Override individual props if the preset is 80%+ correct.

### Option B: Create a New Preset (Design System Extension)

If no preset matches and the section will be reused, add a new preset class:

1. Define the class in `_layout-presets.scss` (for composition) or `_typography-roles.scss` (for text treatment)
2. Use brand tokens from `_tokens.scss` — never hardcode hex values
3. Use elevation mixins for shadows, spacing references from `$spacers`
4. Add the preset to the Twig template's preset map
5. Rebuild with `npm run build`

### Option C: Custom SCSS Class (One-off)

If the section is truly unique, write a custom class in `style.scss`:

```scss
// Good: only visual properties, using token variables
.my-section {
  background-color: $brand-900;
  color: $neutral-0;

  h2 {
    color: $neutral-0;
  }

  p {
    color: rgba($neutral-0, 0.85);
  }
}
```

```scss
// Bad: mixing layout into SCSS
.my-section {
  background-color: $brand-900;
  display: flex;           // ← Bootstrap utility: d-flex
  justify-content: center; // ← Bootstrap utility: justify-content-center
  padding: 3rem 0;         // ← Bootstrap utility: py-5
}
```

```scss
// Bad: hardcoded hex values
.my-section {
  background-color: #000D4D;  // ← Use $brand-900
  color: #ffffff;              // ← Use $neutral-0
}
```

Apply via `custom_class`:

```
Wrapper (
  custom_class: "my-section"       ← SCSS handles background + colors
  padding_y: py-5                  ← Bootstrap handles spacing
  flex_enabled: true               ← Bootstrap handles flex
)
```

### Option D: SDC Component (Reusable Pattern)

If the section is a reusable multi-element pattern, create a Tier 1 SDC component. See `component-strategy.md` → "SDC Gold Standard Pattern".

---

## SDC Component SCSS Rules

Each Tier 1 SDC has its own `.scss` file alongside its Twig template.

**Import chain:**
```scss
@import "component-base";  // Provides $type-scale, $brand-*, mixins — zero CSS output
```

**Rules:**
- Use BEM naming: `.component-name`, `.component-name__element`, `.component-name--modifier`
- Use `$type-scale` for font sizes: `@include font-size(map-get($type-scale, hero))`
- Use `$brand-*` / `$neutral-*` tokens for colors
- Use `elevation-*` mixins for shadows
- **Never** import `_typography-roles` or `_layout-presets` — their classes would duplicate in every component's compiled CSS
- SDC styles are compiled in-place by Webpack (e.g., `hero-carousel.scss` → `hero-carousel.css`)
- See `canvas-sdc-example.md` for the full worked example of SDC SCSS with token integration

---

## Code Component CSS (canvas-components/*)

Code components (Preact/React islands rendered via `<canvas-island>`) have standalone CSS in their `index.css`. Rules:

1. **Use CSS custom properties from the theme** for any color in the token palette:
   ```css
   .my-component { color: var(--color-brand-primary); }
   ```

2. **Use hex values only** for colors unique to the component (gradient intermediates, component-specific accents) that don't exist in `_tokens.scss`.

3. **Code components manage their own layout.** They are not wrapped by the SDC Wrapper, so Bootstrap utilities don't apply. Internal layout (flex, grid, sizing) is written directly in `index.css`.

4. **BEM naming.** Use `block__element--modifier` pattern. No Tailwind, no `cn()`, no `twMerge()`.

---

## Token Pipeline & Color Rules

Colors flow through three layers before reaching the browser:

```
_tokens.scss (raw hex)  →  $brand-500, $neutral-0
      ↓
_variables.scss (Bootstrap mapping)  →  $primary: $brand-500
      ↓
_semantic.scss (CSS custom properties)  →  --color-brand-primary, --color-text-inverse
      ↓
Components consume var(--color-*) or $token-name
```

### Rules

1. **Never hardcode hex values in SCSS.** Use token variables from `_tokens.scss` (e.g., `$neutral-0` not `#ffffff`, `$brand-500` not `#2246FA`).
2. **Never hardcode hex values in code component CSS.** Use `var(--color-*)` from `_semantic.scss`.
3. **rgba() with tokens:** In SCSS, use `rgba($neutral-0, 0.15)` — the compiler resolves it. In code component CSS, `rgba(255, 255, 255, 0.15)` with a comment identifying the source token (CSS can't do `rgba(var(--hex), opacity)`).
4. **New colors:** Add to `_tokens.scss` first. Map in `_variables.scss` if Bootstrap needs it. Map in `_semantic.scss` if code components need it. Never add colors directly to `style.scss`.

For the full token reference (available CSS custom properties), see [Theming](../../drupal/docs/theming/theming.md) → Available CSS Custom Properties.

---

## Typography: Brand Type Scale

The brand owns a semantic type scale in `_typography.scss`. See `component-strategy.md` → "Brand Type Scale" for the full map.

All typography decisions derive from `$type-scale`:
- **Bootstrap overrides:** `$display-font-sizes`, `$lead-font-size`, `$small-font-size` are mapped from `$type-scale`
- **Role classes:** `.role-heading-hero`, `.role-text-lead`, etc. reference `$type-scale` via `map-get()`
- **SDC SCSS:** Components use `$type-scale` directly via `@import "component-base"`

This means: changing a size in `$type-scale` propagates everywhere — Bootstrap classes, role classes, and SDC components.

---

## Quick Reference: Where Does This Property Go?

| CSS Property | Owner | How to Apply |
|-------------|-------|-------------|
| `width`, `height`, `min-*`, `max-*` | Bootstrap | Wrapper `width_class`/`height_class` or `custom_class: "vw-100"` |
| `padding-*` | Bootstrap | Wrapper `padding_*` props |
| `margin-*` | Bootstrap | Wrapper `margin_*` props |
| `display` | Bootstrap | Wrapper `flex_enabled` or `custom_class: "d-block"` |
| `flex-*`, `justify-*`, `align-*` | Bootstrap | Wrapper flex props |
| `gap` | Bootstrap | Wrapper `flex_gap` |
| `text-align` | Bootstrap | `custom_class: "text-center"` or Heading `alignment` |
| `background-*` | **Preset or Custom SCSS** | Preset class or named class in `style.scss` via `custom_class` |
| `color` (text) | **Preset or Custom SCSS** | Preset class (section context) or named class via `custom_class` |
| `font-size`, `font-weight` | **Role class or Custom SCSS** | Role class (via preset) or named class via `custom_class` |
| `letter-spacing` | **Role class or Custom SCSS** | Role class (`.role-label`) or named class via `custom_class` |
| `border-*` | **Preset or Custom SCSS** | Preset class or named class via `custom_class` |
| `box-shadow` | **Preset or Custom SCSS** | Preset class or named class via `custom_class` |
| `transition`, `transform`, `animation` | **Custom SCSS** | Named class via `custom_class` |
| `::before`, `::after` | **Custom SCSS** | Named class via `custom_class` |

### Exception: Full-bleed breakout

The `.full-bleed` class sets `width: 100vw` + positioning/margins. Use with `width_class: none` to avoid `w-100 !important` conflict.

### Exception: Component-specific spacing

If a design requires a non-standard spacing value (e.g., `padding: 2.5rem 0`) that doesn't match any Bootstrap step, it can go in SCSS as part of a named component class or preset. Document why the standard utility doesn't work.

---

## Constraints

- **Never use `!important` in custom SCSS or preset/role classes.** Preset/role classes use normal specificity by design — Bootstrap utility overrides should win. If you need `!important`, you're applying both a preset and a conflicting custom class to the same element — fix the approach instead.
- **Never write custom SCSS for `display`, `flex`, `width`, `height`, `padding`, `margin`, `gap`, or `text-align`** unless it's a multi-property pattern Bootstrap doesn't cover (like `.full-bleed`) or a non-standard value (like `padding: 2.5rem`).
- **Never use Tailwind CSS.** Not in SCSS, not in code components, not anywhere.
- **All colors must trace to `_tokens.scss`.** In SCSS, use raw token variables (`$brand-500`, `$neutral-0`). In code component CSS, use CSS custom properties (`var(--color-brand-primary)`). Never hardcode hex values in either context.
- **Never import `_typography-roles` or `_layout-presets` in SDC component SCSS.** These compile to CSS classes — importing them in every component duplicates those classes in every component's output. SDC SCSS imports `component-base` (which emits zero CSS) and uses variables/mixins directly.

## Failure Modes

- **`w-100` kills full-bleed**: If a full-bleed Wrapper doesn't have `width_class: none`, the breakout silently fails. The section renders contained instead of edge-to-edge.
- **Custom SCSS overridden by utilities**: Any custom class that sets padding, margin, width, display, or flex on an element that also has Bootstrap utilities will be ignored (utilities have `!important`).
- **Hex values drift from tokens**: Code component CSS with hardcoded hex values won't update when `_tokens.scss` changes. Use `var(--color-*)` custom properties instead.
- **Preset utility arrays in Twig**: Scattering Bootstrap utilities in Twig preset maps bypasses the token pipeline. Design decisions can't use brand tokens, broken classes go undetected, and changing the brand requires editing every Twig template. **Fix:** Use single semantic classes from `_typography-roles.scss` / `_layout-presets.scss`.
- **SDC SCSS imports role/preset files**: Classes like `.role-heading-hero` appear in every component's compiled CSS file. **Fix:** SDC SCSS imports only `component-base`.
