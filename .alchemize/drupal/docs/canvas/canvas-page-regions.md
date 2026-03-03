# Canvas Page Regions

## Purpose

Developer guide for Canvas PageRegion entities: the separation of concerns between theme-driven page chrome and Canvas-managed content, operational rules, and migration guidance. For the high-level rendering model, see `global/architecture.md` → Rendering Model.

## Architecture: Theme Chrome vs. Canvas Content

This project uses a **hybrid architecture** where the theme and Canvas each own different parts of the page:

| Responsibility | Owner | Mechanism |
|---------------|-------|-----------|
| Page chrome (header, nav, footer) | **Theme** (`page.html.twig`) | `drupal_block()` calls via Twig Tweak |
| Content rendering | **Canvas** | Content Templates + per-content editing |
| Page variant activation | **Canvas** | Requires ≥1 enabled PageRegion |

### Why this separation?

Canvas's default behavior is to manage ALL theme regions via PageRegion config entities. When Canvas first enables for a theme, `createFromBlockLayout()` migrates all block placements into Canvas component trees. This creates problems:

1. **Infrastructure blocks** (site branding, menus, tabs) become Canvas components that break when edited or deleted in the Canvas UI
2. **Block components** like `TitleBlockPluginInterface` throw errors when previewed outside their expected rendering context (PHP Fibers)
3. **No separation of concerns** — editors can accidentally modify or delete site-wide navigation

The solution: the theme renders infrastructure blocks directly via `drupal_block()` in `page.html.twig`, and Canvas only manages content. This gives editors full Canvas control over content while keeping the page frame stable.

### How `CanvasPageVariant` works

When ≥1 PageRegion config entity has `status: true` for the active theme:

1. `PageVariantSelectorSubscriber` activates `CanvasPageVariant` (replaces `BlockPageVariant`)
2. `CanvasPageVariant::build()` renders each **enabled** PageRegion's component tree into its region key
3. The `content` region always receives the route controller output (no PageRegion needed)
4. Disabled/missing PageRegions = that region key is empty in the render array
5. `page.html.twig` receives the render array as `{{ page.* }}` variables

**Critical**: `page.html.twig` can render content from BOTH sources — `{{ page.content }}` from Canvas AND `{{ drupal_block('...') }}` for direct block rendering. They coexist on the same page.

### Content templates are independent

`ContentTemplateAwareViewBuilder` decorates the entity view builder at the entity rendering level. Content templates work regardless of which PageRegions are enabled or disabled. They do NOT require `CanvasPageVariant` to be active — but the page variant must be active for the Canvas editor preview to work correctly.

## Current PageRegion Configuration

Only minimal PageRegions exist — just enough to keep Canvas active:

| PageRegion ID | Region | Status | Components |
|--------------|--------|--------|------------|
| `alchemize_forge.none` | `none` | **Enabled** | Empty (keeps Canvas active) |
| `alchemize_forge.breadcrumb` | `breadcrumb` | Disabled | Empty |
| `alchemize_forge.highlighted` | `highlighted` | Disabled | `block.help_block`, `block.system_messages_block` |

**All other regions** (header, primary_menu, secondary_menu, footer_*, sidebar_*, etc.) have **no PageRegion entities**. They are rendered by the theme's `page.html.twig` via `drupal_block()`.

### Minimum viable Canvas activation

