# Auto-provision component_tree fields for exposed slots on content template save

**Project:** Canvas (Experience Builder)
**Component:** Field storage / Content templates
**Category:** Feature request
**Priority:** Major
**Canvas Version:** 1.x-dev
**Existing Issue:** New issue

## Problem/Motivation

When a template author defines an exposed slot in a content template, the slot needs a corresponding `component_tree` field on the target entity type/bundle to store per-entity content for that slot. Currently, there is no mechanism to automatically create these fields. A site builder would need to manually:

1. Create a `FieldStorageConfig` for a `component_tree` field with the correct machine name.
2. Create a `FieldConfig` attaching it to the correct bundle.
3. Configure the form display to use the correct widget.

This is error-prone and creates a poor developer experience. The slot key in the exposed slot configuration IS the intended field machine name, so the system has all the information it needs to provision the field automatically.

Additionally, Canvas needs to be aware of dynamically provisioned fields at runtime (before they exist in the database) for entity form displays and other field discovery mechanisms. This requires implementing `hook_entity_bundle_field_info()` to provide `BundleFieldDefinition` objects for slot fields.

## Proposed Resolution

1. **Implement `hook_entity_bundle_field_info()`** in `canvas.module` to dynamically declare `BundleFieldDefinition` for each exposed slot's field machine name. This hook:
   - Iterates over all content templates for the given entity type and bundle.
   - For each active exposed slot, checks whether a `FieldConfig` already exists for that field name.
   - If no `FieldConfig` exists, returns a `BundleFieldDefinition` of type `component_tree` so Drupal is aware of the field at runtime.

2. **Add `postSave()` to `ContentTemplate`** that calls a new `ensureCanvasFieldExists()` method. On every template save, this method:
   - Iterates over the template's active exposed slots.
   - For each slot, checks whether a `FieldStorageConfig` exists for the field machine name; creates one if not.
   - Checks whether a `FieldConfig` exists for the entity type + bundle combination; creates one if not.
   - Configures the form display to use the `component_tree_widget` for the new field.
   - Clears the field definition cache so the new fields are immediately available.

The slot key directly serves as the field machine name, creating a clean 1:1 mapping between exposed slot identifiers and entity fields.

## Remaining Tasks

- [ ] Code review
- [ ] Test coverage
- [ ] Commit

## User Interface Changes

None directly. Fields are provisioned automatically. The provisioned fields will appear on entity edit forms via the `component_tree_widget` (from the `feat/field-widget-component-tree` branch).

## API Changes

- New method `ContentTemplate::ensureCanvasFieldExists()` -- called internally during `postSave()`.
- `hook_entity_bundle_field_info()` implementation provides runtime `BundleFieldDefinition` objects.

## Data Model Changes

- **Field storage:** New `FieldStorageConfig` entities are created automatically for each exposed slot field that does not already exist.
- **Field instance:** New `FieldConfig` entities are created automatically, attaching the field to the appropriate entity type and bundle.
- **Form display:** The `EntityFormDisplay` for the bundle is updated to include the new field with the `component_tree_widget`.

## Release Notes Snippet

Exposed slot fields are now automatically provisioned as component_tree fields when a content template is saved, eliminating manual field setup.
