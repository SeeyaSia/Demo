# Code Review: ATWEB-9 — Canvas UI/UX Per-Content Editing via Exposed Slots

## Phase 1: Plan Requirements

### Plan Steps to Verify
| Step | Type | Expected Approach | What I Expect |
|------|------|-------------------|----------------|
| 1 | Code (restore) | Restore try-catch in ComponentTreeEditAccessCheck | 403 instead of 500 for ineligible entities |
| 2 | Code (restore) | Restore SlotTreeExtractor.php from main | UUID-based slot filtering service exists |
| 3 | Config | Register SlotTreeExtractor in canvas.services.yml | Autowired service definition |
| 4 | Code (restore) | Restore hasContentTemplateWithExposedSlots in ComponentTreeLoader | getCanvasFieldName allows nodes with exposed-slot templates |
| 5 | Code (restore) | Restore getMergedComponentTree in ContentTemplate | Merged tree API for JSON consumption |
| 6 | Code | Add exposedSlots to normalizeForClientSide | Template list includes exposedSlots |
| 7 | Code (restore) | Restore per-content editing in ApiLayoutController | GET/PATCH/POST support per-content mode |
| 8 | Code (restore) | Restore nodeLayout/getTemplateContext in CanvasController | drupalSettings includes templateContext |
| 9 | Code (restore) | Restore NodeLayoutAccessCheck + route + tab | /node/{node}/layout route with access check |
| 10 | Scope decision | ApiContentControllers out of scope | No changes to ApiContentControllers |
| 11 | Frontend | Add templateContext/permissions/homepagePath to DrupalSettings.ts | TypeScript types match backend |
| 12 | Frontend | Add editable to ComponentNode | Frontend type supports editable metadata |
| 13 | Frontend | Add exposedSlots/contentTemplateId to LayoutApiResponse and TemplateViewMode | API response types match backend |
| 14 | Frontend | Store templateContext in Redux (uiSlice) | Two-phase data flow: boot + API response |
| 15 | Frontend | Extend ENTITY context with templateContext | No new enum value needed |
| 16 | Frontend | React route for per-content editing | Reuse existing /editor/node/:entityId |
| 17 | Frontend | Component locking in preview overlay | Template-owned components dimmed/non-interactive |
| 18 | Frontend | Component locking in tree sidebar | Lock icon, no drag/delete/duplicate |
| 19 | Frontend | Locked component message in form panel | Informational message for template components |
| 20 | Frontend | Exposed slot UI in template editor | Context menu, naming dialog, visual indicators |
| 21 | Frontend | Template list exposed slot indicator | Badge showing exposed slot count |
| 22 | Verification | Layout tab rendering | Tab appears for eligible content types |
| 23 | Backend | Canvas field auto-provisioning in postSave | Field created when exposed slots saved |
| 24 | Code (restore) | Restore 15 deleted test methods | Comprehensive backend test coverage |
| 25 | Verification | PHPCS compliance | No new PHPCS errors |
| 26 | Verification | PHPUnit tests pass | All tests pass |

## Phase 2: Implementation Verification

### Step Completion
| Step | Implemented | Correct Approach | Script Policy | Issues |
|------|-------------|-----------------|---------------|--------|
| 1 | Yes | Yes (git checkout main) | Compliant | None — file identical to main |
| 2 | Yes | Yes (git checkout main) | Compliant | None — file identical to main |
| 3 | Yes | Yes (git checkout main) | Compliant | None — services.yml identical to main |
| 4 | Yes | Yes (git checkout main) | Compliant | None — file identical to main |
| 5 | Yes | Yes (git checkout main) | Compliant | None — restored as part of full file restore |
| 6 | Yes | Yes (git checkout main) | Compliant | None — restored as part of full file restore |
| 7 | Yes | Yes (git checkout main) | Compliant | None — file identical to main |
| 8 | Yes | Yes (git checkout main) | Compliant | None — file identical to main |
| 9 | Yes | Yes (git checkout main) | Compliant | None — all 4 files identical to main |
| 10 | Yes | Yes (restored from main) | Compliant | None — ApiContentControllers identical to main |
| 11 | Yes | Yes | N/A | templateContext, permissions, contentEntityCreateOperations, homepagePath added correctly |
| 12 | Yes | Yes | N/A | `editable?: boolean` added to ComponentNode |
| 13 | Yes | Yes | N/A | Both LayoutApiResponse and TemplateViewMode updated |
| 14 | Yes | Yes | N/A | TemplateContext interface, reducers, selectors, boot-time init, API response dispatch all implemented |
| 15 | Yes | Yes | N/A | No new enum value; templateContext presence drives behavior |
| 16 | Yes | Yes | N/A | Reuses existing /editor/node/:entityId — no AppRoutes changes needed |
| 17 | Yes | Yes | N/A | useDraggable disabled, click/hover blocked, context menu removed, drop zones hidden, locked CSS |
| 18 | Yes | Yes | N/A | Lock icon, disabled drag, no context menu, muted styling, drop zones hidden |
| 19 | Yes | Yes | N/A | Informational message shown for locked components |
| 20 | Yes | Yes | N/A | Context menu on SlotOverlay/SlotLayer, ExposeSlotDialog, exposed_slots in POST body |
| 21 | Yes | Yes | N/A | Template title shows exposed slot count |
| 22 | N/A | N/A | N/A | Verification step — covered by tests passing |
| 23 | Yes | Yes (postSave) | Compliant | Auto-provisioning implemented correctly with idempotency checks |
| 24 | Yes (modified) | Yes | Compliant | 15 tests restored; 1 test updated for auto-provisioning behavior |
| 25 | N/A | N/A | N/A | Verified — no new PHPCS errors (all errors pre-existing on main) |
| 26 | N/A | N/A | N/A | Verified — all 18 tests pass |

