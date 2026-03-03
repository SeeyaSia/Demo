# Architecture Review: ATWEB-9 — Canvas UI/UX Per-Content Editing via Exposed Slots

## Phase 1: Plan Completeness Review

### Step Precision

| Step | Script Reference | Precision | Issues |
|------|-----------------|-----------|--------|
| 1 | N/A (code change) | OK | Accurately describes restoring try-catch in `ComponentTreeEditAccessCheck.php`. Verified against `git diff main`. |
| 2 | N/A (restore from main) | OK | `SlotTreeExtractor.php` description matches original code from main exactly (93 lines). UUID-based filtering approach is correct. |
| 3 | N/A (services.yml modification) | OK | Autowired service registration `{}` is the correct pattern for this codebase. Placement after `ComponentTreeLoader` is logical. |
| 4 | N/A (code change) | OK | `hasContentTemplateWithExposedSlots()` description matches main. `getCanvasFieldName()` scope expansion is well-justified with clear access control layering. |
| 5 | N/A (restore from main) | OK | `getMergedComponentTree()` description matches main diff exactly. Suggestion to refactor `build()` to call it is sound but optional for this ticket. |
| 6 | N/A (code change) | OK | Adding `'exposedSlots' => $this->getExposedSlots()` to `normalizeForClientSide()` at line 467 — verified this exact line in the current file. Correct location. |
| 7 | N/A (restore from main) | OK | All 7 sub-items (dependency, get, patch, post/updateEntity, buildPreviewRenderable, buildLayoutAndModel, getPerContentTemplate) accurately match the 223-line diff from main. |
| 8 | N/A (restore from main) | OK | `nodeLayout()`, `getTemplateContext()`, `templateContext` in drupalSettings, and `bundle` in create links all verified against main diff. |
| 9 | N/A (restore + route/service) | OK | `NodeLayoutAccessCheck` description matches 50-line original exactly. `_custom_access` pattern is correct (class doesn't implement `AccessInterface`). Weight 20 matches main. Route definition matches main exactly. |
| 10 | N/A (scope decision) | OK | Well-justified scope exclusion with clear architectural boundary. References to drupal.org issues are appropriate. |
| 11 | N/A (frontend type change) | OK | `DrupalSettings.ts` additions match the PHP output at CanvasController.php lines 193-204. The `templateContext` type shape matches `getTemplateContext()` return value. |
| 12 | N/A (frontend type change) | OK | `ComponentNode` interface verified at line 51-56. Adding `editable?: boolean` is correct and matches API response annotation in Step 7. |
| 13 | N/A (frontend type change) | OK | `LayoutApiResponse` at line 28 and `TemplateViewMode` at line 36 verified. Additions match backend response shapes. |
| 14 | N/A (frontend state) | OK | `uiSlice.ts` extension is well-described with clear two-phase data flow (boot → API response). Separation of `templateContext` (mode detection) vs `ComponentNode.editable` (per-component locking) is architecturally sound. |
| 15 | N/A (frontend design decision) | OK | Extending `ENTITY` context rather than adding new enum value is the right approach — minimal change, behavior driven by `templateContext` selector. |
| 16 | N/A (frontend routing) | OK | Analysis of how `CanvasController.__invoke()` sets `base` (line 172-177) correctly concludes existing `/editor/node/:entityId` route should work. Fallback to dedicated route is prudent. |
| 17 | N/A (frontend component locking) | OK | File references verified: `ComponentOverlay.tsx` (257 lines), `useDraggable` at line 91, `handleComponentClick` at line 132, `handleItemMouseOver` at line 137. All line references accurate. |
| 18 | N/A (frontend tree sidebar locking) | OK | File references verified: `ComponentLayer.tsx` (232 lines), `useDraggable` at line 64, `handleItemClick` at line 73, `SidebarNode draggable={true}` at line 148, `ComponentContextMenu` at line 135. All line references accurate. |
| 19 | N/A (frontend form panel) | OK | Fallback approach (prevent selection in Steps 17-18) is the right primary mechanism, with informational message as safety net. |
| 20 | N/A (frontend exposed slot UI) | OK | 10 specific files listed with line counts verified (all match actual file sizes within 1 line). Pattern references (`SavePatternDialog.tsx`, `ComponentContextMenu.tsx`, `Dialog.tsx`) are appropriate. `postTemplateLayout` mutation at line 181 verified. |
| 21 | N/A (frontend template list) | OK | `TemplateList.tsx` (230 lines) exists. `TemplateViewMode` type (Step 13) provides `exposedSlots` data. |
| 22 | N/A (verification) | OK | Verification criteria are comprehensive. |
| 23 | N/A (backend auto-provisioning) | OK | Using `postSave()` is safer than `preSave()` — the plan correctly explains why. Note: `postSave()` would be a new override method (only `preSave()` currently exists at line 152). |
| 24 | N/A (test restoration) | OK | All 15 deleted test method names verified against `git diff main`. Count matches exactly. |
| 25 | N/A (PHPCS verification) | OK | Correctly notes PHPCS scope limitation for contrib modules. |
| 26 | N/A (test execution) | OK | Test count arithmetic correct (2 existing + 15 restored = 17). |

### Coverage Assessment

- **Ticket requirements addressed**: All 5 work streams covered
  - Work Stream 1 (TypeScript types/plumbing): Steps 11-14
  - Work Stream 2 (Per-content editor mode): Steps 15-19
  - Work Stream 3 (Exposed slot UI): Step 20
  - Work Stream 4 (Navigation/entry points): Steps 9, 16, 21, 22
  - Work Stream 5 (Canvas field auto-provisioning): Step 23
  - Backend restoration: Steps 1-10, 24-26

- **Missing requirements**: None. The plan explicitly documents the scope exclusion for `ApiContentControllers` (Step 10) with sound justification.

- **Edge cases**:
  - Single exposed slot constraint: Covered (Step 20.6)
  - Full view mode only: Covered (Step 20.7)
  - Translation implications: Flagged as known gap (appropriate)
  - Non-eligible entity access: Covered (Step 1 try-catch)
  - Field auto-provisioning idempotency: Covered (Step 23 "only create if doesn't exist")

### Script Catalog Verification

- Scripts found in capabilities/: 9
- Scripts correctly referenced in plan: 1 (`update-article-content-template.drush.php`)
- Scripts missed by architect: 0 — the other 8 scripts are correctly classified as diagnostic/reference/not relevant
- New scripts required: None — all changes are direct code modifications to the contrib module, which is appropriate for this `drupal_contrib_developer` focus

## Phase 2: Codebase Alignment Review

### Search Evidence

| Search | Purpose | Findings |
|--------|---------|----------|
| `git diff main -- SlotTreeExtractor.php` | Verify original code | 93 lines, UUID-based filtering. Plan description matches exactly. |
| `git diff main -- NodeLayoutAccessCheck.php` | Verify original code | 50 lines, plain class (no `AccessInterface`). Plan matches. |
| `git diff main -- ComponentTreeEditAccessCheck.php` | Verify try-catch removal | Exactly 6 lines removed: try/catch block around `load()`. Plan matches. |
| `git diff main -- ComponentTreeLoader.php` | Verify `hasContentTemplateWithExposedSlots` removal | Method removed, `getCanvasFieldName()` reverted to test-only guard. Plan matches. |
| `git diff main -- ContentTemplate.php` | Verify `getMergedComponentTree` removal + `exposedSlots` removal | Both confirmed removed. `build()` has inlined tree injection. Plan matches. |
| `git diff main -- ApiLayoutController.php` | Verify all per-content editing code removal | 223-line diff confirms removal of: SlotTreeExtractor dep, get() per-content path, patch() guard, post() exposed_slots, buildPreviewRenderable() per-content, buildLayoutAndModel() per-content, getPerContentTemplate(), updateEntity() slot extraction. Plan covers all. |
| `git diff main -- CanvasController.php` | Verify nodeLayout, getTemplateContext, templateContext, bundle removal | All confirmed removed. Plan matches. |
| `git diff main -- canvas.routing.yml` | Verify canvas.node.layout route removal | Route removed at line 553. Plan's restoration matches original exactly. |
| `git diff main -- canvas.links.task.yml` | Verify Layout tab removal | `canvas.entity.node.layout` with weight 20 removed. Plan matches. |
| `git diff main -- canvas.services.yml` | Verify service removal | SlotTreeExtractor and NodeLayoutAccessCheck services not present in current branch. |
| `git diff main -- NodeTemplatesTest.php` | Verify test deletion | 15 test methods (1006 lines) deleted. Plan lists all 15 by name. |
| `wc -l ComponentOverlay.tsx` | Verify line count | 257 lines. Matches plan. |
| `wc -l SlotOverlay.tsx` | Verify line count | 179 lines. Matches plan. |
| `wc -l ComponentLayer.tsx` | Verify line count | 232 lines. Matches plan. |
| `wc -l SlotLayer.tsx` | Verify line count | 150 lines. Matches plan. |
| `wc -l componentAndLayout.ts` | Verify line count | 490 lines. Matches plan. |
| `wc -l SidebarNode.tsx` | Verify line count | 162 lines. Plan says 163 (off by 1, trivial). |
| Read `DrupalSettings.ts` | Verify missing types | Confirmed: `templateContext`, `permissions`, `contentEntityCreateOperations`, `homepagePath` all absent. |
| Read `ComponentNode` interface | Verify missing `editable` | Confirmed at lines 51-56: no `editable` property. |
| Read `LayoutApiResponse` type | Verify missing per-content fields | Confirmed at line 28: no `exposedSlots` or `contentTemplateId`. |
| Read `TemplateViewMode` type | Verify missing `exposedSlots` | Confirmed at line 36: no `exposedSlots`. |
| Read `EditorFrameContext` enum | Verify current values | `ENTITY`, `TEMPLATE`, `NONE` at lines 38-42. No `NODE_LAYOUT` needed. |
| Read `AppRoutes.tsx` | Verify routing structure | `/editor/:entityType/:entityId` at line 105 passes `EditorFrameContext.ENTITY`. Reusable for per-content editing. |
| Read `CanvasController.__invoke()` | Verify `base` path logic | Line 172: `base` set from `canvas.boot.entity` route — maps to `/editor/{entity_type}/{entity_id}` which the React router handles. |
| `injectSubTreeItemList` and `getComponentTreeItemByUuid` | Verify methods exist | Both present in `ComponentTreeItemList.php` (lines 151 and 525). |
| `loadForEntity` | Verify method exists | Present at line 133 of `ContentTemplate.php`. |
| `postSave` vs `preSave` | Check existing hooks | Only `preSave()` exists (line 152). `postSave()` would be new. |

### Reusability Findings

- **Existing code leveraged correctly:**
  - `ContentTemplate::loadForEntity()` (line 133) — used in `getPerContentTemplate()` and `hasContentTemplateWithExposedSlots()`
  - `ComponentTreeItemList::injectSubTreeItemList()` (line 525) — used in `getMergedComponentTree()` and `build()`
  - `ComponentTreeItemList::getComponentTreeItemByUuid()` (line 151) — used for `editable` flag annotation
  - `SavePatternDialog.tsx` (165 lines) — correctly identified as pattern for `ExposeSlotDialog.tsx`
  - `ComponentContextMenu.tsx` (244 lines) — correctly identified as pattern for slot context menu
  - `Dialog.tsx` (195 lines) — correctly identified as base component for the dialog

- **Unnecessary new code proposed:** None. The plan restores previously-working code from main and adds only genuinely new frontend code.

### Integration Concerns

1. **No conflicts detected.** The branch reverted ATWEB-7 changes cleanly, so restoring them via `git checkout main -- <file>` should produce no merge conflicts with the current branch state.

2. **canvas.services.yml ordering:** The plan places `SlotTreeExtractor` after `ComponentTreeLoader` (line 68) and `NodeLayoutAccessCheck` after `AuthenticationAccessChecker` (line 147). These locations are appropriate and match the original code's placement on main.

3. **React routing for `/node/{node}/layout`:** The `CanvasController::nodeLayout()` method calls `$this->__invoke('node', $node)`, which sets the `base` drupalSettings to the `canvas.boot.entity` internal path. The React router's `basename` is set from `basePath` which comes from this `base` setting. Since the React router uses `basename` for path prefix resolution, and `/editor/node/:entityId` exists as a route, the per-content editing should work through the existing route. The plan's analysis here is correct.

4. **`build()` duplication with `getMergedComponentTree()`:** The plan notes (Step 5) that after restoring `getMergedComponentTree()`, the `build()` method should call it to eliminate duplication. The current `build()` inlines the same logic. This refactoring is optional for this ticket but is a good follow-up suggestion.

### Better Approaches

No fundamentally better approaches found. The plan's strategy of:
1. Restoring backend code from main via `git checkout main -- <file>`
2. Adding new frontend code for the previously-unimplemented UI features
3. Using the existing `ENTITY` context extended with `templateContext` rather than adding a new enum value

...is sound and minimizes risk. The `git checkout main` approach for Steps 1-9 is significantly safer than manual re-implementation.

## Summary

- **Plan quality**: Strong
- **Key observations**:
  1. The plan is exceptionally thorough — every file reference, line number, and code snippet was verified against the actual codebase and all are accurate
  2. The `git checkout main -- <file>` restoration strategy for Steps 1-9 is the right approach and minimizes the risk of re-implementation errors
  3. Frontend steps (11-21) provide specific, actionable file references with verified line numbers, making implementation straightforward
  4. The scope decision for `ApiContentControllers` (Step 10) is well-justified and properly documented
  5. The two-phase data flow design (boot `templateContext` → API response `editable` flags) is architecturally clean
  6. Step 23 (auto-provisioning) correctly identifies `postSave()` as safer than `preSave()`, though it should note this creates a new override method
  7. Test restoration (Step 24) with all 15 test methods provides comprehensive coverage of the backend changes

## Decision

=== DECISION_BLOCK_START ===
{
  "outcome": "OK",
  "reviewOutcome": {
    "summary": "Plan is complete, precise, and technically feasible. All 26 steps verified against the actual codebase — file references, line numbers, code snippets, and architectural decisions are accurate. The plan correctly restores backend per-content editing code from main (Steps 1-9) and adds well-specified frontend UI work (Steps 11-21). Capability script catalog is complete. No missed reusability opportunities or integration conflicts detected.",
    "feedbackBullets": [
      "All file references and line numbers verified against codebase — accuracy is exceptional across all 26 steps",
      "git checkout main restoration strategy for Steps 1-9 is the safest approach for re-implementing the reverted ATWEB-7 backend code",
      "Two-phase data flow (boot templateContext + API editable flags) is architecturally clean with good separation of concerns",
      "Scope decision for ApiContentControllers (Step 10) is well-justified — per-content editing via Layout tab does not require canvas_page-style CRUD operations",
      "Step 23 auto-provisioning: postSave() creates a new override method (only preSave exists at line 152) — this is fine but implementers should be aware",
      "Step 5 suggestion to refactor build() to call getMergedComponentTree() is good but optional for this ticket — avoid scope creep",
      "Frontend Step 20 provides 10 specific file references with line counts — all verified within 1 line of actual values",
      "Test restoration (Step 24) covers all 15 deleted test methods verified against git diff main — provides comprehensive backend coverage"
    ],
    "requestedChanges": []
  }
}
=== DECISION_BLOCK_END ===
