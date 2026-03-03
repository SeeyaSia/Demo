# Component Strategy & Design System Architecture

## Purpose

Defines the design system layers, component strategy, and preset system for this site. This document is the **single source of truth** for how visual decisions are made — from raw tokens through SCSS classes to Twig templates and Canvas composition. Read this before building any page, creating any component, or writing any SCSS.

---

## The Design System: 8-Layer Architecture

Every visual decision on this site flows through these layers, in order. The brand owns the top; Bootstrap is implementation, not authority.

```
┌─────────────────────────────────────────────────────────────┐
│ 1. DESIGN TOKENS                        _tokens.scss        │
│    Raw palette: $brand-*, $neutral-*, $state-*              │
│    Compile-time only. Never used directly in components.    │
├─────────────────────────────────────────────────────────────┤
│ 2. BOOTSTRAP VARIABLE OVERRIDES         _variables.scss     │
│    Maps tokens → Bootstrap vars: $primary, $dark, $light    │
│    + _typography.scss: font families, weights, type scale   │
│    Brand decisions feed INTO Bootstrap, not the reverse.    │
├─────────────────────────────────────────────────────────────┤
│ 3. BRAND TYPOGRAPHY ROLES               _typography-roles.scss│
│    Semantic text identity: .role-heading-hero, .role-label  │
│    Font-size, weight, letter-spacing — NO layout.           │
│    Compiled once in style.css. Used by Twig preset maps.    │
├─────────────────────────────────────────────────────────────┤
│ 4. SEMANTIC TOKENS (CSS Custom Properties)  _semantic.scss  │
│    Runtime vars: --color-bg-page, --shadow-sm, etc.         │
│    Light/dark mode support. Used by global overrides & SDCs.│
├─────────────────────────────────────────────────────────────┤
│ 5. LAYOUT PRESETS                       _layout-presets.scss │
│    Section/card composition: .preset-section-hero-dark      │
│    Background, padding, shadow, flex, inherited text color. │
│    Compiled once in style.css. Used by Twig preset maps.    │
├─────────────────────────────────────────────────────────────┤
│ 6. GLOBAL BOOTSTRAP OVERRIDES           style.scss          │
│    .card, .btn-primary, .accordion-button, body, a, etc.   │
│    Brand-correct defaults for all Bootstrap components.     │
├─────────────────────────────────────────────────────────────┤
│ 7. SDC COMPONENT STYLES                 components/*.scss   │
│    Per-component CSS via @import "component-base".          │
│    Uses tokens + variables directly. BEM naming.            │
│    Compiled in-place by Webpack. Does NOT import layers 3/5.│
├─────────────────────────────────────────────────────────────┤
│ 8. CANVAS COMPOSITION                   Twig preset maps    │
│    Preset enum → single semantic class from layer 3 or 5.   │
│    Individual prop overrides → Bootstrap utilities (!important) │
│    win over preset classes (normal specificity).            │
└─────────────────────────────────────────────────────────────┘
```

### How Layers Interact

**CSS load order:** `bootstrap.css` → `style.css` (layers 3, 5, 6) → per-component CSS (layer 7)

**Specificity model:**
- Preset/role classes (layers 3, 5) use **normal specificity** — they are sensible defaults
- Bootstrap utility overrides (editor-set props in Canvas) use **`!important`** — they reliably win
- This means: presets are the starting point, individual prop tweaks override them

**The zero-CSS constraint:** SDC component SCSS files import `_component-base.scss`, which provides variables and mixins but emits **zero CSS**. Role and preset classes (layers 3, 5) are compiled **once** in `style.css` — if they were in `component-base`, they'd duplicate in every component's compiled CSS.

---

## Brand Type Scale

The brand owns a semantic type scale. All typography — Bootstrap display classes, role classes, SDC component SCSS — derives from this single map.

```scss
// _typography.scss
$type-scale: (
  hero:       3.5rem,    // Large hero headlines
  section:    2.5rem,    // Section titles
  card:       1.25rem,   // Card/item titles
  lead:       1.25rem,   // Introductory paragraph text
  body:       1rem,      // Standard body text (= $font-size-base)
  caption:    0.875rem,  // Captions, metadata, timestamps
  fine-print: 0.75rem,   // Legal, disclaimers, footnotes
  label:      0.875rem,  // Uppercase labels, category markers
);
```

