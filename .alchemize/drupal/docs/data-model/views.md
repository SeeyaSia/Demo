# Views — Creating Listings and Integrating with Canvas

## Purpose

Developer guide for working with Drupal Views: creating listings, configuring displays, integrating with Canvas as block components, and using view modes for entity rendering. Views are how you query and display lists of content in Drupal.

## How Views Work

Views query entity data (nodes, media, taxonomy terms, users) and render listings. Each View has:

- **Base table**: The entity type being queried (e.g., `node_field_data`, `media_field_data`)
- **Filters**: Conditions that narrow results (content type, published status, taxonomy, date ranges)
- **Sort criteria**: How results are ordered (creation date, title, sticky, weight)
- **Row style**: How each result renders — either as **fields** (individual field formatters) or as **content** (entity view mode)
- **Displays**: Multiple output formats from the same query (Page, Block, Feed, Attachment)

### Display types

| Display type | Purpose | URL | Canvas integration |
|-------------|---------|-----|--------------------|
| **Page** | Full page at a URL path | `/articles`, `/admin/content` | Not directly — use as standalone listing pages |
| **Block** | Embeddable block component | N/A | ✅ Auto-discovered as Canvas block component |
| **Feed** | RSS/Atom feed | `/rss.xml` | Not applicable |
| **Attachment** | Attached to another display | N/A | Not applicable |

### View modes for row rendering

When a View renders entities, each row uses a **view mode** to determine which fields show and how. This is the recommended approach over rendering individual fields:

- **teaser**: Compact summary (image thumbnail, trimmed body, title link)
- **card**: Grid-friendly card layout (for use inside Canvas Row/Column layouts)
- **full**: Complete entity rendering (rarely used in listings)
- Custom view modes for specific listing contexts

**Best practice:** Use entity view modes (`Format → Show → Content → [view mode]`), not the Fields format. View modes keep rendering configuration in Manage Display where it belongs.

---

## Creating a View

### Two approaches

| Approach | When to use | Produces |
|----------|-------------|----------|
| **Views UI + config export** | Most Views (recommended) | Full config YAML with all UI metadata |
| **Programmatic (`View::create()`)** | Reproducible setups in capability scripts | Cleaner YAML (~113 lines vs ~313 from UI) |

### Workflow: Views UI + config export

```bash
# 1. Import latest config to start clean
ddev drush cim -y

# 2. Create the View in the UI at /admin/structure/views/add
#    Configure: base table, filters, sort, row style, displays

# 3. Export config to capture the View
ddev drush cex -y

# 4. Review the generated config file
#    New file: config/<site>/views.view.<view_id>.yml

# 5. Rebuild cache (required for Canvas to discover Block displays)
ddev drush cr

# 6. Commit
git add config/<site>/views.view.<view_id>.yml
git commit -m "Add <view_id> View with block display"
```

### Creating a View for a Canvas listing page

When creating a View that will appear in a Canvas page (Pattern 1 from `architecture.md`), configure these specifics:

1. **Base table**: Content (`node_field_data`)
2. **Filters** (required):
   - Content type = your target type (e.g., `article`, `project`)
   - Published = Yes (`status = 1`)
3. **Sort criteria**: Created date (newest first), or another field
4. **Format → Show**: Content, then select a **view mode** (e.g., `teaser`)
   - Do NOT use "Fields" format — use entity view modes so rendering is controlled by Manage Display
5. **Add a Block display**: Click "Add" → "Block" — this is what makes it available in Canvas
   - The block display ID becomes part of the Canvas component ID: `block.views_block.<view_id>-<block_display_id>`
6. **Pager**: Set items per page (e.g., 10) or "Display all items" depending on your needs

**After creating:** Run `ddev drush cr` so Canvas discovers the new block component, then `ddev drush cex -y` to capture the config.

### Creating a View programmatically

For reproducible setups in capability scripts:

```php
use Drupal\views\Entity\View;

$view = View::create([
  'id' => 'my_listing',
  'label' => 'My Listing',
  'base_table' => 'node_field_data',
  'display' => [
    'default' => [
      'display_plugin' => 'default',
      'display_options' => [
        'filters' => [
          'type' => [
            'id' => 'type',
            'table' => 'node_field_data',
            'field' => 'type',
            'value' => ['my_type' => 'my_type'],
          ],
          'status' => [
            'id' => 'status',
            'table' => 'node_field_data',
            'field' => 'status',
            'value' => '1',
          ],
        ],
        'sorts' => [
          'created' => [
            'id' => 'created',
            'table' => 'node_field_data',
            'field' => 'created',
            'order' => 'DESC',
          ],
        ],
        'row' => [
          'type' => 'entity:node',
          'options' => ['view_mode' => 'teaser'],
        ],
        'pager' => [
          'type' => 'full',
          'options' => ['items_per_page' => 10],
        ],
      ],
    ],
    'block_1' => [
      'display_plugin' => 'block',
      'display_title' => 'Block',
      'display_options' => [
        'block_description' => 'My listing block',
      ],
    ],
  ],
]);
$view->save();
```

**Note:** Programmatic creation produces cleaner YAML (~113 lines) compared to the UI (~313 lines with extra metadata). However, the Views UI is better for complex configurations with exposed filters, contextual filters, or relationship handlers.

---

## Views + Canvas Integration

### How Views blocks become Canvas components

When a View has a **Block display**, Canvas automatically discovers it as a placeable block component. The component appears in the Canvas editor's component library.

**Canvas component ID format:**
```
block.views_block.<view_id>-<block_display_id>
```

Examples:
- View `projects` with block display `block_1` → `block.views_block.projects-block_1`
- View `articles` with block display `listing` → `block.views_block.articles-listing`