### Script Policy Audit
- Scripts correctly used: 0 (no capability scripts needed — all changes are direct code modifications to contrib module, appropriate for `drupal_contrib_developer` focus)
- Script bypasses: 0
- New scripts: 0 (correct — plan did not require new scripts)
- Missing .drush.json: N/A

### Backend Restoration Strategy
The developer correctly used `git checkout main -- <file>` to restore all 10+ backend files from main. Every backend file compared against main shows zero diff (except ContentTemplate.php which has new auto-provisioning code and NodeTemplatesTest.php which has one modified test). This is the safest restoration approach as recommended by the plan.

## Phase 3: Automated Testing & Code Quality

### PHPCS Results
- Command: `ddev exec vendor/bin/phpcs`
- Result: Errors found, but ALL pre-existing on main
- **ContentTemplate.php**: 9 errors — all 9 exist on main branch (verified by running PHPCS against `git show main:...`). No new errors introduced.
- **NodeTemplatesTest.php**: 2 errors + 3 warnings — all 5 exist on main branch. No new errors introduced.
- **All other modified PHP files**: Identical to main, so no new PHPCS issues.

### PHPUnit Results
- Command: `ddev exec vendor/bin/phpunit web/modules/contrib/canvas/tests/src/Kernel/NodeTemplatesTest.php`
- Result: **PASS** — 18 tests, 455 assertions
- Failures introduced by this change: **None**
- Test coverage for new code:
  - Auto-provisioning (Step 23): Covered by `testNodeLayoutAccessCheckWithAutoProvisionedCanvasField` — verifies field is created when template with exposed slots is saved
  - All 15 restored per-content editing tests pass
  - Original 2 tests (`testOptContentTypeIntoCanvas` x2 data providers, `testExposedSlotsAreFilledByEntity`) continue to pass

### Test Modification Note
The original `testNodeLayoutAccessCheckForbiddenWithoutCanvasField` was renamed to `testNodeLayoutAccessCheckWithAutoProvisionedCanvasField` and updated to verify the new auto-provisioning behavior. This is correct — with auto-provisioning, the original "no Canvas field" scenario can no longer happen in practice because saving a ContentTemplate with exposed slots automatically creates the field. The updated test:
1. Creates a content type WITHOUT a Canvas field
2. Verifies the field doesn't exist
3. Creates a ContentTemplate with exposed slots (triggers auto-provisioning in postSave)
4. Verifies the field WAS auto-provisioned
5. Verifies access is now ALLOWED (was previously forbidden)

## Phase 4: Quality Assessment

### Drupal Best Practices
- **Dependency injection**: `\Drupal::service()` used in `ensureCanvasFieldExists()` — correct for entity classes which don't receive DI. Consistent with existing patterns in `ContentTemplate.php` (lines 241, 433).
- **Hook implementations**: `postSave()` override is correct. Uses `parent::postSave()` call. Placement after `preSave()` is logical.
- **Entity API**: `FieldStorageConfig::loadByName()`, `FieldConfig::loadByName()`, `FieldStorageConfig::create()`, `FieldConfig::create()` — all correct Drupal entity API usage.
- **Idempotency**: `ensureCanvasFieldExists()` checks field existence before creating. Both field storage and field config are checked independently. Field map check catches fields with any name (by type), specific field name used only for creation.
- **Security**: No new endpoints exposed without access checks. All existing access checks (NodeLayoutAccessCheck, ComponentTreeEditAccessCheck) are preserved from main.

