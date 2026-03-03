# Canvas SDC Components

## Purpose

Developer guide for Single Directory Components (SDCs) in Drupal Canvas. Covers the SDC file structure, JSON Schema prop definitions, slot definitions, Canvas eligibility criteria, and the complete catalog of components available from Bootstrap Forge and Bootstrap Barrio themes.

**Component strategy context:** SDCs on this site follow a **two-tier architecture** within an **8-layer design system**. **Tier 1** (purpose-built SDCs like `hero-carousel`) are the primary building blocks for reusable patterns. **Tier 2** (primitive SDCs like `wrapper`, `heading`, `paragraph`) are glue for one-off content, with **presets** that map to semantic SCSS classes (`.role-*` for typography, `.preset-*` for layout composition). See `canonical-docs/global/component-strategy.md` for the full design system architecture, brand type scale, preset reference, and SDC-first methodology.

## System Overview

SDC components are Drupal's native component architecture: Twig templates with structured metadata. Canvas discovers SDCs from enabled themes and modules, validates them against eligibility criteria, and exposes them as draggable components in the visual editor. Each eligible SDC gets a `canvas.component.sdc.*` config entity.

In a Bootstrap Forge Canvas project, SDCs come from these providers:

- **`alchemize_forge`** (14 components) — Active theme's components. Includes:
  - **Tier 1 (purpose-built):** `hero-carousel`, `carousel-slide` — Reusable, design-specific components with their own styles/behavior
  - **Tier 2 (primitives):** 12 Bootstrap components — Layout and content building blocks for one-off composition
- **`bootstrap_barrio`** (9 components) — Base theme's components that don't overlap with Forge.
- **`olivero`** (1 component) — Drupal core's Olivero theme contributes `sdc.olivero.teaser` (enabled).
- **`navigation`** (1 component) — Drupal 11 navigation module contributes `sdc.navigation.title` (disabled).
- **`canvas_bootstrap`** (12 components) — Module-provided components that are **auto-disabled** because Bootstrap Forge provides equivalents with the same machine name. See `canvas-bootstrap-integration.md`.

**Total:** 37 SDC components (35 enabled, 2 disabled: `sdc.navigation.title` and all 12 `canvas_bootstrap` components).

## SDC File Structure

Every SDC lives in a single directory under `components/` in a theme or module:

```
web/themes/custom/alchemize_forge/components/
├── accordion/
│   ├── accordion.component.yml    # Metadata (required)
│   └── accordion.twig             # Template (required)
├── button/
│   ├── button.component.yml
│   └── button.twig
├── card/
│   ├── card.component.yml
│   └── card.twig
│   └── card.css                   # Optional CSS
└── ...
```

The two required files:
1. **`<name>.component.yml`** — JSON Schema metadata defining props, slots, and component info
2. **`<name>.twig`** — Twig template that renders the component

Optional files: CSS, JavaScript, images, SVGs, README.

## Component Metadata Schema (`component.yml`)

### Top-level keys

```yaml
$schema: https://git.drupalcode.org/project/drupal/-/raw/HEAD/core/assets/schemas/v1/metadata.schema.json

name: Card                          # Human-readable name
status: experimental                # stable | experimental | deprecated | obsolete
group: Canvas Bootstrap             # Category in Canvas sidebar
description: Bootstrap card with... # What the component does
type: component                     # Always "component" for SDCs

props:                              # Explicit inputs (JSON Schema)
  type: object
  properties:
    prop_name:
      type: string
      title: Prop Title             # REQUIRED for Canvas
      description: What this prop does
      default: some-value
      examples: [some-value]        # First example = default in Canvas editor

slots:                              # Named insertion points for child components
  slot_name:
    title: Slot Title               # REQUIRED for Canvas
    description: What goes here
    required: false
```

### Canvas eligibility criteria for SDCs

An SDC **must** meet all of these to appear in Canvas. Checks happen in two stages:

**Stage 1: Discovery filtering** (`SingleDirectoryComponentDiscovery`):
1. `status` is NOT `obsolete`
2. `noUi` is NOT `true` (checked in both metadata and plugin definition)

**Stage 2: Requirements validation** (`ComponentMetadataRequirementsChecker`):
3. `group` is NOT the reserved `"Elements"` category
4. Every slot has a `title`
5. Every prop has a `title`
6. Every enum prop has no empty string values
7. Every **required** prop has at least one `examples` entry (first example = default value)
8. First `examples` entry must validate against the prop's JSON Schema
9. If `contentMediaType: text/html` is used, `x-formatting-context` must be `"inline"` or `"block"` (if present)
10. `meta:enum` keys must not contain dots, and every enum value must have a `meta:enum` entry
11. Every prop's shape is "storable" — Canvas can generate a field type and widget for it

### Prop types supported

| JSON Schema type | Canvas rendering | Notes |
|-----------------|-----------------|-------|
| `string` | Text field | Unrestricted strings are interpreted as "prose" |
| `string` + `enum` | Select list | Must include `meta:enum` for labels; no empty string values |
| `string` + `format: uri` | URI field | |
| `string` + `format: date-time` | Date picker | |
| `string` + `contentMediaType: text/html` | CKEditor 5 (rich text) | Optionally add `x-formatting-context: block` or `inline` |
| `boolean` | Checkbox | |
| `integer` | Number field | Supports `minimum`, `maximum`, `enum` constraints |
| `number` | Number field (float) | Same constraints as integer |
| `$ref: json-schema-definitions://canvas.module/image` | Media library (image) | References a media entity |
| `string` + `pattern` | Text field with regex validation | JSON Schema regex (no delimiters) |

**Not yet supported:** `array` type (e.g., arrays of objects), `exclusiveMinimum`/`exclusiveMaximum`, `multipleOf`, `x-formatting-context: inline` (blocked on CKEditor 5 support).

### Enum props with `meta:enum`

For human-readable labels on enum options:

```yaml
variant:
  type: string
  title: Variant
  default: primary
  enum:
    - primary
    - secondary
    - success
  meta:enum:
    primary: "Primary"
    secondary: "Secondary"
    success: "Success"
```

## Slots

Slots are named insertion points where editors can nest child components:

```yaml
slots:
  card_header:
    title: Card header
    description: Card header content slot.
    required: false
  card_body:
    title: Card body
    description: Card body content slot.
    required: false
```

In the Canvas editor, each slot appears as a drop zone that accepts other components.

### Slot Twig template rule (critical for drag-and-drop)

Canvas identifies slot drop zones by finding `<!-- canvas-slot-start-{uuid}/{slotName} -->` HTML comments in the rendered preview and mapping them to their **parent HTML element**. This means the `{% block %}` for a slot must be a **direct child of the component's root element** — not nested inside an intermediate wrapper div.

```twig
{# CORRECT — block is a direct child of root element #}
<section{{ attributes.addClass('my-component') }}>
  {% block my_slot %}{% endblock %}
  <div class="my-component__overlay"></div>
</section>

{# WRONG — block is inside a wrapper div #}
<section{{ attributes.addClass('my-component') }}>
  <div class="my-component__inner">
    {% block my_slot %}{% endblock %}
  </div>
  <div class="my-component__overlay"></div>
</section>
```

**Why:** When the slot block is inside a wrapper div, Canvas maps the drop zone to that wrapper element. If the wrapper is positioned/sized identically to the component root (e.g., `position: absolute; inset: 0`), the slot overlay and component overlay compete during drag-and-drop collision detection, and dropped components end up as siblings of the parent instead of landing inside the slot. Making the block a direct child of the root ensures the slot and component share the same element, and `@dnd-kit` correctly resolves the slot as the drop target.

If the slot's children need specific CSS positioning (e.g., `position: absolute`), apply that styling to the child component's own CSS rather than an intermediate wrapper.

