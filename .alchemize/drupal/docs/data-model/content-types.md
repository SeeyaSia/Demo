# Content Types — Content Modeling and Field Management

## Purpose

Developer guide for working with Drupal content types: when to create them, how to add fields, how to configure view modes, and how they flow through Views → Canvas. Also covers the editorial workflow, image styles, Pathauto, and the rules for choosing between content types, Canvas components, and taxonomy.

## Content Types in Drupal

Content types define **structured content** (e.g., Article, Project, Event). Each content type is a node bundle with:

- **Fields** — the data schema (body, image, references, etc.)
- **View modes** — different rendering configurations (full, teaser, card)
- **Form display** — the editing interface (widget types, field ordering)
- **Pathauto patterns** — URL alias generation rules
- **Editorial workflow** — content moderation states (draft → published → archived)

**Key principle:** Content types own data and URLs. Every node has a route (`/node/{nid}` or a Pathauto alias). Canvas controls how content is displayed, not what content exists.

---

## Rules: When to Create a Content Type vs Canvas Component vs Taxonomy

### Content types represent "things that exist"

**Create a content type when:**
- The content is a **standalone thing** with its own URL and editorial lifecycle
- The content will appear in **listings** (Views) or needs to be **queried/filtered**
- The content needs **editorial workflow** (draft → published)
- The content has **fields, references, or media** that editors manage
- The content will be **reused** across multiple pages or contexts
- The content type has a **fundamentally different page layout** from existing types (Canvas gives one full-page template per content type — see `canvas/canvas-content-templates.md`)

**Examples**: Articles, Products, Projects, Team Members, Events — these are things that exist independently and may be listed, filtered, and displayed in multiple ways.

### Canvas components represent "how things look"

**Use Canvas composition (static components in a template) when:**
- The content is **unique to one page's presentation/message** (a hero section headline, a CTA block)
- The element is a **layout/visual pattern** (section wrappers, grid layouts)
- The content **doesn't need to be queried or listed** separately
- The content is **the same every time** the page loads (not data-driven)

**Example**: A hero section with a headline, subtitle, and CTA button is a Canvas composition — it's a visual pattern specific to that page's message, not a standalone thing.

### Entities + view modes for data-driven sections

When a page section shows **multiple items of the same type** (e.g., 4 product cards, a list of team members), those items should be:
1. A **content type** with appropriate fields
2. A **view mode** (e.g., `card`) for how each item renders in that context
3. Placed into the Canvas template via a **Views block component** that queries and renders the entities

This gives you reusability, editorial governance, sort/filter control, and no duplicated content.

**Rule of thumb**: If something is unique to this page's message → Canvas component. If something is a thing that exists elsewhere → Entity + view mode.

### Taxonomy vocabularies for categorization

**Create a taxonomy vocabulary when:**
- You need categorization or tagging across content
- Terms will be referenced by multiple content types
- You need hierarchical organization
- Terms will be used for filtering/grouping in Views

### Anti-patterns

- **Don't** store display-only content in content type fields (use Canvas components for presentation)
- **Don't** create content types for layout elements (use Canvas components)
- **Don't** use taxonomy for one-off lists (use select fields with allowed values)
- **Don't** try to make multiple full-page Canvas templates for one content type — create separate content types instead
- **Don't** hard-code data-driven listings in Canvas — use Views blocks to query entities

---

## Creating Content Types

Per `architecture.md` Rule 5, content types must be created via capability scripts, not by editing YAML files directly. Scripts live at `.alchemize/drupal/capabilities/` and use the Drupal entity API.

### Two approaches for adding fields

**Preferred for most fields — `drush field:create` (tested, works non-interactively):**

```bash
# Media reference field (recommended pattern for images)
ddev drush field:create node my_type \
  --field-name=field_image \
  --field-label="Image" \
  --field-type=entity_reference \
  --target-type=media \
  --target-bundle=image \
  --field-widget=media_library_widget

# Taxonomy reference field
ddev drush field:create node my_type \
  --field-name=field_category \
  --field-label="Category" \
  --field-type=entity_reference \
  --target-type=taxonomy_term \
  --target-bundle=my_categories \
  --field-widget=options_select

# Link field
ddev drush field:create node my_type \
  --field-name=field_url \
  --field-label="URL" \
  --field-type=link \
  --field-widget=link_default
```

`drush field:create` automatically creates field storage, field instance, and adds the field to default form and view displays. See `infrastructure/developer-tools.md` for full command reference.