Bootstrap's `$display-font-sizes`, `$lead-font-size`, and `$small-font-size` are explicitly overridden to reference this map. Even where values match Bootstrap defaults, the brand declares them — making the brand the authority, not Bootstrap.

---

## Typography Role Classes (Layer 3)

Defined in `_typography-roles.scss`. These give text **semantic identity** — what "hero heading" or "lead paragraph" means for this brand.

**Rules:**
- Font-size, weight, letter-spacing, line-height only
- NO layout properties (no padding, flex, gap)
- Text color ONLY when it's part of the role's identity (`.role-label` = muted, `.role-text-caption` = muted)
- All sizes reference `$type-scale`; all weights reference `$font-weight-*`

### Available Roles

| Class | Size | Weight | Other | Use for |
|-------|------|--------|-------|---------|
| `.role-heading-hero` | 3.5rem (hero) | Bold | — | Large hero headlines |
| `.role-heading-section` | 2.5rem (section) | Semibold | text-align: center | Section titles |
| `.role-heading-card` | 1.25rem (card) | Semibold | — | Card/item headings |
| `.role-heading-subtle` | (inherited) | Light | color: muted | Subdued headings |
| `.role-text-lead` | 1.25rem (lead) | Light | — | Introductory paragraphs |
| `.role-text-caption` | 0.875rem (caption) | (inherited) | color: muted | Image captions, metadata |
| `.role-text-fine-print` | 0.75rem (fine-print) | Light | color: secondary | Legal text, disclaimers |
| `.role-label` | 0.875rem (label) | Semibold | uppercase, letter-spacing: 0.1em, color: muted | Category markers, labels |

---

## Layout Preset Classes (Layer 5)

Defined in `_layout-presets.scss`. These give sections and cards their **visual composition** — background, padding, shadow, flex layout.

**Rules:**
- NO typography sizing (no font-size, font-weight, letter-spacing)
- CAN set inherited text color (dark sections must have readable text on their background)
- All colors reference `$brand-*` / `$neutral-*` tokens
- All spacing references `$spacers` map
- All shadows use elevation mixins

### Section Presets (Wrapper component)

| Class | Background | Text Color | Layout | Use for |
|-------|-----------|------------|--------|---------|
| `.preset-section-hero-dark` | `$brand-900` | `$neutral-0` | centered, flex-col, gap-3, py-5 | Hero banners, dark callouts |
| `.preset-section-hero-primary` | `$primary` | `$neutral-0` | centered, flex-col, gap-3, py-5 | CTA sections, brand moments |
| `.preset-section-light` | `$light` | (inherited) | flex-col, gap-4, py-5 | Alternating content sections |
| `.preset-section-content` | (inherited) | (inherited) | flex-col, gap-4, py-5 | Standard body content |
| `.preset-section-cta` | `$primary` | `$neutral-0` | centered, flex-col, gap-3, py-5, rounded, shadow | Call-to-action blocks |
| `.preset-section-feature-strip` | (inherited) | (inherited) | flex-col, gap-4, py-5, top border | Clean section separators |

### Card Presets (Card component)

| Class | Effect | Use for |
|-------|--------|---------|
| `.preset-card-elevated` | Shadow + large radius | Default raised card |
| `.preset-card-bordered` | Primary border + large radius | Attention without weight |
| `.preset-card-dark` | `$brand-900` bg, white text, shadow, large radius | Contrast/inverted |
| `.preset-card-flat` | `$light` bg, no border, large radius | Subtle grouping |
| `.preset-card-glass` | Semi-transparent white, shadow, no border, large radius | Modern frosted look |

---

## The Preset System in Twig

Preset maps in Canvas primitive Twig templates have been refactored to reference **single semantic classes** from layers 3 and 5, instead of scattered Bootstrap utility arrays.

### How It Works

Each Canvas primitive (Wrapper, Heading, Paragraph, Card) exposes a `preset` enum prop. When an editor selects a preset, the Twig template maps it to a single SCSS-defined class:

