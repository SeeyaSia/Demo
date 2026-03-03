# Canvas Build Guide — Design-to-Component Workflow

## Purpose

Teaches agents how to decompose a visual design into a Canvas component tree. This is the reusable methodology — the structural playbook that applies to any design, not a specific example. For a concrete worked example, see `canvas-build-example.md`.

**Critical prerequisite:** Read `canonical-docs/global/component-strategy.md` first. It establishes the **design system architecture** (8-layer token-to-composition pipeline), the **SDC-first methodology**, the **preset system** (`.role-*` and `.preset-*` SCSS classes), and the gold-standard SDC pattern.

## Prerequisites

Before building, the agent must understand:
- **The component strategy & design system** — 8-layer architecture, type scale, role/preset classes, SDC-first methodology (`canonical-docs/global/component-strategy.md`)
- The available SDC components and their props/slots (`canvas-sdc-components.md`)
- When to use code components instead (`canvas-code-components.md`)
- The Canvas component tree data model (`canvas-system-overview.md`)
- The CLI workflow for code components (`canvas-cli.md`)

## Phase 0: SDC Component Identification (Do This First)

> **This phase is mandatory.** Before mapping any design element to Canvas primitives, identify which parts of the design should become purpose-built SDC components. See `component-strategy.md` for the full rationale and the gold-standard pattern.

### Step 0a — Identify SDC Candidates

Scan the entire design and identify **clearly independent, reusable groups** of elements. An SDC candidate:
- Has a **distinct visual boundary** (can be drawn as a rectangle)
- Contains **multiple child elements** that work together as a unit
- Is likely to be **reused** on other pages or sections
- Has **its own styling** beyond Bootstrap utility classes (custom CSS, gradients, animations)
- Would benefit from a **controlled prop interface** for editorial consistency

**Ask for each group:** "If I saw this exact pattern on another page, would I want it to look and behave identically?" If yes → it should be an SDC.

### Step 0b — Classify Each Section

| Classification | Action |
|----------------|--------|
| **Reusable pattern** (appears on 2+ pages, has custom CSS/JS) | → **Create a Tier 1 SDC** in `web/themes/custom/alchemize_forge/components/` |
| **One-off content** (simple text, spacing, appears once) | → **Compose with Tier 2 primitives + presets** (proceed to Phase 1) |
| **Variation of existing SDC** (same structure, different content) | → **Reuse the existing SDC** with different prop values |
| **Interactive/dynamic element** | → **Canvas Code Component** (JSX) |

### Step 0c — Design SDC Interfaces

For each SDC candidate, define before building:
1. **Props** — What editors can change (text, images, enum variants)
2. **Slots** — Where variable child content goes (if the SDC accepts nested components)
3. **Fixed elements** — What is always the same (structural markup, animations, behavior)

Then **build the SDCs** following the gold-standard pattern documented in `component-strategy.md` → "SDC Gold Standard Pattern". For the full worked example — design analysis through every file — see **`canvas-sdc-example.md`** (Hero Carousel case study).

**Output:** A list of SDC components to create, and a list of remaining sections to compose with primitives.

---

## Phase 1: Design Analysis (For Primitive-Composed Sections)

> **This phase applies to sections classified as "one-off content" in Phase 0.** Sections that became SDC components are already handled.

### Step 1 — Identify Sections

Scan the remaining (non-SDC) parts of the design top-to-bottom. Every visually distinct horizontal band is a **section**. Each section becomes a **Wrapper** component (`sdc.alchemize_forge.wrapper`).

Look for:
- Changes in background color or texture
- Clear visual separation (whitespace, dividers, borders)
- Semantic shifts (navigation → hero → features → testimonials → footer)

**Output:** A numbered list of remaining sections with short names (e.g., "Intro Text", "CTA Banner").

### Step 2 — Choose Presets Before Manual Props

For each Wrapper section, **check if a preset covers the use case** before setting individual props:

| Visual intent | Preset to use | SCSS class applied |
|---|---|---|
| Dark hero/banner section | `preset: hero-dark` | `.preset-section-hero-dark` |
| Brand-colored CTA section | `preset: hero-primary` or `preset: cta-banner` | `.preset-section-hero-primary` / `.preset-section-cta` |
| Alternating gray section | `preset: light-section` | `.preset-section-light` |
| Standard content section | `preset: content-section` | `.preset-section-content` |
| Clean separator section | `preset: feature-strip` | `.preset-section-feature-strip` |

