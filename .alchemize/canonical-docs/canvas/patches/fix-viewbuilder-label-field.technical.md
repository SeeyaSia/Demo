# Canvas ViewBuilder Label Field Preservation -- Technical Specification

## Summary

This branch ensures that the entity label field (e.g., `title` for nodes) is included in the render array when Canvas ContentTemplates are used. Without this, `EntityViewController::buildTitle()` cannot resolve the page title, causing empty titles and crashes on revision view routes. The fix adds 19 lines to `ContentTemplateAwareViewBuilder` to explicitly include the label field in the build output.

## Branch

`local/fix/viewbuilder-label-field` based on `origin/1.x`

## Files Changed

| File | Change Type | Description |
|------|-------------|-------------|
| `src/EntityHandlers/ContentTemplateAwareViewBuilder.php` | Modified | Added label field inclusion in render array (+19/-1) |

## Detailed Changes

### `src/EntityHandlers/ContentTemplateAwareViewBuilder.php`

**Context:**

`ContentTemplateAwareViewBuilder` extends Drupal's standard entity view builder. When an entity has a Canvas ContentTemplate, it replaces the standard field-by-field render array with the Canvas component tree output. The problem is that this replacement discards all standard field render elements, including the label field that Drupal's title system relies on.

**The fix adds a block after the Canvas component tree is built:**

```php
// Ensure the entity label field is present in the render array so that
// EntityViewController::buildTitle() can resolve the page title.
$label_key = $entity->getEntityType()->getKey('label');
if ($label_key && $entity->hasField($label_key)) {
  $label_field = $entity->get($label_key);
  if (!$label_field->isEmpty()) {
    // Build the label field's render array using the default view display.
    // We place it in the build array at its field name key, which is where
    // EntityViewController::buildTitle() expects to find it.
    $build[$entity_id][$label_key] = [
      '#theme' => 'field',
      '#title' => $label_field->getFieldDefinition()->getLabel(),
      '#label_display' => 'hidden',
      '#field_name' => $label_key,
      '#field_type' => $label_field->getFieldDefinition()->getType(),
      '#entity_type' => $entity->getEntityTypeId(),
      '#bundle' => $entity->bundle(),
      '#items' => $label_field,
      '#is_multiple' => FALSE,
      0 => ['#markup' => $entity->label()],
    ];
  }
}
```

**Why this approach:**

- `EntityViewController::buildTitle()` calls `$entity->label()` to get the title string, but some revision view controllers and breadcrumb builders look for the label field in the render array directly.
- By including the label field as a standard field render element, we maintain compatibility with all title-resolution code paths.
- The `#label_display => 'hidden'` ensures the field is present in the array but does not visually duplicate the title on the page.

**Execution flow:**

```
EntityViewController::view()
  -> ContentTemplateAwareViewBuilder::buildMultiple()
    -> Build Canvas component tree (existing logic)
    -> NEW: Include label field in render array
    -> Return build array
  -> EntityViewController::buildTitle()
    -> $entity->label() succeeds (was already working)
    -> Render array contains label field (now fixed)
    -> Page title is set correctly
```

**Revision view route fix:**

The revision view route (`entity.node.revision`) uses a title callback that accesses the entity from the render array. When the label field is missing, some title resolvers fall back to the entity object's `label()` method, but others (especially in contributed modules or custom title callbacks) expect the field to exist in the build output. This fix covers both paths.

## Testing

### Manual Verification

1. Create a Canvas ContentTemplate for `article`.
2. Create an article with title "Test Title".
3. View the node -- verify `<title>` contains "Test Title".
4. View the node's revision list and click "View" on a revision -- verify no crash and title displays correctly.
5. Check the HTML source for the `<h1>` page title element.

### Automated Testing

```bash
# Run the Canvas ViewBuilder tests
phpunit --filter=ContentTemplateAwareViewBuilderTest

# Run the entity rendering integration tests
phpunit --group=canvas_entity_rendering

# Specifically test revision routes
phpunit --filter=RevisionViewTest
```

### Regression Checks

- Verify that entities WITHOUT Canvas ContentTemplates still render titles correctly (no double-rendering).
- Verify that entity types without a label key (e.g., some custom entity types) do not cause errors (the `if ($label_key)` guard handles this).
- Verify that the label field does not appear as visible duplicate text on the rendered page.

## Dependencies

None. However, this fix complements the work in [#3567116](https://www.drupal.org/project/experience_builder/issues/3567116) which addresses contextual links in the same ViewBuilder file.
