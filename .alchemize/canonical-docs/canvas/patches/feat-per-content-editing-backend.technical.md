# Per-Content Editing Backend -- Technical Specification

## Summary

This branch implements the backend infrastructure for per-content editing in Canvas. It adds merged component tree construction (combining template layout with entity-specific slot content), recursive editability annotations on every component, enforcement of template component immutability, and slot-scoped save logic that writes only exposed slot subtrees to the entity's canvas fields without modifying the template.

## Branch

`local/feat/per-content-editing-backend` based on `origin/1.x`

## Files Changed

| File | Change Type | Description |
|------|-------------|-------------|
| `src/Controller/ApiLayoutController.php` | Modified | ~450 lines added: merged tree building, editable annotations, per-content save path, helper methods (+450/-20) |
| `src/ClientDataToEntityConverter.php` | Modified | New `convertEntityFormFields()` for per-content editing (+20/-3) |
| `src/Controller/CanvasController.php` | Modified | Entity layout bootstrap data injection (+8/-1) |
| `src/Entity/ContentTemplate.php` | Modified | Imports `getActiveExposedSlots()` (+2/-1) |

## Detailed Changes

### `src/Controller/ApiLayoutController.php`

This file receives the largest changes. The controller handles all layout API requests and must now distinguish between template editing mode and per-content editing mode.

#### Per-content detection

Per-content mode is detected by the presence of entity context in the request (entity type and entity ID, passed via the route or request parameters):

```php
private function isPerContentMode(Request $request): bool {
  return $request->query->has('entity_type') && $request->query->has('entity_id');
}
```

#### `get()` method changes

When in per-content mode, the `get()` method:

1. **Loads the ContentTemplate** via `getPerContentTemplate()`, which resolves the template for the entity's bundle and view mode.
2. **Loads the template tree** -- the base component tree from the template.
3. **Loads entity slot content** -- per-content components stored in the entity's canvas fields.
4. **Builds the merged tree**: Iterates over the template tree's exposed slots, injecting entity slot content into each exposed slot's component list. If an entity has no content for a slot, the slot remains empty but is still marked as exposed.
5. **Annotates editability**: Calls `annotateEditableRecursive()` on the merged tree.
6. **Returns enriched response** including `contentTemplateId` and `exposedSlots` metadata.

```php
public function get(Request $request): JsonResponse {
  if ($this->isPerContentMode($request)) {
    return $this->getPerContentLayout($request);
  }
  // ... existing template/page layout logic
}

private function getPerContentLayout(Request $request): JsonResponse {
  $entity = $this->loadEntityFromRequest($request);
  $content_template = $this->getPerContentTemplate($entity);
  $exposed_slots = $content_template->getActiveExposedSlots();

  // Build merged tree.
  $template_tree = $content_template->getComponentTree();
  $merged_tree = $this->mergeEntitySlotContent($template_tree, $exposed_slots, $entity);

  // Annotate editability.
  $this->annotateEditableRecursive($merged_tree, $exposed_slots, $entity);

  // Mark global region components as non-editable.
  foreach ($this->getGlobalRegionComponents($merged_tree) as $component) {
    $this->annotateAllNonEditableRecursive($component);
  }

  return new JsonResponse([
    'layout' => $merged_tree,
    'contentTemplateId' => $content_template->id(),
    'exposedSlots' => $exposed_slots,
  ]);
}
```

#### `annotateEditableRecursive()` method (new)

Recursively walks the component tree and sets `editable` on each component:

```php
private function annotateEditableRecursive(
  array &$tree,
  array $exposed_slots,
  ContentEntityInterface $entity
): void {
  foreach ($tree as &$component) {
    // A component is editable if it exists in one of the entity's canvas fields
    // (i.e., it was added during per-content editing, not part of the template).
    $component['editable'] = $this->entityHasComponentInAnyField(
      $entity, $component['uuid'], $exposed_slots
    );

    // Recurse into slots.
    if (!empty($component['slots'])) {
      foreach ($component['slots'] as &$slot) {
        if (!empty($slot['components'])) {
          $this->annotateEditableRecursive(
            $slot['components'], $exposed_slots, $entity
          );
        }
      }
    }
  }
}
```

#### `annotateAllNonEditableRecursive()` method (new)

Used for global region components that should never be editable in per-content mode:

```php
private function annotateAllNonEditableRecursive(array &$component): void {
  $component['editable'] = FALSE;
  if (!empty($component['slots'])) {
    foreach ($component['slots'] as &$slot) {
      if (!empty($slot['components'])) {
        foreach ($slot['components'] as &$child) {
          $this->annotateAllNonEditableRecursive($child);
        }
      }
    }
  }
}
```

