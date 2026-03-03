# Auto-Provision component_tree Fields for Exposed Slots -- Technical Specification

## Summary

This branch implements automatic field provisioning for exposed slots. When a content template is saved with exposed slots, the system automatically creates the necessary `FieldStorageConfig`, `FieldConfig`, and form display configuration for each slot's `component_tree` field. A `hook_entity_bundle_field_info()` implementation provides runtime field definitions for slots whose fields have not yet been persisted, ensuring field discovery works at all times.

## Branch

`local/feat/auto-provision-slot-fields` based on `origin/1.x`

## Files Changed

| File | Change Type | Description |
|------|-------------|-------------|
| `canvas.module` | Modified (+hook) | Implements `hook_entity_bundle_field_info()` for dynamic slot field definitions |
| `src/Entity/ContentTemplate.php` | Modified (+method) | Adds `postSave()` and `ensureCanvasFieldExists()` for field provisioning on save |

**Total:** 2 files, +116/-0 lines

## Detailed Changes

### `canvas.module`

**New hook implementation: `canvas_entity_bundle_field_info()`**

```php
function canvas_entity_bundle_field_info(
  EntityTypeInterface $entity_type,
  string $bundle,
  array $base_field_definitions
): array
```

This hook is invoked by Drupal's Entity Field Manager when discovering fields for a given entity type and bundle. The implementation:

1. Loads all `ContentTemplate` entities that target the given entity type and bundle.
2. For each template, retrieves active exposed slots via `getActiveExposedSlots()`.
3. For each exposed slot, the slot key IS the field machine name.
4. Checks whether a `FieldConfig` already exists for `{entity_type_id}.{bundle}.{slot_key}`.
5. If no `FieldConfig` exists, creates a `BundleFieldDefinition`:
   - Sets the field type to `component_tree`.
   - Sets the label from the slot's configuration label.
   - Sets the field as not required.
   - Makes it non-translatable by default.
6. Returns the array of `BundleFieldDefinition` objects keyed by field name.

This ensures that Drupal is aware of slot fields at runtime even before `postSave()` has had a chance to create the persistent field storage and configuration. This is important for entity form displays and field-based queries that might run before a full save cycle.

### `src/Entity/ContentTemplate.php`

**New method: `postSave(EntityStorageInterface $storage, $update = TRUE)`**

Overrides the parent `postSave()` to trigger field provisioning after every template save (both create and update). Calls `$this->ensureCanvasFieldExists()` for each active exposed slot.

**New method: `ensureCanvasFieldExists()`**

Private method that handles the actual field provisioning logic:

1. Iterates over `$this->getActiveExposedSlots()`.
2. For each slot, determines the field machine name (the slot key), entity type ID, and bundle from the template's target configuration.
3. **FieldStorageConfig check/create:**
   - Calls `FieldStorageConfig::loadByName($entity_type_id, $field_name)`.
   - If NULL, creates a new `FieldStorageConfig` with:
     - `field_name`: the slot key
     - `entity_type`: the target entity type
     - `type`: `component_tree`
     - `cardinality`: 1
   - Saves the new field storage.
4. **FieldConfig check/create:**
   - Calls `FieldConfig::loadByName($entity_type_id, $bundle, $field_name)`.
   - If NULL, creates a new `FieldConfig` with:
     - `field_storage`: the FieldStorageConfig from step 3
     - `bundle`: the target bundle
     - `label`: the slot's configured label
     - `required`: FALSE
   - Saves the new field config.
5. **Form display configuration:**
   - Loads (or creates) the `EntityFormDisplay` for `{entity_type_id}.{bundle}.default`.
   - Sets the component for the new field to use the `component_tree_widget` widget type.
   - Saves the form display.
6. **Cache clear:**
   - Calls `\Drupal::service('entity_field.manager')->clearCachedFieldDefinitions()` to ensure the newly created fields are immediately discoverable.

**Error handling:**
- If a `FieldStorageConfig` already exists with a different type (not `component_tree`), the method logs a warning and skips that field to avoid overwriting non-Canvas fields.
- If the entity type does not support bundles, the bundle parameter is set to the entity type ID.

## Testing

### Manual Verification

1. Create a content template targeting `node:article` with an exposed slot keyed as `field_hero_content`.
2. Save the template.
3. Verify that `FieldStorageConfig` `node.field_hero_content` was created with type `component_tree`.
4. Verify that `FieldConfig` `node.article.field_hero_content` was created.
5. Navigate to Admin > Structure > Content types > Article > Manage form display and verify `field_hero_content` is present with the "Component Tree (Canvas)" widget.
6. Add a second exposed slot `field_sidebar` to the template and save again.
7. Verify the second field is provisioned without affecting the first.
8. Delete the exposed slot from the template and save -- verify the field remains (fields are not auto-deleted to prevent data loss).

### Automated Tests

- **Kernel test for `postSave()` provisioning:**
  - Saving a template with one exposed slot creates the correct FieldStorageConfig and FieldConfig.
  - Saving again with an additional slot creates only the new field.
  - Pre-existing FieldConfig for a slot key is not overwritten.
  - Form display is correctly configured.
- **Kernel test for `hook_entity_bundle_field_info()`:**
  - Returns BundleFieldDefinition for slots without persisted FieldConfig.
  - Returns empty array when all slots have persisted FieldConfig.
  - Returns empty array when no templates target the bundle.

## Dependencies

- `feat/active-exposed-slots` -- required for `getActiveExposedSlots()` used to iterate over slots.
- `feat/field-widget-component-tree` -- required for the `component_tree_widget` widget type used in form display configuration.