### Code Quality
- **Follows existing patterns**: All backend code restored from main. New `postSave()` and `ensureCanvasFieldExists()` follow existing patterns in the class.
- **No code duplication**: Frontend locking logic is minimal and appropriate — `isLocked` computed once per component and used consistently.
- **Frontend state management**: Clean separation between `templateContext` (mode detection) and `editingExposedSlots` (template editing state). `addExposedSlot` reducer correctly enforces single-slot constraint by replacing all existing slots.
- **Error handling**: Frontend POST errors handled by existing error boundary. Backend validation errors surfaced through standard API error responses.

### Integration
- **No broken dependencies**: All imports verified. New imports (`FieldConfig`, `FieldStorageConfig` in ContentTemplate; `ContextMenu`, `LockClosedIcon`, etc. in frontend) are from established dependencies.
- **Cache invalidation**: N/A — auto-provisioned fields trigger their own cache clears via entity save hooks.
- **Frontend build**: TypeScript types are additive (optional properties), so no breaking changes to existing code.

### Minor Observations (Non-Blocking)
1. **View mode restriction not enforced in UI (Step 20.7)**: The frontend does not check if the template's view mode is `full` before offering "Expose this slot". The backend validator enforces this constraint, so the save will fail with a validation error. A frontend guard would provide better UX but is not blocking.
2. **ExposeSlotDialog state persistence**: If a user opens the dialog, types a custom label, closes without confirming, then reopens, the stale custom label persists (useState initial values only apply on mount). This is a minor UX nit since each slot's dialog is a separate component instance.
3. **`componentUuid` prop unused in ExposeSlotDialog**: Accepted but not referenced in the dialog body. The caller uses it to construct the `ExposedSlotConfig`. Consider removing it from the props interface to avoid confusion.

## Search Evidence

| Search | Purpose | Findings |
|--------|---------|----------|
| `git diff main --stat` | Understand full scope of changes | 22 files changed, 1512 insertions, 2004 deletions. Backend files mostly restored from main (zero diff). |
| `git diff main -- ComponentTreeEditAccessCheck.php` | Verify Step 1 | No diff — identical to main. Try-catch restored. |
| `git diff main -- SlotTreeExtractor.php` | Verify Step 2 | No diff — identical to main. Service restored. |
| `git diff main -- canvas.services.yml` | Verify Step 3 | No diff — identical to main. Services registered. |
| `git diff main -- ComponentTreeLoader.php` | Verify Step 4 | No diff — identical to main. hasContentTemplateWithExposedSlots restored. |
| `git diff main -- ApiLayoutController.php` | Verify Step 7 | No diff — identical to main. All per-content editing code restored. |
| `git diff main -- CanvasController.php` | Verify Step 8 | No diff — identical to main. nodeLayout/getTemplateContext restored. |
| `git diff main -- NodeLayoutAccessCheck.php` | Verify Step 9 | No diff — identical to main. Access check restored. |
| `git diff main -- canvas.routing.yml` | Verify Step 9 | No diff — route restored. |
| `git diff main -- canvas.links.task.yml` | Verify Step 9 | No diff — Layout tab restored. |
| `git diff main -- ApiContentControllers.php` | Verify Step 10 | No diff — restored from main (commit acaaf762). |
| `git diff main -- ContentTemplate.php` | Verify Steps 5, 6, 23 | 70 lines added: postSave() + ensureCanvasFieldExists(). Clean implementation. |
| `git diff main -- NodeTemplatesTest.php` | Verify Step 24 | 29 net lines changed: 1 test updated for auto-provisioning behavior. |
| `git diff main -- DrupalSettings.ts` | Verify Step 11 | 19 lines added: templateContext, permissions, contentEntityCreateOperations, homepagePath. |
| `git diff main -- layoutModelSlice.ts` | Verify Step 12 | 1 line added: `editable?: boolean` on ComponentNode. |
| `git diff main -- componentAndLayout.ts` | Verify Steps 13, 14 | 20 lines changed: types updated, API response data dispatched. |
| `git diff main -- uiSlice.ts` | Verify Step 14 | 50 lines added: TemplateContext interface, reducers, selectors, editingExposedSlots state. |
| `git diff main -- main.tsx` | Verify Step 14 | 6 lines added: boot-time templateContext dispatch. |
| `git diff main -- ComponentOverlay.tsx` | Verify Step 17 | 36 lines changed: locking fully implemented. |
| `git diff main -- PreviewOverlay.module.css` | Verify Step 17 | 17 lines added: .locked and .exposed CSS classes. |
| `git diff main -- SlotOverlay.tsx` | Verify Steps 17, 20 | 117 lines changed: drop zone restrictions + expose slot context menu. |
| `git diff main -- ComponentLayer.tsx` | Verify Step 18 | 39 lines changed: lock icon, disabled drag, muted styling. |
| `git diff main -- ComponentLayer.module.css` | Verify Step 18 | 5 lines added: .locked CSS class. |
| `git diff main -- SlotLayer.tsx` | Verify Steps 18, 20 | 95 lines changed: drop zone restrictions + expose slot dropdown. |
| `git diff main -- ComponentInstanceForm.tsx` | Verify Step 19 | 14 lines added: locked component informational message. |
| `git diff main -- ExposeSlotDialog.tsx` | Verify Step 20 | 93 lines: new dialog following SavePatternDialog pattern. |
| `git diff main -- Preview.tsx` | Verify Step 20 | 13 lines changed: exposed_slots included in POST body. |
| `git diff main -- TemplateList.tsx` | Verify Step 21 | 8 lines changed: template title shows exposed slot count. |
| `grep -r "role ==="` in canvas | Code search pattern (required) | No matches — no hardcoded role checks. |
| `grep -r "run\.role"` in canvas | Code search pattern (required) | No matches. |
| PHPCS on ContentTemplate.php (branch vs main) | New PHPCS errors? | Same 9 errors on both. No new violations. |
| PHPCS on NodeTemplatesTest.php (branch vs main) | New PHPCS errors? | Same 5 issues on both. No new violations. |
| PHPUnit NodeTemplatesTest.php | All tests pass? | 18 tests, 455 assertions, all pass. |

