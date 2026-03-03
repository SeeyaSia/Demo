# Canvas Shape Matching

## Purpose

Documents how Canvas determines which entity fields can populate which component props in Content Templates. Shape matching is the system that drives the field linking UI — it controls whether a link icon appears next to a prop and what options are available in the dropdown.

## Core Concept

When building a Content Template, you link component props to entity fields. Canvas uses **shape matching** to determine compatibility between a component prop's JSON Schema definition and an entity field's data type. Only compatible matches appear as suggestions in the linking UI.

The shape matcher lives in `JsonSchemaFieldInstanceMatcher` and is invoked by `PropSourceSuggester` when the Canvas editor needs field suggestions for a component.

## Matching Rules

### 1. Type compatibility

The prop's JSON Schema `type` must be compatible with the field's data type:

| Prop Schema | Matches Field Types | Example |
|------------|-------------------|---------|
| `type: string` | `string`, `string_long`, computed string properties | Title → Heading text |
| `type: string` + `contentMediaType: text/html` | `text`, `text_long`, `text_with_summary` (processed) | Body → Paragraph text |
| `type: object` (image shape) | `image` (media reference) | field_image → Image media |
| `type: boolean` | `boolean` fields | Published status → toggle |
| `type: string` + `enum` | `list_string` | Enum fields → select props |

### 2. Required-field constraint (critical)

**If a component prop is `required` in its JSON Schema, only entity fields that are also marked `required` in their field config can match.**

This is the single most important rule and the most common source of confusion. It is documented in Canvas's own `shape-matching.md` (lines 136-138) and enforced in `JsonSchemaFieldInstanceMatcher.php` (line 522):

```php
if ($is_required_in_json_schema && !$field_definition->isRequired()) {
    continue;
}
```

**Practical impact on this project:**

| Scenario | Result |
|----------|--------|
| Required prop + Required field | ✅ Match — appears in UI |
| Required prop + Non-required field | ❌ No match — link icon absent |
| Non-required prop + Required field | ✅ Match — appears in UI |
| Non-required prop + Non-required field | ✅ Match — appears in UI |

### 3. Ignored field types

Canvas excludes certain field types from shape matching entirely:

- `decimal`
- `language`
- `list_float`
- `list_integer`

### 4. Ignored fields

Canvas filters out administrative/internal fields that aren't useful for content display:

- `promote` (promoted to front page)
- `sticky` (sticky at top of lists)
- `revision_log` (revision log message)
- `default_langcode`
- `revision_default`
- `revision_translation_affected`

### 5. Text processing

For text fields (`text`, `text_long`, `text_with_summary`):
- The **processed** text property matches `contentMediaType: text/html` props
- The **summary** property (for `text_with_summary`) also matches as a separate suggestion
- The `x-formatting-context` annotation (`block` or `inline`) determines what CKEditor 5 capabilities the editor shows

## PropSourceSuggester

`PropSourceSuggester` is the service that generates field suggestions for the Canvas editor. It:

1. Gets all field definitions for the target entity type + bundle
2. Filters out irrelevant fields (admin fields, ignored types)
3. For each component prop, calls `JsonSchemaFieldInstanceMatcher::findFieldInstanceFormatMatches()`
4. Returns suggestions grouped by prop name, with labels and prop expressions
5. Orders results by form display weight

The structured output includes:
- **instances**: Direct field matches (e.g., Title → Heading text)
- **adapters**: Type-converted matches (e.g., `unix_to_date` for timestamp → date)
- **host_entity_urls**: Entity canonical URL matches

## Entity Reference Traversal

Shape matching doesn't just look at the host entity's own fields. `JsonSchemaFieldInstanceMatcher` recursively follows entity reference fields to find matches on referenced entities.

### Traversal depth (`levels_to_recurse`)

The recursion depth depends on the prop's JSON Schema type:

- **Scalars** (`string`, `boolean`, `integer`, `number`): **1 level** — follows one entity reference hop
- **Objects**: **2 levels** — follows two hops (e.g., node → media → file)
- **URIs** (`format: uri` or `uri-reference`): **2 levels** — for finding image/file URLs

### Bundle-specific matching

When traversing an entity reference, Canvas:
1. Matches base fields on the target entity type (no bundle restriction)
2. Iterates `target_bundles` from the handler settings
3. For each bundle, matches bundle-specific fields (excluding already-matched base fields)

This means Canvas discovers fields on specific bundles of referenced entities. For example, `field_hero_slider` pointing to `block_content:hero_slider` allows Canvas to find `field_autoplay` (a bundle field on `hero_slider`).

### Cardinality matching

Field cardinality must be compatible with the prop's expected cardinality:
- A single-cardinality prop cannot match an unlimited-cardinality field
- A finite-cardinality field (>1) can match a higher-cardinality JSON Schema array (`maxItems`)
- Unlimited fields only match unlimited array props

### Well-known image shapes — specialized path

Canvas's image handling does NOT use general traversal. Instead, `ShapeMatchingHooks::mediaLibraryStorablePropShapeAlter()` creates hardcoded `ReferenceFieldTypePropExpression`s for:
- `$ref: json-schema-definitions://canvas.module/image` → traverses entity_reference → media → source field → image properties (src, alt, width, height)
- `$ref: json-schema-definitions://canvas.module/stream-wrapper-image-uri` → traverses to file URI

