# Media Handling — Rules, Placeholder Images, and Agent Workflow

## Purpose

Definitive guide for how agents and capability scripts must handle images and media in this project. Covers the mandatory Drupal Media pattern, placeholder image generation for demo content, and the `media-lib.php` helper library. **Every agent creating content with image fields must read this document.**

For media type configuration and entity structure, see `entity-types.md`. For field creation patterns, see `content-types.md`.

---

## Rule: Always Use Drupal Media

**This is a hard rule with no exceptions for new content types.**

| Approach | When to use |
|----------|------------|
| **Media reference field** (`entity_reference` → `media:image`) | ✅ Always — for all new content types and new image fields |
| **Direct image field** (`type: image`) | ❌ Never create new ones. Only tolerate existing legacy fields (e.g., `article.field_image` from Drupal's standard install profile) |

### Why

- **Reuse**: Upload once, reference from many entities
- **Central management**: Media Library UI for browsing, searching, filtering
- **Alt text governance**: Set once on the media entity, consistent everywhere
- **Canvas integration**: Canvas Image component links to media entities via `$ref: json-schema-definitions://canvas.module/image`
- **Revision tracking**: Media entities have revisions
- **Future-proof**: Media is Drupal's recommended approach; direct image fields are legacy

### Creating a media reference field

```bash
# Preferred: use drush field:create
ddev drush field:create node my_type \
  --field-name=field_image \
  --field-label="Image" \
  --field-type=entity_reference \
  --target-type=media \
  --target-bundle=image \
  --field-widget=media_library_widget
```

```php
// PHP API (for capability scripts):
'field_my_image' => [
  'type' => 'entity_reference',
  'cardinality' => 1,
  'settings' => ['target_type' => 'media'],
],

// Field instance settings:
'settings' => [
  'handler' => 'default:media',
  'handler_settings' => [
    'target_bundles' => ['image' => 'image'],
  ],
],
```

### Attaching media to content programmatically

```php
// For media reference fields (entity_reference → media):
$node->set('field_image', ['target_id' => $media->id()]);

// For legacy direct image fields (like article.field_image):
$node->set('field_image', [
  'target_id' => $file->id(),
  'alt' => 'Description of the image',
]);
```

---

## Placeholder Images for Demo Content

When agents generate demo content (test articles, showcase pages, sample nodes), **image fields must not be left empty**. Empty image fields produce broken layouts and make demo content look unfinished.

### The approach: picsum.photos → Drupal file/media

1. **Download** a random image from `https://picsum.photos/{width}/{height}`
2. **Save** it as a permanent Drupal file entity (`public://placeholders/`)
3. **Create** a Drupal Media entity (for media reference fields) or attach the file directly (for legacy image fields)
4. **Attach** to the content entity's image field

This is safe because:
- The image is downloaded **once** and saved permanently — it does NOT change on page load
- Each request to picsum.photos returns a different random photo, so each piece of content gets a unique image
- We are NOT using the picsum URL as an `<img src>` — we download and persist

### ⚠️ Never use picsum URLs as image sources

Using `https://picsum.photos/...` directly in `<img>` tags, SDC component `examples`, or Canvas component props produces **different images on every page load**. Always download and save first. See `canvas-sdc-components.md` → "Prop examples and placeholder images" for the related SDC rule.

### Common placeholder dimensions

| Use case | Width × Height | Aspect ratio |
|----------|---------------|-------------|
| Hero / banner | 1920 × 800 | ~2.4:1 |
| Article header | 1200 × 800 | 3:2 |
| Card image | 800 × 600 | 4:3 |
| Square thumbnail | 400 × 400 | 1:1 |
| Portrait | 600 × 800 | 3:4 |
| Wide banner | 1200 × 400 | 3:1 |

---

## Media Library: `media-lib.php`

The shared helper library at `.alchemize/drupal/capabilities/lib/media-lib.php` provides functions for downloading images and creating Drupal file/media entities.

### Setup

```php
require_once __DIR__ . '/../lib/media-lib.php';
```

### Functions

#### `media_lib_download_to_file(string $url, string $filename, string $directory = 'placeholders'): ?File`

Downloads a file from a URL and saves it as a Drupal file entity.

```php
$file = media_lib_download_to_file(
  'https://picsum.photos/1200/800',
  'my-hero-image.jpg',
  'hero-images'      // saved to public://hero-images/
);
// Returns File entity or NULL on failure.
```

#### `media_lib_create_from_url(string $url, string $name, string $alt, ?string $filename = NULL, string $directory = 'placeholders'): ?Media`

Downloads an image and creates a complete Media entity (type: image).

```php
$media = media_lib_create_from_url(
  'https://picsum.photos/800/600',
  'Project hero image',         // Media name (shown in Media Library)
  'A landscape placeholder',    // Alt text (required for accessibility)
);
// Returns Media entity or NULL on failure.
// Use: $node->set('field_media_image', ['target_id' => $media->id()]);
```

#### `media_lib_create_from_file_entity(File $file, string $name, string $alt): ?Media`

Creates a Media entity from an already-saved File entity.

#### `media_lib_picsum_url(int $width, int $height): string`

Returns a picsum.photos URL for the given dimensions.

```php
$url = media_lib_picsum_url(1200, 800);
// Returns: 'https://picsum.photos/1200/800'
```

#### `media_lib_create_placeholder_batch(int $count, int $width, int $height, string $name_prefix, string $alt_prefix): Media[]`

Creates multiple placeholder media entities in one batch.

```php
$media_entities = media_lib_create_placeholder_batch(
  6,                // count
  1200, 800,        // dimensions
  'Article hero',   // name prefix → "Article hero 1", "Article hero 2", ...
  'Article image'   // alt prefix → "Article image 1", "Article image 2", ...
);
```

---

## Capability Scripts

### `create-placeholder-media.drush.php`

Standalone script for batch-creating placeholder media entities.

```bash
# Create 6 default placeholders (1200×800)
ddev drush php:script .alchemize/drupal/capabilities/generators/create-placeholder-media.drush.php

# Custom dimensions and count (use ddev exec for env vars)
ddev exec "COUNT=10 WIDTH=800 HEIGHT=800 PREFIX='Card image' drush php:script \
  .alchemize/drupal/capabilities/generators/create-placeholder-media.drush.php"
```

Parameters: `COUNT`, `WIDTH`, `HEIGHT`, `PREFIX`

### `create-test-articles.drush.php`

Updated to automatically download placeholder images for each article. Controlled by the `IMAGES` parameter (default: enabled).

```bash
# Articles with images (default)
ddev drush php:script .alchemize/drupal/capabilities/generators/create-test-articles.drush.php

# Articles without images
ddev exec "IMAGES=0 drush php:script \
  .alchemize/drupal/capabilities/generators/create-test-articles.drush.php"
```

**Note:** The article content type uses a legacy direct image field (`field_image`), not a media reference. The script handles this by attaching file entities directly. For new content types, always use media references.

---

## Agent Workflow: Content Creation Checklist

When creating demo content or generating sample data, follow this workflow:

1. **Check if the content type has image fields** — inspect field config or use `ddev drush field:info node.<type>`
2. **Determine the field type**:
   - `entity_reference` → `media:image` → use `media_lib_create_from_url()` then attach `['target_id' => $media->id()]`
   - Legacy `image` field → use `media_lib_download_to_file()` then attach `['target_id' => $file->id(), 'alt' => '...']`
3. **Choose appropriate dimensions** based on the field's usage context (hero, card, thumbnail — see table above)
4. **Always provide alt text** — it is required for accessibility and by most field configurations
5. **Include `media-lib.php`** at the top of your script: `require_once __DIR__ . '/../lib/media-lib.php';`

### Example: Creating a node with a media image

```php
require_once __DIR__ . '/../lib/media-lib.php';

use Drupal\node\Entity\Node;

// Create a placeholder media entity.
$media = media_lib_create_from_url(
  media_lib_picsum_url(1200, 800),
  'My Project Image',
  'Placeholder image for project'
);

// Create the node with media attached.
$node = Node::create([
  'type' => 'project',
  'title' => 'My Project',
  'field_project_image' => $media ? ['target_id' => $media->id()] : [],
  'status' => 1,
  'uid' => 1,
]);
$node->save();
```

---

## Legacy Fields: The Article Exception

The `article` content type ships with Drupal's standard install profile and uses `field_image` — a direct `image` field, not a media reference. This is a legacy pattern.

- **Do NOT convert** the existing article field — it would require migrating existing content and could break Views, Canvas Content Templates, and other integrations.
- **Do** work with it as-is using `media_lib_download_to_file()` for placeholder images.
- **For all new content types**, use media references. See the field creation patterns above.

---

## Gotchas

- **picsum.photos rate limiting**: If creating many images in rapid succession, picsum.photos may throttle requests. The `media_lib_download_to_file()` function handles failures gracefully (returns NULL, logs a warning).
- **File permissions**: Files are saved to `public://` which maps to `web/sites/default/files/`. Ensure the directory is writable inside DDEV (it should be by default).
- **Alt text is required**: The `field_media_image` field has `alt_field_required: true`. Always provide meaningful alt text.
- **Empty media in Canvas Image component**: Passing an empty array or NULL for the image `media` prop causes `AssertionError` in `StaticPropSource::isMinimalRepresentation()`. Either omit the image component entirely or provide a complete media object.
- **Env vars in DDEV**: Use `ddev exec "VAR=val drush ..."` to pass environment variables to scripts. The simpler `VAR=val ddev drush ...` does NOT pass vars into the container.

---

## Related Documentation

| Document | Relevance |
|---|---|
| `entity-types.md` | Media type configuration, media entity structure, media vs direct image comparison |
| `content-types.md` | Field creation patterns, media reference field creation, image field guidance |
| `content-type-listing-pattern.md` | End-to-end content type example using media reference fields |
| `canvas/canvas-sdc-components.md` | Image component props, placeholder image rules for SDC `examples` |
| `canvas/canvas-build-guide.md` | Using `canvas_image()` in component trees, avoiding external image URLs |
| `global/architecture.md` | Architecture rules, Canvas Image component + empty media gotcha |