### Prop `examples` and placeholder images

The first entry in a prop's `examples` array is used as the default/placeholder value in the Canvas editor. For props that reference entities (e.g., `$ref: json-schema-definitions://canvas.module/image`), Canvas cannot store the example as a real Drupal entity, so it uses a `DefaultRelativeUrlPropSource` that renders the example URL directly.

- **Use relative paths** pointing to a static file inside the component directory (e.g., `placeholder.jpg`). Canvas resolves these to proper local URLs.
- **Never use external random-image services** like `https://picsum.photos/...` — they produce different images on every request, both in preview and on the live site.
- **Omit `examples` entirely** if you want the prop to be empty until the content author fills it (valid for optional props).

## Tier 1: Purpose-Built SDC Components (Active — 2 components)

Source: `web/themes/custom/alchemize_forge/components/`
Config prefix: `canvas.component.sdc.alchemize_forge.*`

These are the **primary building blocks** of the site — reusable, design-specific components with their own styles and behavior. New SDC components should follow this pattern. See `component-strategy.md` for the gold-standard file structure.

| Component | Machine name | Key props | Slots | Pattern |
|-----------|-------------|-----------|-------|---------|
| **Hero Carousel** | `hero-carousel` | `heading`, `subheading`, `auto_rotate_interval` | `slides` | Parent container: full-viewport carousel with crossfade, gradient overlay, info bar with nav |
| **Carousel Slide** | `carousel-slide` | `title` *(req)*, `stat`, `background_image` (image ref) | — | Child item: background image + metadata for parent carousel |

**Hero Carousel is the gold-standard SDC pattern.** All new Tier 1 SDCs should follow its structure:
- `component.yml` with clear prop titles, descriptions, and `examples` for required props
- Twig template with BEM naming, slot blocks as direct children of root element
- SCSS with `@import "component-base"` and design token variables
- Optional JS for client-side behavior (e.g., carousel rotation)

**Parent + Child pattern:** The Hero Carousel / Carousel Slide pair demonstrates the recommended approach for container/item SDCs. The parent defines the structure and slot; the child defines the repeatable item.

> **Full worked example:** See `canvas-sdc-example.md` for the complete story — design analysis, decision to create an SDC, every file explained with conventions, build/registration steps, and how brand changes cascade through the component.

---

## Tier 2: Bootstrap Primitive Components (Active — 12 components)

Source: `web/themes/custom/alchemize_forge/components/`
Config prefix: `canvas.component.sdc.alchemize_forge.*`

These are **layout and content building blocks** for one-off composition, spacing between Tier 1 SDCs, and quick prototyping. **Use presets** on Wrapper, Heading, Paragraph, and Card rather than manually setting individual props — each preset maps to a single semantic SCSS class (`.role-*` from `_typography-roles.scss` for text, `.preset-*` from `_layout-presets.scss` for composition). See `component-strategy.md` → "Typography Role Classes" and "Layout Preset Classes" for the full reference.

| Component | Machine name | Key props | Slots |
|-----------|-------------|-----------|-------|
| **Accordion** | `accordion` | `always_open`, `flush` | `accordion_items` |
| **Accordion Container** | `accordion-container` | `title`, `show` | `accordion_body` |
| **Blockquote** | `blockquote` | `text`, `cite`, `source`, `alignment` | — |
| **Button** | `button` | `text` *(req)*, `variant` *(req)*, `url`, `size`, `outline` | — |
| **Card** | `card` | `show_header/image/footer`, `body_orientation`, `bg_color`, `border_color`, `card_rounding`, `reverse_order` | `card_header`, `card_image`, `card_body`, `card_footer` |
| **Column** | `column` | `col` (base), `col_sm/md/lg/xl/xxl` (responsive widths) | `column` |
| **Heading** | `heading` | `text` *(req)*, `level` *(req)*, `alignment`, `text_color`, `preset` | — |
| **Image** | `image` | `media` (image ref), `size` (aspect ratio), `width_class`, `height_class`, `radius`, `caption` | — |
| **Link** | `link` | `url` *(req)*, `text` *(req)*, `target`, `stretched_link`, `as_button`, `button_variant` | — |
| **Paragraph** | `paragraph` | `text` *(req, HTML)*, `text_color`, spacing props | — |
| **Row** | `row` | `gap/gap_sm/.../gap_xxl`, `gap_x/gap_y` (responsive), `row_cols/row_cols_sm/.../row_cols_xxl` | `row` |
| **Wrapper** | `wrapper` | `html_tag`, `container_type`, `custom_class`, `flex_enabled/direction/gap`, `justify_content`, `align_items`, spacing props | `content` |

