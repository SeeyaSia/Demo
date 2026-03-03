# Per-content editing backend: merged tree, editability annotations, and slot-scoped saves

**Project:** Canvas (Experience Builder)
**Component:** API controllers / Content templates
**Category:** Feature request
**Priority:** Major
**Canvas Version:** 1.x-dev
**Existing Issue:** New issue (per-content editing backend per post-1.0 roadmap, related to META #3541000 and #3520487)

## Problem/Motivation

Canvas currently operates in two modes: template editing (full control over the component tree) and content viewing (read-only rendering). There is no mechanism for content editors to add, edit, or rearrange components within specific slots on an individual entity -- the "per-content editing" workflow.

Issue #3520487 deliberately "refuses editing an individual node's component tree" because the exposed slots infrastructure was not yet in place. Now that the exposed slots data model and UI exist (via companion branches), the backend needs to support:

1. Building a **merged component tree** that combines the template's locked layout with the entity's per-content slot data.
2. **Annotating each component** with an `editable` flag so the frontend knows which components the editor can interact with.
3. **Saving only the exposed slot subtrees** back to the entity's canvas fields, never modifying the template.

Per META issue #3541000, this is a forward-looking implementation for the post-1.0 roadmap.

## Proposed Resolution

Major backend changes across 4 files (~480 lines added):

### ApiLayoutController enhancements (~450 lines)

- **`get()` method**: Detects per-content mode (entity context present), builds a merged tree by overlaying entity slot content onto the template tree, and annotates every component with `editable: true/false`. Template-owned components are locked; slot-inserted components are editable. Global region components are always non-editable. Returns `contentTemplateId` and `exposedSlots` metadata in the response.

- **`patchComponent()` method**: Rejects edits to template-owned components with a 403 Forbidden response. Only components within exposed slots can be patched.

- **`postLayout()` method**: Handles `exposed_slots` in the request body. In per-content mode, extracts each exposed slot's subtree from the full component tree and saves it to the correct entity canvas field. The template tree is never modified.

- **New helper methods**:
  - `getPerContentTemplate()` -- loads the ContentTemplate for the current entity context.
  - `annotateEditableRecursive()` -- walks the component tree marking exposed-slot children as editable.
  - `annotateAllNonEditableRecursive()` -- marks all components in a subtree as non-editable (used for global regions).
  - `entityHasComponentInAnyField()` -- checks whether a component ID exists in any of the entity's canvas fields.
  - `loadTreeContainingComponent()` -- finds which canvas field contains a given component.
  - `stripExposedSlotContent()` -- removes per-content components from the template tree for clean separation.

### ClientDataToEntityConverter

New `convertEntityFormFields()` method for per-content editing where the component tree is handled separately from page data fields. In per-content mode, the entity's form fields (title, body, etc.) are not editable through Canvas -- only the component tree within exposed slots.

### CanvasController

Adds entity layout bootstrap data injection into `drupalSettings` so the SPA boots with the correct entity context (entity type, entity ID, template context).

### ContentTemplate

Imports and uses `getActiveExposedSlots()` to provide exposed slot metadata to the API controller.

## Remaining Tasks

- [ ] Code review
- [ ] Test coverage
- [ ] Commit

## User Interface Changes

None directly (this is a backend-only branch). The API response shape changes are consumed by the companion frontend branch.

## API Changes

### Layout GET response (per-content mode)

The layout API response in per-content mode now includes:

```json
{
  "layout": { /* merged component tree with editable annotations */ },
  "contentTemplateId": "template_123",
  "exposedSlots": {
    "hero__main": { "machineName": "hero__main", "label": "Hero Content", "fieldName": "field_canvas_hero" }
  }
}
```

Each component in the tree gains an `editable` boolean:

```json
{
  "uuid": "comp-abc",
  "type": "hero_banner",
  "editable": false,
  "slots": {
    "main": {
      "components": [
        { "uuid": "comp-xyz", "type": "text_block", "editable": true }
      ]
    }
  }
}
```

### Layout PATCH (per-content mode)

Requests to patch template-owned components return 403 Forbidden:

```json
{ "error": "Cannot edit template-owned component in per-content mode." }
```

### Layout POST/save (per-content mode)

The save endpoint extracts slot subtrees and writes them to the entity's canvas fields. The template is never modified.

## Data Model Changes

None (the data model is defined in companion branches). This branch reads and writes to existing canvas field storage.

## Release Notes Snippet

Added backend support for per-content editing: merged component trees with editability annotations, template component protection, and slot-scoped entity saves.
