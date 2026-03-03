# Block Layout — Block Placement and Canvas PageRegion Integration

## Purpose

Developer guide for understanding Drupal's block placement systems: traditional Block Layout (theme regions) and Canvas PageRegions. Covers how blocks are placed, configured, and how the two systems interact.

---

## Two Block Placement Systems

Drupal sites with Canvas have **two** systems for placing blocks:

### Block Layout (traditional Drupal)

- Blocks placed in **theme regions** (header, content, footer, sidebar, etc.)
- Configured at `/admin/structure/block`
- Stored in `config/<site>/block.block.*.yml`
- Applies to **all pages** using that theme (unless visibility rules restrict them)
- Each theme has its own set of block placements

### Canvas PageRegions

- Blocks composed in **Canvas PageRegion entities** using the Canvas editor
- Stored in `config/<site>/canvas.page_region.*.yml`
- Applies only to **Canvas pages** that reference that PageRegion
- Uses the Canvas component tree (not Drupal block regions)

### Which system takes priority?

| Page type | Block source |
|-----------|-------------|
| **Canvas pages** | Canvas PageRegions take priority for regions they define |
| **Non-Canvas pages** (nodes, Views, admin) | Block Layout from the active theme |
| **Admin pages** | Block Layout from the admin theme |

See `canvas/canvas-page-regions.md` for details on Canvas PageRegion configuration.

---

## Block Layout Basics

### Block types

| Block type | Source | Examples |
|-----------|--------|---------|
| **System blocks** | Drupal core | Main content, page title, messages, breadcrumbs, help |
| **Menu blocks** | Menu module | Main navigation, account menu, footer menu |
| **Custom blocks** | Block content entities | Reusable content blocks (see `data-model/entity-types.md`) |
| **Views blocks** | Views module | Any View with a Block display |
| **Plugin blocks** | Modules | Search form, site branding, local tasks |

### Theme regions

Each theme defines its own set of regions. Standard regions include:

| Region | Purpose | Common blocks |
|--------|---------|---------------|
| `header` | Site header | Site branding (logo + name) |
| `primary_menu` | Main navigation | Main menu block |
| `content` | Page content | **System main block** (required) |
| `sidebar_first` / `sidebar_second` | Sidebars | Custom blocks, menu blocks |
| `footer` | Site footer | Powered by, footer menu |
| `highlighted` | Above content | Messages block |
| `breadcrumb` | Breadcrumb trail | Breadcrumbs block |

**Important:** The `system_main_block` (Main page content) must be placed in the content region. Without it, page content won't render.

### Block placement configuration

Each block placement is a config entity with:

```yaml
# block.block.THEME_BLOCKNAME.yml
id: alchemize_forge_main_menu
theme: alchemize_forge
region: primary_menu
plugin: 'system_menu_block:main'
weight: -6
status: true
visibility: {}
settings:
  level: 1
  depth: 2
  expand_all_items: true
```

Key properties:
- `theme` — Which theme this placement belongs to
- `region` — Which theme region the block appears in
- `plugin` — The block plugin ID
- `weight` — Sort order within the region (lower = higher)
- `visibility` — Conditions for when the block shows (empty = always)
- `settings` — Plugin-specific settings

---

## Common Block Plugins

| Plugin ID | Purpose | Settings |
|-----------|---------|----------|
| `system_main_block` | Main page content | None |
| `system_branding_block` | Site logo + name | `use_site_logo`, `use_site_name`, `use_site_slogan` |
| `page_title_block` | Page title | None |
| `system_messages_block` | Status/error messages | None |
| `system_breadcrumb_block` | Breadcrumbs | None |
| `help_block` | Contextual help | None |
| `local_tasks_block` | Tabs (primary/secondary) | `primary`, `secondary` |
| `local_actions_block` | Action buttons | None |
| `system_menu_block:MENU` | Menu navigation | `level`, `depth`, `expand_all_items` |
| `search_form_block` | Search form | `page_id` |
| `block_content:UUID` | Custom block entity | None (content from entity) |

---

## Managing Block Layout

### Via admin UI

1. Navigate to `/admin/structure/block`
2. Select the theme tab (frontend or admin)
3. Click "Place block" in the target region
4. Configure settings and visibility
5. Export: `ddev drush cex -y`