### Practical Usage Rules (Validated)

These rules were validated through programmatic page building (see `canvas-build-example.md`):

| Component | Rule |
|---|---|
| **Wrapper** | **Use presets first** (`preset: hero-dark`, `light-section`, `content-section`, `cta-banner`, `feature-strip`) — they set background, text color, layout, and spacing in one class. Presets auto-set `html_tag: section` and `container_type: container`. Only fall back to `custom_class` with Bootstrap bg classes when no preset matches. |
| **Heading** | **Use presets first** (`preset: hero`, `section-title`, `card-title`, `subtle`, `uppercase-label`) — they set font-size and weight from the brand type scale. Alignment prop is `alignment` (text-start/center/end). `text_color` values include the `text-` prefix but are rarely needed when inside a dark preset section (text color is inherited). |
| **Paragraph** | `text` prop has `contentMediaType: text/html` — always wrap in HTML tags: `"<p>Your text</p>"`. Passing plain text works for rendering but breaks CKEditor re-editing. |
| **Button** | `url` accepts relative paths (`/explore`). `size` values are `default`, `sm`, `lg` (not Bootstrap class names like `btn-lg`). |
| **Card** | Slots `card_header`, `card_image`, `card_footer` **only render** when `show_*` is `true` — children in those slots are silently hidden otherwise. `card_body` always renders. Set `position: relative` when using `stretched_link` on a child Link. |
| **Link** | `stretched_link: true` requires nearest ancestor to have `position: relative`. Unicode in `text` works: `"Learn More →"`. |
| **Row** | `row_cols` values are full Bootstrap classes: `row-cols-1`, `row-cols-md-2`, `row-cols-lg-4`. `gap` values: `g-0` through `g-5`. |
| **Column** | `col` value `col` means auto-equal-width. Specific widths: `col-6`, `col-lg-4`, etc. |
| **All enums** | Values are the **full Bootstrap class name**: `py-5` not `5`, `bg-dark` not `dark`, `text-center` not `center`. |

### Card component deep-dive (example)

The card is the most complex SDC component. Its `component.yml` at `web/themes/custom/alchemize_forge/components/card/card.component.yml` demonstrates:

- **Responsive props**: `body_orientation`, `body_orientation_sm/md/lg/xl/xxl` — separate enum for each breakpoint
- **Bootstrap utilities as enums**: `bg_color` (11 options), `border_color` (9 options), `card_rounding` (7 options)
- **Flex layout props**: `body_justify_content_*`, `body_align_items_*` per breakpoint
- **Toggle props**: `show_header`, `show_image`, `show_footer`, `reverse_order`
- **Position prop**: `static` (default), `relative`, `absolute` — set to `relative` when using stretched links
- **4 slots**: `card_header`, `card_image`, `card_body`, `card_footer`
- **`canvas_bootstrap` UI groups**: Props organized into tabbed groups (Card > Structure/Style, Header, Body > Body/Flex, Footer)

## Bootstrap Barrio Components (Active — 9 unique components)

Source: `web/themes/contrib/bootstrap_barrio/components/`
Config prefix: `canvas.component.sdc.bootstrap_barrio.*`

These provide components that Bootstrap Forge doesn't override:

| Component | Machine name | Purpose |
|-----------|-------------|---------|
| **Badge** | `badge` | Bootstrap badge with color variants |
| **Button** | `button` | Barrio's button (different prop set from Forge's) |
| **Card** | `card` | Barrio's card (simpler than Forge's) |
| **Container** | `container` | Bootstrap container wrapper |
| **Div** | `div` | Generic div with class support |
| **Figure** | `figure` | HTML figure/figcaption |
| **Modal** | `modal` | Bootstrap modal dialog |
| **Teaser** | `teaser` | Content teaser display |
| **Toasts** | `toasts` | Bootstrap toast notifications |

**Note:** Barrio's `button` and `card` components have different config entity IDs (`sdc.bootstrap_barrio.button` vs `sdc.alchemize_forge.button`), so both coexist. The deduplication only targets `canvas_bootstrap` module components vs theme components with the **same machine name**.

## canvas_bootstrap Module Components (Auto-disabled — 12 components)

Config prefix: `canvas.component.sdc.canvas_bootstrap.*`

These mirror Bootstrap Forge's 12 components and are automatically disabled by `ThemeAwareSingleDirectoryComponentDiscovery` because the active theme (Bootstrap Forge) provides components with identical machine names. They exist as config entities but cannot be used in the editor.

See `canvas-bootstrap-integration.md` for the deduplication mechanism.

## navigation Module Component (Disabled — 1 component)

Config prefix: `canvas.component.sdc.navigation.*`

- **`sdc.navigation.title`** — Title component from Drupal 11 navigation module. Currently disabled. Provides props: `modifiers`, `extra_classes`, `html_tag`, `icon`, and a `content` slot.

## Change Surface

- `web/themes/custom/alchemize_forge/components/*/` — SDC source files
- `web/themes/custom/alchemize_forge/components/*/*.component.yml` — Component metadata
- `config/<site>/canvas.component.sdc.*` — Component config entities (auto-generated)

## Failure Modes

- **Missing `title` on a prop**: Component fails requirements check and won't appear in Canvas
- **Missing `examples` on a required prop**: Same — component is ineligible
- **Unsupported prop shape**: If Canvas can't match a JSON Schema type to a Drupal field type, the component is ineligible
- **`status: obsolete`**: Component is hidden from Canvas
- **Deduplication failure**: If Bootstrap Forge is not the default theme, `canvas_bootstrap` components won't be auto-disabled, causing duplicates. Fix: run component regeneration (see `canvas-bootstrap-integration.md`)

## Notes for Future Changes

- **Adding a new Tier 1 SDC**: Create a directory in `web/themes/custom/alchemize_forge/components/<name>/` with `<name>.component.yml`, `<name>.twig`, and `<name>.scss`. Follow the gold-standard pattern from `component-strategy.md`. Run `ddev drush cr` to discover it. Canvas auto-generates the config entity. Then `ddev drush cex -y` to export.
- **Modifying props**: After changing a `component.yml`, run `ddev drush cr` to refresh. Then `ddev drush cex -y` to export the updated config entity.
- **Adding presets to a component**: Define a `preset` enum prop in the component's `component.yml`. Create the SCSS class in `_typography-roles.scss` (for text roles, prefix `.role-*`) or `_layout-presets.scss` (for composition, prefix `.preset-*`). Add the preset-to-class mapping in the Twig template (see `wrapper.twig` for the pattern — each preset maps to a single semantic class). Rebuild the theme with `npm run build`. Add new presets to the Preset Showcase page (`/node/6`) by updating `build-preset-demo-page.drush.php`.
- **Adding `canvas_bootstrap` UI groups**: Add a `canvas_bootstrap.ui_groups` key to the component's `component.yml`. See the card component for the full syntax.
- **SDC component grouping**: Currently all SDCs use `group: Canvas Bootstrap`. A future improvement would create separate groups to distinguish Tier 1 (site components) from Tier 2 (primitives) in the Canvas sidebar.