```twig
{# wrapper.twig #}
{% set preset_classes = {
  'hero-dark':       ['preset-section-hero-dark'],
  'hero-primary':    ['preset-section-hero-primary'],
  'light-section':   ['preset-section-light'],
  'content-section': ['preset-section-content'],
  'cta-banner':      ['preset-section-cta'],
  'feature-strip':   ['preset-section-feature-strip'],
} %}
```

```twig
{# heading.twig #}
{% set preset_classes = {
  'hero':            ['role-heading-hero'],
  'section-title':   ['role-heading-section'],
  'card-title':      ['role-heading-card'],
  'subtle':          ['role-heading-subtle'],
  'uppercase-label': ['role-label'],
} %}
```

```twig
{# paragraph.twig #}
{% set preset_classes = {
  'lead':       ['role-text-lead'],
  'caption':    ['role-text-caption'],
  'fine-print': ['role-text-fine-print'],
} %}
```

```twig
{# card.twig #}
{% set preset_classes = {
  'elevated': ['preset-card-elevated'],
  'bordered': ['preset-card-bordered'],
  'dark':     ['preset-card-dark'],
  'flat':     ['preset-card-flat'],
  'glass':    ['preset-card-glass'],
} %}
```

### Why Single Classes

**Before (scattered utilities):**
```twig
'hero-dark': ['bg-dark', 'text-white', 'text-center', 'py-5', 'd-flex', 'flex-column', 'gap-3', 'align-items-center']
```
Problems: Design decisions live in Twig, not SCSS. Can't use brand tokens. Broken class references go undetected (e.g., `letter-spacing-1` doesn't exist). Changing the brand requires editing every Twig template.

**After (semantic class):**
```twig
'hero-dark': ['preset-section-hero-dark']
```
Benefits: Design lives in SCSS with full token access. Build-time compilation catches errors. Changing the brand means editing one SCSS file. Same class works in both Canvas presets and SDC templates.

### Preset Override Rules

1. **Always check if a preset covers the use case** before setting individual props.
2. **Individual prop overrides win.** Bootstrap utilities set by Canvas props use `!important`; preset classes use normal specificity. Presets are defaults, not constraints.
3. **Wrapper presets auto-set `html_tag: section` and `container_type: container`** — don't redundantly set these.
4. **The Preset Showcase** at `/node/6` shows every preset live. Reference it when choosing presets.

---

## Traced Example: "Lead Text in a Dark Section"

This example follows a single design decision through all 8 layers to show how the system works.

**The intent:** A dark hero section with lead-sized introductory text.

| Layer | What happens | File |
|-------|-------------|------|
| 1. Tokens | `$brand-900: #000D4D`, `$neutral-0: #FFFFFF` defined | `_tokens.scss` |
| 2. Bootstrap overrides | `$dark: $brand-900`. `$lead-font-size: map-get($type-scale, lead)` = 1.25rem | `_variables.scss`, `_typography.scss` |
| 3. Typography roles | `.role-text-lead { font-size: 1.25rem; font-weight: 300 }` | `_typography-roles.scss` |
| 4. Semantic tokens | `--color-text-inverse: #FFFFFF` available for dark contexts | `_semantic.scss` |
| 5. Layout presets | `.preset-section-hero-dark { background: $brand-900; color: $neutral-0; ... }` | `_layout-presets.scss` |
| 6. Global overrides | `body { color: var(--color-text-primary) }` sets default | `style.scss` |
| 7. SDC styles | (not applicable — using Canvas primitives here) | — |
| 8. Canvas composition | Wrapper `preset: hero-dark` → Paragraph `preset: lead` | Twig preset maps |

**Result:** The Wrapper gets `.preset-section-hero-dark` (dark background + white inherited text color). The Paragraph gets `.role-text-lead` (1.25rem, light weight). Text color inherits from the section — no explicit color needed on the paragraph.

**If CSS changes:** Updating `$brand-900` in `_tokens.scss` changes the dark section background everywhere — in Canvas presets, SDC components, and global styles. One change, one build, complete consistency.

---

## Component Architecture

### Two-Tier SDC Strategy

Components follow a two-tier approach. The balance between tiers depends on the project's needs — heavily customized sites may lean more toward SDCs, while simpler sites may use more Canvas primitives.

#### Tier 1: Purpose-Built SDC Components (primary)