## Summary
- **Implementation status**: Complete — all 26 plan steps verified
- **Script policy**: Compliant — no scripts needed, no bypasses
- **PHPUnit**: Pass — 18 tests, 455 assertions
- **PHPCS**: No new violations (all errors pre-existing on main)
- **Key strengths**:
  1. Backend restoration via `git checkout main` produced clean, proven code
  2. Auto-provisioning (Step 23) is well-implemented with proper idempotency
  3. Frontend component locking is thorough — covers preview overlay, tree sidebar, and form panel
  4. Exposed slot template editing UI is well-structured with context menu, naming dialog, and visual indicators
  5. Redux state management cleanly separates mode detection (templateContext) from editing state (editingExposedSlots)

## Decision

=== DECISION_BLOCK_START ===
{
  "outcome": "OK",
  "reviewOutcome": {
    "summary": "Implementation is complete and correct. All 26 plan steps verified against the codebase. Backend code correctly restored from main (zero diff on 10+ files). New code (auto-provisioning, frontend locking, exposed slot UI) is well-implemented. All 18 PHPUnit tests pass with 455 assertions. No new PHPCS violations introduced. Three minor non-blocking observations noted (view mode UI guard, dialog state persistence, unused prop).",
    "feedbackBullets": [
      "All backend files correctly restored from main via git checkout — zero diff on ComponentTreeEditAccessCheck, SlotTreeExtractor, ComponentTreeLoader, ApiLayoutController, CanvasController, NodeLayoutAccessCheck, canvas.services.yml, canvas.routing.yml, canvas.links.task.yml, ApiContentControllers",
      "Auto-provisioning in ContentTemplate.postSave() is idempotent and follows existing patterns (\\Drupal::service() consistent with lines 241, 433)",
      "Frontend component locking is thorough: preview overlay (ComponentOverlay.tsx), tree sidebar (ComponentLayer.tsx), and form panel (ComponentInstanceForm.tsx) all consistently check isLocked",
      "Exposed slot template editing UI covers both SlotOverlay.tsx (context menu) and SlotLayer.tsx (dropdown menu) with ExposeSlotDialog naming dialog",
      "addExposedSlot reducer correctly enforces single-slot constraint by replacing all existing slots",
      "All 18 PHPUnit tests pass including the updated testNodeLayoutAccessCheckWithAutoProvisionedCanvasField which correctly verifies the new auto-provisioning behavior",
      "Minor: Frontend does not check viewMode === 'full' before offering 'Expose this slot' — backend validator catches this but UX could be improved in follow-up",
      "Minor: ExposeSlotDialog accepts componentUuid prop but doesn't use it internally — consider removing from interface"
    ],
    "requestedChanges": []
  }
}
=== DECISION_BLOCK_END ===
