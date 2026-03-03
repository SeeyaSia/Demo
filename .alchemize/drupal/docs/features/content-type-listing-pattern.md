# Feature: Content Type + Listing + Canvas Page + Detail Page

## Purpose

End-to-end implementation guide for the most common Drupal + Canvas feature: a new content type with fields, a View listing exposed as a Canvas block, a Canvas Page that shows the listing, and entity detail pages that users navigate to when clicking items. This is **Pattern 1** from `architecture.md` (Canvas → Views Block → View Mode → Entity Route).

Read this after `architecture.md` and `developer-tools.md`. This doc assumes you understand the invariant rule, know which tool to use when, and want a concrete implementation walkthrough.

## Feature Overview

**What entity "owns" this feature?** A custom content type (node bundle). The content type owns the data, fields, URLs, and editorial lifecycle. Views owns the listing query. Canvas owns the page layout.

**Is it reusable?** Yes — this pattern applies to any "listing + detail" feature: articles, projects, products, team members, events, case studies, etc.

**Is it editorial?** Yes — content editors create and manage items through the standard Drupal node editing interface. No custom code required.

## Implementation Steps

### Overview

| Step | What | Tool | Produces |
|------|------|------|----------|
| 1 | Create content type + fields | Capability script | `node.type.*.yml`, `field.storage.*.yml`, `field.field.*.yml`, display YMLs |
| 2 | Configure view modes | Capability script (same as step 1) | `core.entity_view_display.*.yml` |
| 3 | Create View with Block display | Views UI + `ddev drush cex -y` | `views.view.*.yml` |
| 4 | Rebuild cache | `ddev drush cr` | Canvas discovers new Views block component |
| 5 | Build Canvas Page | Capability script | `canvas_page` entity in DB |
| 6 | (Optional) Create ContentTemplate | Canvas UI or capability script | `canvas.content_template.*.yml` |
| 7 | Export + commit | `ddev drush cex -y` + git | All config captured in version control |

### Step 1: Create the content type and fields (capability script)

Write a capability script following the conventions in `architecture.md` Rule 5.

**Location:** `.alchemize/drupal/capabilities/create-<type>-type.drush.php` + `.drush.json`

**PHP API patterns** (adapt to your specific content type):

