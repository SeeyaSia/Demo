# ATWEB-7: Full Canvas Solution — Architecture Review (Iteration 1 of 2)

## Code Search Patterns — Results

### Critical Searches
| Pattern | Files Found | Relevance |
|---------|------------|-----------|
| `grep -r "role ==="` | 2 files (core only: `RendererBubblingTest.php`, `UserRolesCacheContext.php`) | Not relevant — Drupal core internals only |
| `grep -r "run\.role"` | 0 files | Not relevant to this codebase |
| `grep -r "roleEmoji"` | 0 files | Not relevant to this codebase |
| `grep -r "roleTitle"` | 0 files | Not relevant to this codebase |

### Important Searches
| Pattern | Files Found | Relevance |
|---------|------------|-----------|
| `grep -r "DecisionValidator\|validateDecision"` | 0 files | Not relevant to this codebase |
| `grep -r "artifactOutputs\|artifactNeeds"` | 0 files | Not relevant to this codebase |

**Note**: These search patterns appear to be from the review orchestration framework and are not relevant to the Drupal Canvas module codebase being reviewed.

---

## Plan Completeness Review

### Step Precision

| Step | Script Reference | Precision | Issues |
|------|-----------------|-----------|--------|
| 1 | N/A (direct code edit) | OK | Verified — `ComponentTreeLoader.php` lines 55-71 match exactly. Restriction at lines 57-61 confirmed. `drupal_valid_test_ua()` gate confirmed at line 58. |
| 2 | N/A (direct code edit) | OK | Verified — `ComponentTreeEditAccessCheck.php` line 36 calls `$this->componentTreeLoader->load($entity)` which triggers `getCanvasFieldName()`. Exception propagation confirmed. |
| 3 | N/A (direct code edit) | OK | Verified — routing lines 226-258 confirmed hardcoded `canvas_page`. `ApiContentControllers.php` line 132 hardcoded check confirmed. `getUrlFromRoute()` lines 427-440 match() closure at lines 428-431 confirmed. **Minor issue**: `getAllContentEntityCreateLinks()` line 383 `@todo` references issue #3513566, plan references this correctly. |
| 4 | N/A (direct code edit) | OK | Verified — `normalizeForClientSide()` lines 436-476 confirmed, `exposedSlots` absent from values array. `getExposedSlots()` line 222 confirmed returns proper array. |
| 5 | N/A (direct code edit) | OK | Verified — `updateEntity()` lines 556-579 confirmed: ContentTemplate branch only calls `setComponentTree()`, no `exposed_slots` handling. `post()` destructuring at lines 312-317 confirmed. |
| 6 | N/A (direct code edit + new file) | OK | Verified — `convert()` lines 48-62 confirmed. Line 55 `componentTreeLoader->load($entity)` confirmed. Line 61 `self::convertClientToServer($layout['components'], $model, ...)` iterates components, not model — implicit filtering claim verified. Assertions at lines 57-59 confirmed. |
| 6b | N/A (direct code edit) | OK | Verified — `patch()` lines 262-266: `getAutoSavedVersionIfAvailable` then `getEntityWithComponentInstance`. Line 503 `getEntityWithComponentInstance` confirmed — throws `NotFoundHttpException` at line 510 if component not found. Ordering fix (template check BEFORE search) is critical and correctly identified. |
| 6c | Verification step | OK | Well-specified verification of auto-save round-trip behavior. No code changes needed beyond Step 7. |
| 7 | N/A (direct code edit) | OK | Verified — `get()` lines 80-127 confirmed. Line 92 `componentTreeLoader->load($entity)` confirmed. `ContentTemplate::build()` lines 350-373 merge logic confirmed — plan correctly proposes extracting to `getMergedComponentTree()`. Refactoring `build()` to use the new method eliminates duplication. |
| 7b | N/A (direct code edit) | OK | Verified — `buildPreviewRenderable()` lines 379-396 confirmed. Line 383 `componentTreeLoader->load($entity)->toRenderable($entity, isPreview: TRUE)` for non-ContentTemplate. `buildLayoutAndModel()` lines 430-444 confirmed. Line 433 `componentTreeLoader->load($entity)` confirmed. |
| 8 | N/A (direct code edit) | OK | Verified — `CanvasController::__invoke()` lines 101-237. Line 126 node bundle iteration confirmed. `drupalSettings.canvas` at lines 170-205 confirmed — no `templateContext` currently. |
| 9 | N/A (direct code edit + new files) | **Has Issue** | Return type mismatch: plan shows `nodeLayout(NodeInterface $node): array` but `__invoke()` returns `HtmlResponse`. Should be `nodeLayout(NodeInterface $node): HtmlResponse`. See detailed issue below. |
| 10 | `ddev drush field:create` | OK | Correctly uses `ddev drush field:create` tool. Field type `component_tree` verified against `ComponentTreeItem::PLUGIN_ID`. Integration concern about `getAllContentEntityCreateLinks()` auto-discovery correctly noted. |
| 11 | Requires new script: `update-article-content-template.drush.php` | OK | Existing template config verified at `config/alchemizetechwebsite/canvas.content_template.node.article.full.yml` — 3 components, `exposed_slots: {}`, `status: true`. Plan correctly identifies need to update (not create). `injectSubTreeItemList()` line 536-538 throws if slot not empty — plan correctly addresses with "Option A" (empty exposed slot). |
| 12 | N/A (tests) | OK | Existing `NodeTemplatesTest.php` verified. `testExposedSlotsAreFilledByEntity()` already tests exposed slot rendering (through view builder, gated by `drupal_valid_test_ua()`). Test extension plan is comprehensive. |
| 13 | `ddev drush cex -y` / `cim -y` | OK | Standard config export/import verification. |
| 14 | `ddev exec vendor/bin/phpcs` / `phpcbf` | OK | Standard code quality check. |