After cache rebuild (`ddev drush cr`), the component appears in the Canvas editor and can be placed in Canvas Pages, PageRegions, or ContentTemplates.

### Placing a Views block in Canvas programmatically

When building Canvas Pages or Content Templates with capability scripts, embed a Views block component like this:

```php
$tree[] = [
  'uuid' => $uuid_gen->generate(),
  'component_id' => 'block.views_block.projects-block_1',
  'component_version' => $versions['block.views_block.projects-block_1'],
  'parent_uuid' => $wrapper_uuid,
  'slot' => 'content',
  'inputs' => json_encode([
    'label' => 'Projects',       // Required for all block components
    'label_display' => '0',      // '0' = hidden, 'visible' = shown
  ]),
];
```

**Required inputs for block components:**
- `label` (string) — block title text
- `label_display` — `'0'` to hide, `'visible'` to show

Without these, Canvas throws: `LogicException: inputs: 'label' is a required key. 'label_display' is a required key.`

### View modes for Canvas-embedded listings

When a View is embedded in a Canvas layout, the **view mode** of each row controls how individual entities render. Create custom view modes for compact display within Canvas layouts:

1. Create the view mode at `/admin/structure/display-modes/view/add`
2. Enable it for the content type at `/admin/structure/types/manage/<type>/display`
3. Configure field formatters in the view mode's Manage Display tab
4. Set the View's row style to use that view mode

**Important:** Non-`full` view modes use standard Drupal Manage Display (field formatters), NOT Canvas templates. Only the `full` view mode can have a Canvas Content Template.

---

## Common View Patterns

### Content listing with clickable items (Pattern 1)

The most common pattern: a listing page where each item links to its detail page.

| Setting | Value |
|---------|-------|
| Base table | Content (`node_field_data`) |
| Filter | Content type = target type, Published = Yes |
| Sort | Created date DESC (newest first) |
| Row | Content → teaser view mode |
| Display | Block (for Canvas embedding) |
| Pager | Full pager, 10 items per page |

### Admin content listing

Standard content management view for editors.

| Setting | Value |
|---------|-------|
| Base table | Content (`node_field_data`) |
| Filters (exposed) | Content type, Published status, Title search |
| Fields | Bulk operations, title, type, author, status, updated |
| Display | Page at `/admin/content` |
| Access | `administer content` permission |

### Taxonomy-filtered listing

Content filtered by taxonomy vocabulary (e.g., articles by tag, projects by category).

| Setting | Value |
|---------|-------|
| Base table | Content (`node_field_data`) |
| Relationship | Content → Taxonomy term (via reference field) |
| Contextual filter | Taxonomy term ID (from URL) |
| Filter | Content type, Published = Yes |
| Row | Content → card view mode |
| Display | Block + Page |

### Dashboard block

Small widget for the admin dashboard showing recent items.

| Setting | Value |
|---------|-------|
| Base table | Content / Comments / Users |
| Pager | Fixed, 5 items |
| Row | Fields (title + date) |
| Display | Block only |
| Access | Admin permissions |

---

## View Display Lifecycle

### Default Views from Drupal core and modules

Drupal ships with many default Views that are auto-created when modules are installed:

| Module | Views provided |
|--------|---------------|
| **Node** | `content` (admin), `frontpage` (homepage listing) |
| **Comment** | `comment` (admin), `comments_recent` (dashboard) |
| **User** | `user_admin_people` (admin), `who_s_new`, `who_s_online` |
| **Media** | `media` (admin), `media_library` (widget) |
| **Taxonomy** | `taxonomy_term` (admin listing) |
| **Content Moderation** | `moderated_content` (moderation workflow) |
| **Database Logging** | `watchdog` (log viewer) |
| **Webform** | `webform_submissions` (submission admin) |
| **Canvas** | `canvas_pages` (Canvas page admin) |
| **Redirect** | `redirect` (redirect admin) |

Most of these are admin-only and require no configuration. The `frontpage` View is the notable exception — it powers the default homepage at `/node` by showing promoted content.

### Modifying default Views

Default Views can be customized through the Views UI. Changes are captured via `ddev drush cex -y`. Common modifications:

- Adding exposed filters to the content admin View
- Customizing the frontpage View's sort/filter criteria
- Adding fields to admin listings

---

## Gotchas and Edge Cases

- **Block display required for Canvas**: Only Views with Block displays appear in the Canvas component library. Page displays are standalone and not embeddable.
- **Cache rebuild after new Views**: Canvas discovers Views block components during cache rebuild. New Views won't appear in Canvas until `ddev drush cr`.
- **View mode vs Fields**: Use entity view modes for row rendering in Canvas-embedded listings. The "Fields" display format works but bypasses Manage Display configuration.
- **Media Library View**: The `media_library` View is used internally as a form widget, not as a standalone listing. Don't modify it unless you understand the Media Library module internals.
- **Module-provided Views**: Views from contrib modules (Canvas, Webform, etc.) may be overwritten when the module is updated. Export customizations to config.
- **Exposed filters in Canvas**: Exposed filters on Views blocks may not render well inside Canvas layouts. Test the UX before relying on them.

---

## Related Documentation

| Document | Relevance |
|---|---|
| `architecture.md` | Pattern 1 (Canvas → Views Block → View Mode → Entity Route) |
| `content-types.md` | Content types that Views query, view mode configuration |
| `canvas/canvas-build-guide.md` | Embedding Views blocks in Canvas page trees |
| `canvas/canvas-content-templates.md` | ContentTemplates for detail pages (Pattern 2) |
| `features/content-type-listing-pattern.md` | End-to-end walkthrough: content type + View + Canvas page |
| `infrastructure/developer-tools.md` | Tool reference for Views creation |
| `global/drush-config-workflow.md` | Config export workflow after View changes |