#### `patchComponent()` method changes

Adds a guard that rejects edits to template-owned components:

```php
public function patchComponent(Request $request, string $component_uuid): JsonResponse {
  if ($this->isPerContentMode($request)) {
    $entity = $this->loadEntityFromRequest($request);
    $content_template = $this->getPerContentTemplate($entity);

    // Check if the component belongs to the template.
    if ($this->componentBelongsToTemplate($content_template, $component_uuid)) {
      return new JsonResponse(
        ['error' => 'Cannot edit template-owned component in per-content mode.'],
        403
      );
    }
  }
  // ... existing patch logic
}
```

#### `postLayout()` method changes

Handles two distinct save paths:

1. **Template mode** (existing): Saves the full component tree to the template, including any `exposed_slots` metadata from the request body.
2. **Per-content mode** (new): Extracts each exposed slot's subtree from the submitted component tree and saves them to the correct entity canvas fields.

```php
public function postLayout(Request $request): JsonResponse {
  $data = json_decode($request->getContent(), TRUE);

  if ($this->isPerContentMode($request)) {
    return $this->savePerContentLayout($request, $data);
  }
  // ... existing template save logic (now also handles exposed_slots)
}

private function savePerContentLayout(Request $request, array $data): JsonResponse {
  $entity = $this->loadEntityFromRequest($request);
  $content_template = $this->getPerContentTemplate($entity);
  $exposed_slots = $content_template->getActiveExposedSlots();

  // For each exposed slot, extract the subtree and save to the entity field.
  foreach ($exposed_slots as $slot_config) {
    $slot_components = $this->extractSlotSubtree(
      $data['layout'], $slot_config['componentId'], $slot_config['slotId']
    );
    $field_name = $slot_config['fieldName'];
    $entity->get($field_name)->setValue($this->serializeComponents($slot_components));
  }

  $entity->save();
  return new JsonResponse(['status' => 'saved']);
}
```

#### `getPerContentTemplate()` method (new)

Resolves the ContentTemplate for an entity:

```php
private function getPerContentTemplate(ContentEntityInterface $entity): ContentTemplate {
  // Look up the ContentTemplate for the entity's bundle and default view mode.
  $template = $this->contentTemplateStorage->loadByProperties([
    'target_entity_type_id' => $entity->getEntityTypeId(),
    'target_bundle' => $entity->bundle(),
  ]);
  return reset($template);
}
```

#### `entityHasComponentInAnyField()` method (new)

Checks whether a component UUID exists in any of the entity's canvas fields (indicating it was added during per-content editing):

```php
private function entityHasComponentInAnyField(
  ContentEntityInterface $entity,
  string $component_uuid,
  array $exposed_slots
): bool {
  $canvas_fields = array_unique(array_column($exposed_slots, 'fieldName'));
  foreach ($canvas_fields as $field_name) {
    if ($entity->hasField($field_name)) {
      $tree = $entity->get($field_name)->getValue();
      if ($this->treeContainsComponent($tree, $component_uuid)) {
        return TRUE;
      }
    }
  }
  return FALSE;
}
```

#### `loadTreeContainingComponent()` method (new)

Finds which canvas field contains a given component, used during patch operations to determine the correct save target:

```php
private function loadTreeContainingComponent(
  ContentEntityInterface $entity,
  string $component_uuid,
  array $exposed_slots
): ?string {
  $canvas_fields = array_unique(array_column($exposed_slots, 'fieldName'));
  foreach ($canvas_fields as $field_name) {
    if ($entity->hasField($field_name)) {
      $tree = $entity->get($field_name)->getValue();
      if ($this->treeContainsComponent($tree, $component_uuid)) {
        return $field_name;
      }
    }
  }
  return NULL;
}
```

#### `stripExposedSlotContent()` method (new)

Removes per-content components from the template tree, used internally to ensure the template tree stays clean:

```php
private function stripExposedSlotContent(array &$tree, array $exposed_slots): void {
  foreach ($tree as &$component) {
    if (!empty($component['slots'])) {
      foreach ($component['slots'] as $slot_name => &$slot) {
        $slot_key = $component['uuid'] . '__' . $slot_name;
        if (isset($exposed_slots[$slot_key])) {
          // Clear per-content components from exposed slots in the template tree.
          $slot['components'] = [];
        } elseif (!empty($slot['components'])) {
          $this->stripExposedSlotContent($slot['components'], $exposed_slots);
        }
      }
    }
  }
}
```

