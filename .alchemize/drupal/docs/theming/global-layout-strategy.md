# Global Layout Strategy

## Purpose

Documents how to set up the foundational site layout — header, footer, content area containers, and global styles — so that individual Canvas content pages (articles, homepage, etc.) can be built without re-thinking global layout. This is the "do once, forget about it" layer.

**Before writing any section CSS**, read [CSS Strategy](../../../canonical-docs/global/css-strategy.md) for the rules on what Bootstrap utilities own vs what custom SCSS owns.

## The Three-Layer Architecture

The site layout has three distinct layers:

### Layer 1: Theme Page Template — `page.html.twig` (the "chrome")

The custom `page.html.twig` in `alchemize_forge` renders page chrome (header, navigation, footer) **directly using `drupal_block()`** from the Twig Tweak module. This bypasses Canvas PageRegions for infrastructure elements, giving the theme full control over the page frame.

```
┌──────────────────────────────────────────────────────┐
│ <header>  <- Theme-driven via drupal_block()          │
│   ├─ Account menu (drupal_block system_menu:account) │
│   ├─ Site branding (drupal_block system_branding)    │
│   └─ Main menu (drupal_block system_menu:main)       │
├──────────────────────────────────────────────────────┤
│ <div id="main-wrapper">                              │
│   <div id="main" class="container"> <- CONSTRAINING  │
│     ├─ Local tasks/actions (drupal_block)             │
│     ├─ CONTENT <- Canvas content templates go here    │
│   </div>                                             │
│ </div>                                               │
├──────────────────────────────────────────────────────┤
│ <footer>  <- Theme-driven via drupal_block()          │
│   └─ Footer content                                  │
│ </footer>                                            │
└──────────────────────────────────────────────────────┘
```

**Key blocks rendered by the theme:**

| Block plugin ID | What it renders |
|----------------|----------------|
| `system_branding_block` | Site logo and name |
| `system_menu_block:main` | Primary navigation menu |
| `system_menu_block:account` | User account menu |
| `page_title_block` | Page title |
| `local_tasks_block` | View/Edit/Delete tabs |
| `local_actions_block` | Action buttons |
| `system_powered_by_block` | "Powered by Drupal" footer |

### Layer 2: Canvas PageRegions (minimal — content-adjacent only)

Only content-adjacent regions use Canvas PageRegions. Infrastructure regions (header, nav, footer) are **not** managed by Canvas.

**Active PageRegions:** Only the `none` region is currently enabled (the minimum needed to keep `CanvasPageVariant` active). The `highlighted` and `breadcrumb` regions exist but are disabled.

**Key rule**: Canvas requires at least one enabled PageRegion to activate as the page display variant. Without any, Drupal falls back to Block Layout and content templates stop working.

### Layer 3: Canvas Content Templates (per-content-type layout)

Content templates control how node content types render. They operate at the entity rendering level via `ContentTemplateAwareViewBuilder` — completely independent of which PageRegions are enabled.

**Key rule**: Content templates work regardless of PageRegion configuration. They intercept node rendering at the view builder level, not the page variant level.

## Critical Container Wrapping Issue

### The Problem

In `page.html.twig` (Bootstrap Barrio), the `content` region is wrapped in:

```html
<div id="main-wrapper" class="layout-main-wrapper clearfix">
  <div id="main" class="container">  <!-- Bootstrap container: max-width constrained -->
    <div class="row row-offcanvas row-offcanvas-left clearfix">
      <main>
        <section class="section">
          {{ page.content }}  <!-- Canvas content renders here -->
        </section>
      </main>
    </div>
  </div>
</div>
```

This means Canvas content renders **inside a `.container` div** with `max-width` constraints (typically 1140px at xl breakpoint). This prevents truly full-width/edge-to-edge sections like heroes or dark background sections.

### What This Means for Page Designs

Pages often need:
- **Full-bleed backgrounds** — extending edge-to-edge across the viewport
- **Content within constrained width** — text and cards centered within a narrower column

With the current `page.html.twig`, background colors from a Wrapper component stop at the container edge, not the viewport edges. To get edge-to-edge backgrounds, use the **CSS breakout pattern**.

