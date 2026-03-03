# Canvas Build Example — Homepage with Hero + Product Grid

## Purpose

Worked example of decomposing a real design into Canvas components using the methodology from `canvas-build-guide.md`. This is a **tested, working** example — the ExampleHomepage Canvas Page at `/example-homepage` was built with the Drush script documented here. The full script is available as a capability script at `.alchemize/drupal/capabilities/canvas-build-page.drush.php`.

> **Historical note:** This example was created before the SDC-first methodology (`component-strategy.md`) was established. It uses only Tier 2 primitives (Wrapper, Heading, Paragraph, Card, etc.) to build the entire page. Under the current SDC-first approach, the hero section and the product card grid would likely become purpose-built Tier 1 SDC components. The example remains valuable for demonstrating Canvas tree construction, programmatic assembly, preset-based composition, and the design system's inherited text color pattern.

> **Design system note:** The script and this example use the **preset system** (`.role-*` and `.preset-*` SCSS classes) instead of manual Bootstrap utility arrays. Presets provide consistent brand styling through single semantic classes — see `component-strategy.md` → "The Preset System".

## The Design

The design shows a dark-themed homepage with two visual zones:

**Zone A — Hero Text (top)**
- Large headline: "Building Intelligent Systems Across Code, Media, and Infrastructure."
- Subtitle paragraph describing the Alchemize Suite
- Primary CTA button: "Explore Alchemize Suite →"
- Tagline: "Empowering builders with structured AI to create more intelligently."

**Zone B — Product Card Grid (bottom)**
- 4 product cards in a horizontal row, equal width
- Each card: product name (heading), description paragraph, "Learn More →" link
- Products: Alchemize Dev, Alchemize Beats, Alchemize Studio, Alchemize Chat
- Cards have dark backgrounds with subtle borders

**Background**: Dark space-themed cosmic imagery spanning both zones.

## Phase 1: Design Analysis

### Sections Identified

| # | Section | Visual signature |
|---|---|---|
| 1 | **Hero** | Dark background, centered text stack, CTA button |
| 2 | **Product Grid** | 4 equal-width cards on dark background |

> **Decision**: Use **two Wrapper components** — the hero text can be swapped independently of the product grid, even though they share the same dark background.

### Layout Analysis

**Section 1 (Hero):**
- Layout: Centered single-column stack
- No Row/Column needed — just vertical children in Wrapper's `content` slot
- Center alignment via `custom_class: "text-center"`

**Section 2 (Product Grid):**
- Layout: 4-column equal-width grid
- Row with `row_cols_lg: row-cols-lg-4` → 4 columns on large screens
- Responsive: `row_cols_md: row-cols-md-2` → 2 columns on medium, `row_cols: row-cols-1` → 1 column on small

### Atomic Content Pieces

| Element | Component | Key props | Design system |
|---|---|---|---|
| "Building Intelligent Systems..." | **Heading** | `level: h1`, `preset: hero` | `.role-heading-hero` (3.5rem, bold) |
| Suite description | **Paragraph** | `text: "<p>...</p>"`, `preset: lead`, `margin_bottom: mb-4` | `.role-text-lead` (1.25rem) |
| "Explore Alchemize Suite →" | **Button** | `variant: primary`, `size: lg`, `url: /explore` | Standard Bootstrap button |
| "Empowering builders..." tagline | **Paragraph** | `text: "<p>...</p>"`, `preset: caption`, `margin_top: mt-4` | `.role-text-caption` (0.875rem) |
| Product name (e.g., "Alchemize Dev") | **Heading** | `level: h3`, `preset: card-title` | `.role-heading-card` (1.25rem, semibold) |
| Product description | **Paragraph** | `text: "<p>...</p>"` | Inherits white from dark card |
| "Learn More →" | **Link** | `url`, `stretched_link: true`, `link_classes: text-info` | Standard stretched link |
| Product icon | ⚠️ **No SDC available** | See "Gaps" section below | — |

> **Note:** No `text_color` overrides needed — the `hero-dark` wrapper preset sets `color: $neutral-0` which all children inherit. Similarly, the `dark` card preset handles text color for card content.

## Phase 2: Component Tree (Visual Notation)

### Section 1: Hero