### `src/ClientDataToEntityConverter.php`

New method `convertEntityFormFields()` that handles the per-content editing case where only the component tree (via canvas fields) is being edited, not the entity's standard form fields:

```php
public function convertEntityFormFields(
  ContentEntityInterface $entity,
  array $slot_data,
  array $exposed_slots
): ContentEntityInterface {
  // In per-content mode, we only write to canvas fields.
  // Page data fields (title, body, etc.) are not modified.
  foreach ($exposed_slots as $slot_config) {
    $field_name = $slot_config['fieldName'];
    if (isset($slot_data[$slot_config['machineName']])) {
      $entity->get($field_name)->setValue(
        $slot_data[$slot_config['machineName']]
      );
    }
  }
  return $entity;
}
```

### `src/Controller/CanvasController.php`

The `entityLayout()` method (added in the `feat/entity-layout-tab` branch) is enhanced to inject template context into `drupalSettings`:

```php
public function entityLayout(string $entity_type, ContentEntityInterface $entity): array {
  $content_template = $this->getContentTemplate($entity);
  $exposed_slots = $content_template->getActiveExposedSlots();

  return $this->buildCanvasApp([
    'base_path_override' => "/canvas/layout/{$entity_type}/{$entity->id()}",
    'entity_type' => $entity_type,
    'entity_id' => $entity->id(),
    'templateContext' => [
      'contentTemplateId' => $content_template->id(),
      'hasExposedSlots' => !empty($exposed_slots),
      'exposedSlots' => $exposed_slots,
    ],
  ]);
}
```

### `src/Entity/ContentTemplate.php`

Minor change -- adds the import/use of `getActiveExposedSlots()` (the method itself is defined in the `feat/active-exposed-slots` branch):

```php
use Drupal\canvas\ExposedSlots\ActiveExposedSlotsTrait;

class ContentTemplate extends ConfigEntityBase {
  use ActiveExposedSlotsTrait;
  // ...
}
```

## Architecture: Merged Tree Flow

```
Per-content GET request
  |
  v
Load ContentTemplate for entity
  |
  v
Get template component tree (base layout)
  |
  v
Get entity's canvas field values (per-content slot data)
  |
  v
For each exposed slot:
  - Find the slot in the template tree
  - Inject entity's slot components into it
  |
  v
Annotate entire merged tree:
  - Template components -> editable: false
  - Entity slot components -> editable: true
  - Global region components -> editable: false
  |
  v
Return merged tree + metadata to frontend
```

## Architecture: Per-Content Save Flow

```
Per-content POST request (full merged tree from frontend)
  |
  v
Load ContentTemplate, get exposed slot config
  |
  v
For each exposed slot:
  - Extract the slot's subtree from submitted tree
  - Save to entity's canvas field (slot_config.fieldName)
  |
  v
Save entity (NOT the template)
  |
  v
Return success response
```

## Testing

### Manual Verification

1. Create a template with exposed slots (requires companion branches).
2. Create an entity using that template.
3. Navigate to the entity's Layout tab.
4. Verify the GET response contains a merged tree with `editable` flags.
5. Verify template components show `editable: false`.
6. Add a component to an exposed slot via the frontend.
7. Save -- verify only the entity's canvas field is written.
8. Reload -- verify the component persists in the merged tree with `editable: true`.
9. Attempt to PATCH a template-owned component -- verify 403 response.

### Automated Testing

```bash
# Run API controller tests
phpunit --filter=ApiLayoutControllerTest

# Run per-content specific tests
phpunit --filter=PerContentEditingTest

# Run ClientDataToEntityConverter tests
phpunit --filter=ClientDataToEntityConverterTest

# Run ContentTemplate tests
phpunit --filter=ContentTemplateTest

# Full test suite
phpunit --group=canvas
```

### Edge Cases to Verify

- Entity with no per-content data yet (all exposed slots empty): merged tree should show empty exposed slots as editable drop zones.
- Entity with per-content data in some slots but not others: only populated slots should have editable components.
- Template updated after entity has per-content data: merged tree should reflect template changes while preserving entity slot data.
- Multiple exposed slots pointing to the same canvas field: each slot's subtree should be independently extractable and saveable.
- Component moved between exposed slots: the save should correctly update both source and destination fields.

## Dependencies

- **`feat/merged-component-tree`**: Provides the tree merging utilities used by the controller.
- **`feat/entity-layout-tab`**: Provides the route and bootstrap that this controller serves.
- **`feat/generalize-content-api`**: Provides the generic entity loading and canvas field access utilities.