### Recommended Approach: CSS Breakout Pattern

**Keep the `container` setting** (fixed max-width). The content area stays constrained at ~1140px, which is correct for readability and standard pages. For sections that need full-bleed backgrounds, use the **CSS breakout pattern**:

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

This pattern:
- Extends the background/element to the full viewport width
- Content inside the element still respects its own container width
- Works inside the `.container` constraint without changing the page template
- No `overflow: hidden` exists on parent elements (verified), so this works cleanly

**Critical: `width_class: none` is required.** The Wrapper component defaults `width_class` to `w-100`, which outputs `width: 100% !important`. This overrides the breakout's `width: 100vw` and silently breaks full-bleed. Always set `width_class: none` on full-bleed Wrappers.

**Implementation**: The `.full-bleed` utility class is defined in theme SCSS. Use it via the Wrapper component's `custom_class` prop along with any background styling class.

**For background images**: CSS `background-image` is the preferred approach. Define a CSS class in `style.scss` that applies the background image, then use it in Wrapper's `custom_class`. The image file goes in the theme's `images/` directory.

### Alternative Approaches (not recommended for this project)

**Option B: `container-fluid`** — Changes theme setting so content area has no max-width. Requires every section to manage its own content width via nested containers. More work, less predictable for standard content pages.

**Option C: Template override** — Override `page.html.twig` to remove `.container` from `#main`. Surgical control but creates maintenance burden when the base theme updates.

**Option D: `featured_top` region** — Place hero content in a region outside the container. Downside: shows on ALL pages (no per-page conditionals yet).

## The Wrapper Component: Your Section Builder

The Wrapper SDC component (`sdc.alchemize_forge.wrapper`) is the key building block for page sections. Its props map directly to Bootstrap utility classes.

### Wrapper Props Reference

| Prop | Purpose | Example Values | Default |
|------|---------|---------------|---------|
| `html_tag` | HTML element type | `section`, `div`, `article` | `div` |
| `container_type` | Bootstrap container class | `container`, `container-fluid`, `none` | `none` |
| `custom_class` | Additional CSS classes (SCSS visual classes) | `"full-bleed approach-section"` | — |
| `width_class` | Bootstrap width utility | `w-100`, `w-auto`, `none` | `w-100` |
| `height_class` | Bootstrap height utility | `h-100`, `h-auto` | — |
| `padding_y` | Vertical padding | `py-3`, `py-5` | — |
| `padding_top` | Top padding | `pt-3`, `pt-5` | — |
| `padding_bottom` | Bottom padding | `pb-3`, `pb-5` | — |
| `margin_bottom` | Bottom margin | `mb-3`, `mb-5` | — |
| `flex_enabled` | Enable flexbox | `true` / `false` | `false` |
| `flex_direction` | Flex direction | `flex-column`, `flex-row` | — |
| `justify_content` | Horizontal alignment | `justify-content-center`, `justify-content-between` | — |
| `align_items` | Vertical alignment | `align-items-center`, `align-items-start` | — |
| `flex_gap` | Flex gap | `gap-2`, `gap-4` | — |

### Key Prop: `width_class`

The `width_class` prop outputs a Bootstrap width utility class on the Wrapper element. It defaults to `w-100`.

**When to use `none`:** Set `width_class: none` when the element manages its own width via `custom_class` — specifically for full-bleed sections. The default `w-100` adds `width: 100% !important` which overrides any custom width declarations.

### Pattern: Full-Bleed Section (Correct)

```
Wrapper (
  html_tag: section,
  custom_class: "full-bleed my-section-bg",
  width_class: none,           <-- CRITICAL: prevents w-100 !important
  padding_y: py-5
)
  └─ [slot: content]
      └─ Wrapper (container_type: container)
          └─ [slot: content]
              ├─ Heading
              ├─ Paragraph
              └─ Button
```

- **Outer Wrapper:** `full-bleed` for viewport breakout + custom SCSS class for background. `width_class: none` prevents the `w-100 !important` conflict.
- **Inner Wrapper:** `container_type: container` constrains content back to standard max-width. Layout props (flex, justify, align, gap) on this Wrapper.
- **SCSS class** (`my-section-bg`): Only visual properties — `background-color`, `color`, heading/paragraph color overrides. No layout/spacing.

