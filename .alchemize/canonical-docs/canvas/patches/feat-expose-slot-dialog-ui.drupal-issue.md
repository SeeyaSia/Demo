# Add UI for managing exposed slots during template editing

**Project:** Canvas (Experience Builder)
**Component:** UI / Editor
**Category:** Feature request
**Priority:** Normal
**Canvas Version:** 1.x-dev
**Existing Issue:** New issue (foundational UI for post-1.0 exposed slots feature per META #3541000)

## Problem/Motivation

Canvas templates define reusable layouts, but there is no mechanism for template authors to designate specific slots as "exposed" -- meaning content editors can later insert per-content components into those slots on individual entities. Without a UI for managing exposed slots, the entire per-content editing workflow has no starting point.

Per META issue #3541000, exposed slots and per-content editing are intentionally deferred to post-1.0. The community position (per @lauriii): "we won't be building exposed slots until after 1.0." This branch provides the forward-looking template-editing UI that will be needed when exposed slots are enabled.

## Proposed Resolution

Add a complete UI for managing exposed slots during template editing, consisting of:

1. **ExposeSlotDialog** (new modal): A dialog that lets template authors configure an exposed slot with a machine name (auto-generated from the slot identifier, editable), a human-readable label, and a canvas field dropdown (populated via `useGetCanvasFieldsQuery`). The canvas field determines which entity field stores per-content components for that slot.

2. **SlotLayer enhancements**: Adds a dropdown menu on each slot in the template editor with "Expose this slot" and "Remove exposed slot" options. Drops into exposed slots are disabled at the template level (content only goes there during per-content editing). An "(exposed)" label is shown on exposed slots.

3. **SlotOverlay enhancements**: Exposed slots get a green dashed border in the preview. A context menu provides expose/remove actions.

4. **EditorFrame protection**: Prevents deletion of components that own or contain exposed slots while in template editing mode. This avoids orphaning exposed slot configurations.

5. **ComponentContextMenu protection**: Blocks copy/cut/paste/delete on components containing exposed slots to prevent accidental data loss.

6. **State management**: New `editingExposedSlots` state in `uiSlice` with `addExposedSlot`/`removeExposedSlot` reducers and a `selectEditingExposedSlots` selector. A new `deleteNodeAndCleanupExposedSlots` thunk in `layoutModelSlice` cleans up exposed slot references before component deletion.

7. **API integration**: Template saves include `exposed_slots` in the POST body. Template loads dispatch `setEditingExposedSlots` to restore state.

8. **Template list badge**: Shows exposed slot count on template list items so authors can see at a glance which templates have exposed slots.

## Remaining Tasks

- [ ] Code review
- [ ] Test coverage
- [ ] Commit

## User Interface Changes

- New "Expose this slot" / "Remove exposed slot" options in slot dropdown menus and context menus during template editing.
- New **ExposeSlotDialog** modal with machine name, label, and canvas field inputs.
- Exposed slots display a green dashed border and "(exposed)" label in the template editor preview.
- Components containing exposed slots cannot be deleted, copied, cut, or pasted in template mode.
- Template list items show a badge with the count of exposed slots.

## API Changes

- Template save requests now include an `exposed_slots` object in the POST body.
- Template load responses are expected to include exposed slot configuration, which is dispatched to the Redux store.
- New RTK Query endpoint: `useGetCanvasFieldsQuery` is consumed by the ExposeSlotDialog for the canvas field dropdown.

## Data Model Changes

None (data model changes are in the `feat/active-exposed-slots` backend branch).

## Release Notes Snippet

Added a template editing UI for managing exposed slots, including a configuration dialog, visual indicators, and component deletion protection.