### Block visibility conditions

Blocks can be shown/hidden based on conditions:

| Condition type | Purpose | Example |
|---------------|---------|---------|
| `request_path` | Show on specific paths | Only on `/contact` |
| `entity_bundle:node` | Show for specific content types | Only on article nodes |
| `user_role` | Show for specific roles | Only for authenticated users |
| `language` | Show for specific languages | Only for English |

```yaml
# Example: Show block only on article pages
visibility:
  entity_bundle:node:
    id: entity_bundle:node
    bundles:
      article: article
    negate: false
    context_mapping:
      node: '@node.node_route_context:node'
```

### Via capability script

```php
use Drupal\block\Entity\Block;

if (!Block::load('alchemize_forge_my_custom_block')) {
  Block::create([
    'id' => 'alchemize_forge_my_custom_block',
    'theme' => 'alchemize_forge',
    'region' => 'sidebar_first',
    'plugin' => 'block_content:BLOCK_UUID_HERE',
    'weight' => 0,
    'status' => TRUE,
    'visibility' => [],
    'settings' => [
      'label' => 'My Custom Block',
      'label_display' => '0',  // '0' = hidden
      'provider' => 'block_content',
    ],
  ])->save();
}
```

---

## Standard Block Placements

Every Drupal theme needs certain essential blocks placed. These are typically auto-created during theme installation:

### Essential blocks (must-have)

| Block | Plugin | Region | Purpose |
|-------|--------|--------|---------|
| Main page content | `system_main_block` | content | **Required.** Renders the page body. |
| Page title | `page_title_block` | content (or header) | Shows the page title |
| Messages | `system_messages_block` | highlighted | Status/error messages |
| Breadcrumbs | `system_breadcrumb_block` | breadcrumb | Navigation breadcrumbs |

### Common additional blocks

| Block | Plugin | Region | Purpose |
|-------|--------|--------|---------|
| Site branding | `system_branding_block` | header | Logo + site name |
| Main menu | `system_menu_block:main` | primary_menu | Primary navigation |
| Local tasks | `local_tasks_block` | content | Admin tabs (edit, view, etc.) |
| Local actions | `local_actions_block` | content | Action buttons (Add content, etc.) |
| Account menu | `system_menu_block:account` | secondary_menu | Login/logout links |

---

## Multi-Theme Considerations

Block placements are **per-theme**. Each installed theme has its own set of block placement configs:
- `block.block.FRONTEND_THEME_*.yml` — Frontend blocks
- `block.block.ADMIN_THEME_*.yml` — Admin blocks
- Inactive themes may have block configs that exist but aren't used

**Cleanup tip:** If themes are installed but not used, their block configs still exist in the config directory. They're harmless but add noise. Uninstalling unused themes removes their block configs.

---

## Configuration Files

| File pattern | Contents |
|-------------|----------|
| `config/<site>/block.block.*.yml` | Block placement configurations |
| `config/<site>/block_content.type.*.yml` | Custom block content types (see `data-model/entity-types.md`) |

---

## Gotchas

- **Missing main content block.** If `system_main_block` isn't placed, pages render blank. This is the most common block issue.
- **Block placements are per-theme.** Placing a block in the frontend theme doesn't place it in the admin theme. Each needs its own placement.
- **Canvas overrides Block Layout.** On Canvas pages, Canvas PageRegions control what appears — Block Layout placements for header/footer regions may be ignored.
- **Block visibility is AND logic.** Multiple visibility conditions on a block use AND logic — all conditions must be true for the block to show.
- **Inactive theme blocks.** Block configs for inactive themes exist in config but don't render. They'll activate if the theme is switched.
- **Weight within regions.** Blocks are ordered by weight within each region. Lower weight = appears higher. Default is 0.

---

## Related Documentation

| Document | Relevance |
|---|---|
| `canvas/canvas-page-regions.md` | Canvas PageRegions (override Block Layout for Canvas pages) |
| `data-model/entity-types.md` | Custom block content types |
| `theming/theming.md` | Theme regions and template structure |
| `integrations/site-services.md` | Menu configuration (menu blocks reference menus) |
