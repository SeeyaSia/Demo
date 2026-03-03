# Autosave Revision Ordering and Layout Post Gate -- Technical Specification

## Summary

This branch fixes revision metadata issues in the Canvas autosave system. On the backend, it reorders `set('vid', NULL)` before `setNewRevision()` in `ApiAutoSaveController` to prevent revision ID collisions, and adds `revision_created` timestamp handling so each revision gets a current timestamp instead of cloning the previous one. On the frontend, it introduces a promise-based "layout post gate" that prevents PATCH requests from firing while a layout POST is in-flight, with a 15-second safety timeout. Together, these changes prevent data loss and ensure correct revision metadata during Canvas editor operations.

## Branch

`local/fix/autosave-revision-ordering` based on `origin/1.x`

## Files Changed

| File | Change Type | Description |
|------|-------------|-------------|
| `src/Controller/ApiAutoSaveController.php` | Modified | Reordered vid=NULL before setNewRevision(); added revision_created timestamp |
| `ui/src/features/layout/layoutModelSlice.ts` | Modified | Integrated layout post gate with layout model |
| `ui/src/services/componentAndLayout.ts` | Modified | Wrapped layout POST calls with gate activation |
| `ui/src/services/preview.ts` | Modified | Wrapped PATCH calls with gate await |
| `ui/src/utils/layoutPostGate.ts` | New | Promise-based gate utility for POST/PATCH coordination |

**Total: +147/-35 across 5 files**

## Detailed Changes

### Backend Fix: `src/Controller/ApiAutoSaveController.php`

**The problem in detail:**

`ContentEntityBase::postCreate()` is called during the entity save lifecycle. It contains logic that copies the current revision ID to the entity if one is not set. When the original code called `setNewRevision()` first, the entity was flagged for a new revision but still had the old `vid`. Then `set('vid', NULL)` cleared it, but by this point `postCreate()` had already captured the old `vid` internally, leading to a collision.

**Before:**
```php
$entity->setNewRevision(TRUE);
$entity->set('vid', NULL);
$entity->save();
```

**After:**
```php
$entity->set('vid', NULL);
$entity->setNewRevision(TRUE);
$entity->save();
```

By clearing `vid` first, when `setNewRevision()` is called, the entity has no existing revision ID to collide with. The save lifecycle then correctly generates a new, unique revision ID.

**Why this order matters:**

`setNewRevision()` internally calls `updateLoadedRevisionId()` which snapshots the current revision state. If `vid` is still set at that point, the snapshot includes the old revision ID, and the subsequent save may reuse it. Clearing `vid` first ensures the snapshot starts clean.

### Backend Fix 2: Revision Creation Timestamp

Canvas bypasses `ContentEntityForm::buildEntity()`, which is where Drupal core normally calls `$entity->setRevisionCreationTime($this->time->getRequestTime())`. The controller already set `revision_user` but was missing `revision_created`, causing every Canvas-created revision to carry the timestamp from the previous revision.

**Fix:**
```php
// Right after the revision_user set:
if ($revision_created = $entity_definition->getRevisionMetadataKey('revision_created')) {
  \assert(is_string($revision_created));
  $entity->set($revision_created, \Drupal::time()->getRequestTime());
}
```

This uses the same `getRevisionMetadataKey()` pattern as the `revision_user` handling, keeping the code consistent and entity-type-agnostic.

### Frontend Fix: Layout Post Gate

#### `ui/src/utils/layoutPostGate.ts` (New File)

A self-contained utility module that provides three functions:

```typescript
// Signal that a layout POST is starting. Returns a function to call when done.
export function openGate(): () => void;

// Returns a promise that resolves when no POST is in-flight.
// If no POST is active, resolves immediately.
// Includes a 15-second safety timeout.
export function waitForGate(): Promise<void>;

// Check if a POST is currently in-flight (synchronous).
export function isGateOpen(): boolean;
```

**Implementation:**

```typescript
let gatePromise: Promise<void> | null = null;
let gateResolve: (() => void) | null = null;

const SAFETY_TIMEOUT_MS = 15_000;

export function openGate(): () => void {
  // Create a new promise that will resolve when the POST completes.
  gatePromise = new Promise<void>((resolve) => {
    gateResolve = resolve;
  });

  // Safety timeout: if the POST takes longer than 15 seconds,
  // release the gate to prevent indefinite blocking.
  const timeoutId = setTimeout(() => {
    if (gateResolve) {
      gateResolve();
      gatePromise = null;
      gateResolve = null;
    }
  }, SAFETY_TIMEOUT_MS);

  // Return the close function.
  return () => {
    clearTimeout(timeoutId);
    if (gateResolve) {
      gateResolve();
    }
    gatePromise = null;
    gateResolve = null;
  };
}

export function waitForGate(): Promise<void> {
  return gatePromise ?? Promise.resolve();
}

export function isGateOpen(): boolean {
  return gatePromise !== null;
}
```