Presets auto-set `html_tag: section` and `container_type: container`. Each preset maps to a single semantic class defined in `_layout-presets.scss` using brand tokens. See `component-strategy.md` → "Layout Preset Classes" for the full reference.

Only set individual props when **no preset matches** or you need to **override a specific preset value**.

### Step 3 — Identify Layout Patterns Within Each Section

For each section, determine the internal layout:

| Visual pattern | Layout approach |
|---|---|
| Single column of stacked content | **Wrapper** with children placed directly in `content` slot |
| Multi-column equal-width grid | **Row** (`row_cols_*` props) → N × **Column** |
| Multi-column unequal-width layout | **Row** → **Column** components with specific `col_*` widths |
| Card-based grid | **Row** → **Column** → **Card** (use card presets) |
| Centered text stack | **Wrapper** with `custom_class: "text-center"` |

### Step 4 — Identify Atomic Content Pieces (Use Presets)

Within each layout, identify the leaf-level content. **Use presets for Heading, Paragraph, and Card.** Each preset maps to a single semantic class defined in SCSS (`_typography-roles.scss` for text, `_layout-presets.scss` for cards):

| Content type | SDC Component | Preset to consider | SCSS class applied | Required props |
|---|---|---|---|---|
| Hero headline | **Heading** | `preset: hero` | `.role-heading-hero` | `text`, `level: h1` |
| Section title | **Heading** | `preset: section-title` | `.role-heading-section` | `text`, `level: h2` |
| Card/item title | **Heading** | `preset: card-title` | `.role-heading-card` | `text`, `level: h3` |
| Label/category | **Heading** | `preset: uppercase-label` | `.role-label` | `text`, `level: h6` |
| Intro paragraph | **Paragraph** | `preset: lead` | `.role-text-lead` | `text` (HTML) |
| Caption/meta text | **Paragraph** | `preset: caption` | `.role-text-caption` | `text` (HTML) |
| Body text | **Paragraph** | _(none needed)_ | — | `text` (HTML) |
| Call-to-action button | **Button** | — | — | `text`, `variant` |
| Navigation link | **Link** | — | — | `url`, `text` |
| Photo/illustration | **Image** | — | — | `media` (image ref) |
| Grouped card content | **Card** | `elevated`/`bordered`/`dark`/`flat`/`glass` | `.preset-card-*` | toggle `show_*`, fill slots |

## Phase 2: Component Mapping

### The Decision Flowchart

Follow this sequence for every element in the design. **Start with SDC check:**

```
Is it a reusable multi-element design pattern?
  YES → Create a purpose-built SDC (Phase 0)
  │     Gold standard: hero-carousel pattern
  │     Location: web/themes/custom/alchemize_forge/components/<name>/
  │
  NO (one-off or simple content) ↓

Is it a full-width section or band?
  YES → Wrapper (use a preset if applicable)
  │
  Does it contain a multi-column layout?
    YES → Row inside Wrapper's "content" slot
    │     └─ How many columns?
    │        EQUAL widths → Set row_cols_* props on Row
    │        UNEQUAL widths → Set col_* props on each Column
    │
    NO → Place children directly in Wrapper's "content" slot
  │
  For each content item:
    Is it a card-like grouping (icon + title + text + link)?
      YES → Card with preset (inside Column's "column" slot)
      │     ├─ Card header needed? → show_header: true → fill card_header slot
      │     ├─ Card image needed? → show_image: true → fill card_image slot
      │     ├─ Card body → fill card_body slot with Heading + Paragraph + Link/Button
      │     └─ Card footer needed? → show_footer: true → fill card_footer slot
      │
    Is it a headline?
      YES → Heading with preset (h1 for main, h2 for section, h3 for card)
      │
    Is it body/paragraph text?
      YES → Paragraph with preset if applicable (text prop, HTML)
      │
    Is it a CTA button?
      YES → Button (text, variant, url)
      │
    Is it a text link?
      YES → Link (url, text; use stretched_link for card links)
      │
    Is it an image?
      YES → Image (media, size for aspect ratio)
      │
    None of the above?
      → Code Component needed (see Phase 4)
```

### Slot Reference (Critical)

These are the **exact** slot machine names that must be used in `parent_uuid`/`slot` references:

| Component | Slot name | Accepts |
|---|---|---|
| **Wrapper** | `content` | Any component |
| **Row** | `row` | Column components |
| **Column** | `column` | Any component |
| **Card** | `card_header` | Any component (toggle: `show_header: true`) |
| **Card** | `card_image` | Image component (toggle: `show_image: true`) |
| **Card** | `card_body` | Any component (primary content area) |
| **Card** | `card_footer` | Any component (toggle: `show_footer: true`) |
| **Accordion** | `accordion_items` | Accordion Container components |
| **Accordion Container** | `accordion_body` | Any component |
| **Hero Carousel** | `slides` | Carousel Slide components |

> **Twig template rule for slots:** When creating new SDC components with slots, the `{% block %}` tag must be a **direct child of the component's root HTML element**. Do not wrap it in an intermediate `<div>`. Canvas maps slot drop zones to the parent element of the `{% block %}`, and nesting it inside a wrapper causes drag-and-drop to misfire — components land as siblings instead of inside the slot. See `canvas-sdc-components.md` → "Slot Twig template rule" for the full explanation.

### Heading Level Convention

| Context | Level |
|---|---|
| Page main title / hero headline | `h1` (one per page) |
| Section title | `h2` |
| Card title / subsection | `h3` |
| Sub-item within a card | `h4` |

## Phase 3: Component Tree Construction

### Tree Structure Rules

The Canvas component tree is stored as a **flat list** of component instances. Nesting is expressed through `parent_uuid` and `slot` fields:

1. **Root items**: `parent_uuid` and `slot` are both empty/null. They appear at the top level of the region or content area.
2. **Nested items**: Set `parent_uuid` to the UUID of the parent component instance, and `slot` to the machine name of the target slot.
3. **Order**: The `delta` (list position) determines rendering order among siblings at the same tree level.
4. **Every instance gets a UUID**: Generate a unique UUID for each component instance.

### Stored Representation Format

Each component instance in the tree is a PHP array with these exact keys:

```php
[
  'uuid'              => 'a1000000-...', // Unique per instance (generate with Uuid\Php)
  'component_id'      => 'sdc.alchemize_forge.heading', // Full component ID
  'component_version' => '5a794444498af945', // Hash from component entity's active_version
  'parent_uuid'       => 'parent-uuid-or-null', // NULL for root items
  'slot'              => 'content', // NULL for root items
  'inputs'            => '{"text":"Hello","level":"h2"}', // JSON-ENCODED string
  'label'             => NULL, // Optional label, usually NULL
]
```

**Critical rules for the `inputs` field:**
- **For Canvas Pages**: Must be a **JSON-encoded string**, not a PHP array — use `json_encode($inputs)`
- **For Content Templates**: Must be a **PHP array** (NOT JSON-encoded) — `setComponentTree()` handles serialization. See `canvas-content-templates.md` → "Programmatic Template Creation"
- Enum values must be the **exact enum string** (e.g., `"py-5"` not `5`, `"bg-dark"` not `"dark"`)
- HTML-capable fields (Paragraph `text`) must include HTML tags: `"<p>Your text</p>"` not just `"Your text"`
- Boolean values are native JSON booleans: `true`/`false`

### Component ID Format

| Component type | Format | Example |
|---|---|---|
| SDC (theme) | `sdc.<theme>.<name>` | `sdc.alchemize_forge.wrapper` |
| SDC (module) | `sdc.<module>.<name>` | `sdc.bootstrap_barrio.badge` |
| Block | `block.<plugin_id>` | `block.system_branding_block` |
| Code component | `js.<machine_name>` | `js.hero_section` |

### Block Component Requirements ⚠️

Block components (Views blocks, system blocks, custom blocks) have **additional required inputs** that SDC and code components do not:

```php
// REQUIRED for all block components:
$inputs = [
  'label'         => 'My Block Title',    // Block title text
  'label_display' => '0',                 // '0' = hidden, 'visible' = shown
  // ... other block-specific inputs
];
```

**Without `label` and `label_display`, Canvas will throw:**
```
LogicException: inputs: 'label' is a required key. 'label_display' is a required key.
```

**Example: Embedding a Views block in a Canvas page:**
```php
$tree[] = tree_item(
  $uuid,
  'block.views_block.projects-block_1',  // Views block plugin ID
  $versions['block.views_block.projects-block_1'],
  [
    'label'         => 'Projects listing',
    'label_display' => '0',  // Hide the block title
  ],
  $parent_uuid,
  'content'
);
```

