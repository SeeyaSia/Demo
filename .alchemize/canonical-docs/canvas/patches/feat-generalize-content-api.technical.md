# Generalize Canvas Content API -- Technical Specification

## Summary

This branch generalizes the Canvas content CRUD API from hardcoded `canvas_page` references to support any content entity type. It refactors route definitions, controller methods, access checks, and the component tree loader to accept entity type as a parameter. The `CanvasController` admin UI is updated to deduplicate create links across multiple `component_tree` fields on the same bundle. This is a significant architectural change that enables Canvas to manage content on nodes, media, taxonomy terms, and other entity types.

## Branch

`local/feat/generalize-content-api` based on `origin/1.x`

## Files Changed

| File | Change Type | Description |
|------|-------------|-------------|
| `canvas.routing.yml` | Modified | Generalizes content routes with `{entity_type_id}` parameter |
| `src/Controller/ApiContentControllers.php` | Modified | Refactors CRUD methods for any entity type, adds access methods |
| `src/Controller/CanvasController.php` | Modified | Deduplicates create links across multiple canvas fields |
| `src/Storage/ComponentTreeLoader.php` | Modified | Generalizes loading for any entity type |

**Total:** 4 files, +167/-64 lines

## Detailed Changes

### `canvas.routing.yml`

Routes are updated from hardcoded patterns to parameterized ones. Example transformation:

**Before:**
```yaml
canvas.api.v0.content.post:
  path: '/canvas/api/v0/content'
  defaults:
    _controller: '\Drupal\canvas\Controller\ApiContentControllers::post'
```

**After:**
```yaml
canvas.api.v0.content.post:
  path: '/canvas/api/v0/content/{entity_type_id}/{bundle}'
  defaults:
    _controller: '\Drupal\canvas\Controller\ApiContentControllers::post'
  requirements:
    _custom_access: '\Drupal\canvas\Controller\ApiContentControllers::createAccess'
```

Similar changes apply to the `delete`, `get`, and `list` routes. The `{entity_type_id}` parameter is added to all content routes, and `{bundle}` is added where entity creation or listing requires bundle context.

### `src/Controller/ApiContentControllers.php`

**Constructor changes:**

- Injects `EntityTypeBundleInfoInterface` via dependency injection to resolve bundle information at runtime.

**Refactored method: `post(Request $request, string $entity_type_id, string $bundle): JsonResponse`**

Previous implementation:
- Hardcoded `canvas_page` entity creation.

New implementation:
1. Loads the entity type definition via `$this->entityTypeManager->getDefinition($entity_type_id)`.
2. Checks if the entity type has a bundle key via `$entity_type->getKey('bundle')`.
3. If a bundle key exists, includes it in the entity creation values array: `[$bundle_key => $bundle]`.
4. Creates the entity using `$storage->create($values)`.
5. Saves and returns the JSON response with the new entity's ID and relevant metadata.

**Refactored method: `delete(Request $request, string $entity_type_id, $entity): JsonResponse`**

- Parameter renamed from `$canvas_page` to `$entity` to reflect generic entity handling.
- Uses entity type parameter conversion (Drupal's `ParamConverter`) to load the entity.
- Calls `$entity->delete()` without type-specific assumptions.

**Refactored method: `list(Request $request, string $entity_type_id): JsonResponse`**

Previous implementation:
- Hardcoded query against `canvas_page` entity storage.

New implementation:
1. Gets the storage handler for the parameterized entity type.
2. Builds an entity query using the correct storage.
3. Supports revision tracking if the entity type is revisionable (`$entity_type->isRevisionable()`).
4. Returns the list response in the same JSON format, now with entity type metadata included.

**New method: `createAccess(string $entity_type_id, string $bundle): AccessResultInterface`**

Custom access check for the `post` route:
1. Checks `$this->entityTypeManager->getAccessControlHandler($entity_type_id)->createAccess($bundle)`.
2. Returns `AccessResult::allowed()` or `AccessResult::forbidden()`.
3. Adds cache contexts for `user.permissions`.

**New method: `listAccess(string $entity_type_id): AccessResultInterface`**

Custom access check for the `list` route:
1. Verifies the user has permission to access the entity type's admin pages or the Canvas admin permission.
2. Returns appropriate `AccessResult`.

### `src/Controller/CanvasController.php`

**Modified: create link generation**

Previous behavior: generated one create link for every `component_tree` field found across all bundles. If a bundle had two canvas fields (e.g., `field_hero` and `field_sidebar`), two identical create links would appear for that bundle.

New behavior:
1. Collects canvas field information per bundle.
2. Deduplicates: emits one create link per entity type + bundle combination, regardless of how many `component_tree` fields exist on that bundle.
3. The link label includes the bundle label for clarity (e.g., "Create Article" rather than "Create canvas_page").

### `src/Storage/ComponentTreeLoader.php`

**Generalized loading logic:**

Previous implementation:
- Methods referenced `canvas_page` entity type directly for loading component trees.

New implementation:
- Methods accept `$entity_type_id` as a parameter.
- Uses `$this->entityTypeManager->getStorage($entity_type_id)` instead of hardcoded storage.
- Query builders use the entity type's data table and revision table (if revisionable) dynamically.
- Field column names are resolved via the field storage definition rather than hardcoded column names.

## Testing

### Manual Verification

1. **Node content creation:** Make a POST request to `/canvas/api/v0/content/node/article` with appropriate payload. Verify a new article node is created.
2. **Entity listing:** Make a GET request to `/canvas/api/v0/content/node` and verify all node entities with canvas fields are listed.
3. **Entity deletion:** Make a DELETE request to `/canvas/api/v0/content/node/{nid}` and verify the node is deleted.
4. **Access control:** Verify that unauthenticated users receive 403 for all routes. Verify that users without create permission for a bundle receive 403 on the POST route.
5. **Backward compatibility:** Verify that existing `canvas_page` operations still work via `/canvas/api/v0/content/canvas_page/canvas_page`.
6. **Admin UI:** Navigate to the Canvas admin dashboard and verify create links are deduplicated (one per bundle).

### Automated Tests

- **Functional tests for each CRUD operation:**
  - POST creates entity of correct type and bundle.
  - GET retrieves correct entity.
  - DELETE removes the entity.
  - LIST returns entities of the specified type.
- **Access control tests:**
  - `createAccess()` returns forbidden for anonymous users.
  - `createAccess()` returns allowed for users with correct permissions.
  - `listAccess()` behaves correctly for various permission combinations.
- **Kernel test for `ComponentTreeLoader`:**
  - Loads component trees for nodes.
  - Loads component trees for custom entity types.
  - Handles revisionable vs non-revisionable entity types.
- **Functional test for `CanvasController` deduplication:**
  - Bundle with two canvas fields shows one create link.
  - Multiple bundles each show their own create link.

## Dependencies

None. This branch does not depend on other feature branches. It can be developed and tested independently. However, it is architecturally complementary to the exposed slots infrastructure -- generalized content API routes are necessary for managing per-slot content on entity types other than `canvas_page`.