Reusable, design-specific components that encapsulate complete visual patterns. Create an SDC when the pattern:
- Appears on 2+ pages (or has complexity warranting encapsulation)
- Requires custom CSS beyond what presets/utilities provide
- Has client-side behavior (animations, interactions)
- Must enforce brand consistency every time it's used

**Current Tier 1 SDCs:**
- `hero-carousel` — Full-viewport carousel with crossfade transitions, info bar, auto-rotation
- `carousel-slide` — Individual slide with background image, title, stat (child of hero-carousel)

**Location:** `web/themes/custom/alchemize_forge/components/<component-name>/`

#### Tier 2: Canvas Primitive Components (supporting)

12 Bootstrap-based SDC primitives for one-off content and layout glue between Tier 1 components. **Always use presets** rather than manually configuring individual style props.

**The 12 primitives:** Wrapper, Heading, Paragraph, Button, Link, Image, Card, Row, Column, Accordion, Accordion Container, Blockquote

**Use primitives for:**
- Section wrappers with presets (spacing, background between SDC sections)
- One-off text blocks that don't warrant a custom SDC
- Simple layouts that appear only once on the site
- Quick prototyping before promoting a pattern to Tier 1

### Decision Reference: SDC or Primitives?

| Signal | Decision |
|--------|----------|
| The pattern appears on 2+ pages | **Create SDC** |
| The pattern has custom CSS (not just presets/utilities) | **Create SDC** |
| The pattern has client-side behavior | **Create SDC** |
| It's simple text + spacing that appears once | **Use primitives + presets** |
| It's a spacer/separator between SDC sections | **Use primitives + presets** |
| It's a quick prototype to validate a layout idea | **Use primitives**, promote to SDC if reused |

### The Mentality: Avoid Micro-Customizations

The design system should guide agents toward **presets, libraries, and components** — not one-off utility combinations. When building a page:

1. **Favor presets over manual props.** If a preset covers 80% of the need, use the preset and override the remaining 20% with individual props.
2. **Favor SDCs over repeated primitive compositions.** If you find yourself building the same Wrapper → Heading → Paragraph → Button pattern more than once, it should be an SDC.
3. **Favor the design system over pixel-perfect tweaks.** The brand type scale and preset library define what this site looks like. Deviating from them for minor visual differences creates inconsistency.

---

## SDC Gold Standard Pattern

The Hero Carousel is the reference pattern for new SDC components. For the full worked example — design analysis through YAML, Twig, SCSS, JS, build, and Canvas integration — see **`canvas-sdc-example.md`**. The conventions below are the summary.

### File Structure

```
web/themes/custom/alchemize_forge/components/<component-name>/
├── <component-name>.component.yml    # Metadata: props, slots, group
├── <component-name>.twig             # Twig template
├── <component-name>.scss             # Styles (imports component-base)
├── <component-name>.js               # Optional: client-side behavior
└── placeholder.jpg                   # Optional: default image for Canvas preview
```

### component.yml Conventions

```yaml
$schema: https://git.drupalcode.org/project/drupal/-/raw/HEAD/core/modules/sdc/src/metadata.schema.json

name: Human Readable Name
description: >-
  Clear description of what this component does, where to use it,
  and what to put in its slots.
type: component
status: experimental
group: Canvas Bootstrap

props:
  type: object
  required:
    - required_prop_name
  properties:
    required_prop_name:
      type: string
      title: Prop Title          # REQUIRED for Canvas visibility
      description: What this prop controls.
      examples:
        - "Default value"        # First example = default in Canvas editor

slots:
  slot_name:
    title: Slot Title            # REQUIRED for Canvas visibility
    description: What goes here.
```

### Twig Conventions

```twig
{# Component root element with BEM class #}
<section{{ attributes.addClass('my-component') }}>

  {# Slot blocks MUST be direct children of root element #}
  {% block slot_name %}{% endblock %}

  {# Fixed structural elements #}
  <div class="my-component__overlay">
    {{ heading }}
  </div>

</section>
```

### SCSS Conventions

```scss
@import "component-base";  // Always import — provides variables + mixins, zero CSS output

.my-component {
  // Use $type-scale for sizes, $brand-* for colors, elevation mixins for shadows
  @include font-size(map-get($type-scale, hero));
  background-color: $brand-900;
  color: $neutral-0;
  @include elevation-md;

  &__child {
    // BEM child element
  }

  &--modifier {
    // BEM modifier
  }
}
```