### Pattern: Full-Bleed with Layout Header (Correct)

For sections with a header bar (e.g., icon + title with space-between):

```
Wrapper (
  html_tag: section,
  custom_class: "full-bleed approach-section",
  width_class: none,
  padding_y: py-5
)
  └─ Wrapper (container_type: container)
       └─ Wrapper (
            custom_class: "approach-header",
            flex_enabled: true,
            justify_content: justify-content-between,
            align_items: align-items-center,
            padding_bottom: pb-4,
            margin_bottom: mb-4
          )
            └─ Heading + Icon
```

- **Layout** (flex, justify, align, padding, margin): Bootstrap utility props on the Wrapper.
- **Visual** (border-bottom, heading override): Custom SCSS class `approach-header`.
- This separation prevents Bootstrap's `!important` utilities from overriding custom styles.

### Pattern: Standard Section (No Breakout)

```
Wrapper (html_tag: section, padding_y: py-5)
  └─ [slot: content]
      ├─ Heading
      ├─ Paragraph
      └─ Row → Column → components
```

Standard sections don't need breakout — they render within the existing container width. The default `width_class: w-100` is fine here.

### CSS Ownership Split (Summary)

This is a summary. See [CSS Strategy](../../../canonical-docs/global/css-strategy.md) for the complete rules.

| Owned By | Properties | How Applied |
|----------|-----------|-------------|
| **Bootstrap utilities** | width, height, padding, margin, display, flex, gap, text-align | Wrapper component props or `custom_class` |
| **Custom SCSS** | background-color, background-image, color, font-size, border, box-shadow, transition, animation, pseudo-elements | Named class in `style.scss` via `custom_class` |

**Never write custom SCSS that sets a property Bootstrap utilities already control on the same element.** Bootstrap utilities use `!important` and will always win.

## Design Decomposition → Global Regions

Based on a typical homepage design:

```
┌──────────────────────────────────────────────────────────┐
│  HEADER REGION (dark bg, site branding + nav)            │  <- Theme-owned: page.html.twig + drupal_block()
│  Site branding block + Main menu block                   │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  CONTENT REGION (where Canvas content template renders)  │  <- Content Template OR node/view content
│                                                          │
│  ┌────────────────────────────────────────────────────┐  │
│  │  HERO SECTION (full-bleed, gradient bg)            │  │  <- Wrapper (full-bleed, width_class: none)
│  │  Heading + Cards + Controls                        │  │
│  └────────────────────────────────────────────────────┘  │
│                                                          │
│  ┌────────────────────────────────────────────────────┐  │
│  │  STANDARD SECTION (within container)               │  │  <- Wrapper (padding_y: py-5)
│  │  Row → Columns → Cards                            │  │
│  └────────────────────────────────────────────────────┘  │
│                                                          │
│  ┌────────────────────────────────────────────────────┐  │
│  │  DARK SECTION (full-bleed, dark navy bg)           │  │  <- Wrapper (full-bleed, width_class: none)
│  │  Custom SCSS: approach-section                     │  │
│  └────────────────────────────────────────────────────┘  │
│                                                          │
│  ┌────────────────────────────────────────────────────┐  │
│  │  CTA SECTION (full-bleed, brand blue bg)           │  │  <- Wrapper (full-bleed, width_class: none)
│  │  Custom SCSS: cta-section                          │  │
│  └────────────────────────────────────────────────────┘  │
│                                                          │
├──────────────────────────────────────────────────────────┤
│  FOOTER REGION (dark bg, copyright, links)               │  <- Theme-owned: page.html.twig + drupal_block()
└──────────────────────────────────────────────────────────┘
```

## Implementation Workflow

### 1. Container Strategy (decided)

**Decision**: Keep the current `container` (fixed max-width ~1140px). Use the CSS breakout pattern for sections that need edge-to-edge backgrounds.

### 2. Adding a New Full-Bleed Section