**Finding block plugin IDs for Views:** The format is `block.views_block.VIEW_ID-DISPLAY_ID`. For a View named `projects` with a block display `block_1`, the component ID is `block.views_block.projects-block_1`.

**Note:** Block-specific settings like `items_per_page` must be numeric values, not strings. When in doubt, omit optional settings and let the block use its configured defaults.

### Getting Component Version Hashes

The `component_version` is required for every tree item. Get it from the `component` entity's `active_version`:

```php
$storage = \Drupal::entityTypeManager()->getStorage('component');
$entity = $storage->load('sdc.alchemize_forge.heading');
$version = $entity->toArray()['active_version'];
// Returns: "5a794444498af945"
```

**Note:** The entity type machine name is `component` (not `canvas_component`). The config prefix `canvas.component.*` is the config entity naming convention — don't confuse them.

### Visual Tree Notation

When documenting a component tree, use indented notation to show the hierarchy before converting to flat storage:

```
Wrapper (section, container, py-5)
  └─ [slot: content]
      ├─ Heading (h1, "Page Title")
      ├─ Paragraph ("<p>Description text...</p>")
      └─ Row (row-cols-lg-3, g-4)
          └─ [slot: row]
              ├─ Column (col)
              │   └─ [slot: column]
              │       └─ Card (show_header: false)
              │           └─ [slot: card_body]
              │               ├─ Heading (h3, "Card Title")
              │               └─ Paragraph ("<p>Card text...</p>")
              ├─ Column → Card → ...
              └─ Column → Card → ...
```

## Phase 4: When SDCs Are Not Enough — Code Components

Some design elements cannot be expressed with server-side Twig SDCs (either purpose-built Tier 1 SDCs or Tier 2 primitives). Specifically, **client-side interactivity, dynamic data fetching, or complex state management** require code components. When this happens, create a code component using the `@drupal-canvas/cli`.

> **Note:** Before reaching for a code component, consider whether a Twig SDC with JavaScript enhancement (like `hero-carousel.js`) can solve the problem. Code components are the **Tier 3 escape hatch** — use Tier 1 SDCs first, Tier 2 primitives second.

### Triggers for Code Components

| Design element | Why SDCs can't handle it | Code component approach |
|---|---|---|
| **Custom background** (gradient, image, pattern) | Wrapper supports `custom_class` but not inline styles or dynamic backgrounds | Create a section wrapper code component with background props |
| **Icons** (SVG, icon fonts) | No SDC icon component exists | Embed SVG inline in JSX, or use an icon library via `esm.sh` |
| **Animations / transitions** | SDCs are static Twig templates | Use Preact hooks + CSS transitions, or import `motion` from `esm.sh` |
| **Interactive elements** (tabs, modals, carousels) | Limited SDC interactivity | Full Preact component with state management |
| **Dynamic data fetching** | SDCs render server-side, no client fetch | Use `useSWR` + `JsonApiClient` from `drupal-canvas` |
| **Complex layouts** beyond Bootstrap grid | Row/Column only supports 12-column grid | Flexbox or CSS Grid via Tailwind classes in JSX |

### Code Component Workflow

```bash
# 1. Scaffold the component
npx canvas scaffold --name hero_section --dir ./canvas-components

# 2. Define props in canvas-components/hero_section/component.yml
# 3. Write JSX in canvas-components/hero_section/index.jsx
# 4. Add styles in canvas-components/hero_section/index.css

# 5. Validate
npx canvas validate --components hero_section --yes

# 6. Build
npx canvas build --components hero_section --yes

# 7. Upload to Drupal
npx canvas upload --components hero_section --yes

# 8. Export config
ddev drush cex -y
```

See `canvas-code-components.md` for full details on JSX patterns, available packages, and ESLint rules.

## Phase 5: Assembly Strategy

### Where to Place the Component Tree

Canvas component trees live in one of three locations:

| Location | Entity type | Field name | Use case |
|---|---|---|---|
| **Canvas Page** | `canvas_page` | `components` | Standalone visual pages at `/page/{id}`. Independent of nodes. |
| **PageRegion** | `page_region` (config) | `component_tree` | Site-wide regions (header, footer, sidebars). Applied across all pages. |
| **ContentTemplate** | `content_template` (config) | `component_tree` | Per-content-type rendering. Replaces Manage Display for nodes. |