```
Wrapper [hero_wrapper]
  preset: hero-dark                    ← .preset-section-hero-dark handles bg, text color,
                                         centering, flex, gap, alignment — one class
  └─ [slot: content]
      ├─ Heading [hero_heading]
      │     text: "Building Intelligent Systems Across Code, Media, and Infrastructure."
      │     level: h1, preset: hero    ← .role-heading-hero (3.5rem, bold)
      ├─ Paragraph [hero_description]
      │     text: "<p>The Alchemize Suite is a powerful set of AI-driven tools...</p>"
      │     preset: lead               ← .role-text-lead (1.25rem)
      │     margin_bottom: mb-4        ← individual prop override (Bootstrap utility wins)
      ├─ Button [hero_button]
      │     text: "Explore Alchemize Suite →"
      │     variant: primary, size: lg, url: /explore
      └─ Paragraph [hero_tagline]
            text: "<p>Empowering builders with structured AI to create more intelligently.</p>"
            preset: caption            ← .role-text-caption (0.875rem, muted)
            margin_top: mt-4           ← individual prop override
```

> **No text_color overrides needed.** The `hero-dark` preset sets `color: $neutral-0` on the wrapper, and all child text inherits white. This is a core design system pattern — dark section presets handle text color inheritance.

### Section 2: Product Grid

```
Wrapper [grid_wrapper]
  preset: hero-dark                    ← same dark section preset
  └─ [slot: content]
      └─ Row [grid_row]
            row_cols: row-cols-1, row_cols_md: row-cols-md-2, row_cols_lg: row-cols-lg-4
            gap: g-4
            └─ [slot: row]
                ├─ Column [col1]  col: col
                │   └─ [slot: column]
                │       └─ Card [card1]
                │             preset: dark                ← .preset-card-dark handles bg, text, shadow, radius
                │             position: relative          ← required for stretched_link!
                │             └─ [slot: card_body]
                │                 ├─ Heading [card1_heading]
                │                 │     "Alchemize Dev", h3, preset: card-title  ← .role-heading-card
                │                 ├─ Paragraph [card1_desc]
                │                 │     "<p>CLI-Integrated AI Development Platform.</p>"
                │                 │     (no preset needed — inherits from dark card)
                │                 └─ Link [card1_link]
                │                       "Learn More →", stretched_link: true, link_classes: text-info
                │
                ├─ Column [col2] → Card [card2] → "Alchemize Beats" (same pattern)
                ├─ Column [col3] → Card [card3] → "Alchemize Studio" (same pattern)
                └─ Column [col4] → Card [card4] → "Alchemize Chat" (same pattern)
```

## Phase 3: Implementation

The full working Drush script is at `.alchemize/drupal/capabilities/canvas-build-page.drush.php`. Run it with:

```bash
ddev drush php:script .alchemize/drupal/capabilities/canvas-build-page.drush.php
```

Key implementation details from the working script:

### Component version lookup

```php
$comp_storage = \Drupal::entityTypeManager()->getStorage('component');
$entity = $comp_storage->load('sdc.alchemize_forge.wrapper');
$version = $entity->toArray()['active_version'];
// e.g., "a2ada112d1451cde"
```

### Tree item construction (using canvas-lib helpers)

```php
// canvas-lib helpers handle UUID generation, component resolution, and JSON encoding.
// Presets replace manual Bootstrap utility arrays — one semantic key per component.

// Hero section — preset handles bg, text color, centering, flex layout
$tree[] = canvas_wrapper(canvas_uuid('hero_wrapper'), [
  'preset' => 'hero-dark',
]);

// Heading with typography role — preset handles font-size + weight
$tree[] = canvas_heading(canvas_uuid('hero_heading'), 'Page Title', 'h1', [
  'preset' => 'hero',        // .role-heading-hero (3.5rem, bold)
], canvas_uuid('hero_wrapper'), 'content');

// Individual prop overrides still work — Bootstrap utility !important wins
$tree[] = canvas_paragraph(canvas_uuid('hero_desc'), '<p>Description</p>', [
  'preset' => 'lead',        // .role-text-lead (1.25rem)
  'margin_bottom' => 'mb-4', // Individual override — works alongside preset
], canvas_uuid('hero_wrapper'), 'content');
```

> **Note:** The Heading alignment prop is `alignment` (text-start/center/end). When using presets, this is rarely needed — `section-title` preset includes `text-align: center` via `.role-heading-section`.

### Card + stretched_link pattern (with preset)

For cards with clickable "Learn More" links that cover the whole card:

```php
// Card preset handles bg, text color, shadow, border-radius — one key
$tree[] = canvas_card(canvas_uuid('card1'), [
  'preset' => 'dark',       // .preset-card-dark — bg-brand-900, white text, shadow, rounded
  'position' => 'relative', // ← still required for stretched_link!
], canvas_uuid('card1_col'), 'column');

// No text_color needed on children — dark card preset sets color: $neutral-0
$tree[] = canvas_heading(canvas_uuid('card1_heading'), 'Alchemize Dev', 'h3', [
  'preset' => 'card-title', // .role-heading-card (1.25rem, semibold)
], canvas_uuid('card1'), 'card_body');

// Link with stretched_link makes the entire card clickable
$tree[] = canvas_link(canvas_uuid('card1_link'), 'Learn More →', '/products/dev', [
  'stretched_link' => TRUE,
  'link_classes' => 'text-info',
], canvas_uuid('card1'), 'card_body');
```

## Component Count Summary

| Component | Instances | Notes |
|---|---|---|
| Wrapper | 2 | Hero section + Product grid section |
| Heading | 5 | 1 × h1 (hero) + 4 × h3 (card titles) |
| Paragraph | 6 | 2 × hero text + 4 × card descriptions |
| Button | 1 | Hero CTA |
| Row | 1 | Product grid |
| Column | 4 | One per product card |
| Card | 4 | One per product |
| Link | 4 | "Learn More" per card |
| **Total** | **27** | All SDC components, no code components needed for structure |

## Gaps Identified

### Gap 1: Dark Cosmic Background

**Problem**: The design has a space-themed cosmic background. The Wrapper SDC supports `custom_class` but not `background-image` or custom gradients.

**Solution used**: `preset: "hero-dark"` — uses the design system's `.preset-section-hero-dark` class, which sets `background-color: $brand-900` (dark brand color) plus white text, centering, and flex layout. For the cosmic imagery, add a custom CSS class (e.g., `bg-cosmic`) to the theme's stylesheet, then use `custom_class: "bg-cosmic"` alongside the preset. Alternatively, create a code component hero wrapper with background props.

### Gap 2: Product Icons

**Problem**: Each product card has a colored icon. No SDC icon component exists.

**Options (in order of complexity)**:
1. **Image in `card_image` slot**: Upload icons as images, set `show_image: true` on Card. Simplest.
2. **HTML in Paragraph**: Embed SVG markup in a Paragraph's `text` prop (supports `contentMediaType: text/html`).
3. **Code component**: Create an `icon_card` code component with an icon identifier prop. Most reusable.

### Gap 3: Not a gap — Unicode arrows work

The "Learn More →" text uses `→` (Unicode arrow) directly in the Link `text` prop. Works natively.

## Lessons Learned

These lessons came from building this example and should inform future page builds:

1. **Use presets first, individual props second** — Check if a preset covers your use case before setting individual props. Presets ensure consistent brand styling and reduce prop sprawl. See `canvas-build-guide.md` → "Phase 1" for the full preset reference.
2. **Dark presets handle text color inheritance** — `hero-dark`, `cta-banner`, and card `dark` presets set `color: $neutral-0`. All child text inherits white — no `text_color` overrides needed.
3. **Individual prop overrides win** — Presets use normal CSS specificity; Bootstrap utilities use `!important`. Setting `margin_bottom: 'mb-4'` alongside `preset: 'lead'` works correctly.
4. **`inputs` must be a JSON string** — `json_encode($inputs)`, not a raw array. (Handled by canvas-lib helpers.)
5. **Entity type is `component`**, not `canvas_component` — the `canvas.component.*` config prefix is misleading.
6. **Paragraph text needs HTML tags** — `"<p>Your text</p>"` not `"Your text"`. The field has `contentMediaType: text/html`.
7. **Enum values are full Bootstrap classes** — `"py-5"` not `"5"`, `"bg-dark"` not `"dark"`.
8. **Heading alignment prop is `alignment`** — `text-start`, `text-center`, `text-end`. (When using presets, rarely needed — `section-title` includes centered alignment.)
9. **Card `position: relative`** is required when using `stretched_link: true` on a child Link.
10. **Card slot toggles** — `card_header`, `card_image`, `card_footer` slots only render if `show_header`/`show_image`/`show_footer` is `true`. Forgetting the toggle silently hides children.
11. **Build order matters** — parents must exist before children reference them via `parent_uuid`.

## Related Documentation

| Document | Relevance |
|---|---|
| `canvas-build-guide.md` | The reusable methodology this example follows |
| `canvas-sdc-components.md` | Complete prop/slot reference used for mapping |
| `canvas-build-page.drush.php` | The full working Drush script for this example |
