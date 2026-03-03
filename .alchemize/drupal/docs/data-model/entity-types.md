# Entity Types — Taxonomy, Media, Comments, Block Content, Users

## Purpose

Developer guide for working with Drupal entity types beyond content types (nodes). Covers taxonomy vocabularies, media types, comment types, block content types, and user entities — when to use each, how to create and configure them, and how they relate to content types.

For content types (nodes), see `content-types.md`. For Views that query entities, see `views.md`.

---

## Drupal Entity Type System

Drupal's entity system is the foundation for all structured data. Every piece of content, configuration, and user is an entity.

### Content entities vs configuration entities

| Category | Examples | Stored in | Has revisions | Has fields |
|----------|---------|-----------|---------------|------------|
| **Content entities** | Nodes, taxonomy terms, media, comments, users, block content | Database | Yes (configurable) | Yes |
| **Configuration entities** | Content types, Views, image styles, workflows, Canvas pages | Config YAML | No | No |

Content entities are the things editors create and manage. Configuration entities define the structure and behavior of the system.

### Entity type → Bundle → Field pattern

Most content entity types use **bundles** (subtypes):

| Entity type | Bundles called | Example bundles |
|------------|----------------|-----------------|
| `node` | Content types | `article`, `page`, `event` |
| `taxonomy_term` | Vocabularies | `tags`, `categories` |
| `media` | Media types | `image`, `video`, `document` |
| `comment` | Comment types | `comment` |
| `block_content` | Block types | `basic` |
| `user` | *(no bundles)* | N/A — single bundle |

Each bundle can have its own set of fields. This is how `article` nodes have different fields than `page` nodes, even though both are `node` entities.

---

## Taxonomy Vocabularies

Taxonomy provides **categorization and tagging** across content. Terms are organized in vocabularies, optionally with hierarchy.

### When to use taxonomy

**Create a vocabulary when:**
- You need **categorization or tagging** that spans multiple content types
- Terms will be used for **filtering/grouping** in Views
- You need **hierarchical organization** (parent/child terms)
- The same set of values should be **shared** across different node bundles

**Don't use taxonomy when:**
- Values are specific to one content type and won't be shared → use a List (text) field with allowed values
- You need a flat list of 3-5 options → use a select field
- The "terms" are actually standalone content that needs its own URL → use a content type instead

### Creating a vocabulary

**Via capability script:**

```php
use Drupal\taxonomy\Entity\Vocabulary;

if (!Vocabulary::load('categories')) {
  Vocabulary::create([
    'vid' => 'categories',
    'name' => 'Categories',
    'description' => 'Content categories for filtering and organization.',
    'weight' => 0,
  ])->save();
}
```

**Adding fields to taxonomy terms:**

```bash
# Add a description image to terms
ddev drush field:create taxonomy_term categories \
  --field-name=field_term_image \
  --field-label="Term Image" \
  --field-type=entity_reference \
  --target-type=media \
  --target-bundle=image \
  --field-widget=media_library_widget
```

### Referencing taxonomy from content types

```bash
# Add a taxonomy reference field to a content type
ddev drush field:create node my_type \
  --field-name=field_category \
  --field-label="Category" \
  --field-type=entity_reference \
  --target-type=taxonomy_term \
  --target-bundle=categories \
  --field-widget=options_select
```

**Key settings for taxonomy reference fields:**
- **Cardinality**: `1` for single-select, `-1` (unlimited) for tagging
- **Widget**: `options_select` for dropdown, `options_buttons` for checkboxes/radios, `entity_reference_autocomplete` for free-text autocomplete
- **Auto-create**: Enable to let editors create new terms inline (common for tag-style vocabularies)

### Taxonomy terms and URLs

Each taxonomy term gets a route at `/taxonomy/term/{tid}`. By default, this shows a listing of content tagged with that term. Configure Pathauto for cleaner URLs:

```php
use Drupal\pathauto\Entity\PathautoPattern;

PathautoPattern::create([
  'id' => 'categories_pattern',
  'label' => 'Categories URL Pattern',
  'type' => 'canonical_entities:taxonomy_term',
  'pattern' => 'category/[term:name]',
  'selection_criteria' => [
    [
      'id' => 'entity_bundle:taxonomy_term',
      'bundles' => ['categories' => 'categories'],
      'negate' => FALSE,
      'context_mapping' => ['taxonomy_term' => 'taxonomy_term'],
    ],
  ],
])->save();
```

### Configuration files

- `config/<site>/taxonomy.vocabulary.<vid>.yml` — Vocabulary definition
- `config/<site>/field.storage.taxonomy_term.field_*.yml` — Field storage (shared)
- `config/<site>/field.field.taxonomy_term.<vid>.field_*.yml` — Field instances (per vocabulary)

---

## Media Types

Media entities provide a **reusable asset library** — images, videos, documents, and audio files managed centrally and referenced from content. Media is the recommended approach over direct file/image fields.

### Standard media types (from Media module)

| Media type | Machine name | Source plugin | Use case |
|-----------|-------------|---------------|----------|
| **Image** | `image` | `image` | Photos, graphics, illustrations |
| **Video** | `video` | `video_file` | Local video files |
| **Document** | `document` | `file` | PDFs, Word docs, spreadsheets |
| **Audio** | `audio` | `audio_file` | Podcasts, music, sound effects |
| **Remote Video** | `remote_video` | `oembed:video` | YouTube, Vimeo embeds |

### Why use media instead of direct file fields

| | Direct file/image field | Media reference |
|---|---|---|
| **Reuse** | ❌ Upload per field | ✅ Upload once, reference many times |
| **Central management** | ❌ No library | ✅ Media Library UI |
| **Alt text governance** | ⚠️ Per-field | ✅ Set once on media entity |
| **Canvas integration** | ⚠️ Limited | ✅ Canvas Image component links to media |
| **Revision tracking** | ❌ None | ✅ Media has revisions |

**Recommendation:** Always use media reference fields for new content types. See `content-types.md` for the `drush field:create` command and `media-handling.md` for the complete media workflow, placeholder image generation, and the `media-lib.php` helper library.

### Creating a media reference field

```bash
ddev drush field:create node my_type \
  --field-name=field_media \
  --field-label="Featured Image" \
  --field-type=entity_reference \
  --target-type=media \
  --target-bundle=image \
  --field-widget=media_library_widget
```

### Creating custom media types

For specialized media needs (e.g., SVG icons, infographics):

```php
use Drupal\media\Entity\MediaType;

if (!MediaType::load('svg_icon')) {
  MediaType::create([
    'id' => 'svg_icon',
    'label' => 'SVG Icon',
    'description' => 'SVG icon files for UI components.',
    'source' => 'file',
    'source_configuration' => [
      'source_field' => 'field_media_file',
    ],
  ])->save();
}
```

### Media Library

The Media Library module provides a visual media browser widget. When using `media_library_widget` as the field widget, editors get:
- Grid view of existing media
- Upload new media inline
- Filter by media type
- Search by name

### Configuration files

- `config/<site>/media.type.<type>.yml` — Media type definitions
- `config/<site>/field.storage.media.field_*.yml` — Field storage
- `config/<site>/field.field.media.<type>.field_*.yml` — Field instances per media type

---

## Comment Types

Comments provide threaded discussion on content entities. Comments are themselves entities with bundles (comment types).

### When to use comments

- User-facing discussion on articles, blog posts, or other content
- Internal editorial notes (with restricted permissions)
- Feedback collection on specific content items

### Structure

A comment type targets a specific **host entity type** (usually `node`). Each comment type has:
- **Target entity type**: The entity type comments attach to
- **Fields**: `comment_body` (text_long) by default, plus any custom fields
- **Threading**: Supports nested replies

### Creating a comment type

