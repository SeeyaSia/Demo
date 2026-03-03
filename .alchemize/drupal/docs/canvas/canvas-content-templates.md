# Canvas Content Templates

## Purpose

Developer guide for Canvas Content Templates: how they control the visual rendering of node content types, replacing Drupal's Manage Display / field formatters. Covers the ContentTemplate config entity, prop source linking, shape matching constraints, and programmatic creation.

## System Overview

A ContentTemplate config entity defines how a **node** content type renders using Canvas components, replacing the traditional Manage Display / field formatter pipeline. It is tied to a specific entity type + bundle + view mode (e.g., `node.article.full`).

When a ContentTemplate exists for a node's type and view mode, Canvas renders the node using the template's component tree instead of the field formatter system. This means `hook_entity_display_build_alter()` is **NOT invoked** when a ContentTemplate is active.

## How It Works

### Rendering decision

`ContentTemplateAwareViewBuilder` is a decorator on Node's view builder that intercepts rendering:

1. Is there a `ContentTemplate` for this entity type + bundle + view mode?
2. Is the template `status: true` (enabled)?
3. Is the entity type `Node`? (Currently limited to nodes only)

If all three are true → Canvas renders using the ContentTemplate's component tree (adds `with-canvas` cache key).
If any is false → Standard Drupal field formatter rendering is used (adds `without-canvas` cache key).

### Prop sources

Content Templates support three types of prop sources:

| Type | Description | Example |
|------|-------------|---------|
| **Static** | Fixed values stored in the template | `heading: "Welcome"` — always shows "Welcome" |
| **Dynamic** | Entity field data via expressions | Maps `node.title` → Heading `text` prop |
| **Host Entity URL** | The URL of the rendered entity | Maps the node's canonical URL to a link `href` |

Dynamic prop sources use **prop expressions** — opaque string expressions that navigate the entity data graph. Format: `ℹ︎␜entity:node:article␝fieldname␞␟property`.

### The content region

The Canvas editor for a content template shows the `content` region as the editable area. Components added here form the template's component tree. If PageRegions are also configured, the global regions (header, footer, nav) appear around the content region but belong to their respective PageRegion entities.

## Config Entity Structure

```yaml
# canvas.content_template.node.my_type.full.yml
uuid: 35a67d17-55bf-416d-8119-96e344eaccc4
langcode: en
status: true               # Must be true to activate rendering
id: node.my_type.full      # Pattern: {entity_type}.{bundle}.{view_mode}
content_entity_type_id: node
content_entity_type_bundle: my_type
content_entity_type_view_mode: full
component_tree: {}         # Empty until components are added and published
exposed_slots: {}
```

When saved with components, the `component_tree` contains the full tree structure with UUIDs, component IDs, version hashes, and input values (both static and dynamic).

## Exposed Slots and Per-Content Editing

Content templates support a hybrid model: **default layout from the content type + per-entity customization inside designated slots**.

### The model

| Layer | What it provides | Who edits |
|-------|------------------|-----------|
| **ContentTemplate** | Component tree structure with **exposed slots** (e.g., `content`, `sidebar`) | Site builders (edit the template) |
| **Entity's Canvas field** | One component tree per exposed slot — fills that slot for **this** entity | Content creators (edit this node) |

So the template defines the shell (hero, body region, footer); each node can have its own components inside the exposed slots. The template tree is merged with the entity's per-slot trees at render time via `ContentTemplate::build()` and `ComponentTreeItemList::injectSubTreeItemList()`.

### Config schema

The `exposed_slots` key in the ContentTemplate config defines which component slots are editable per-entity. Each slot name maps to a slot in the template's component tree. When a slot is exposed:

1. The template provides the parent component and structure.
2. The content entity (e.g., Node) must have a Canvas field to store that slot's tree.
3. At render time, the entity's tree for that slot is injected into the template tree.

### Current scope