```php
<?php
// Example: creating a "project" content type with title, body, media image, and category fields.
// Run: ddev drush php:script .alchemize/drupal/capabilities/create-project-type.drush.php

use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;

// --- Content type ---
// Idempotent: check before create.
$type_id = 'project';

if (NodeType::load($type_id)) {
  echo "Content type '$type_id' already exists.\n";
}
else {
  $type = NodeType::create([
    'type' => $type_id,
    'name' => 'Project',
    'description' => 'A portfolio project or case study.',
    'help' => '',
    'new_revision' => TRUE,
    'display_submitted' => TRUE,
    'preview_mode' => 1,  // DRUPAL_OPTIONAL
  ]);
  $type->save();
  echo "Created content type '$type_id'.\n";
}

// --- Field storage ---
// Field storage is shared across bundles. Check before creating.
$fields = [
  'field_project_image' => [
    'type' => 'entity_reference',
    'cardinality' => 1,
    'settings' => ['target_type' => 'media'],
  ],
  'field_project_category' => [
    'type' => 'entity_reference',
    'cardinality' => 1,
    'settings' => ['target_type' => 'taxonomy_term'],
  ],
  'field_project_url' => [
    'type' => 'link',
    'cardinality' => 1,
  ],
];

foreach ($fields as $field_name => $def) {
  if (!FieldStorageConfig::loadByName('node', $field_name)) {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => $def['type'],
      'cardinality' => $def['cardinality'],
      'settings' => $def['settings'] ?? [],
    ])->save();
    echo "Created field storage: $field_name\n";
  }
}

// --- Field instances on the content type ---
$instances = [
  'body' => [
    'field_name' => 'body',
    'label' => 'Body',
    'required' => TRUE,  // Required for Canvas Paragraph prop linking
    'settings' => ['display_summary' => TRUE],
  ],
  'field_project_image' => [
    'field_name' => 'field_project_image',
    'label' => 'Image',
    'required' => FALSE,
    'settings' => [
      'handler' => 'default:media',
      'handler_settings' => [
        'target_bundles' => ['image' => 'image'],
      ],
    ],
  ],
  'field_project_category' => [
    'field_name' => 'field_project_category',
    'label' => 'Category',
    'required' => FALSE,
    'settings' => [
      'handler' => 'default:taxonomy_term',
      'handler_settings' => [
        'target_bundles' => ['project_categories' => 'project_categories'],
        'auto_create' => TRUE,
      ],
    ],
  ],
  'field_project_url' => [
    'field_name' => 'field_project_url',
    'label' => 'Project URL',
    'required' => FALSE,
  ],
];

foreach ($instances as $key => $def) {
  if (!FieldConfig::loadByName('node', $type_id, $def['field_name'])) {
    // body field uses existing field storage from node module
    if ($def['field_name'] === 'body') {
      node_add_body_field(NodeType::load($type_id), 'Body');
      // Update to set required if needed
      $body = FieldConfig::loadByName('node', $type_id, 'body');
      if ($body && !$body->isRequired()) {
        $body->setRequired(TRUE);
        $body->save();
      }
    }
    else {
      FieldConfig::create([
        'field_name' => $def['field_name'],
        'entity_type' => 'node',
        'bundle' => $type_id,
        'label' => $def['label'],
        'required' => $def['required'],
        'settings' => $def['settings'] ?? [],
      ])->save();
    }
    echo "Created field instance: {$def['field_name']} on $type_id\n";
  }
}

// --- Form display ---
$form_display = EntityFormDisplay::load("node.$type_id.default");
if (!$form_display) {
  $form_display = EntityFormDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => $type_id,
    'mode' => 'default',
    'status' => TRUE,
  ]);
}
$form_display->setComponent('title', ['type' => 'string_textfield', 'weight' => 0])
  ->setComponent('field_project_image', ['type' => 'media_library_widget', 'weight' => 1])
  ->setComponent('body', ['type' => 'text_textarea_with_summary', 'weight' => 2])
  ->setComponent('field_project_category', ['type' => 'entity_reference_autocomplete', 'weight' => 3])
  ->setComponent('field_project_url', ['type' => 'link_default', 'weight' => 4])
  ->save();
echo "Configured form display.\n";

// --- View display: default (full page) ---
$view_display = EntityViewDisplay::load("node.$type_id.default");
if (!$view_display) {
  $view_display = EntityViewDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => $type_id,
    'mode' => 'default',
    'status' => TRUE,
  ]);
}
$view_display->setComponent('field_project_image', [
    'type' => 'entity_reference_entity_view',
    'label' => 'hidden',
    'settings' => ['view_mode' => 'default'],
    'weight' => -1,
  ])
  ->setComponent('body', [
    'type' => 'text_default',
    'label' => 'hidden',
    'weight' => 0,
  ])
  ->setComponent('field_project_category', [
    'type' => 'entity_reference_label',
    'label' => 'above',
    'settings' => ['link' => TRUE],
    'weight' => 5,
  ])
  ->setComponent('field_project_url', [
    'type' => 'link',
    'label' => 'inline',
    'weight' => 10,
  ])
  ->save();
echo "Configured default view display.\n";

// --- View display: teaser (for View listings) ---
$teaser = EntityViewDisplay::load("node.$type_id.teaser");
if (!$teaser) {
  $teaser = EntityViewDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => $type_id,
    'mode' => 'teaser',
    'status' => TRUE,
  ]);
}
$teaser->setComponent('field_project_image', [
    'type' => 'entity_reference_entity_view',
    'label' => 'hidden',
    'settings' => ['view_mode' => 'media_library'],
    'weight' => -1,
  ])
  ->setComponent('body', [
    'type' => 'text_summary_or_trimmed',
    'label' => 'hidden',
    'settings' => ['trim_length' => 300],
    'weight' => 0,
  ])
  ->setComponent('field_project_category', [
    'type' => 'entity_reference_label',
    'label' => 'above',
    'settings' => ['link' => TRUE],
    'weight' => 5,
  ])
  ->removeComponent('field_project_url')
  ->save();
echo "Configured teaser view display.\n";

echo "\n=== Done. Run 'ddev drush cex -y' to export config. ===\n";
```