### Coverage Assessment
- **Ticket requirements addressed**: All — the 10 blockers/gaps from the ticket are systematically addressed across the 4 work streams.
- **Missing requirements**: None identified — all ticket requirements covered.
- **Edge cases**: Well-covered — disabled template, no ContentTemplate, no exposed slots, auto-save round-trips, PATCH ordering, model data implicit filtering, `ComponentTreeEntityInterface` entities, and validation edge cases.

### Script Catalog Verification
- **Scripts found in capabilities/**: 10 (filesystem count)
- **Scripts listed in plan catalog**: 8 (plan count)
- **Scripts missed by architect**: `canvas-build-projects-page.drush.php` and `create-project-type.drush.php` are not listed in the plan's catalog. Neither is relevant to this ticket, so this is a minor completeness gap.
- **New script proposed**: `update-article-content-template.drush.php` — correctly identified as needed, existing scripts don't cover ContentTemplate modification with exposed slots.

---

## Codebase Alignment Review

### Reusability Findings

| Existing Code | Plan Leverages It? | Notes |
|--------------|-------------------|-------|
| `ContentTemplate::loadForEntity()` (line 133) | Yes — Step 1, 7, 8, 9 | Correctly used for template discovery |
| `ContentTemplate::getExposedSlots()` (line 222) | Yes — Steps 4, 7, 8 | Correctly used without transformation |
| `ComponentTreeItemList::injectSubTreeItemList()` (line 525) | Yes — Step 7 `getMergedComponentTree()` | Correctly delegates to existing merge infrastructure |
| `ComponentTreeItemList::getComponentTreeItemByUuid()` | Yes — Steps 6b, 7 | Correctly used for UUID lookup |
| `CanvasFieldCreationTrait` | Indirectly — Step 10 uses `drush field:create` instead | Test trait used in `NodeTemplatesTest` for programmatic field creation; plan uses Drush CLI which is correct for project integration |
| `canvas.services.yml` `_defaults: autoconfigure/autowire` | Yes — Step 9 | Correctly leverages existing pattern for service registration |

- **Unnecessary new code proposed**: None identified. The `SlotTreeExtractor` service is genuinely needed — no existing utility performs slot tree extraction from a merged tree. The extraction algorithm (template UUID comparison) is distinct from `injectSubTreeItemList()` which handles the merge, not the extraction.

### Integration Concerns

1. **`ComponentAudit.php` (line 160)**: Plan correctly identifies this as a non-blocker (deprecation notice, not exception). After loader relaxation, node entities will trigger `E_USER_DEPRECATED` in auto-save audit. This is acceptable for MVP but should be documented as a follow-up.

2. **`ContentTemplateHooks::entityFormDisplayAlter()`**: Plan Step 10 correctly notes this hook will remove the `published` field from Article edit forms on Canvas routes. This is the intended behavior and the plan calls for verification.

3. **`getAllContentEntityCreateLinks()` (CanvasController.php line 355-397)**: Plan correctly identifies that this auto-discovers entities with `component_tree` fields. After Step 10 adds the field to Article, `node:article` will appear in create links. The dependency order (Step 3 before Step 10) is correctly enforced.

4. **Potential issue — `ApiContentControllers::post()` line 92**: Currently creates entities with `'title' => static::defaultTitle(...)` and `'status' => FALSE`. Plan Step 3 point 7 correctly identifies the bundle key requirement for node entities. However, note that node entities also typically require a `uid` field. The plan should verify whether `$new->save()` at line 96 requires `uid` to be set, or if it defaults to the current user. In Drupal core, `NodeAccessControlHandler::createAccess()` typically requires an authenticated user. The plan should add a note about ensuring `uid` is set during node creation.

5. **`normalize()` method in `ApiContentControllers` (line 198)**: This method calls `$content_entity->toUrl()` at line 199. For node entities, this should work fine (`entity.node.canonical`). No issue expected.

### Better Approaches

1. **Step 9 `nodeLayout()` return type**: The plan specifies `nodeLayout(NodeInterface $node): array` but `CanvasController::__invoke()` returns `HtmlResponse`. The return type should be `HtmlResponse`. This is a precision error that would cause a PHP type error at runtime.

2. **Step 9 alternative — refactoring**: The plan considers and correctly rejects `RedirectResponse` (visible redirect). The "refactor into `renderEditor()`" alternative is mentioned but the plan settles on directly calling `__invoke()`. Given `__invoke()` already accepts nullable `$entity_type` and `$entity` parameters, direct delegation is the simplest approach. The plan should just fix the return type.

3. **Step 6 `SlotTreeExtractor` namespace**: The plan places it in `Storage\SlotTreeExtractor`. Consider whether `Service\SlotTreeExtractor` or just `SlotTreeExtractor` (root namespace) would be more appropriate. `Storage` namespace in Canvas is used for `ComponentTreeLoader` which handles entity field access. `SlotTreeExtractor` operates on tree data structures, not storage. However, this is a minor naming concern and `Storage` is acceptable since it relates to how data is stored per-entity.

4. **Step 7 `getPerContentTemplate()` helper visibility**: The plan defines this as `private` on `ApiLayoutController`. Since `hasContentTemplateWithExposedSlots()` is added to `ComponentTreeLoader` (Step 1) and `getTemplateContext()` is added to `CanvasController` (Step 8), there are now 3 places with per-content editing detection logic. The plan correctly notes this concern and addresses it by having each context use appropriate but consistent checks. However, a shared utility method on `ContentTemplate` itself (e.g., `ContentTemplate::hasExposedSlotsForEntity()`) would reduce duplication further. This is a design preference, not a blocker.

---

## Detailed Issue: Step 9 Return Type Mismatch

**File**: `web/modules/contrib/canvas/src/Controller/CanvasController.php`
**Method**: `__invoke()` at line 101 returns `HtmlResponse`
**Plan states**: `nodeLayout(NodeInterface $node): array`
**Should be**: `nodeLayout(NodeInterface $node): HtmlResponse`

The plan's code snippet at Step 9:
```php
public function nodeLayout(NodeInterface $node): array {
  return $this->__invoke('node', $node);
}
```

Must be:
```php
public function nodeLayout(NodeInterface $node): HtmlResponse {
  return $this->__invoke('node', $node);
}
```

This is a precision error that would cause a PHP type error at runtime. The fix is trivial but important for implementation correctness.

---

## Detailed Issue: Missing `uid` in Node Creation (Step 3)

**File**: `web/modules/contrib/canvas/src/Controller/ApiContentControllers.php`
**Method**: `post()` at line 92

When creating node entities via the Canvas API, the `create()` call sets `title` and `status` but not `uid`. While Drupal core's `Node::preCreate()` defaults `uid` to the current user, the plan should explicitly verify this works in the Canvas API context (where authentication may use OAuth tokens). If the user is not set, `$new->save()` may fail or create orphaned content.

This is a verification item, not a plan change — but it should be noted in Step 3's implementation.

---

## Summary
- **Plan quality**: Strong — comprehensive, well-researched, with accurate code references verified against the actual codebase
- **Key issues**:
  1. **Step 9 return type mismatch** (`array` vs `HtmlResponse`) — must be fixed before implementation
  2. **Capability script catalog incomplete** — 2 scripts missing from catalog (minor, not relevant to ticket)
  3. **Node creation `uid` verification** — Step 3 should explicitly verify `uid` is set correctly when creating node entities via Canvas API

---

## Decision

=== DECISION_BLOCK_START ===
{
  "outcome": "NEEDS_INFO",
  "reviewOutcome": {
    "summary": "Plan is comprehensive and technically well-aligned with the codebase. One precision error (Step 9 return type mismatch) must be fixed. Two minor items should be addressed.",
    "feedbackBullets": [
      "All 14 steps verified against actual codebase — code references (file paths, line numbers, method signatures) are accurate",
      "Step 1 (ComponentTreeLoader restriction) correctly identifies the gate at lines 57-61 and proposes surgical relaxation with ContentTemplate check",
      "Step 2 (access checker) correctly identifies the secondary gate at ComponentTreeEditAccessCheck line 36",
      "Step 3 (CRUD route generalization) correctly maps all three routes and proposes consistent parameter naming with existing layout routes",
      "Steps 4-5 (exposed_slots API surface) correctly identify the gap in normalizeForClientSide() and updateEntity()",
      "Steps 6-6c (slot-aware save) correctly identify that model filtering is handled implicitly by convertClientToServer() iterating layout components",
      "Step 6b PATCH ordering fix (template check BEFORE getEntityWithComponentInstance) is critical and correctly placed",
      "Step 7 getMergedComponentTree() extraction from ContentTemplate::build() eliminates merge logic duplication",
      "Step 7b buildPreviewRenderable() and buildLayoutAndModel() fixes are essential — without them, previews and PATCH responses would be broken for per-content editing",
      "Step 9 has a return type mismatch: nodeLayout() returns 'array' but __invoke() returns HtmlResponse",
      "Step 11 correctly handles the injectSubTreeItemList() requirement that exposed slots must be empty in the template tree",
      "Capability script catalog lists 8 scripts but filesystem has 10 (canvas-build-projects-page.drush.php and create-project-type.drush.php are missing from catalog)",
      "Architectural decision (frontend sends full merged tree, backend extracts slot subtree) is sound and well-justified",
      "Work stream dependency order (WS1→WS2→WS3→WS4) is correct and enforces the critical Step 3 before Step 10 dependency"
    ],
    "requestedChanges": [
      "Fix Step 9 return type: change 'nodeLayout(NodeInterface $node): array' to 'nodeLayout(NodeInterface $node): HtmlResponse' — __invoke() returns HtmlResponse, not array",
      "Add canvas-build-projects-page.drush.php and create-project-type.drush.php to the capability script catalog (they exist on filesystem but are not listed)",
      "Add a verification note to Step 3 point 7 (post() method) to explicitly confirm that node entity creation via Canvas API correctly sets 'uid' from the authenticated user, especially in OAuth contexts"
    ]
  }
}
=== DECISION_BLOCK_END ===