- Per-content editing via exposed slots is **implemented and functional** for any fieldable entity type that has:
  1. An enabled ContentTemplate (`status: true`) with at least one exposed slot.
  2. A Canvas field (`component_tree` type) on the entity bundle to store per-entity slot content.
- The `ComponentTreeLoader` accepts both `canvas_page` entities (full per-entity layout) and any fieldable entity with an enabled ContentTemplate with exposed slots. The `hasContentTemplateWithExposedSlots()` helper on `ComponentTreeLoader` checks these conditions.
- The `SlotTreeExtractor` service filters the merged tree (template + slot content) down to slot-only components before persisting to the entity's Canvas field, ensuring template-owned components are never overwritten.
- **Currently limited to one exposed slot** — `getMergedComponentTree()` has `assert(count($this->getExposedSlots()) === 1)`. Multiple exposed slots per template are planned but not yet supported.
- For the full design rationale, consult the Canvas module's `docs/adr/0006-One-field-row-per-component-instance.md` and `docs/config-management.md` in the contrib module.

### Exposing slots in the template editor

Slots are exposed through the Canvas template editor's component right-click context menu. When editing a template (`EditorFrameContext.TEMPLATE`), right-clicking a component with slots shows an **"Expose slot"** option.

**How it works:**

1. Right-click a component in the template editor (e.g., a Wrapper component).
2. If the component has slots, the context menu shows "Expose slot" (single slot) or a submenu listing each slot (multiple slots).
3. Clicking opens a dialog that lists available `component_tree` fields on the content type as a dropdown.
4. Selecting a field and confirming sets that slot as exposed — the field name becomes the exposed slot key, directly linking the slot to the storage field.
5. The exposed slot is persisted to the ContentTemplate config when the template is saved/published.

**Developer notes:**
- The "Expose slot" option is on the **component** context menu, not the slot overlay's context menu. The slot overlay's context menu exists in `SlotOverlay.tsx` but is unreachable because `ComponentOverlay` has `pointer-events: all` covering the slot area.
- Only **empty** slots can be exposed — the slot must contain no components in the template tree.
- Only **one** exposed slot is currently supported. The UI disables exposing additional slots when one already exists.
- The exposed slot key should match the canvas field name on the content type (e.g., `field_component_tree`, `field_canvas_body`). This makes the slot-to-field connection explicit and traceable.

### Auto-provisioning of the canvas field

When a ContentTemplate is saved with non-empty `exposed_slots`, `ContentTemplate::postSave()` auto-provisions a `component_tree` field on the target bundle:

1. Calls `ensureCanvasFieldExists()` which checks `EntityFieldManagerInterface::getFieldMapByFieldType('component_tree')`.
2. If no `component_tree` field exists on the bundle, creates `FieldStorageConfig` + `FieldConfig` for `field_component_tree`.
3. If the bundle already has a `component_tree` field (even with a different name), skips provisioning.

**Important:** Auto-provisioning always uses the name `field_component_tree`. If you add a canvas field with a different name through the Field UI before exposing a slot, the template will use your existing field instead.

### Canvas field API endpoint

The template editor uses `GET /canvas/api/v0/ui/content_template/canvas-fields/{entity_type_id}/{bundle}` to list available `component_tree` fields for the Expose Slot dialog. This returns `[{name, label}]` for each canvas field on the bundle, allowing the dropdown to show field-specific options rather than hardcoded names.

## Field Linking Workflow

### How it works in the Canvas editor

When you select a component instance in the Canvas editor, its props appear in the sidebar form. For content templates, each prop that has compatible entity field suggestions shows a **link icon** (🔗) next to the field label:

1. **Unlinked props** show the link icon with field suggestions dropdown → click a suggestion to link
2. **Linked props** show a "linked field" badge replacing the form widget → click to change or unlink

The linking workflow is driven by `PropSourceSuggester`, which uses **shape matching** to determine which entity fields can populate which component props.

### Shape matching: how fields map to props

Canvas uses JSON Schema-based shape matching to determine compatibility between entity fields and component props. The key rules:

1. **Type compatibility**: The field type must produce data matching the prop's JSON Schema type (e.g., `text_with_summary` → `contentMediaType: text/html`, `image` → structured image object)
2. **Required-field constraint**: **If a component prop is `required`, only entity fields that are also `required` can link to it.** This is Canvas's deliberate design rule for data integrity — documented in Canvas's own `shape-matching.md`.
3. **Format matching**: Props with `contentMediaType: text/html` match processed text fields; plain `type: string` props match string/title fields.

### Article field → component prop mapping (verified)

| Component | Prop | Required? | Linkable Article Fields | Notes |
|-----------|------|-----------|------------------------|-------|
| **Heading** | `text` | yes (string) | ✅ **Title** | Title is required → matches |
| **Image** | `media` | no (image object) | ✅ **Image** (field_image) | Non-required prop → matches non-required field |
| **Image** | `caption` | no (string) | ✅ Title, Image alt, Image title | Multiple string fields match |
| **Paragraph** | `text` | yes (text/html) | ❌ **No suggestions** | Body is NOT required → cannot match required prop |
| **Card** | various string props | varies | ✅ Title, Image alt, etc. | String props match string fields |

### The body field problem

The Paragraph component's `text` prop (`required: true`, `contentMediaType: text/html`, `x-formatting-context: block`) gets **zero field linking suggestions** for the Article content type because:

- The `body` field (`text_with_summary`) is **not required** in the Article content type's field config
- Canvas shape matching enforces: **required prop → only required fields match** (`JsonSchemaFieldInstanceMatcher.php` line 522)
- Therefore the link icon never appears for the Paragraph's text prop