**Key patterns to follow:**
- **Idempotent**: Always check if the entity exists before creating (`NodeType::load()`, `FieldStorageConfig::loadByName()`, `FieldConfig::loadByName()`)
- **Body field**: Use `node_add_body_field()` — it handles the shared field storage correctly
- **Make body required**: If you plan to use a Canvas ContentTemplate with the Paragraph component, the body field must be required (Canvas shape matching requires this — see `canvas/canvas-content-templates.md`)
- **Two view displays minimum**: `default` (full page) and `teaser` (for View listings)
- **Media reference for images**: Always use `entity_reference` → `media:image` with `media_library_widget`, not direct image fields. See `data-model/entity-types.md` and `data-model/content-types.md`

### Step 2: Create the View (Views UI + config export)

Views config is deeply nested and purpose-built for the Views UI. See `data-model/views.md` "Creating New Views" for the full workflow.

**Quick checklist for a Canvas listing View:**

1. Go to `/admin/structure/views/add`
2. View name: e.g., "Projects"
3. Show: Content, of type: your content type
4. **Add a Block display** (not just Page)
5. Filter: Published = Yes
6. Sort: Authored on (newest first)
7. Format → Show: Content → Teaser view mode
8. Save

```bash
# After saving the View:
ddev drush cex -y   # Capture views.view.<id>.yml
ddev drush cr       # Canvas discovers the new block component
```

The Views block component ID will be `block.views_block.<view_id>-<block_display_id>` (e.g., `block.views_block.projects-block_1`).

### Step 3: Build the Canvas Page (capability script)

Use the patterns from `canvas/canvas-build-guide.md` and the reference implementation at `.alchemize/drupal/capabilities/canvas-build-page.drush.php`.

The Canvas Page places the Views block component in a layout:

```
Wrapper (section, container, py-5)
  └─ [slot: content]
      ├─ Heading (h1, "Projects")
      ├─ Paragraph ("<p>Browse our portfolio...</p>")
      └─ Views Block (block.views_block.projects-block_1)
```

**Important:** The Views block is placed as a block component in the Canvas tree, using `component_id: 'block.views_block.projects-block_1'`. See `canvas-build-guide.md` Phase 6 for the PHP tree construction pattern.

**Note on block components in the tree:** Block components use the same tree item structure as SDC components, but the `component_id` is `block.<plugin_id>` instead of `sdc.<theme>.<name>`. The `inputs` field is typically an empty JSON object `'{}'` since blocks get their configuration from the block plugin, not from Canvas props.

### Step 4: Detail page rendering (optional ContentTemplate)

When a user clicks an item in the listing, they navigate to the entity route (`/node/{nid}` or a Pathauto alias). The detail page renders using one of two approaches:

**Option A: Manage Display (default — no Canvas template)**
The node renders using the `default` view display configured in Step 1. This uses standard Drupal field formatters. Good enough for most cases.

**Option B: Canvas ContentTemplate (visual Canvas rendering)**
Create a ContentTemplate to control the detail page layout with Canvas components. See `canvas/canvas-content-templates.md` for the full workflow:

1. Open Canvas editor at `/canvas`
2. Expand Templates → Content types
3. Add template for your content type's `Full content` view mode
4. Map entity fields to component props (Title → Heading, Body → Paragraph, Image → Image)
5. Publish the template

**Reminder:** If using the Paragraph component, the body field must be `required: true` for Canvas shape matching to offer it as a linkable field.

### Step 5: Pathauto (optional SEO-friendly URLs)