**Key rules:**
- Use BEM naming: `.component-name`, `.component-name__element`, `.component-name--modifier`
- Import `component-base` — it provides `$type-scale`, `$brand-*`, `$font-weight-*`, elevation mixins, etc.
- Never import `_typography-roles` or `_layout-presets` in component SCSS (would duplicate classes)
- SDC SCSS uses tokens/variables directly, not role/preset classes — those are for Twig presets

---

## Composition Patterns

### Pattern: SDC Section + Primitive Spacer

The most common page pattern: purpose-built SDC sections separated by preset-styled primitives.

```
Hero Carousel SDC                    ← Tier 1: reusable hero component
Wrapper (preset: content-section)    ← Tier 2: one-off intro text
  ├─ Heading (preset: section-title)
  └─ Paragraph (preset: lead)
Feature Cards SDC                    ← Tier 1: reusable feature grid
Wrapper (preset: cta-banner)         ← Tier 2: one-off CTA
  ├─ Heading (preset: hero)
  ├─ Paragraph (preset: lead)
  └─ Button
Testimonials SDC                     ← Tier 1: reusable testimonials
```

### Pattern: SDC with Preset-Styled Wrapper

An SDC can be placed inside a Wrapper with a preset for section context:

```
Wrapper (preset: hero-dark)          ← Tier 2: provides dark section context
  └─ [slot: content]
      └─ Custom Stats Bar SDC        ← Tier 1: stats display component
```

### Pattern: Parent + Child SDC Pair

For container/item relationships, create two SDCs:

```
Hero Carousel SDC (parent)
  └─ [slot: slides]
      ├─ Carousel Slide SDC (child)
      ├─ Carousel Slide SDC (child)
      └─ Carousel Slide SDC (child)
```

Works for: carousels + slides, grids + grid items, tabs + tab panels, accordions + items.

---

## SCSS File Reference

| File | Layer | Emits CSS? | What It Does |
|------|-------|-----------|-------------|
| `_tokens.scss` | 1 | No | Raw hex palette: `$brand-*`, `$neutral-*`, `$state-*` |
| `_variables.scss` | 2 | No | Maps tokens → Bootstrap vars: `$primary`, `$dark`, borders, shadows |
| `_typography.scss` | 2 | No | Font families, weights, `$type-scale` map, Bootstrap typography overrides |
| `_component-base.scss` | — | **No** | Variables-and-mixins-only import for SDC component SCSS. Safe to `@import` in every component. |
| `_semantic.scss` | 4 | Yes (`:root` vars) | CSS custom properties for light/dark mode |
| `_mixins.scss` | — | No | `elevation-sm/md/lg`, `transition-fast/base` mixins |
| `_typography-roles.scss` | 3 | Yes | `.role-*` classes — semantic text identity |
| `_layout-presets.scss` | 5 | Yes | `.preset-*` classes — section/card composition |
| `style.scss` | 6 | Yes | Imports all layers + global Bootstrap overrides |
| `components/*.scss` | 7 | Yes (per-component) | BEM-named component styles |

### Build Chain

**Webpack** compiles:
- Global SCSS (`scss/*.scss`) → `css/` directory (e.g., `style.scss` → `css/style.css`)
- Component SCSS (`components/**/*.scss`) → compiled in-place (e.g., `hero-carousel.scss` → `hero-carousel.css`)
- `includePaths: [scss/]` allows component SCSS to `@import "component-base"` from anywhere

Build command: `npm run build` (from theme directory) or `ddev theme-build`

---

## Constraints and Tradeoffs

- **SDC creation requires theme build knowledge.** Agents creating SDCs need to understand `.component.yml` schema, Twig, SCSS, and the build pipeline. Higher upfront complexity for better long-term reuse.
- **`group: Canvas Bootstrap` is the current convention.** All SDCs use this group. Future: separate groups for Tier 1 vs Tier 2.
- **Presets are defined in Twig maps pointing to SCSS classes.** Changing a preset's visual treatment means editing the SCSS class and rebuilding. The Twig preset map only changes if presets are added/removed.
- **No SDC array props yet.** Canvas doesn't support `array` type props, so SDCs with variable-count items use slots (like Hero Carousel's `slides` slot).
- **Flat SCSS directory (for now).** All SCSS files live in `scss/`. If the file count grows significantly, consider `scss/roles/` and `scss/presets/` subdirectories.
- **Layout presets include inherited text color.** Dark sections (`hero-dark`, `cta`) MUST set `color: $neutral-0` for readability. This is intentional — without it, text on dark backgrounds would be invisible. Role classes should NOT need to set their own color for dark contexts; it inherits from the section.

