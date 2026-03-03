# Entity Layout Tab -- Technical Specification

## Summary

This branch adds a "Layout" local task (tab) on content entity canonical pages that allows editors to open the Canvas SPA scoped to a specific entity for per-content editing. The tab only appears when the entity has a ContentTemplate with active exposed slots and the user has update access. A new route at `/canvas/layout/{entity_type}/{entity}` boots the SPA with entity-specific context, and a path processor ensures Drupal access checks are not bypassed by the SPA's path rewriting.

## Branch

`local/feat/entity-layout-tab` based on `origin/1.x`

## Files Changed

| File | Change Type | Description |
|------|-------------|-------------|
| `canvas.links.task.yml` | Modified | Adds local task definition for the "Layout" tab on entity pages |
| `canvas.routing.yml` | Modified | Adds `canvas.entity_layout` route at `/canvas/layout/{entity_type}/{entity}` |
| `canvas.services.yml` | Modified | Registers `EntityLayoutAccessCheck` as an access check service |
| `src/Access/EntityLayoutAccessCheck.php` | New | Access check verifying entity has active exposed slots and user has update access |
| `src/Controller/CanvasController.php` | Modified | Adds `entityLayout()` method to boot the SPA in per-content mode |
| `src/Menu/EntityLayoutTaskLink.php` | New | Local task link deriver mapping `{node}` to `{entity}` route parameter |
| `src/PathProcessor/CanvasPathProcessor.php` | Modified | Excludes `/canvas/layout/` paths from SPA path rewriting |

## Detailed Changes

### `canvas.links.task.yml`

Adds a new local task entry that attaches the "Layout" tab to the `entity.node.canonical` base route. The task references the `canvas.entity_layout` route and uses the `EntityLayoutTaskLink` deriver for parameter mapping. The tab's weight is set to place it after standard entity tabs (View, Edit, etc.).

```yaml
canvas.entity_layout:
  route_name: canvas.entity_layout
  base_route: entity.node.canonical
  title: 'Layout'
  weight: 50
  class: \Drupal\canvas\Menu\EntityLayoutTaskLink
```

### `canvas.routing.yml`

Defines the route that the Layout tab points to:

```yaml
canvas.entity_layout:
  path: '/canvas/layout/{entity_type}/{entity}'
  defaults:
    _controller: '\Drupal\canvas\Controller\CanvasController::entityLayout'
    _title: 'Layout'
  requirements:
    _custom_access: '\Drupal\canvas\Access\EntityLayoutAccessCheck::access'
  options:
    parameters:
      entity:
        type: entity:{entity_type}
```

The `entity` parameter uses Drupal's dynamic entity upcasting via `entity:{entity_type}`, so the route works for any content entity type (not just nodes).

### `canvas.services.yml`

Registers the access check as a tagged service:

```yaml
canvas.entity_layout_access_check:
  class: Drupal\canvas\Access\EntityLayoutAccessCheck
  tags:
    - { name: access_check }
```

### `src/Access/EntityLayoutAccessCheck.php` (New)

Custom access check service with an `access()` method that:

1. Loads the ContentTemplate associated with the entity (via its bundle/view mode).
2. Calls `getActiveExposedSlots()` on the ContentTemplate to determine if any slots are exposed.
3. If no active exposed slots exist, returns `AccessResult::forbidden()`.
4. Checks that the current user has `update` access to the entity.
5. Returns the combined access result, cacheable per entity and user.

```php
public function access(RouteMatchInterface $route_match, AccountInterface $account): AccessResultInterface {
  $entity = $route_match->getParameter('entity');
  if (!$entity instanceof ContentEntityInterface) {
    return AccessResult::forbidden();
  }

  $content_template = $this->getContentTemplate($entity);
  if (!$content_template || empty($content_template->getActiveExposedSlots())) {
    return AccessResult::forbidden()
      ->addCacheableDependency($content_template ?? $entity);
  }

  return $entity->access('update', $account, TRUE)
    ->addCacheableDependency($content_template);
}
```

### `src/Controller/CanvasController.php`

Adds the `entityLayout()` method:

```php
public function entityLayout(string $entity_type, ContentEntityInterface $entity): array {
  // Boot the Canvas SPA with entity-specific context.
  // base_path_override tells the SPA to use the entity-specific
  // path instead of the default /canvas/ base.
  return $this->buildCanvasApp([
    'base_path_override' => "/canvas/layout/{$entity_type}/{$entity->id()}",
    'entity_type' => $entity_type,
    'entity_id' => $entity->id(),
  ]);
}
```

The `base_path_override` is critical: it tells the Canvas SPA to construct its API calls relative to the entity-specific path, enabling the backend to know which entity's layout is being edited.

### `src/Menu/EntityLayoutTaskLink.php` (New)

Extends Drupal's `LocalTaskDefault` to map route parameters. The node canonical route uses `{node}` as its entity parameter, but the layout route uses `{entity}`. This class overrides `getRouteParameters()` to perform the translation:

```php
public function getRouteParameters(RouteMatchInterface $route_match): array {
  $parameters = parent::getRouteParameters($route_match);
  // Map the entity-type-specific parameter to the generic {entity} parameter.
  $entity = $route_match->getParameter('node');
  if ($entity) {
    $parameters['entity_type'] = 'node';
    $parameters['entity'] = $entity->id();
  }
  return $parameters;
}
```

### `src/PathProcessor/CanvasPathProcessor.php`

The Canvas SPA has a path processor that rewrites paths to route them through the SPA. This change adds an exclusion so that `/canvas/layout/` paths are NOT rewritten. This is essential because:

1. The initial page load at `/canvas/layout/{entity_type}/{entity}` must go through Drupal's normal routing so that the access check runs.
2. Once the SPA boots, it uses AJAX calls that do not need path processing.

```php
public function processInbound($path, Request $request) {
  // Do not rewrite /canvas/layout/ paths -- they need standard Drupal routing.
  if (str_starts_with($path, '/canvas/layout/')) {
    return $path;
  }
  // ... existing SPA path rewriting logic
}
```

## Testing

### Manual Verification

1. Create a content type "Article" with a Canvas template.
2. In the template editor, expose at least one slot (requires `feat/active-exposed-slots`).
3. Visit an Article node's canonical page.
4. Verify the "Layout" tab appears alongside View/Edit tabs.
5. Click the "Layout" tab -- the Canvas SPA should load in per-content mode.
6. Remove all exposed slots from the template.
7. Revisit the Article node -- the "Layout" tab should no longer appear.

### Access Control Verification

1. Log in as a user without "edit any article content" permission.
2. Visit an Article node with active exposed slots.
3. Verify the "Layout" tab does NOT appear.
4. Attempt to access `/canvas/layout/node/{nid}` directly.
5. Verify a 403 Forbidden response.

### Automated Testing

```bash
# Run routing tests
phpunit --filter=CanvasRoutingTest

# Run access check tests
phpunit --filter=EntityLayoutAccessCheckTest

# Run controller tests
phpunit --filter=CanvasControllerTest
```

## Dependencies

- **`feat/active-exposed-slots`**: Required for `ContentTemplate::getActiveExposedSlots()` method, which the access check relies on to determine tab visibility.
