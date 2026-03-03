# Graceful 403 for Unsupported Canvas Entities -- Technical Specification

## Summary

This branch wraps the `componentTreeLoader->load()` call in `ComponentTreeEditAccessCheck` with a try/catch for `\LogicException`, converting an unhandled 500 error into a proper `AccessResult::forbidden()` response. This affects any route where Drupal evaluates Canvas edit access for an entity that does not support Canvas editing.

## Branch

`local/fix/access-check-graceful-403` based on `origin/1.x`

## Files Changed

| File | Change Type | Description |
|------|-------------|-------------|
| `src/Access/ComponentTreeEditAccessCheck.php` | Modified | Added try/catch for \LogicException, returns forbidden (+6/-1) |

## Detailed Changes

### `src/Access/ComponentTreeEditAccessCheck.php`

**Context:**

`ComponentTreeEditAccessCheck` implements `AccessInterface` and is used as a route access checker for Canvas editing routes (e.g., the Canvas editor for a specific entity). The `access()` method receives the entity from the route parameters and calls `$this->componentTreeLoader->load($entity)` to verify that the entity has a Canvas component tree.

**Before:**

```php
public function access(EntityInterface $entity, AccountInterface $account): AccessResultInterface {
  $component_tree = $this->componentTreeLoader->load($entity);
  // ... further access checks using $component_tree ...
}
```

If `load()` throws a `\LogicException` (e.g., the entity type does not have a Canvas field), the exception propagates up and Drupal's error handler converts it to a 500 response.

**After:**

```php
public function access(EntityInterface $entity, AccountInterface $account): AccessResultInterface {
  try {
    $component_tree = $this->componentTreeLoader->load($entity);
  }
  catch (\LogicException $e) {
    return AccessResult::forbidden(sprintf(
      'Entity %s/%s does not support Canvas editing: %s',
      $entity->getEntityTypeId(),
      $entity->id(),
      $e->getMessage()
    ));
  }
  // ... further access checks using $component_tree ...
}
```

**Why `\LogicException`:**

The `componentTreeLoader->load()` method throws `\LogicException` specifically when the entity does not meet the prerequisites for Canvas editing. This is the correct exception type per PHP/Drupal conventions -- it represents a programming/configuration error (the route should not have been matched for this entity type), not a runtime error.

**Why `AccessResult::forbidden()` instead of `AccessResult::neutral()`:**

- `forbidden()` is appropriate because we have a definitive answer: this entity cannot be edited with Canvas, so access is explicitly denied.
- `neutral()` would allow other access checkers to potentially grant access, which is incorrect -- if the entity does not support Canvas editing, no access checker should be able to override that.
- The reason string is included for debugging (visible in access check logs and Drupal's debug mode).

**Cache metadata:**

The returned `AccessResult::forbidden()` does not add cache tags for the entity because the entity's Canvas support is a static property of its entity type, not something that changes per-entity. If needed, `addCacheableDependency($entity->getEntityType())` could be added, but entity type definitions rarely change at runtime.

## Testing

### Manual Verification

1. Ensure Canvas editing works normally for supported entity types:
   - Navigate to `/canvas/edit/node/1` for a node with a Canvas ContentTemplate.
   - Verify the Canvas editor loads correctly.
2. Test the fix for unsupported entities:
   - Navigate to `/canvas/edit/taxonomy_term/1` (assuming taxonomy terms do not support Canvas).
   - Verify a 403 Forbidden response (not 500).
3. Check Drupal's watchdog log:
   - The 403 case should log an access denial, not an exception.

### Automated Testing

```bash
# Run the access check tests
phpunit --filter=ComponentTreeEditAccessCheckTest

# Run the full access control test suite
phpunit --group=canvas_access
```

### Test Cases

| Input | Expected Output |
|-------|-----------------|
| Node with Canvas ContentTemplate | Access check proceeds normally (no change) |
| Node without Canvas ContentTemplate | `AccessResult::forbidden()` with reason |
| Taxonomy term (no Canvas support) | `AccessResult::forbidden()` with reason |
| User entity (no Canvas support) | `AccessResult::forbidden()` with reason |
| NULL entity (edge case) | Existing behavior (likely a different error path) |

## Dependencies

None. This is a standalone defensive fix.