**This is a known Canvas limitation.** Issue [#3564206](https://www.drupal.org/project/canvas/issues/3564206) tracks allowing rich text props to access plain text fields. The Drupal CMS project works around this by using `field_content` (configured as required) instead of the standard `body` field.

**Important**: The body.processed expression (`ℹ︎␜entity:node:article␝body␞␟processed`) **does work** at the code level — Canvas's `NodeTemplatesTest` proves this (line 176). The constraint is only in the UI's PropSourceSuggester. Making the body field required enables the UI linking.

### Solution: Make body field required

The correct approach for this project is to mark the `body` field as required on content types where it should be linked to a Paragraph component. This:

- Matches Canvas's design intent (required prop ↔ required field)
- Follows the Drupal CMS pattern (their article recipe uses `field_content` as required)
- Makes semantic sense (articles should have body content)
- Enables the link icon to appear in the Canvas editor UI

**How**: Edit the body field config at `/admin/structure/types/manage/article/fields/node.article.body` → check "Required field". Or update the field config YAML: `required: true`.

## Creating Content Templates

### Approach comparison

| Approach | Pros | Cons | Best for |
|----------|------|------|----------|
| **Canvas editor UI** | Visual feedback, shape matching suggestions, auto-save | Manual, not automatable, requires browser | Ad-hoc template design, iterating on layout |
| **Programmatic via Drush** | Reproducible, scriptable, exact control | Must know expression format, no live preview | Agent workflows, CI/CD, recipes |
| **Canvas CLI** | Excellent for code components | **Does not support content templates** — only scaffold/build/upload | Code components only |

**Recommended for agents:** Programmatic via Drush. The Canvas CLI has no template commands. The UI is designed for humans. Drush scripts give agents deterministic, repeatable template creation.

### Via Canvas editor UI

1. Open the Canvas editor at `/canvas`
2. In the left sidebar, expand **Templates** → **Content types**
3. Click **"+ Add new template"** button
4. Select the **content type** (e.g., Article) and **view mode** (currently only `Full content` is supported)
5. Click **"Add new template"** — creates a disabled template with empty component tree
6. The editor redirects to `/canvas/template/node/{bundle}/{view_mode}/{preview_entity_id}`
7. Add components to the content region, configure props, link fields
8. **Publish** to activate the template (`status: true`)

**Note:** Only the `full` view mode is currently supported for template creation. Other view modes show "(support coming soon)" in the UI.

### Via Manage Display redirect

When a content template exists for a view mode, Canvas redirects the Manage Display sub-tab to the Canvas editor (`ViewModeDisplayController`). If no template exists, the standard Drupal Manage Display form is shown.

### Template editor URL patterns

```
# Canvas editor (full editor with component library, sidebar, preview)
/canvas/editor/content_template/{entity_type}.{bundle}.{view_mode}

# Canvas template route (with specific preview entity)
/canvas/template/{entity_type}/{bundle}/{view_mode}/{preview_entity_id?}
```

The preview entity is a real content entity used for previewing the template in the editor. The Canvas editor route (`/canvas/editor/content_template/...`) is the direct entry point — it automatically selects a preview entity.

### Auto-save system

Changes in the Canvas editor are auto-saved to a key-value store (`canvas.auto_save`) keyed by `content_template:{entity_type}.{bundle}.{view_mode}`. Auto-saves are drafts — they don't affect the live template until published.

## Programmatic Template Creation (Validated Approach)

This is the **proven, agent-friendly approach** for creating content templates. It was validated by programmatically building the project content template with all field linkings.

### Key differences from Canvas Page assembly

Content template component trees differ from Canvas Page trees in one critical way: **inputs can contain dynamic prop sources** that reference entity field data. In Canvas Pages, all inputs are static values.

| Input type | Canvas Pages | Content Templates |
|------------|-------------|-------------------|
| Static value | ✅ `'text' => 'Hello'` | ✅ `'text' => 'Hello'` |
| Dynamic prop source | ❌ Not allowed | ✅ `'text' => ['sourceType' => 'dynamic', 'expression' => '...']` |
| Host entity URL | ❌ Not allowed | ✅ `'text' => ['sourceType' => 'host-entity-url']` |

### Step 1 — Generate prop expressions for entity fields

Use `BetterEntityDataDefinition` and `FieldPropExpression` to generate the correct Unicode expressions:

```php
use Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression;
use Drupal\canvas\TypedData\BetterEntityDataDefinition;

// IMPORTANT: Use the short entity type ID ('node', 'media', 'file'), NOT 'entity:node'
$entity_def = BetterEntityDataDefinition::create('node', 'project');

// Simple field → value
$title_expr = new FieldPropExpression($entity_def, 'title', NULL, 'value');
// Result: ℹ︎␜entity:node:project␝title␞␟value

// Processed text (body.processed for HTML rendering)
$body_expr = new FieldPropExpression($entity_def, 'body', NULL, 'processed');
// Result: ℹ︎␜entity:node:project␝body␞␟processed

// Link field (url property for the URL, title for the link text)
$url_expr = new FieldPropExpression($entity_def, 'field_project_url', NULL, 'url');
$link_text_expr = new FieldPropExpression($entity_def, 'field_project_url', NULL, 'title');

// Media reference entity traversal
$media_expr = new FieldPropExpression($entity_def, 'field_project_media', NULL, 'entity');
// Result: ℹ︎␜entity:node:project␝field_project_media␞␟entity
```

**Common pitfall:** `BetterEntityDataDefinition::create('entity:node', 'project')` produces `entity:entity:node:project` (doubled prefix). Always use the short form: `'node'`, `'media'`, `'file'`.

### Step 2 — Build the dynamic prop source inputs

Each dynamic prop source is an associative array with `sourceType` and `expression`:

```php
// Simple string field → component text prop
$inputs = [
  'text' => [
    'sourceType' => 'dynamic',
    'expression' => (string) $title_expr,  // Cast to string to serialize
  ],
  'level' => 'h1',  // Static props remain as plain values
];
```

### Step 3 — Construct and validate the component tree

**Critical difference from Canvas Pages**: Content template inputs are **PHP arrays**, not JSON strings. The `setComponentTree()` method handles serialization.

```php
use Drupal\canvas\Entity\ContentTemplate;
use Drupal\Component\Uuid\Php;

$uuid = new Php();
$wrapper_uuid = $uuid->generate();

$tree = [
  // Root component
  [
    'uuid' => $wrapper_uuid,
    'component_id' => 'sdc.alchemize_forge.wrapper',
    'component_version' => 'a2ada112d1451cde',  // From component entity active_version
    'inputs' => [
      'html_tag' => 'section',  // Must be valid enum value: 'div' or 'section'
    ],
  ],
  // Child with dynamic prop source
  [
    'uuid' => $uuid->generate(),
    'component_id' => 'sdc.alchemize_forge.heading',
    'component_version' => '5a794444498af945',
    'parent_uuid' => $wrapper_uuid,
    'slot' => 'content',
    'inputs' => [
      'text' => [
        'sourceType' => 'dynamic',
        'expression' => 'ℹ︎␜entity:node:project␝title␞␟value',
      ],
      'level' => 'h1',
    ],
  ],
];

// Load, set, validate, save
$template = ContentTemplate::load('node.project.full');
$template->setComponentTree($tree);
$template->enable();

// ALWAYS validate before saving
$violations = $template->getTypedData()->validate();
if (count($violations) > 0) {
  foreach ($violations as $violation) {
    echo $violation->getPropertyPath() . ': ' . $violation->getMessage() . "\n";
  }
} else {
  $template->save();
}
```

### Step 4 — Verify dependencies auto-calculated

After saving, Canvas automatically calculates config dependencies:

```yaml
dependencies:
  config:
    - canvas.component.sdc.alchemize_forge.heading      # Component configs
    - canvas.component.sdc.alchemize_forge.wrapper
    - core.entity_view_mode.node.full                    # View mode
    - field.field.node.project.body                      # Referenced field configs
    - field.field.node.project.field_project_media
    - node.type.project                                  # Content type
    - media.type.image                                   # Referenced media type
  module:
    - media
    - node
    - options
```

These dependencies ensure the template is properly invalidated if any referenced field, component, or content type changes.

### Complete working example: project content template

This is the exact script used to build this project's product template (validated and deployed):

```php
use Drupal\canvas\Entity\ContentTemplate;
use Drupal\Component\Uuid\Php;

$uuid = new Php();
$wrapper_uuid = $uuid->generate();
$heading_uuid = $uuid->generate();
$image_uuid = $uuid->generate();
$body_uuid = $uuid->generate();
$link_uuid = $uuid->generate();

// Component version hashes (from canvas.component.sdc.alchemize_forge.* active_version)
$versions = [
  'wrapper'   => 'a2ada112d1451cde',
  'heading'   => '5a794444498af945',
  'paragraph' => '8d99f51dca7dcd73',
  'image'     => 'bf90d410725640a8',
  'link'      => '7ab585c79e208df1',
];

$tree = [
  // Root: Wrapper (section container)
  [
    'uuid' => $wrapper_uuid,
    'component_id' => 'sdc.alchemize_forge.wrapper',
    'component_version' => $versions['wrapper'],
    'inputs' => ['html_tag' => 'section'],
  ],
  // Heading → title field (dynamic)
  [
    'uuid' => $heading_uuid,
    'component_id' => 'sdc.alchemize_forge.heading',
    'component_version' => $versions['heading'],
    'parent_uuid' => $wrapper_uuid,
    'slot' => 'content',
    'inputs' => [
      'text' => ['sourceType' => 'dynamic', 'expression' => 'ℹ︎␜entity:node:project␝title␞␟value'],
      'level' => 'h1',
    ],
  ],
  // Image → field_project_media (media reference, dynamic)
  [
    'uuid' => $image_uuid,
    'component_id' => 'sdc.alchemize_forge.image',
    'component_version' => $versions['image'],
    'parent_uuid' => $wrapper_uuid,
    'slot' => 'content',
    'inputs' => [
      'media' => ['sourceType' => 'dynamic', 'expression' => 'ℹ︎␜entity:node:project␝field_project_media␞␟entity'],
    ],
  ],
  // Paragraph → body field processed HTML (dynamic)
  [
    'uuid' => $body_uuid,
    'component_id' => 'sdc.alchemize_forge.paragraph',
    'component_version' => $versions['paragraph'],
    'parent_uuid' => $wrapper_uuid,
    'slot' => 'content',
    'inputs' => [
      'text' => ['sourceType' => 'dynamic', 'expression' => 'ℹ︎␜entity:node:project␝body␞␟processed'],
    ],
  ],
  // Link → field_project_url (URL + title, dynamic)
  [
    'uuid' => $link_uuid,
    'component_id' => 'sdc.alchemize_forge.link',
    'component_version' => $versions['link'],
    'parent_uuid' => $wrapper_uuid,
    'slot' => 'content',
    'inputs' => [
      'url' => ['sourceType' => 'dynamic', 'expression' => 'ℹ︎␜entity:node:project␝field_project_url␞␟url'],
      'text' => ['sourceType' => 'dynamic', 'expression' => 'ℹ︎␜entity:node:project␝field_project_url␞␟title'],
    ],
  ],
];

$template = ContentTemplate::load('node.project.full');
$template->setComponentTree($tree);
$template->enable();

$violations = $template->getTypedData()->validate();
if (count($violations) > 0) {
  foreach ($violations as $violation) {
    echo $violation->getPropertyPath() . ': ' . $violation->getMessage() . "\n";
  }
} else {
  $template->save();
  echo "Template saved! Components: " . count($template->get('component_tree')) . "\n";
}
```

### Expression format reference for content templates

| Field type | Property | Expression | Use case |
|-----------|----------|------------|----------|
| Title (string) | `value` | `ℹ︎␜entity:node:BUNDLE␝title␞␟value` | Heading text |
| Body (text_with_summary) | `processed` | `ℹ︎␜entity:node:BUNDLE␝body␞␟processed` | HTML-rendered body |
| Body (text_with_summary) | `value` | `ℹ︎␜entity:node:BUNDLE␝body␞␟value` | Raw body value |
| Link | `url` | `ℹ︎␜entity:node:BUNDLE␝FIELD␞␟url` | Link URL |
| Link | `title` | `ℹ︎␜entity:node:BUNDLE␝FIELD␞␟title` | Link text |
| Entity reference (media) | `entity` | `ℹ︎␜entity:node:BUNDLE␝FIELD␞␟entity` | Media image |
| Entity reference (taxonomy) | `entity` | `ℹ︎␜entity:node:BUNDLE␝FIELD␞␟entity` | Term reference |
| String | `value` | `ℹ︎␜entity:node:BUNDLE␝FIELD␞␟value` | Plain text |
| Created/Changed date | `value` | `ℹ︎␜entity:node:BUNDLE␝created␞␟value` | Timestamp |
| Author (uid) reference | via traversal | `ℹ︎␜entity:node:BUNDLE␝uid␞␟entity␜␜entity:user␝name␞␟value` | Author name |

### Validation error patterns

| Error message | Cause | Fix |
|--------------|-------|-----|
| `Does not have a value in the enumeration` | Invalid enum value (e.g., `html_tag: 'article'`) | Check component config for allowed values |
| `Component version mismatch` | Stale `component_version` hash | Reload from `component.toArray()['active_version']` |
| `FIELD field referenced in EXPR does not exist` | Field name doesn't exist on content type | Verify field exists: `ddev drush field:info --entity-type=node --bundle=BUNDLE` |
| `inputs: 'label' is a required key` | Block component missing required `label` input | Add `'label' => '', 'label_display' => FALSE` |

## Building an Article Template (Step-by-Step)

This is the practical workflow for creating a working article content template:

### Prerequisites

1. **Make body field required**: Edit field config at `/admin/structure/types/manage/article/fields/node.article.body` → check "Required field"
2. **Ensure at least one article exists**: The template editor needs a preview entity

### Template structure

For an article, a recommended component structure:

```
Row
├── Column (full width)
│   ├── Heading         → link text to Title
│   ├── Image           → link media to Image (field_image)
│   └── Paragraph       → link text to Body (body.processed) — requires body to be required
```

### Linking props to fields

For each component, click on the component in the editor, then use the link icon (🔗) next to each prop:

| Component | Prop | Link to |
|-----------|------|---------|
| Heading | text | Title |
| Image | media | Image |
| Paragraph | text | Body → Processed text |

### Publishing

Once the template is complete, publish it to make `status: true`. After publishing, all article nodes rendered in the `full` view mode will use this template instead of the Manage Display field formatters.

## Prop Expression Format

Dynamic prop sources use encoded expressions to navigate entity field data:

| Expression | Meaning |
|------------|---------|
| `ℹ︎␜entity:node:article␝title␞␟value` | Article title → value |
| `ℹ︎␜entity:node:article␝body␞␟processed` | Article body → processed HTML |
| `ℹ︎␜entity:node:article␝field_image␞␟{src↠src_with_alternate_widths,alt↠alt,...}` | Image → structured object with responsive widths |
| `ℹ︎␜entity:node:article␝uid␞␟entity␜␜entity:user␝name␞␟value` | Author → user name (entity reference traversal) |

The expression format uses Unicode control characters as delimiters. Expressions are opaque to the UI — the `PropSourceSuggester` generates them and the `Evaluator` resolves them at render time.

## Architectural Rule: One Full-Page Template Per Content Type

Canvas gives you **one full-page Canvas template per content type** (for the `full` view mode). This is a deliberate design choice, not a temporary limitation.

### Why?

Canvas treats the `full` view mode template as **the authoritative page layout** for that content type. PageRegions (header/footer) + the Content Template = the complete page. Canvas is a layout system, not just a formatter — it owns the entire page composition.

### Consequence: Different page layouts = different content types

If two pages have fundamentally different layouts (e.g., a marketing landing page vs. a standard text page), they should be **separate content types**, each with their own Canvas template.

| Scenario | Approach |
|----------|----------|
| Standard text page | `page` content type with simple Canvas template |
| Marketing landing page with hero + sections | `landing_page` content type with rich Canvas template |
| Article detail page | `article` content type with article Canvas template |
| Product detail page | `product` content type with product Canvas template |

### What about minor layout variations within a content type?

For small variations (e.g., with/without sidebar, different header styles), handle them **inside the single Canvas template** using:
- Conditional visibility (planned Canvas feature)
- Boolean/select fields that control which sections show
- Component slot variations

### Anti-pattern: multiple full-page templates per content type

Do NOT try to create multiple full-page Canvas templates for different view modes on the same content type. Canvas UI only supports `full` view mode for template creation. The correct pattern is to use separate content types when layouts are truly different.

## Content Type Modeling for Canvas

### Rule: entities represent things, Canvas composes presentation

When deciding what should be a content type vs. what should be Canvas components:

| If the content... | Then use... |
|-------------------|-------------|
| Exists as a standalone thing (has its own URL, editorial lifecycle) | Content type |
| Appears in multiple places or listings | Content type + view modes |
| Is unique to one page's presentation/message | Canvas component (static content in template) |
| Is a layout/visual element | Canvas component |

**Example**: A "Hero" section with a headline, subtitle, and CTA button is a **Canvas composition** (Wrapper + Heading + Paragraph + Button). The products shown as cards below the hero are **entities** (a Product or Project content type) rendered via a view mode and placed into the Canvas layout via a Views block.

### View modes for non-full-page contexts

Non-`full` view modes (teaser, card, etc.) are for **embedding** content in other contexts:
- Views listings
- Entity reference displays
- Canvas templates that embed other entities

These view modes use standard Drupal Manage Display (field formatters), not Canvas templates.

## Layout Tab (Per-Content Editing Entry Point)

When a node's content type has an enabled ContentTemplate with exposed slots, a **"Layout" tab** appears on the node view page alongside the standard View/Edit/Delete tabs. This tab opens the Canvas editor scoped to that node's per-content slot editing.

### Route

| Property | Value |
|----------|-------|
| **Route name** | `canvas.node.layout` |
| **Path** | `/node/{node}/layout` |
| **Controller** | `CanvasController::nodeLayout()` |
| **Access check** | `NodeLayoutAccessCheck::access()` |

### Access conditions (`NodeLayoutAccessCheck`)

The Layout tab is visible and accessible only when **all** of the following are true:

1. The node's content type has an enabled ContentTemplate (`status: true`) with at least one exposed slot — checked via `ComponentTreeLoader::hasContentTemplateWithExposedSlots()`.
2. The node's bundle has a Canvas field (`field_component_tree`) — verified by `ComponentTreeLoader::getCanvasFieldName()`.
3. The current user has `update` access to the node — checked via `$node->access('update', $account, TRUE)`.

If any condition fails, the tab is hidden (access denied with caching).

### Local task definition

The tab is defined in `canvas.links.task.yml`:

```yaml
canvas.entity.node.layout:
  route_name: canvas.node.layout
  title: 'Layout'
  base_route: entity.node.canonical
  weight: 20
```

The `base_route: entity.node.canonical` places the tab alongside the standard node tabs (View, Edit, Delete, Revisions).

## Constraints and Tradeoffs

- **Entity type requirements for per-content editing**: Any fieldable entity type can use per-content editing via ContentTemplates, provided it has: (1) an enabled ContentTemplate with exposed slots, and (2) a Canvas field (`field_component_tree`) on the bundle. The `ContentTemplateAwareViewBuilder` decorator is currently registered for `Node` only, so template-based rendering is node-specific. The per-content editing API (layout GET/POST/PATCH) is entity-type-agnostic. The Layout tab UI entry point (`/node/{node}/layout`) is node-specific.
- **Full view mode only**: The UI currently only supports creating templates for the `full` view mode. The backend API accepts any view mode, but the UI restricts creation to `full`.
- **One template per content type**: Canvas assumes one full-page layout per content type. Different page layouts should use different content types.
- **Required-field shape matching**: Required component props only match required entity fields. This is a deliberate design decision, not a bug. See Canvas's `shape-matching.md`.
- **`hook_entity_display_build_alter()` skipped**: Display-altering hooks are not invoked when Canvas renders via a ContentTemplate.
- **At least one dynamic prop source required**: Content templates must reference at least one entity field (cannot be purely static).

## Integration Points

- **Node content types** — ContentTemplates target node bundles (`article`, `page`)
- **View modes** — Each template targets one specific view mode (currently only `full`)
- **Canvas components** — SDC, Block, or JS components from any enabled theme/module
- **Entity fields** — Dynamic prop sources reference field values via prop expressions
- **Manage Display** — Completely bypassed when a ContentTemplate is active and enabled
- **Auto-save** — Draft changes in key-value store until published
- **Permissions** — `administer content templates` permission required
- **Canvas sidebar** — Templates listed and managed in the Canvas editor sidebar

## Change Surface

- `config/<site>/canvas.content_template.*` — ContentTemplate config entities
- Canvas editor UI — Visual editing at `/canvas/template/{entity_type}/{bundle}/{view_mode}`
- Canvas HTTP API — `/canvas/api/v0/config/content_template` for programmatic CRUD
- Key-value store — Auto-saves at `canvas.auto_save:content_template:{id}`
- Content type field config — Field `required` setting directly affects Canvas shape matching/linking

## Failure Modes

- **Missing referenced component**: Fallback renders a placeholder, preserving tree structure.
- **Field removed from content type**: Dynamic prop source returns null/empty.
- **Config not exported**: Templates lost on `ddev drush cim`.
- **Non-required field + required prop**: Prop linking unavailable in UI. Make the field required to fix.
- **Template disabled but exists**: No Canvas rendering, but Manage Display redirect may still be affected.
