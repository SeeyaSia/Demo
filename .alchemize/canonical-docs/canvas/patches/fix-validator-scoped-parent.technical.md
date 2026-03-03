# Scoped Parent UUID Validation in Component Tree -- Technical Specification

## Summary

This branch fixes the `ComponentTreeStructureConstraintValidator` to scope its `parent_uuid` lookup to the specific parent component's context within a ContentTemplate tree, rather than merging the entire template tree into the lookup pool. This prevents false validation passes where a component references a `parent_uuid` from an unrelated template branch.

## Branch

`local/fix/validator-scoped-parent` based on `origin/1.x`

## Files Changed

| File | Change Type | Description |
|------|-------------|-------------|
| `src/Plugin/Validation/Constraint/ComponentTreeStructureConstraintValidator.php` | Modified | Scoped template tree lookup to specific parent UUID (+5/-1) |

## Detailed Changes

### `src/Plugin/Validation/Constraint/ComponentTreeStructureConstraintValidator.php`

**Context:**

The validator checks that every component in a component tree has a valid `parent_uuid` -- that is, the referenced parent actually exists in the tree. When the entity uses a ContentTemplate, some parent UUIDs reference template-defined components rather than user-created components. The validator needs to check the template tree to resolve these references.

**Before:**

```php
// Merge the entire template tree into the lookup pool.
$template_tree = $content_template->getComponentTree();
$all_components = array_merge($entity_components, $template_tree->getAllComponents());

// Check if parent_uuid exists in the merged pool.
if (!isset($all_components[$item->parent_uuid])) {
  // Add violation...
}
```

The problem: `$template_tree->getAllComponents()` returns every component from the template, regardless of structural relationship. A component could reference a `parent_uuid` from a completely different slot/region and pass validation.

**After:**

```php
// Scope the template tree lookup to the specific parent UUID context.
$template_components = [];
if ($content_template && $item->parent_uuid) {
  $template_components = $content_template->getComponentTree()
    ->getComponentsForParent($item->parent_uuid);
}
$all_components = array_merge($entity_components, $template_components);

if (!isset($all_components[$item->parent_uuid])) {
  // Add violation...
}
```

By calling `getComponentsForParent($item->parent_uuid)` instead of `getAllComponents()`, the lookup is scoped to only those template components that are structurally relevant to the referenced parent. If the parent UUID does not exist in the template tree, `getComponentsForParent()` returns an empty array, and the validation correctly fails.

**Edge cases handled:**

1. **No ContentTemplate:** `$content_template` is NULL, `$template_components` remains empty -- behaves as before.
2. **parent_uuid is in the entity tree, not the template:** The parent is found in `$entity_components`, template lookup returns empty -- works correctly.
3. **parent_uuid is a root-level template component:** `getComponentsForParent()` returns the root context components -- valid references pass.
4. **parent_uuid references a deeply nested template component:** The scoped lookup includes it only if it exists under the correct structural path.

## Testing

### Manual Verification

1. Create a ContentTemplate with two slot regions containing distinct components.
2. Use the API (or a test script) to create a component tree item with a `parent_uuid` pointing to a component in the wrong region.
3. Validate the entity and confirm a validation error is raised.
4. Fix the `parent_uuid` to point to the correct region and confirm validation passes.

### Automated Testing

```bash
# Run the constraint validator tests
phpunit --filter=ComponentTreeStructureConstraintValidatorTest

# Run the full validation test suite
phpunit --group=canvas_validation
```

### Test Cases to Add

- Test that a component with a `parent_uuid` referencing the correct template slot passes validation.
- Test that a component with a `parent_uuid` referencing a different template slot fails validation.
- Test that a component with a `parent_uuid` referencing a non-existent UUID fails validation (existing test, should still pass).

## Dependencies

None. This is a standalone validation logic fix.