By default, content is accessible at `/node/{nid}`. For SEO-friendly URLs:

1. Create a Pathauto pattern at `/admin/config/search/path/patterns`
2. Pattern type: Content
3. Bundle: your content type
4. Pattern: e.g., `projects/[node:title]`
5. Export config: `ddev drush cex -y`

See `data-model/content-types.md` "Pathauto Configuration" for programmatic pattern creation.

### Step 6: Export and commit

```bash
ddev drush cex -y
git add config/<site>/
git commit -m "Add <type> content type with View listing and Canvas page"
```

## Composition Map

### Entity & data model

- **Entity type**: `node` (content entity)
- **Bundle**: Custom (e.g., `project`, `product`, `event`)
- **Revisioned**: Yes (`new_revision: TRUE`)
- **Routes**: `/node/{nid}` or Pathauto alias (e.g., `/projects/my-project`)

### Fields

Varies by content type. Minimum recommended:

| Field | Type | Required | Purpose |
|-------|------|----------|---------|
| `title` | (base field) | Yes | Node title, links to entity route |
| `body` | `text_with_summary` | Yes (for Canvas) | Main content; required enables Canvas Paragraph linking |
| `field_<type>_image` | `entity_reference` → `media:image` | No | Visual representation in listings and detail page (always use media reference, not direct image) |

### View modes

| Mode | Used for | Configured in |
|------|----------|---------------|
| `default` | Full entity page | `core.entity_view_display.node.<type>.default.yml` |
| `teaser` | View listing rows | `core.entity_view_display.node.<type>.teaser.yml` |

### Views

| View | Display | Canvas component ID |
|------|---------|-------------------|
| `<type>s` (e.g., `projects`) | Block (`block_1`) | `block.views_block.<type>s-block_1` |

### Canvas components

**Canvas Page** (listing page):
- Wrapper → Heading + Paragraph + Views Block component

**ContentTemplate** (detail page, optional):
- Maps entity fields → SDC component props (Heading, Paragraph, Image)

### Configuration (CMI)

Files produced by this pattern:

```
# Content type
config/<site>/node.type.<type>.yml

# Field storage (one per field)
config/<site>/field.storage.node.field_<type>_*.yml

# Field instances (one per field per bundle)
config/<site>/field.field.node.<type>.*.yml

# Form display
config/<site>/core.entity_form_display.node.<type>.default.yml

# View displays
config/<site>/core.entity_view_display.node.<type>.default.yml
config/<site>/core.entity_view_display.node.<type>.teaser.yml

# View
config/<site>/views.view.<type>s.yml

# Canvas Page — stored in DB, not config (content entity)
# ContentTemplate (if created)
config/<site>/canvas.content_template.node.<type>.full.yml
```

### Relationships

- **Depends on**: Canvas module (page building), Views module (listing), node module (content type)
- **Optionally depends on**: Pathauto (URL aliases), Content Moderation (editorial workflow)
- **Canvas component availability**: Views block appears in Canvas after `ddev drush cr`
- **Breaks if**: View references a content type that doesn't exist; Canvas page references a Views block that hasn't been cache-rebuilt; ContentTemplate links to a non-required field for a required prop

## Related Documentation

| Document | What it covers for this feature |
|----------|-------------------------------|
| `global/architecture.md` | Invariant rule, Pattern 1, Rule 5 (capability scripts), Rule 6 (config export) |
| `infrastructure/developer-tools.md` | Which tool when: scripts vs `drush generate` vs UI |
| `data-model/content-types.md` | Content type reference, field specs, view modes, when-to-create rules |
| `data-model/views.md` | Creating Views, Views + Canvas integration, block display workflow |
| `canvas/canvas-build-guide.md` | Component tree construction, PHP assembly, common mistakes |
| `canvas/canvas-build-example.md` | Working reference: `canvas-build-page.drush.php` |
| `canvas/canvas-content-templates.md` | ContentTemplate for detail page, field linking, shape matching |
| `global/drush-config-workflow.md` | Config import/export workflow |