Canvas requires at least one enabled PageRegion to activate. The validation in `PageRegionHooks::formSystemThemeSettingsValidate()` enforces this at the UI level. If all PageRegions are disabled, Canvas falls back to `BlockPageVariant` and **content templates stop working** (the Canvas editor preview won't render correctly).

## Theme's `page.html.twig`

Located at: `web/themes/custom/alchemize_forge/templates/layout/page.html.twig`

This template renders page chrome directly using Twig Tweak's `drupal_block()`:

```twig
{# Header: rendered by theme, not Canvas #}
{{ drupal_block('system_branding_block', wrapper=false) }}
{{ drupal_block('system_menu_block:main', wrapper=false) }}
{{ drupal_block('system_menu_block:account', wrapper=false) }}

{# Admin elements: rendered by theme #}
{{ drupal_block('local_tasks_block', wrapper=false) }}
{{ drupal_block('local_actions_block', wrapper=false) }}

{# Content area: rendered by Canvas via CanvasPageVariant #}
{{ page.content }}

{# Footer: rendered by theme #}
{{ drupal_block('system_powered_by_block', wrapper=false) }}
```

**Twig Tweak dependency**: The `drupal_block()` function requires the `twig_tweak` module. Do not uninstall it.

## Operational Rules

### DO NOT

- **Do not create PageRegions for infrastructure regions** (header, primary_menu, footer_*, etc.). These are owned by the theme.
- **Do not use `createFromBlockLayout()`** when setting up Canvas for a new theme. It migrates block placements into Canvas and creates problematic block components.
- **Do not disable ALL PageRegions** — Canvas needs ≥1 to stay active for content templates.
- **Do not place site branding, menu, or tab blocks** as Canvas components. They have rendering context requirements (PHP Fibers) that break in the Canvas editor preview.

### DO

- **Edit `page.html.twig`** to change page chrome (add/remove/rearrange header, nav, footer elements).
- **Use Canvas content templates** for content type layouts.
- **Use Canvas per-content editing** for individual page customization within exposed slots.
- **Keep at least one PageRegion enabled** (currently `none`) as the Canvas activation anchor.

## Theme Migration: Switching Themes

When switching to a new custom theme:

1. **Create the theme** using `bootstrap_forge`'s `scripts/create_subtheme.sh` or manually
2. **Create a `page.html.twig`** with `drupal_block()` calls for page chrome
3. **Create minimal PageRegions** for the new theme (at least one enabled)
4. **Migrate SDC component references**: All `sdc.{old_theme}.*` component IDs stored in content templates, node canvas fields, and PageRegion trees must be updated to `sdc.{new_theme}.*`
5. **Uninstall the old theme** only AFTER migrating all component references

### Component reference migration

SDC components are namespaced by theme (e.g., `sdc.alchemize_forge.heading`). Switching themes changes the namespace. Stored component trees in these locations must be updated:

- **Node canvas fields** (`field_canvas_body`, `field_component_tree`, etc.) — update `component_id` in each field value row
- **ContentTemplate config entities** (`canvas.content_template.*.yml`) — update `component_id` in `component_tree`
- **PageRegion config entities** (`canvas.page_region.*.yml`) — update `component_id` in `component_tree`

### Canvas field reset (emergency recovery)

If per-content Canvas data becomes corrupted (e.g., referencing deleted/renamed components), clear the canvas field while preserving all other node fields:

```php
// Clear canvas component tree — node fields (title, body, etc.) are unaffected
$node->set('field_canvas_body', [])->save();
```

The node reverts to using the content template's default layout with empty exposed slots.

## Config Entity Structure

```yaml
uuid: <auto-generated>
langcode: en
status: true                              # true = enabled, false = disabled
dependencies:
  config:
    - canvas.component.<component_id>     # Dependencies on used components
  theme:
    - alchemize_forge                     # Theme dependency
id: alchemize_forge.<region_machine_name> # Unique ID
region: <region_machine_name>             # Maps to theme region
theme: alchemize_forge                    # Theme this belongs to
component_tree:                           # Array of component instances (or empty)
  - uuid: <instance-uuid>
    component_id: <component-config-entity-id>
    component_version: <version-hash>
    inputs: { ... }
```

## Constraints and Tradeoffs

- **Site-wide only**: No per-route or per-page region variants. Planned as requirement "41. Conditional display of components."
- **Binary status**: A PageRegion is either enabled (rendered + editable in Canvas) or disabled (not rendered). There is no "render but not editable" built-in state. Our per-content editing patch adds frontend locking for global regions.
- **`content` region excluded**: Always renders route-determined content; cannot have a PageRegion.
- **Theme-scoped**: PageRegions are tied to a specific theme. Switching themes requires new PageRegion entities.
- **Block Layout bypassed**: When Canvas is active, the Block module's `BlockPageVariant` doesn't run. Block placements in `/admin/structure/block` are ignored. Blocks still exist as plugins — they're rendered by `drupal_block()` in the template instead.