This only works for **direct** media references on the host entity. It does not work for media references through intermediate entities (e.g., node → block → block's media field).

## Impact on This Project

### Article content type field matching

The Article content type has these fields and their required status:

| Field | Type | Required? | Canvas Implications |
|-------|------|-----------|-------------------|
| `title` | `string` | ✅ Yes | Matches both required and non-required string props |
| `body` | `text_with_summary` | ❌ No | Only matches non-required text/html props |
| `field_image` | `image` (entity ref) | ❌ No | Only matches non-required image/media props |
| `field_tags` | `entity_reference` | ❌ No | Limited matching — no standard component consumes tag references |
| `comment` | `comment` | ❌ No | No component prop matching |

### The body → Paragraph problem

The Bootstrap Forge **Paragraph** component defines:
```yaml
props:
  type: object
  required:
    - text
  properties:
    text:
      type: string
      contentMediaType: text/html
      x-formatting-context: block
```

Since `text` is **required** and `body` is **not required**, Canvas returns zero suggestions. The fix is to make body required on the content type.

### Heading → Title works

The Bootstrap Forge **Heading** component defines:
```yaml
props:
  type: object
  required:
    - text
  properties:
    text:
      type: string
```

Since `text` is **required** and `title` is also **required**, Canvas successfully suggests Title for the Heading text prop.

### Image → field_image works

The Bootstrap Forge **Image** component defines:
```yaml
props:
  type: object
  properties:
    media:
      type: object
      # (structured image schema - not required)
```

Since `media` is **not required**, it matches `field_image` even though `field_image` is also not required.

## Common Misconception: "All Fields Must Be Required"

**This is false.** The constraint only applies when a component prop is marked `required` in its JSON Schema. Most component props are optional, and optional props match any field regardless of its required status.

### Which Bootstrap Forge props are required?

| Component | Required Props | Optional Props |
|-----------|---------------|----------------|
| **Heading** | `text`, `level` | `text_color`, `alignment`, `preset`, spacing props |
| **Paragraph** | `text` | `text_color`, all spacing props |
| **Button** | `text`, `variant` | `url`, `size`, `outline`, spacing props |
| **Link** | `url`, `text` | `stretched_link`, `as_button`, `button_variant` |
| **Image** | *(none)* | `media`, `caption`, `img_class`, spacing props |
| **Card** | *(none)* | All visual props; uses optional slots for content |
| **Blockquote** | `text` | `footer`, `cite` |
| **Row** | *(none)* | All column/gap props; uses `row` slot |
| **Column** | *(none)* | All responsive size props; uses `column` slot |
| **Wrapper** | *(none)* | All container/spacing/class props; uses `content` slot |

**Key insight**: Image, Card, Row, Column, and Wrapper have **no required props at all**. These components work with non-required fields without any issues.

### What actually needs to be required?

For the Article content type, only one pairing has a problem:

| Field | Required? | Target Component Prop | Prop Required? | Problem? |
|-------|-----------|----------------------|----------------|----------|
| `title` | ✅ Yes | Heading `text` | ✅ Yes | **No** — both required |
| `field_image` | ❌ No | Image `media` | ❌ No | **No** — optional prop accepts any field |
| `body` | ❌ No | Paragraph `text` | ✅ Yes | **YES** — required prop rejects non-required field |
| `field_tags` | ❌ No | Link `text` (optional) | Depends | **No** — if linked to optional props |

Only the body → Paragraph pairing is blocked. Tags, image, and other non-required fields work fine with components that have optional props for their target data type.

### Alternative: custom component with optional rich text prop

Instead of making the body field required, you could create a custom component (via Canvas Code Components) with an **optional** `text/html` prop. This component would gracefully render nothing when the field is empty, avoiding the render-time crash. However, making body required is simpler and semantically correct for articles.

## Design Rationale: Render-Time Safety

Canvas's required-field constraint exists for **render-time data integrity**. The enforcement happens at two levels:

### 1. UI-time (shape matching)

`JsonSchemaFieldInstanceMatcher` prevents linking required props to non-required fields. This is the gatekeeper that controls which field suggestions appear in the Canvas editor.

### 2. Render-time (Evaluator)

When a content template renders, `Evaluator.php` resolves prop expressions against the actual entity data:

```php
// Evaluator.php lines 234-273 (simplified)
if (!$is_required) {
    // Optional prop: NULL is acceptable, component renders without this prop
    return new EvaluationResult($result, $result_cacheability);
}

// Required prop: if the resolved value is empty/null...
// → throws CacheableAccessDeniedHttpException (effectively a 403)
// → the entire page fails to render
```

**This is why the constraint exists**: if you could link a required prop to a non-required field, and a content editor published a node without filling that field, the page would **crash with a 403 error** instead of rendering. The shape matching constraint prevents this scenario by design.

### Summary

The pattern is protective, not restrictive:
- **Optional props** → safe with any field (empty = graceful null)
- **Required props** → only safe with required fields (empty = crash)
- The UI prevents you from creating combinations that would crash at render time

The Drupal CMS project addressed the body field case by making their article recipe's content field (`field_content`) required. This is the recommended pattern — fields that should always be present in content templates should be required in their field config.

## Related Issues

- [#3564206](https://www.drupal.org/project/canvas/issues/3564206) — "Allow rich text props to access plain text fields when linking" (active)
- [#3556590](https://www.drupal.org/project/canvas/issues/3556590) — "Generate Content Templates with AI" — the AI helper bypasses shape matching constraints
- Canvas internal docs: `web/modules/contrib/canvas/docs/shape-matching.md` (382 lines)