**Why a promise-based gate instead of a queue:**

- A queue would add complexity for ordering multiple PATCH requests. In practice, only one PATCH is blocked at a time (the one triggered by the immediate onChange after a drop).
- The promise-based approach is simpler, has no memory accumulation, and the 15-second timeout prevents deadlocks.
- If multiple PATCHes arrive during a POST, they all await the same promise and fire simultaneously when the POST completes. This is acceptable because each PATCH targets a different component or field.

#### `ui/src/services/componentAndLayout.ts`

**Before:**
```typescript
async function postLayout(payload: LayoutPayload): Promise<LayoutResponse> {
  const response = await apiClient.post('/canvas/layout', payload);
  return response.data;
}
```

**After:**
```typescript
async function postLayout(payload: LayoutPayload): Promise<LayoutResponse> {
  const closeGate = openGate();
  try {
    const response = await apiClient.post('/canvas/layout', payload);
    return response.data;
  } finally {
    closeGate();
  }
}
```

The `openGate()` call signals that a POST is in-flight. The `finally` block ensures the gate is closed even if the POST fails, preventing the gate from staying open indefinitely (in addition to the 15-second safety timeout).

#### `ui/src/services/preview.ts`

**Before:**
```typescript
async function patchComponentContent(
  componentId: string,
  content: Record<string, unknown>
): Promise<void> {
  await apiClient.patch(`/canvas/component/${componentId}`, content);
}
```

**After:**
```typescript
async function patchComponentContent(
  componentId: string,
  content: Record<string, unknown>
): Promise<void> {
  // Wait for any in-flight layout POST to complete before sending the PATCH.
  await waitForGate();
  await apiClient.patch(`/canvas/component/${componentId}`, content);
}
```

The `waitForGate()` call returns immediately if no POST is in-flight. If a POST is active, it pauses the PATCH until the POST completes (or the 15-second timeout elapses).

#### `ui/src/features/layout/layoutModelSlice.ts`

Minor integration changes to ensure the layout model's optimistic state updates are coordinated with the gate:

- When a layout POST is dispatched via the Redux thunk, the gate is opened.
- When the thunk resolves or rejects, the gate is closed.
- This ensures that even if the POST is initiated from the Redux layer (rather than directly from `componentAndLayout.ts`), the gate is properly managed.

## Timing Diagram

### Before (Race Condition)

```
User drops component
  |
  +-> Layout POST starts -----> POST in flight -----> POST completes
  |
  +-> CKEditor onChange fires
        |
        +-> PATCH fires immediately -----> 404/409 ERROR (component not yet created)
```

### After (With Gate)

```
User drops component
  |
  +-> openGate()
  +-> Layout POST starts -----> POST in flight -----> POST completes -> closeGate()
  |                                                                         |
  +-> CKEditor onChange fires                                               |
        |                                                                   |
        +-> waitForGate() -----> awaiting... -----> gate released ----------+
                                                         |
                                                         +-> PATCH fires -> SUCCESS
```

## Testing

### Backend Testing

```bash
# Run autosave controller tests
phpunit --filter=ApiAutoSaveControllerTest

# Verify revision creation
phpunit --filter=RevisionCreationTest
```

**Manual verification:**
1. Open Canvas editor, make a change, wait for autosave.
2. Check the revision table in the database:
   ```sql
   SELECT vid, nid, revision_timestamp FROM node_revision WHERE nid = 1 ORDER BY vid;
   ```
3. Verify that each autosave creates a new, distinct revision ID (no collisions).

### Frontend Testing

**Unit tests for layoutPostGate.ts:**
```typescript
describe('layoutPostGate', () => {
  it('waitForGate resolves immediately when no POST is active', async () => {
    await expect(waitForGate()).resolves.toBeUndefined();
  });

  it('waitForGate blocks until gate is closed', async () => {
    const close = openGate();
    let resolved = false;
    waitForGate().then(() => { resolved = true; });
    expect(resolved).toBe(false);
    close();
    await Promise.resolve(); // flush microtasks
    expect(resolved).toBe(true);
  });

  it('gate auto-closes after 15 second timeout', async () => {
    jest.useFakeTimers();
    openGate();
    const gatePromise = waitForGate();
    jest.advanceTimersByTime(15_000);
    await expect(gatePromise).resolves.toBeUndefined();
    jest.useRealTimers();
  });
});
```

**Integration test (manual):**
1. Open Canvas editor.
2. Open browser DevTools Network tab.
3. Drag a Text component into the layout.
4. Immediately start typing in the component.
5. Observe the Network tab:
   - The layout POST fires first.
   - The content PATCH fires only after the POST completes (look at timing waterfall).
   - Both requests succeed (200/201 status).
6. Verify the typed text is preserved in the component.

## Dependencies

None. Both fixes are self-contained within this branch. The backend fix is independent of the frontend fix, but they are shipped together because they address the same user-facing problem (data loss during rapid editing).
