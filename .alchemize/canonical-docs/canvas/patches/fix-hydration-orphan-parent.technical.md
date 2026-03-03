# Component Tree Hydration Orphan Parent Handling -- Technical Specification

## Summary

This branch adds a safety guard to `ComponentTreeItemList` that prevents assertion failures during component tree hydration when a `parent_uuid` references a template component that is not present in the current hydration context. Instead of crashing, orphaned components are treated as root-level items. This primarily affects non-standard render contexts such as search indexing, Views rendering, and REST serialization.

## Branch

`local/fix/hydration-orphan-parent` based on `origin/1.x`

## Files Changed

| File | Change Type | Description |
|------|-------------|-------------|
| `src/Plugin/Field/FieldType/ComponentTreeItemList.php` | Modified | Added orphan parent guard during hydration (+10/-0) |

## Detailed Changes

### `src/Plugin/Field/FieldType/ComponentTreeItemList.php`

**Context:**

`ComponentTreeItemList` is the field item list class for Canvas component tree fields. During hydration (when stored component data is loaded into the field's runtime representation), each component item's `parent_uuid` is resolved against the tree to establish parent-child relationships.

When an entity uses a ContentTemplate, some component items have a `parent_uuid` that points to a template-defined component (e.g., a slot container in the template). In the normal page rendering context, the template tree is available and these references resolve correctly. However, in non-standard contexts (search indexing, Views, REST), the template tree may not be loaded, leaving these references dangling.

**The fix:**

```php
// During the hydration loop where parent-child relationships are established:
foreach ($items as $delta => $item) {
  if (!empty($item->parent_uuid)) {
    // Check if the parent exists in the current tree context.
    // If not (e.g., it references a template component not loaded in this
    // context), treat this component as root-level rather than crashing.
    if (!isset($tree_components[$item->parent_uuid])) {
      // The parent is not in our hydration scope. This typically happens
      // during search indexing or Views rendering where the full template
      // tree is not available. Treat as root-level.
      $item->parent_uuid = NULL;
      $item->weight = $item->weight ?? 0;
      $root_items[] = $item;
      continue;
    }
    // ... existing parent resolution logic ...
  }
}
```

**Why `parent_uuid = NULL` is safe:**

- Setting `parent_uuid` to NULL on the runtime item does NOT modify the stored field data. The item list is hydrated from storage each time, and this modification only affects the current in-memory representation.
- Root-level components are rendered independently in the component tree, which is the correct fallback behavior -- the component's content is still accessible for search indexing or Views output.
- When the entity is rendered in the normal page context (with the template tree available), the `parent_uuid` is resolved correctly from the original stored data.

**Assertion that was failing:**

The original code contained something like:
```php
assert(isset($tree_components[$item->parent_uuid]),
  'Parent UUID must exist in the component tree.');
```

This assertion is valid in the normal render context but incorrect as a global invariant. The fix replaces the assertion with a graceful fallback.

## Testing

### Manual Verification

1. Create a Canvas ContentTemplate and add components to template slots.
2. Create a node using that ContentTemplate.
3. Run `drush search:index` -- should complete without errors.
4. Create a View that displays fields from the Canvas-enabled content type -- should render without errors.
5. Access the node via JSON:API (`/jsonapi/node/article/{uuid}`) -- should serialize without errors.
6. View the node normally in a browser -- should render correctly with template slot components in the right positions.

### Automated Testing

```bash
# Run the component tree field type tests
phpunit --filter=ComponentTreeItemListTest

# Run search indexing integration tests if available
phpunit --group=canvas_search

# Run the full field type test suite
phpunit --group=canvas_field_types
```

### Specific Scenarios to Test

| Scenario | Expected Behavior |
|----------|-------------------|
| Normal page render with template | Components render in correct template slots |
| Search indexing with template | Indexing completes, text extracted from all components |
| Views rendering with template | Components render as independent items |
| REST serialization with template | Field serializes without error |
| Entity without ContentTemplate | No change in behavior (no orphan parents possible) |
| Component with parent in entity tree (not template) | Parent resolved normally, no fallback triggered |

## Dependencies

None. This is a standalone resilience improvement.