**Use capability scripts (PHP API) when you need:**
- Content type creation (`NodeType::create()`)
- Body field (`node_add_body_field()` — not available via drush)
- Allowed values for list fields
- Custom form/view display settings beyond defaults
- Multiple interrelated entities in one reproducible script

### PHP API summary

| What | Class | Method |
|------|-------|--------|
| Content type | `Drupal\node\Entity\NodeType` | `NodeType::create(['type' => 'my_type', 'name' => 'My Type', ...])` |
| Field storage | `Drupal\field\Entity\FieldStorageConfig` | `FieldStorageConfig::create(['field_name' => '...', 'entity_type' => 'node', 'type' => 'image', ...])` |
| Field instance | `Drupal\field\Entity\FieldConfig` | `FieldConfig::create(['field_name' => '...', 'entity_type' => 'node', 'bundle' => 'my_type', ...])` |
| Body field | `node_add_body_field()` | Uses shared field storage from node module |
| Form display | `Drupal\Core\Entity\Entity\EntityFormDisplay` | `EntityFormDisplay::load('node.my_type.default')->setComponent(...)` |
| View display | `Drupal\Core\Entity\Entity\EntityViewDisplay` | `EntityViewDisplay::load('node.my_type.default')->setComponent(...)` |

### Idempotent pattern

All capability scripts must check before creating:

```php
// Content type
if (NodeType::load('my_type')) {
  echo "Already exists.\n";
} else {
  NodeType::create([...])->save();
}

// Field storage (shared across bundles)
if (!FieldStorageConfig::loadByName('node', 'field_my_image')) {
  FieldStorageConfig::create([...])->save();
}

// Field instance (per bundle)
if (!FieldConfig::loadByName('node', 'my_type', 'field_my_image')) {
  FieldConfig::create([...])->save();
}
```

### Common field types

| Drupal field type | PHP `'type'` value | Notes |
|---|---|---|
| Text (plain) | `string` | Single-line, no formatting |
| Text (formatted, long) | `text_long` | Multi-line with text format |
| Text (formatted + summary) | `text_with_summary` | Body-style field |
| Image (direct) | `image` | File upload with alt/title — **legacy pattern, prefer media reference** |
| Entity reference | `entity_reference` | Set `target_type` in storage settings |
| Link | `link` | URL + title |
| Boolean | `boolean` | Checkbox |
| List (text) | `list_string` | Select dropdown with allowed values |
| Date | `datetime` | Date/time picker |
| Email | `email` | Email address |

### Image fields: direct image vs media reference

| Approach | Field type | Pros | Cons |
|----------|-----------|------|------|
| **Direct image** | `image` | Simple, no extra entities | No reuse, no centralized management |
| **Media reference** ✅ | `entity_reference` → `media:image` | Reusable assets, Media Library, Canvas Image component linking | Extra entity layer |

**Recommended:** Always use media reference fields for new content types. Use `--field-type=entity_reference --target-type=media --target-bundle=image --field-widget=media_library_widget`. When generating demo content, use `media-lib.php` to create placeholder Media entities — see `media-handling.md` for the full workflow.

### Body field and Canvas

If the content type will use a Canvas ContentTemplate with the Paragraph component, **make the body field required** (`$body->setRequired(TRUE)`). Canvas shape matching only links required props to required fields — see `canvas/canvas-content-templates.md` for details.

---

## Feature Composition: How Content Types Flow Through the System

When building a feature that uses content types, follow this flow:

1. **Content Type** (`node.type.*.yml`) — Defines the entity bundle
2. **Fields** (`field.field.node.*.yml`) — Define data structure
3. **View Modes** (`core.entity_view_display.node.*.yml`) — Define how fields render
4. **Views** (if listing needed) — Query and list entities using view modes
5. **ContentTemplate** (optional) — Canvas template mapping fields to components
6. **Canvas Page/PageRegion** — Composes the page layout

**End-to-end walkthrough:** See `features/content-type-listing-pattern.md` for a complete implementation guide with capability script patterns, View creation workflow, and Canvas page assembly.

---

## View Modes

View modes control how entities render in different contexts. Common view modes:

| View Mode | Purpose | Used For |
|-----------|---------|----------|
| `default`/`full` | Full entity page | Detail pages, Canvas ContentTemplates |
| `teaser` | Compact summary | Views listings, promoted content |
| `card` | Grid-friendly layout | Card grids in Canvas Row/Column |
| `rss` | RSS feed output | Feed displays |