For a homepage hero design → **Canvas Page**.
For site-wide header/footer → **PageRegion**.
For a reusable product listing layout → **ContentTemplate** on the product content type.

### Build Order

1. **Outermost first**: Create Wrapper (section) components first
2. **Layout second**: Add Row/Column structure inside Wrappers
3. **Containers third**: Add Card or other grouping components inside Columns
4. **Content last**: Fill leaf slots with Heading, Paragraph, Button, Link, Image

This order ensures each parent exists before its children reference it via `parent_uuid`.

## Phase 6: Programmatic Assembly via Drush

This is how agents create and populate Canvas Pages programmatically. For **Content Templates** (which support dynamic prop sources that reference entity field data), see `canvas-content-templates.md` → "Programmatic Template Creation".

### Step 1 — Create the Canvas Page entity

```php
$storage = \Drupal::entityTypeManager()->getStorage('canvas_page');
$page = $storage->create([
  'title' => 'My Page Title',
  'status' => 1,           // 1 = published
  'path' => '/my-page',    // Clean URL path
]);
$page->save();
// Page is now accessible at /page/{id} and /my-page
```

### Step 2 — Get component version hashes

Every component in the tree needs a `component_version` hash. Batch-load them:

```php
$needed = [
  'sdc.alchemize_forge.wrapper',
  'sdc.alchemize_forge.heading',
  'sdc.alchemize_forge.paragraph',
  'sdc.alchemize_forge.button',
  'sdc.alchemize_forge.row',
  'sdc.alchemize_forge.column',
  'sdc.alchemize_forge.card',
  'sdc.alchemize_forge.link',
];
$comp_storage = \Drupal::entityTypeManager()->getStorage('component');
$versions = [];
foreach ($needed as $id) {
  $entity = $comp_storage->load($id);
  $versions[$id] = $entity->toArray()['active_version'];
}
```

### Step 3 — Build the component tree array

> **Recommended:** Use the `canvas-lib.php` helpers (see `canvas-build-page.drush.php`) instead of raw tree items. The helpers handle UUID generation, component resolution, and JSON encoding. The raw format below is shown for understanding the data model.

**Using canvas-lib helpers (preferred):**

```php
require_once __DIR__ . '/../lib/canvas-lib.php';
[$theme, $components, $versions] = canvas_lib_init(['wrapper', 'heading']);

$tree = [];

// Preset handles bg, text color, centering, flex layout — one key
$tree[] = canvas_wrapper(canvas_uuid('hero'), [
  'preset' => 'hero-dark',
]);

// Heading preset handles font-size + weight from the brand type scale
$tree[] = canvas_heading(canvas_uuid('hero_h'), 'Page Title', 'h1', [
  'preset' => 'hero',       // .role-heading-hero (3.5rem, bold)
], canvas_uuid('hero'), 'content');
```

**Raw tree item format (for understanding the data model):**

```php
use Drupal\Component\Uuid\Php as UuidGenerator;
$uuid_gen = new UuidGenerator();

function tree_item($uuid, $component_id, $version, array $inputs, $parent_uuid = NULL, $slot = NULL) {
  return [
    'uuid'              => $uuid,
    'component_id'      => $component_id,
    'component_version' => $version,
    'parent_uuid'       => $parent_uuid,
    'slot'              => $slot,
    'inputs'            => json_encode($inputs),  // MUST be JSON string
    'label'             => NULL,
  ];
}

$tree = [];

// Root wrapper — uses preset instead of manual utility classes
$wrapper_uuid = $uuid_gen->generate();
$tree[] = tree_item($wrapper_uuid, 'sdc.alchemize_forge.wrapper', $versions['sdc.alchemize_forge.wrapper'], [
  'preset' => 'hero-dark',  // .preset-section-hero-dark handles bg, text color, centering, flex
]);

// Child heading — preset handles typography from brand type scale
$tree[] = tree_item($uuid_gen->generate(), 'sdc.alchemize_forge.heading', $versions['sdc.alchemize_forge.heading'], [
  'text' => 'Page Title',
  'level' => 'h1',
  'preset' => 'hero',       // .role-heading-hero (3.5rem, bold)
  // No text_color needed — inherits white from hero-dark section
  'alignment' => 'text-center',
], $wrapper_uuid, 'content');
```