## Failure Modes

- **Agent creates everything from primitives**: No reusable components. Every page rebuilt from scratch. **Fix:** Follow the SDC-first workflow.
- **Agent manually sets 10 props instead of using a preset**: Inconsistent styling. **Fix:** Always check presets first.
- **Agent uses Bootstrap utility arrays in Twig presets**: Design decisions scattered across Twig, not SCSS. Can't use tokens. Broken classes undetected. **Fix:** Every preset maps to a single semantic class from `_typography-roles.scss` or `_layout-presets.scss`.
- **SDC SCSS imports `_typography-roles` or `_layout-presets`**: Role/preset classes duplicate in every component's compiled CSS. **Fix:** SDC SCSS imports only `component-base` (zero-CSS output).
- **SDC slot `{% block %}` nested inside wrapper div**: Canvas drag-and-drop misfires. **Fix:** Slot blocks must be direct children of root HTML element.

## Integration Points

- **`css-strategy.md`** — Detailed rules for SCSS authoring, Bootstrap utility ownership, full-bleed pattern
- **`canvas-build-guide.md`** — Design-to-component workflow (Phase 0: SDC identification, Phase 1+: primitive composition with presets)
- **`canvas-sdc-components.md`** — Complete prop/slot reference for all SDC components
- **`canvas-sdc-example.md`** — **Tier 1 SDC worked example** — Hero Carousel from design intent through YAML/Twig/SCSS/JS to Canvas integration
- **`canvas-build-example.md`** — **Tier 2 primitive worked example** — ExampleHomepage from design decomposition to working Drush script
- **`canvas-code-components.md`** — Tier 3 escape hatch for dynamic/interactive elements
- **`data-model/media-handling.md`** — SDCs with image props must follow media-first rules
- **`infrastructure/developer-tools.md`** — Full capability script catalog with design system annotations

## Capability Script Reference Examples

The generator scripts in `.alchemize/drupal/capabilities/generators/` serve as living examples for AI agents building Canvas pages. They demonstrate a **balanced approach**:

| Script | Approach | Best for learning |
|--------|----------|-------------------|
| `canvas-build-page` | Preset-first | Preset composition basics (hero-dark, card-title, lead) |
| `build-demo-page` | Preset-first | Full preset variety (all 4 wrapper presets, card presets, individual prop overrides) |
| `canvas-build-projects-page` | Preset-first | Presets + Views block integration |
| `build-preset-demo-page` | Preset catalog | How presets map to SCSS classes (living showcase) |
| `build-component-showcase-page` | Individual props | Prop API reference only — **NOT a template for new work** |

> **Important:** The Component Showcase script (`build-component-showcase-page`) exists solely as a prop API reference — it demonstrates what individual props are available on each component. **Do not use it as a template for building new pages.** For new page work, follow the preset-first scripts above (`canvas-build-page`, `build-demo-page`, `canvas-build-projects-page`). These demonstrate the correct design system approach: use presets for consistent brand styling, add individual prop overrides only when a preset doesn't cover the specific need.

## Notes for Future Changes

- **SDC grouping**: Consider a `Canvas Site Components` group to separate Tier 1 from Tier 2 in the Canvas sidebar.
- **Component library page**: The Preset Showcase (`/node/6`) demonstrates primitives. A similar showcase for Tier 1 SDCs would help editors.
- **Capability scripts**: A `create-sdc-scaffold.drush.php` script could automate boilerplate for new SDCs.
- **Split surface vs structure presets**: If the preset library grows, consider splitting `_layout-presets.scss` into surface (background, color) and structure (flex, gap, padding) concerns.
- **Spacing as first-class system**: Define a brand spacing scale (like `$type-scale` for typography) that feeds `$spacers`. Currently using Bootstrap's default 0–5 scale.