**Creating a view mode:**
1. Go to `/admin/structure/display-modes/view/add`
2. Enable it for the content type at `/admin/structure/types/manage/<type>/display`
3. Configure field formatters in the view mode's Manage Display tab

**Canvas integration:** Only the `full` view mode can use a Canvas ContentTemplate. All other view modes use standard Drupal Manage Display (field formatters).

---

## Image Styles

Image styles transform uploaded images into specific dimensions/formats. Standard Drupal image styles:

| Style | Typical dimensions | Use case |
|-------|--------------------|----------|
| `wide` | Full content width | Hero images, article headers |
| `medium` | ~220×220px | Teaser thumbnails |
| `thumbnail` | ~100×100px | Small previews |
| `large` | ~480×480px | General large display |
| `canvas_parametrized_width` | Dynamic | Canvas components (auto-configured) |

Image styles use effects like scale, crop, and format conversion (WebP). Configure at `/admin/config/media/image-styles`.

---

## Editorial Workflow

Drupal's Content Moderation module provides editorial workflow states:

**Standard editorial workflow:**
- `draft` — Unpublished, work in progress
- `published` — Live, visible to public
- `archived` — Unpublished, removed from public view

**Transitions:**
- `create_new_draft` — From draft/published → draft
- `publish` — From draft/published → published
- `archive` — From published → archived
- `archived_draft` — From archived → draft (restore)
- `archived_published` — From archived → published (restore)

**Assigning workflow to content types:** Must be done via capability script, updating the `entity_types` key in `workflows.workflow.editorial.yml`.

---

## Pathauto Configuration

Pathauto generates URL aliases automatically based on token patterns.

**Common patterns:**
- Articles: `articles/[node:title]`
- Products: `products/[node:title]`
- Events: `events/[node:created:custom:Y-m]/[node:title]`

**Creating a pattern programmatically:**

```php
use Drupal\pathauto\Entity\PathautoPattern;

$pattern = PathautoPattern::create([
  'id' => 'my_type_pattern',
  'label' => 'My Type URL Pattern',
  'type' => 'canonical_entities:node',
  'pattern' => 'my-type/[node:title]',
  'selection_criteria' => [
    [
      'id' => 'entity_bundle:node',
      'bundles' => ['my_type' => 'my_type'],
      'negate' => FALSE,
      'context_mapping' => ['node' => 'node'],
    ],
  ],
]);
$pattern->save();
```

**Generating aliases:** `ddev drush pathauto:aliases-generate all all`

---

## Gotchas and Edge Cases

- **Editorial workflow not assigned by default:** The workflow config may exist but isn't functional until assigned to content types via capability script.
- **View modes are shared:** Changing view mode configuration affects all Views using that mode. Test changes carefully.
- **Image styles are shared:** Multiple view modes may use the same image style. Changing a style affects all usages.
- **Missing view mode:** If a View references a view mode that doesn't exist, entities won't render correctly.
- **Field storage mismatch:** Field instances must match field storage configuration. Use capability scripts to ensure consistency.
- **Body field + Canvas:** Canvas Paragraph component's `text` prop is required. If body isn't required, the Canvas UI won't suggest it for linking. Make body required for Canvas compatibility.

---

## Change Surface

**Content type configuration files (produced by `ddev drush cex -y`):**
- `config/<site>/node.type.*.yml` — Content type definitions
- `config/<site>/field.storage.node.*.yml` — Field storage (shared)
- `config/<site>/field.field.node.*.yml` — Field instances (per bundle)
- `config/<site>/core.entity_view_display.node.*.yml` — View mode displays
- `config/<site>/core.entity_form_display.node.*.yml` — Form displays
- `config/<site>/image.style.*.yml` — Image styles
- `config/<site>/pathauto.pattern.*.yml` — URL alias patterns

---

## Related Documentation

| Document | Relevance |
|---|---|
| `architecture.md` | Invariant rule, composition patterns, Rule 5 (capability scripts) |
| `media-handling.md` | **Media rules, placeholder images, media-lib.php helper** — mandatory reading for agents creating content with images |
| `views.md` | Creating Views for content type listings |
| `canvas/canvas-content-templates.md` | ContentTemplates for entity detail pages |
| `canvas/canvas-shape-matching.md` | How fields map to component props |
| `features/content-type-listing-pattern.md` | End-to-end walkthrough |
| `infrastructure/developer-tools.md` | `drush field:create`, capability scripts, tool reference |
| `global/drush-config-workflow.md` | Config export after changes |