### Step 4 — Save to the page

```php
$page = \Drupal::entityTypeManager()->getStorage('canvas_page')->load($page_id);
$page->set('components', $tree);
$page->save();
```

### Full working example

See the capability script `canvas-build-page.drush.php` for a complete 27-component page build that creates the ExampleHomepage with hero + product grid.

### Common mistakes

| Mistake | Symptom | Fix |
|---|---|---|
| `inputs` as PHP array instead of JSON string | Components render with no prop values | Use `json_encode($inputs)` |
| Wrong entity type: `canvas_component` | "entity type does not exist" error | Use `component` (no prefix) |
| Missing `component_version` | Component may not render or render stale version | Load from `$entity->toArray()['active_version']` |
| Plain text in Paragraph `text` prop | Text renders but CKEditor may not re-edit cleanly | Wrap in `<p>` tags: `"<p>Your text</p>"` |
| `stretched_link: true` on Link but Card has default `position` | Link doesn't cover the card | Set Card `position: "relative"` |
| Enum value without prefix (e.g., `5` for padding) | Prop silently ignored | Use full Bootstrap class: `"py-5"` |

## Limitations and Gotchas

- **SDC-first, not primitive-first**: When a design pattern is reusable, create a purpose-built SDC in the theme rather than assembling it from primitives every time. See `component-strategy.md` for the full methodology, 8-layer design system architecture, and the hero carousel gold-standard pattern.
- **No icon SDC**: Icons must be embedded as HTML in Paragraph `text`, or built as code components.
- **Bootstrap spacing only**: Padding/margin is limited to Bootstrap's 0–5 scale (`p-0` through `p-5`). Custom spacing requires `custom_class` or code components.
- **12-column max**: Row/Column follows Bootstrap's 12-column grid. For non-grid layouts, use Wrapper with `flex_enabled: true` or a code component.
- **No nested Rows**: While technically possible, nesting Row inside Column inside Row is fragile. Prefer flat layouts or code components for complex grids.
- **`stretched_link` requires `position: relative`**: When using `stretched_link: true` on a Link inside a Card, the Card must have `position: "relative"` for the link to cover the card area.
- **Card slot toggles**: Card slots (`card_header`, `card_image`, `card_footer`) only render if their corresponding `show_*` prop is `true`. The `card_body` slot always renders. Forgetting the toggle means children in that slot are silently hidden.
- **Row `gap` vs `row_cols`**: These are separate prop families. `gap`/`gap_*` controls spacing between columns. `row_cols`/`row_cols_*` controls how many columns per row at each breakpoint.
- **Slot `{% block %}` must be direct child of root element**: When building SDCs with slots, never wrap `{% block slot_name %}` inside an intermediate `<div>`. Canvas maps the slot drop zone to the block's parent HTML element — if that's a nested div instead of the component root, drag-and-drop silently misfires.
- **Never use external URLs in image `examples`**: Using services like `picsum.photos` in prop examples causes random images on every page load. Use local relative paths to static files inside the component directory, or omit `examples` for optional props.
- **Image components need real media**: When building pages with Image components programmatically, always create proper Drupal Media entities first using `media-lib.php`. Never pass empty arrays or NULL for the `media` prop — it causes `AssertionError`. See `data-model/media-handling.md` for the full workflow.

## Related Documentation

| Document | Relevance |
|---|---|
| `canonical-docs/global/component-strategy.md` | **Read first.** 8-layer design system architecture, type scale, role/preset classes, SDC-first methodology, gold-standard pattern |
| `canvas-sdc-components.md` | Complete prop/slot reference for all SDC components (Tier 1 and Tier 2) |
| `canvas-sdc-example.md` | **Tier 1 SDC worked example** — Hero Carousel from design intent through every file to Canvas integration |
| `canvas-build-example.md` | **Tier 2 primitive worked example** — ExampleHomepage design decomposition + working Drush script |
| `canvas-code-components.md` | Code component JSX patterns, ESLint, CLI workflow (Tier 3) |
| `canvas-cli.md` | CLI command reference for scaffold/build/upload |
| `canvas-page-regions.md` | PageRegion entity structure and populated regions |
| `data-model/media-handling.md` | **Media rules, placeholder images, `media-lib.php`** — required reading when building pages with images |