1. Create a CSS class in `style.scss` for the visual treatment (background, text colors only)
2. Use token variables — never hardcode hex
3. In Canvas, create a Wrapper with:
   - `custom_class: "full-bleed your-bg-class"`
   - `width_class: none` (prevents `w-100 !important` conflict)
   - Layout props as needed (padding_y, flex, etc.)
4. Nest a `container` Wrapper inside for content width constraint
5. Rebuild: `ddev theme-build && ddev drush cr`

### 3. Page Chrome (Theme-Owned)

The header, navigation, and footer are rendered by `page.html.twig` using `drupal_block()` from Twig Tweak. To modify the page frame, edit the template directly. Canvas PageRegions are minimal activation anchors only — they do NOT manage chrome. See `global/architecture.md` → Rendering Model.

### 4. Canvas Content Templates (Per-Content-Type Layout)

Each content type gets one full-page Canvas template. The template controls how that content type renders when viewed as a full page. See `canvas-content-templates.md` for the one-template-per-content-type rule.

### 5. Front Page

The front page is a Canvas Page at `/homepage`. For unique landing pages, use Canvas Pages. For content-type-driven pages, use nodes with Canvas content templates.

## Key Layout Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| **Container** | `container` (fixed max-width) | Keeps content readable; use CSS breakout for full-bleed backgrounds |
| **Dark backgrounds** | Per-section via `custom_class`, not global `$body-bg` | Keeps global theme neutral; sections control their own styling |
| **page.html.twig** | Custom override with `drupal_block()` for chrome | Theme owns header/menu/footer; Canvas owns content only |
| **Full-bleed backgrounds** | `.full-bleed` CSS class + `width_class: none` + Wrapper `custom_class` | Works inside `.container` constraint without template changes |

## Region Usage

Run `canvas-page-region-status.drush.php` to see current region state. Key regions for theming:

| Category | Regions | Notes |
|----------|---------|-------|
| **Header** | `top_header`, `header`, `primary_menu`, `secondary_menu` | Site branding, navigation, admin tabs |
| **Content** | `content` | Special: Canvas never manages this. Route-determined content renders here. |
| **Pre-footer** | `featured_bottom_first/second/third` | Available for pre-footer content sections |
| **Footer** | `footer_first` through `footer_fifth` | Footer columns |
| **Above content** | `featured_top` | Available for site-wide banners (renders outside `.container` constraint) |
| **Sidebars** | `sidebar_first`, `sidebar_second` | Available but typically empty for full-width designs |

## Change Surface

- **Theme settings**: `/admin/appearance/settings/alchemize_forge` → Container type, navbar config
- **SCSS tokens**: `web/themes/custom/alchemize_forge/scss/_tokens.scss` → Raw color palette
- **SCSS variables**: `web/themes/custom/alchemize_forge/scss/_variables.scss` → Bootstrap variable overrides
- **CSS custom properties**: `web/themes/custom/alchemize_forge/scss/_semantic.scss` → Theme tokens exposed to CSS
- **Custom styles**: `web/themes/custom/alchemize_forge/scss/style.scss` → Visual classes for sections
- **Page template**: `web/themes/custom/alchemize_forge/templates/layout/page.html.twig` → Container wrapping, chrome
- **Canvas Page Regions**: `/canvas/page-regions` → Header, footer, sidebar content
- **Canvas Content Templates**: `/canvas` → Per-content-type layouts
- **Site information**: `/admin/config/system/site-information` → Front page setting

## Failure Modes

- **Full-bleed silently broken by `w-100`:** If a full-bleed Wrapper omits `width_class: none`, Bootstrap's `w-100` outputs `width: 100% !important` which defeats the breakout's `width: 100vw`. The section renders within the container instead of edge-to-edge with no visual error — just slightly wrong. Always verify full-bleed sections visually.
- **Custom SCSS overridden by Bootstrap utilities:** Layout properties (padding, margin, display, flex, width) set in SCSS on elements that also have Bootstrap utility classes will be silently ignored. Bootstrap utilities use `!important`. Move layout to component props.
- **Missing container on inner Wrapper:** A full-bleed section without an inner `container` Wrapper will have content that spans the full viewport — usually unreadable. Always nest a constrained Wrapper.
