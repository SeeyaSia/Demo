# Autosave race condition: revision ID collision and PATCH-before-POST ordering

**Project:** Canvas (Experience Builder)
**Component:** Autosave / API controllers
**Category:** Bug report
**Priority:** Critical
**Canvas Version:** 1.x-dev
**Existing Issue:** New issue

## Problem/Motivation

Multiple issues with revision metadata during Canvas autosave publish operations:

### Problem 1: Revision ID Collision (Backend)

In `ApiAutoSaveController`, the sequence of operations when creating a new autosave revision is:

1. `$entity->setNewRevision(TRUE)` -- marks the entity for a new revision
2. `$entity->set('vid', NULL)` -- clears the revision ID so a new one is generated

However, `ContentEntityBase::postCreate()` (called during the save lifecycle) re-populates the `vid` from the entity's existing state, causing a collision where the new revision overwrites the current revision instead of creating a new one. This can result in silent data loss.

### Problem 2: PATCH fires before POST completes (Frontend)

When a user drops a new component onto the Canvas layout:

1. A layout **POST** request is sent to create the new component.
2. The CKEditor instance inside the component fires an `onChange` event immediately.
3. A **PATCH** request is sent to update the component's content.

If the PATCH arrives at the server before the POST completes, the server either returns a 404 (component does not exist yet) or a 409 (revision conflict), both of which cause the user's text edits to be lost.

### Problem 3: Revision timestamp not updated (Backend)

Canvas bypasses `ContentEntityForm::buildEntity()`, which is where Drupal core normally calls `setRevisionCreationTime()`. The controller already sets `revision_user` but was missing the corresponding `revision_created` update. As a result, every revision created via the Canvas editor carries the timestamp from the previous revision, making the Revisions tab show identical dates for all entries.

### Steps to Reproduce

**Problem 1:**
1. Open the Canvas editor on a node.
2. Make a change and wait for autosave.
3. Check the revision table -- in some cases, the original revision is overwritten instead of a new one being created.

**Problem 2:**
1. Open the Canvas editor.
2. Drag a Text component from the component library into the layout.
3. Immediately begin typing in the new Text component.
4. Observe that the first few characters are lost, or a console error appears showing a failed PATCH request.

## Proposed Resolution

### Fix 1: Reorder `set(vid, NULL)` before `setNewRevision()`

By clearing the revision ID **before** calling `setNewRevision()`, the entity's revision creation lifecycle correctly generates a new, unique revision ID. The `postCreate()` method does not re-populate `vid` because it is already NULL at the point where `setNewRevision()` initializes the revision tracking.

### Fix 2: Layout Post Gate (Frontend)

Introduce a promise-based "layout post gate" utility (`layoutPostGate.ts`) that:

1. Tracks whether a layout POST is currently in-flight.
2. When a PATCH request is about to fire, checks the gate.
3. If a POST is in-flight, the PATCH `await`s the POST's completion promise before proceeding.
4. Includes a 15-second safety timeout to prevent indefinite blocking if the POST fails silently.

This gate is integrated into:
- `componentAndLayout.ts` -- wraps layout POST calls with gate activation.
- `preview.ts` -- wraps PATCH calls with gate checks.
- `layoutModelSlice.ts` -- coordinates gate state with the layout model.

### Fix 3: Set `revision_created` timestamp on publish

Add `$entity->set($revision_created, \Drupal::time()->getRequestTime())` alongside the existing `revision_user` set, using the same `getRevisionMetadataKey()` pattern. This mirrors what `ContentEntityForm::buildEntity()` does in the standard Drupal form submit flow.

## Remaining Tasks

- [ ] Code review
- [ ] Test coverage (especially frontend race condition simulation)
- [ ] Commit

## API Changes

None. The backend fix changes the internal ordering of method calls. The frontend fix adds an internal coordination mechanism that is not exposed in any public API.

## Data Model Changes

None.

## Release Notes Snippet

Fixed revision ID collision during autosave, a race condition where content edits could be lost when typing immediately after dropping a new component, and revision timestamps not being updated when saving from the Canvas editor.