```php
use Drupal\comment\Entity\CommentType;

if (!CommentType::load('article_comments')) {
  CommentType::create([
    'id' => 'article_comments',
    'label' => 'Article Comments',
    'description' => 'Comments on article content.',
    'target_entity_type_id' => 'node',
  ])->save();
}
```

### Adding a comment field to a content type

Comment fields are special — they're added to the host entity and control the comment form display:

```php
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

// Field storage
if (!FieldStorageConfig::loadByName('node', 'field_comments')) {
  FieldStorageConfig::create([
    'field_name' => 'field_comments',
    'entity_type' => 'node',
    'type' => 'comment',
    'settings' => [
      'comment_type' => 'article_comments',
    ],
  ])->save();
}

// Field instance
if (!FieldConfig::loadByName('node', 'my_type', 'field_comments')) {
  FieldConfig::create([
    'field_name' => 'field_comments',
    'entity_type' => 'node',
    'bundle' => 'my_type',
    'label' => 'Comments',
    'settings' => [
      'default_mode' => 1,        // Threaded
      'per_page' => 50,
      'form_location' => 1,       // Below comments
      'anonymous' => 0,           // No anonymous comments
      'preview' => 1,             // Optional preview
    ],
  ])->save();
}
```

### Configuration files

- `config/<site>/comment.type.<type>.yml` — Comment type definition
- `config/<site>/field.field.comment.<type>.comment_body.yml` — Default body field

---

## Block Content Types

Block content entities are **reusable content blocks** that can be placed in theme regions (Block Layout) or Canvas PageRegions. They differ from Views blocks and Canvas components.

### When to use block content

- **Reusable content** that appears in multiple places (footer CTA, sidebar promo)
- **Editor-managed** content that isn't a full node (no URL, no listing needed)
- Content for **site-wide regions** like headers, footers, sidebars

**Don't use block content when:**
- The content is page-specific → use Canvas components
- The content is a full entity with its own URL → use a content type
- The content is a listing → use a Views block

### Default block type: Basic

Drupal ships with a `basic` block content type that has a `body` field (text_long). This handles most simple content block needs.

### Creating a custom block content type

```php
use Drupal\block_content\Entity\BlockContentType;

if (!BlockContentType::load('promo_block')) {
  BlockContentType::create([
    'id' => 'promo_block',
    'label' => 'Promo Block',
    'description' => 'Promotional content block with image and CTA.',
    'revision' => TRUE,
  ])->save();
}
```

Then add fields:

```bash
ddev drush field:create block_content promo_block \
  --field-name=field_promo_image \
  --field-label="Promo Image" \
  --field-type=entity_reference \
  --target-type=media \
  --target-bundle=image \
  --field-widget=media_library_widget

ddev drush field:create block_content promo_block \
  --field-name=field_promo_link \
  --field-label="CTA Link" \
  --field-type=link \
  --field-widget=link_default
```

### Placing block content

Block content entities can be placed:
1. **Block Layout** — `/admin/structure/block` → Place block → Custom block
2. **Canvas PageRegions** — As block components in Canvas page trees
3. **Views** — Block content can be queried by Views like any entity

### Configuration files

- `config/<site>/block_content.type.<type>.yml` — Block content type definition
- `config/<site>/field.field.block_content.<type>.*.yml` — Field instances

---

## User Entity

The user entity is unique — it has **no bundles**. All users share the same fields. User entities handle authentication, authorization, and profile data.

### Default fields

| Field | Type | Purpose |
|-------|------|---------|
| `name` | Base field | Username (login name) |
| `mail` | Base field | Email address |
| `pass` | Base field | Password (hashed) |
| `status` | Base field | Active/blocked |
| `user_picture` | Image field | Profile picture/avatar |

### Adding fields to users

```bash
ddev drush field:create user user \
  --field-name=field_bio \
  --field-label="Bio" \
  --field-type=text_long \
  --field-widget=text_textarea
```

Note: The entity type is `user` and the bundle is also `user` (since there are no bundles).

### User roles

Roles control permissions. Standard roles:

| Role | Machine name | Purpose |
|------|-------------|---------|
| Anonymous | `anonymous` | Unauthenticated visitors |
| Authenticated | `authenticated` | Any logged-in user |
| Administrator | `administrator` | Full site access |
| Content Editor | `content_editor` | Content management permissions |

Custom roles can be created via capability scripts:

```php
use Drupal\user\Entity\Role;

if (!Role::load('site_manager')) {
  Role::create([
    'id' => 'site_manager',
    'label' => 'Site Manager',
  ])->save();

  // Grant permissions
  $role = Role::load('site_manager');
  $role->grantPermission('administer nodes');
  $role->grantPermission('administer taxonomy');
  $role->save();
}
```

See `configuration/users-and-roles.md` for permission management details.

### Configuration files

- `config/<site>/user.role.<role>.yml` — Role definitions and permissions
- `config/<site>/user.settings.yml` — Registration, email verification, cancellation settings
- `config/<site>/field.field.user.user.*.yml` — User profile fields

---

## Entity Relationships

Entities connect through **entity reference fields**. Understanding these relationships is key to content modeling.

### Common relationship patterns

| Pattern | Field type | Example |
|---------|-----------|---------|
| **Node → Taxonomy** | `entity_reference` → `taxonomy_term` | Article → Tags |
| **Node → Media** | `entity_reference` → `media` | Article → Featured Image |
| **Node → Node** | `entity_reference` → `node` | Article → Related Articles |
| **Node → User** | Base field `uid` | Article → Author |
| **Comment → Node** | Comment field on node | Discussion thread |
| **Block Content → Media** | `entity_reference` → `media` | Promo block → Image |

### Bidirectional references

Drupal entity references are **unidirectional** by default. To display reverse references (e.g., "articles in this category" on a taxonomy term page), use:
- **Views** with contextual filters (filter by taxonomy term from URL)
- **Entity Reference Revisions** module for parent-child relationships

---

## Gotchas and Edge Cases

- **Field storage is shared across bundles.** A field named `field_image` on the `article` content type shares storage with `field_image` on `page`. They must have the same type and storage settings. Use unique field names if types differ.
- **Deleting a vocabulary deletes all its terms.** This is destructive and cannot be undone without a database restore.
- **Media entities have their own access control.** Media permissions are separate from node permissions. Ensure content editors have the right media permissions.
- **Comment fields are special.** They use the `comment` field type which is different from entity reference. Don't try to create comment fields with `drush field:create` — use the PHP API.
- **Block content vs blocks.** Block content entities are not the same as block plugin instances. A block content entity is a piece of content; a block instance is its placement in a region.
- **User entity has no bundles.** You can't create "user types" — all users share the same field configuration. Use roles for differentiation, not fields.

---

## Change Surface

**Entity configuration files (produced by `ddev drush cex -y`):**

- `config/<site>/taxonomy.vocabulary.*.yml` — Vocabulary definitions
- `config/<site>/comment.type.*.yml` — Comment type definitions
- `config/<site>/block_content.type.*.yml` — Block content type definitions
- `config/<site>/media.type.*.yml` — Media type definitions
- `config/<site>/user.role.*.yml` — User role definitions
- `config/<site>/field.storage.<entity_type>.*.yml` — Field storage (shared)
- `config/<site>/field.field.<entity_type>.<bundle>.*.yml` — Field instances (per bundle)

---

## Related Documentation

| Document | Relevance |
|---|---|
| `content-types.md` | Node content types, field creation, view modes |
| `media-handling.md` | **Media rules, placeholder images, media-lib.php helper** — mandatory reading for agents creating content |
| `views.md` | Creating Views that query any entity type |
| `configuration/users-and-roles.md` | User roles, permissions, account settings |
| `integrations/site-services.md` | Media module configuration, Media Library |
| `infrastructure/developer-tools.md` | `drush field:create` command reference |
| `global/drush-config-workflow.md` | Config export after entity changes |
