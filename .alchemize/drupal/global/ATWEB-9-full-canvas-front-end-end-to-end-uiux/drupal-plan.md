# ATWEB-9: Canvas UI/UX — Per-Content Editing via Exposed Slots

## Ticket Analysis

- **Core request**: Enable the Canvas React frontend to support per-content editing — where site builders expose slots on ContentTemplates and content editors edit content within those slots. This includes all 5 work streams: TypeScript types/plumbing, per-content editor mode, exposed slot UI in template editor, navigation/entry points, and Canvas field auto-provisioning.
- **Affected areas**: Canvas contrib module (`web/modules/contrib/canvas/`) — both PHP backend and React frontend (`ui/` directory). Specifically:
  - Backend: `CanvasController`, `ApiLayoutController`, `ComponentTreeLoader`, `ContentTemplate`, `SlotTreeExtractor` (new), `NodeLayoutAccessCheck` (new), `ComponentTreeEditAccessCheck` (fix), routing, services
  - Frontend: TypeScript types, Redux slices, React routes, Editor components, Template editor UI, Tree sidebar, Preview overlay, API services

## Critical Discovery: Branch State

The current branch (`alchemize/atweb-9-...`) has **reverted** the ATWEB-7 backend per-content editing changes. The git diff shows:
- `SlotTreeExtractor.php` — **deleted** (needs to be re-created)
- `NodeLayoutAccessCheck.php` — **deleted** (needs to be re-created)
- `ApiLayoutController.php` — `getPerContentTemplate()`, per-content `get()`, `patch()` guard, `updateEntity()` slot extraction, and `buildPreviewRenderable()` per-content path all **removed**
- `CanvasController.php` — `nodeLayout()`, `getTemplateContext()`, and `templateContext` in drupalSettings all **removed**
- `ComponentTreeLoader.php` — `hasContentTemplateWithExposedSlots()` **removed**
- `ContentTemplate.php` — `getMergedComponentTree()` **removed**, `normalizeForClientSide()` no longer includes `exposedSlots`
- `ComponentTreeEditAccessCheck.php` — **try-catch around `load()` removed** (critical regression)
- `canvas.routing.yml` — `canvas.node.layout` route **removed**, `ApiContentControllers` routes reverted to canvas_page-only
- `canvas.services.yml` — `SlotTreeExtractor` and `NodeLayoutAccessCheck` services **removed**
- `canvas.links.task.yml` — `canvas.entity.node.layout` task link **removed**
- `ApiContentControllers.php` — bundle-aware create/delete/list routes **reverted** to canvas_page-only
- `NodeTemplatesTest.php` — **~1000 lines of per-content editing tests deleted** (15 test methods)

**Implication**: Steps 1-10 below re-implement the backend changes that were previously working and tested in ATWEB-7. Steps 11+ are new frontend work.

**Recommended approach**: Use `git checkout main -- <file>` to restore original files where possible, then apply only net-new changes. This is safer than manual re-implementation.

## Capability Script Catalog

| Script | Purpose | Parameters | Relevant |
|--------|---------|------------|----------|
| `update-article-content-template.drush.php` | Updates Article ContentTemplate with exposed slot | None | **Yes** — already exists, sets up the test fixture for per-content editing |
| `canvas-component-status.drush.php` | Lists all Canvas components | None | Diagnostic |
| `canvas-regenerate-components.drush.php` | Forces component rediscovery | None | Diagnostic |
| `canvas-build-page.drush.php` | Builds example page | None | Reference |
| `canvas-code-component-sync.drush.php` | Lists code components | None | Diagnostic |
| `canvas-page-region-status.drush.php` | Shows PageRegion config | None | Diagnostic |
| `canvas-oauth-setup.drush.php` | Verifies OAuth setup | None | Not relevant |
| `add-hero-slider-reference.drush.php` | Adds hero slider field | None | Not relevant |
| `create-hero-slider-block-type.drush.php` | Creates hero slider block type | None | Not relevant |

## Implementation Plan

---

### Step 1: Restore `ComponentTreeEditAccessCheck` try-catch block

- **Type:** Code Change (critical fix — addresses reviewer Finding 1)
- **File:** `web/modules/contrib/canvas/src/Access/ComponentTreeEditAccessCheck.php` — **Modify**
- **Details:** Restore the try-catch block around `$this->componentTreeLoader->load($entity)` at line 36. The current branch removed this, which means any non-canvas_page entity hitting layout API routes (`_canvas_component_tree_edit_access`) will get a 500 error (uncaught `\LogicException`) instead of a clean 403 when `getCanvasFieldName()` is called on an ineligible entity.

  **Change at line 35-36:** Replace:
  ```php
  $tree = $this->componentTreeLoader->load($entity);
  ```
  With:
  ```php
  try {
    $tree = $this->componentTreeLoader->load($entity);
  }
  catch (\LogicException) {
    return AccessResult::forbidden('Entity does not support Canvas component tree editing.');
  }
  ```

  **Why critical**: After Step 4 updates `getCanvasFieldName()` to allow entities with ContentTemplates that have exposed slots, any node entity WITHOUT an enabled ContentTemplate will reach this access check via layout API routes. Without the try-catch, `getCanvasFieldName()` throws `\LogicException` → uncaught → 500 error. With the try-catch restored → clean 403.

- **Expected outcome:** Layout API routes return 403 (not 500) for entities that lack Canvas field support.
- **Depends on:** None

### Step 2: Re-create `SlotTreeExtractor` service

- **Type:** Code Change (restore deleted code from main)
- **File:** `web/modules/contrib/canvas/src/Storage/SlotTreeExtractor.php` — **Create**
- **Details:** Re-create using the **original code from main** (`git checkout main -- web/modules/contrib/canvas/src/Storage/SlotTreeExtractor.php`). The original implementation uses simple UUID-based filtering that works identically on both client-side and server-side data representations.

  Methods (matching original API exactly):
  - `extractSlotSubtree(array $merged_components, ContentTemplate $template): array` — Filters `$merged_components` to exclude template-owned components by UUID.
  - `filterLayoutToSlotComponents(array $layout, ContentTemplate $template): array` — Preserves region structure (`nodeType`, `id`, `name`) but filters `$layout['components']` to exclude template-owned components.
  - `isSlotComponent(string $component_uuid, ContentTemplate $template): bool` — Returns TRUE if UUID is NOT in the template tree.
  - `private getTemplateUuids(ContentTemplate $template): array` — Builds `[uuid => TRUE]` map from template's `getComponentTree()`.

  **Note (addressing reviewer Finding 3)**: The original code's UUID-based filtering works correctly because UUIDs are shared between client-side and server-side representations. No need to distinguish "client-side format" vs "server-side format" — the filter operates on a `components` array where each element has a `uuid` key, which is the same UUID used in `ComponentTreeItem` objects on the server side.

- **Expected outcome:** `SlotTreeExtractor` service exists with the proven original API.
- **Depends on:** None

### Step 3: Register `SlotTreeExtractor` service

- **Type:** Code Change
- **File:** `web/modules/contrib/canvas/canvas.services.yml` — **Modify**
- **Details:** Add service definition:
  ```yaml
  Drupal\canvas\Storage\SlotTreeExtractor: {}
  ```
  Place it after the existing `Drupal\canvas\Storage\ComponentTreeLoader: {}` line (currently at line 68).
- **Expected outcome:** `SlotTreeExtractor` is available for dependency injection via autowiring.
- **Depends on:** Step 2

### Step 4: Re-add `hasContentTemplateWithExposedSlots()` to `ComponentTreeLoader`

- **Type:** Code Change (restore deleted code from main)
- **File:** `web/modules/contrib/canvas/src/Storage/ComponentTreeLoader.php` — **Modify**
- **Details:**
  1. Add `use Drupal\canvas\Entity\ContentTemplate;` import (was removed in the revert).
  2. Re-add method `hasContentTemplateWithExposedSlots(FieldableEntityInterface $entity): bool` that:
     - Loads `ContentTemplate::loadForEntity($entity, 'full')`
     - Returns `$template !== NULL && $template->status() && !empty($template->getExposedSlots())`
  3. Update `getCanvasFieldName()` (line 55) — replace the test-only guard (`drupal_valid_test_ua()`) with the proper per-content editing check from main:
     - Allow `canvas_page` entities unconditionally (existing behavior)
     - For other entity types, allow if they have an enabled ContentTemplate with exposed slots (call `$this->hasContentTemplateWithExposedSlots($entity)`)
     - Throw `\LogicException` with descriptive message for unsupported entity types

  **Scope expansion acknowledgement**: Removing the `drupal_valid_test_ua()` guard is an intentional scope expansion. Currently only `canvas_page` and test-only `article` bundles can use Canvas fields. After this change, ANY node bundle with an enabled ContentTemplate that has exposed slots can use Canvas fields in production. This is the intended behavior — it implements the generalization described in https://drupal.org/i/3498525. Access control is adequately gated by `NodeLayoutAccessCheck` (Step 9) and `ComponentTreeEditAccessCheck` (Step 1's try-catch restoration).

- **Expected outcome:** `ComponentTreeLoader::getCanvasFieldName()` accepts node entities that have an enabled ContentTemplate with exposed slots. `hasContentTemplateWithExposedSlots()` is available for access checks and other services.
- **Depends on:** None

### Step 5: Re-add `getMergedComponentTree()` to `ContentTemplate`

- **Type:** Code Change (restore deleted code from main)
- **File:** `web/modules/contrib/canvas/src/Entity/ContentTemplate.php` — **Modify**
- **Details:** Re-add the `getMergedComponentTree(FieldableEntityInterface $entity): ComponentTreeItemList` method (restore from main):
  - Throws `\LogicException` if entity implements `ComponentTreeEntityInterface`
  - Asserts `count($this->getExposedSlots()) === 1`
  - Gets Canvas field name via `\Drupal::service(ComponentTreeLoader::class)->getCanvasFieldName($entity)`
  - Loads `$sub_tree_item_list = $entity->get($canvas_field_name)` (asserted as `ComponentTreeItemList`)
  - Returns `$this->getComponentTree($entity)->injectSubTreeItemList($this->getExposedSlots(), $sub_tree_item_list)`

  **Relationship to `ContentTemplate::build()`**: The existing `build()` method (lines 360-373 on current branch) contains equivalent tree-injection logic inlined for the **rendering** path — it calls `injectSubTreeItemList()` then `->toRenderable()`. The restored `getMergedComponentTree()` method is specifically for the **JSON API path** — it returns the `ComponentTreeItemList` directly (not as a renderable array) so that `ApiLayoutController::get()` can serialize it to JSON with `editable` metadata annotations. After restoration, `build()` should be updated to call `$this->getMergedComponentTree($entity)->toRenderable($this, $isPreview)` to eliminate duplication.

- **Expected outcome:** `ContentTemplate::getMergedComponentTree()` returns a merged tree (template shell + entity slot content) as a `ComponentTreeItemList` for API consumption.
- **Depends on:** Step 4

### Step 6: Add `exposedSlots` to `ContentTemplate::normalizeForClientSide()`

- **Type:** Code Change (restore deleted line from main)
- **File:** `web/modules/contrib/canvas/src/Entity/ContentTemplate.php` — **Modify**
- **Details:** In `normalizeForClientSide()` (line 441 on current branch), add `'exposedSlots' => $this->getExposedSlots()` to the `values` array in `ClientSideRepresentation::create()`, alongside the existing `'suggestedPreviewEntityId'` entry (line 467 on current branch).
- **Expected outcome:** The template list API response includes `exposedSlots` for each template, enabling the frontend to show which templates have exposed slots.
- **Depends on:** None

### Step 7: Re-implement per-content editing in `ApiLayoutController`

- **Type:** Code Change (restore deleted code from main)
- **File:** `web/modules/contrib/canvas/src/Controller/ApiLayoutController.php` — **Modify**
- **Details:** Restore the per-content editing functionality from main. Recommended approach: `git checkout main -- web/modules/contrib/canvas/src/Controller/ApiLayoutController.php` to get the complete file, then verify.

  What must be restored:

  1. **Add `SlotTreeExtractor` dependency** to constructor (add `private readonly SlotTreeExtractor $slotTreeExtractor` parameter after `ComponentTreeLoader`). Canvas uses autowiring.

  2. **`get()` method** — Restore per-content editing detection:
     - Call `$this->getPerContentTemplate($entity)` (see step 7.7)
     - If per-content template found: merge trees via `$perContentTemplate->getMergedComponentTree($entity)`, build region from merged tree, then annotate each component with `$component['editable']` flag (TRUE for slot components, FALSE for template-owned components — use `$template_tree->getComponentTreeItemByUuid($component['uuid']) !== NULL` to determine). Also add `exposedSlots` and `contentTemplateId` to the response data.
     - If no per-content template: use existing standard path.

  3. **`patch()` method** — Restore slot-awareness guard:
     - After determining entity to patch, if `$this->getPerContentTemplate($entity)` returns a template, check that `$componentInstanceUuid` is NOT in the template tree (use `$template_tree->getComponentTreeItemByUuid()`). If it IS a template component, throw `AccessDeniedHttpException('Cannot edit template-owned components in per-content editing mode.')`.

  4. **`post()` / `updateEntity()` method** — Restore three changes:
     - Accept optional `$exposed_slots` parameter in `updateEntity()`.
     - In `post()`, read `$body['exposed_slots'] ?? NULL` and pass to `updateEntity()`.
     - In `updateEntity()`, for the `FieldableEntityInterface` branch: when `getPerContentTemplate($entity)` returns non-null, filter `$layout` using `$this->slotTreeExtractor->filterLayoutToSlotComponents($layout, $perContentTemplate)` **before** passing to `$this->converter->convert()`. This ensures only slot components are stored on the node's Canvas field.
     - If `$entity instanceof ContentTemplate` and `$exposed_slots !== NULL`, call `$entity->set('exposed_slots', $exposed_slots)`.

  5. **`buildPreviewRenderable()` method** — Restore per-content editing path:
     - For per-content editing, use `$perContentTemplate->getMergedComponentTree($entity)->toRenderable($perContentTemplate, isPreview: TRUE)` instead of the entity's own tree.

  6. **`buildLayoutAndModel()` method** — Restore per-content editing detection: if per-content template exists, use merged tree and annotate with `editable` flags.

  7. **Restore `getPerContentTemplate()` private method** — Returns `ContentTemplate|null`:
     - Returns NULL if entity is `ContentTemplate` or implements `ComponentTreeEntityInterface`
     - Returns NULL if entity is not `FieldableEntityInterface`
     - Loads `ContentTemplate::loadForEntity($entity, 'full')`
     - Returns NULL if template is null, disabled, or has no exposed slots
     - Otherwise returns the template

- **Expected outcome:** Layout API GET returns merged tree with `editable` metadata + `exposedSlots`/`contentTemplateId`. PATCH guards against template-owned edits. POST extracts slot subtree before save.
- **Depends on:** Steps 2, 3, 4, 5

### Step 8: Re-implement `CanvasController` per-content editing support

- **Type:** Code Change (restore deleted code from main)
- **File:** `web/modules/contrib/canvas/src/Controller/CanvasController.php` — **Modify**
- **Details:**
  1. Add `use Drupal\Core\Entity\FieldableEntityInterface;` and `use Drupal\node\NodeInterface;` imports.
  2. Add `templateContext` to drupalSettings in `__invoke()` (after line 202, alongside existing `homepagePath`):
     ```php
     'templateContext' => $entity !== NULL && $entity instanceof FieldableEntityInterface
       ? $this->getTemplateContext($entity)
       : NULL,
     ```
  3. Add `private function getTemplateContext(FieldableEntityInterface $entity): ?array` that:
     - Returns NULL if entity implements `ComponentTreeEntityInterface`
     - Loads `ContentTemplate::loadForEntity($entity, 'full')`
     - Returns NULL if template is null, disabled, or has no exposed slots
     - Returns `['contentTemplateId' => $template->id(), 'hasExposedSlots' => TRUE, 'exposedSlots' => $template->getExposedSlots()]`
  4. Add `public function nodeLayout(NodeInterface $node): HtmlResponse` that calls `$this->__invoke('node', $node)`.
  5. In `getAllContentEntityCreateLinks()`, add `'bundle' => $bundle` to the CanvasResourceLink target attributes.
- **Expected outcome:** `drupalSettings.canvas.templateContext` is populated when editing a node with an enabled ContentTemplate with exposed slots. `nodeLayout()` method is available for the canvas.node.layout route.
- **Depends on:** None

### Step 9: Re-add `canvas.node.layout` route and `NodeLayoutAccessCheck`

- **Type:** Code Change (restore deleted code from main — multiple files)
- **Files:**
  - `web/modules/contrib/canvas/src/Access/NodeLayoutAccessCheck.php` — **Create** (restore from main)
  - `web/modules/contrib/canvas/canvas.routing.yml` — **Modify**
  - `web/modules/contrib/canvas/canvas.services.yml` — **Modify**
  - `web/modules/contrib/canvas/canvas.links.task.yml` — **Modify**
- **Details:**
  1. **Create `NodeLayoutAccessCheck`** (restore from main via `git checkout main -- web/modules/contrib/canvas/src/Access/NodeLayoutAccessCheck.php`): Plain class with `access(NodeInterface $node, AccountInterface $account): AccessResultInterface`:
     - Check `$this->componentTreeLoader->hasContentTemplateWithExposedSlots($node)` — forbidden if FALSE
     - Try `$this->componentTreeLoader->getCanvasFieldName($node)` — catch `\LogicException` → forbidden
     - Check `$node->access('update', $account, TRUE)`
     - **Important**: This class does NOT implement `AccessInterface`. It is a plain class designed for the `_custom_access` routing pattern.

  2. **Add route** `canvas.node.layout` to `canvas.routing.yml` (restoring from main) — **using `_custom_access` pattern, NOT tagged access check** (addresses reviewer Finding 2):
     ```yaml
     canvas.node.layout:
       path: '/node/{node}/layout'
       defaults:
         _controller: 'Drupal\canvas\Controller\CanvasController::nodeLayout'
         _title: 'Layout'
       requirements:
         _custom_access: 'Drupal\canvas\Access\NodeLayoutAccessCheck::access'
       options:
         parameters:
           node:
             type: entity:node
     ```
     **Why `_custom_access` and not `_canvas_node_layout_access`**: The `_custom_access` pattern is simpler — it directly references the class and method without requiring tag registration or `AccessInterface` implementation. This matches the original code on `main`. The tagged approach (proposed in the previous plan revision) would require additional class changes that add complexity for no benefit.

  3. **Register access check service** in `canvas.services.yml` — **without tags** (addresses reviewer Finding 2):
     ```yaml
     Drupal\canvas\Access\NodeLayoutAccessCheck: {}
     ```
     Place it after the existing `Drupal\canvas\Access\AuthenticationAccessChecker` block. No tags needed because `_custom_access` resolves the service by class name directly.

  4. **Add Layout tab** in `canvas.links.task.yml` with **weight: 20** (addresses reviewer feedback — was incorrectly 100 in previous plan):
     ```yaml
     canvas.entity.node.layout:
       route_name: canvas.node.layout
       title: 'Layout'
       base_route: entity.node.canonical
       weight: 20
     ```

- **Expected outcome:** `/node/{node}/layout` route exists, access-checked via `_custom_access`, renders Canvas UI for per-content editing. "Layout" tab appears on node view pages for eligible content types with weight 20.
- **Depends on:** Steps 4, 8

### Step 10: Address `ApiContentControllers` reverted changes — scope decision

- **Type:** Scope Decision (addresses reviewer Finding 5)
- **Files:** `web/modules/contrib/canvas/src/Controller/ApiContentControllers.php`, `web/modules/contrib/canvas/canvas.routing.yml`
- **Details:** The branch reverted significant changes to `ApiContentControllers.php`:
  - **Create route**: Reverted from `_custom_access` (bundle-aware) to `_entity_create_access: 'canvas_page:canvas_page'` — only canvas_page can be created
  - **Delete route**: Reverted from generic `{entity}` parameter to hardcoded `{canvas_page}` — only canvas_page can be deleted
  - **List route**: Reverted from `_custom_access` (generic) to `_permission: 'edit canvas_page'` — only canvas_page can be listed
  - **`post()` method**: Removed bundle-aware entity creation (bundle key from request body)
  - **`delete()` method**: Parameter changed from `$entity` to `$canvas_page`
  - **`createAccess()` and `listAccess()` methods**: Deleted entirely

  **Scope decision: EXPLICITLY OUT OF SCOPE for this ticket.** Per-content editing in ATWEB-9 only enables editing EXISTING nodes via `/node/{node}/layout`. It does NOT require:
  - Creating new node entities via the Canvas API (nodes are created through standard Drupal admin UI or other means)
  - Deleting node entities via the Canvas API
  - Listing node entities via the Canvas content list API

  The per-content editing flow is: user navigates to a node → clicks "Layout" tab → edits slot content → saves. The Canvas content listing (Work Stream 4 "Content list → layout link") refers to adding a layout link to the standard Drupal content admin page, NOT to the Canvas sidebar content list.

  The `ApiContentControllers` generalization is tracked separately at https://drupal.org/i/3498525 and https://drupal.org/i/3513566 (referenced in `@todo` comments on the current branch).

  **Impact on frontend**: The Canvas sidebar content list will continue to show only `canvas_page` entities. Node entities eligible for per-content editing are accessed via their standard Drupal node view pages, where the "Layout" tab appears.

- **Expected outcome:** No changes to `ApiContentControllers.php`. The scope is documented and the architectural boundary is clear.
- **Depends on:** None

---

### Step 11: Update `DrupalSettings` TypeScript interface

- **Type:** Code Change (Frontend)
- **File:** `web/modules/contrib/canvas/ui/src/types/DrupalSettings.ts` — **Modify**
- **Details:** Add the following properties to the `canvas` object in the `DrupalSettings` interface (after `loginUrl` on line 38):
  ```typescript
  templateContext?: {
    contentTemplateId: string;
    hasExposedSlots: boolean;
    exposedSlots: Record<string, {
      component_uuid: string;
      slot_name: string;
      label: string;
    }>;
  } | null;
  permissions: {
    globalRegions: boolean;
    patterns: boolean;
    codeComponents: boolean;
    contentTemplates: boolean;
    publishChanges: boolean;
    folders: boolean;
  };
  contentEntityCreateOperations: Record<string, Record<string, string>>;
  homepagePath: string;
  ```
  Note: `permissions`, `contentEntityCreateOperations`, and `homepagePath` already exist in drupalSettings output from CanvasController (lines 193-202) but are consumed via untyped globals (`drupal-globals.ts` line 10-11, `configurationSlice.ts`). Adding them to the TypeScript interface formalizes the contract.
- **Expected outcome:** TypeScript types match the backend's drupalSettings shape.
- **Depends on:** Step 8

### Step 12: Add `editable` property to `ComponentNode` interface

- **Type:** Code Change (Frontend)
- **File:** `web/modules/contrib/canvas/ui/src/features/layout/layoutModelSlice.ts` — **Modify**
- **Details:** Add `editable?: boolean` to the `ComponentNode` interface (after line 55, `slots: SlotNode[];`):
  ```typescript
  export interface ComponentNode {
    nodeType: NodeType.Component;
    uuid: UUID;
    type: string;
    slots: SlotNode[];
    editable?: boolean;  // false for template-owned, true for slot components; undefined in non-per-content mode
  }
  ```
- **Expected outcome:** Frontend type system supports the `editable` metadata from the API.
- **Depends on:** None

### Step 13: Update `LayoutApiResponse` and `TemplateViewMode` types

- **Type:** Code Change (Frontend)
- **File:** `web/modules/contrib/canvas/ui/src/services/componentAndLayout.ts` — **Modify**
- **Details:**
  1. Update `LayoutApiResponse` (line 28):
     ```typescript
     type LayoutApiResponse = RootLayoutModel & {
       entity_form_fields: Record<string, any>;
       isNew: boolean;
       isPublished: boolean;
       html: string;
       autoSaves: AutoSavesHash;
       exposedSlots?: Record<string, { component_uuid: string; slot_name: string; label: string }>;
       contentTemplateId?: string;
     };
     ```
  2. Update `TemplateViewMode` (line 36):
     ```typescript
     export type TemplateViewMode = {
       entityType: string;
       bundle: string;
       viewMode: string;
       viewModeLabel: string;
       label: string;
       status: boolean;
       id: string;
       suggestedPreviewEntityId?: number;
       exposedSlots?: Record<string, { component_uuid: string; slot_name: string; label: string }>;
     };
     ```
- **Expected outcome:** API response types match the backend's per-content editing response shape.
- **Depends on:** None

### Step 14: Store `templateContext` and API response per-content data in Redux state

- **Type:** Code Change (Frontend)
- **File:** `web/modules/contrib/canvas/ui/src/features/ui/uiSlice.ts` — **Modify**
- **Details:** Extend `uiSlice` to store template context (both from drupalSettings boot and from API response):
  1. Add to `uiSliceState` interface (after `editorFrameContext`):
     ```typescript
     templateContext?: {
       contentTemplateId: string;
       hasExposedSlots: boolean;
       exposedSlots: Record<string, { component_uuid: string; slot_name: string; label: string }>;
     } | null;
     ```
  2. Add reducer `setTemplateContext` to set this value.
  3. Add selector `selectTemplateContext`.
  4. **Boot-time initialization**: On app initialization (in `main.tsx` or the Editor component), read `drupalSettings.canvas.templateContext` and dispatch `setTemplateContext`. This provides the initial per-content editing signal before the layout API response arrives.

  **Consuming API response data**: The layout API response returns `exposedSlots` and `contentTemplateId` alongside the layout data (Step 13). Update `getPageLayout` in `componentAndLayout.ts` (line 143) — in `onQueryStarted`, extract and dispatch these fields:
  ```typescript
  // Inside existing onQueryStarted callback, after other dispatches:
  if (contentTemplateId && exposedSlots) {
    dispatch(setTemplateContext({
      contentTemplateId,
      hasExposedSlots: true,
      exposedSlots,
    }));
  }
  ```

  **Two-phase data flow**:
  - **Phase 1 (boot)**: `drupalSettings.canvas.templateContext` provides the initial signal that this is per-content editing mode. This determines routing and initial UI mode.
  - **Phase 2 (API response)**: `getPageLayout` response includes `exposedSlots`, `contentTemplateId`, and per-component `editable` flags on `ComponentNode` objects. The `editable` flags are part of the layout model (stored in `layoutModelSlice` automatically). The `exposedSlots`/`contentTemplateId` are stored in `uiSlice.templateContext`. Component locking in Steps 17-18 reads `editable` from `ComponentNode` (via `layoutModelSlice`) — NOT from `templateContext`. The `templateContext` provides the high-level "are we in per-content mode?" signal, while `editable` on individual components drives specific locking behavior.

- **Expected outcome:** Template context is available to all editor components via Redux from both boot time and API response, with clear separation: `templateContext` for mode detection, `ComponentNode.editable` for per-component locking.
- **Depends on:** Steps 11, 12, 13

### Step 15: Extend `ENTITY` context with template awareness

- **Type:** Code Change (Frontend)
- **File:** `web/modules/contrib/canvas/ui/src/features/ui/uiSlice.ts` — **Modify**
- **Details:** The recommended approach is to **extend `ENTITY` context** with template awareness rather than adding a new enum value. The `templateContext` in Redux state (Step 14) provides the signal:
  - `EditorFrameContext.ENTITY` + `templateContext !== null` → per-content editing mode
  - `EditorFrameContext.ENTITY` + `templateContext === null` → standard entity editing mode

  No new `EditorFrameContext` value is needed. The behavioral differences (component locking, slot restrictions) are driven by the `templateContext` selector and per-component `editable` flags.
- **Expected outcome:** Minimal change — existing context system works, with per-content behavior determined by templateContext presence.
- **Depends on:** Step 14

### Step 16: Add React route for per-content node editing

- **Type:** Code Change (Frontend)
- **File:** `web/modules/contrib/canvas/ui/src/app/AppRoutes.tsx` — **Modify**
- **Details:** The `/node/{node}/layout` Drupal route boots the Canvas app with `CanvasController::nodeLayout()`, which sets the base path to the entity editor route. The React router already handles `/editor/:entityType/:entityId`. When Canvas boots from `/node/{node}/layout`, CanvasController passes `entity_type='node'` and `entity={node_id}`, which maps to the existing `/editor/node/:entityId` route.

  However, to ensure the correct behavior, verify:
  1. The `basePath` in `AppRoutes` correctly resolves when booting from `/node/{node}/layout`.
  2. The `Editor` component checks `templateContext` from Redux to enable per-content editing mode.

  If needed, add a dedicated route:
  ```typescript
  {
    path: '/node-layout/:entityId',
    element: (
      <UiShell>
        <Editor context={EditorFrameContext.ENTITY} />
      </UiShell>
    ),
    children: [/* same component instance form child routes */],
  },
  ```
  But first try reusing `/editor/node/:entityId` — the per-content behavior is driven by `templateContext`, not the route.
- **Expected outcome:** Per-content editing loads via existing `/editor/node/:entityId` route with templateContext driving behavior.
- **Depends on:** Steps 9, 14

### Step 17: Implement component locking in preview overlay

- **Type:** Code Change (Frontend)
- **Files:**
  - `web/modules/contrib/canvas/ui/src/features/layout/previewOverlay/ComponentOverlay.tsx` (257 lines) — **Primary file for preview overlay locking**
  - `web/modules/contrib/canvas/ui/src/features/layout/previewOverlay/SlotOverlay.tsx` (179 lines) — **Slot drop zone restrictions**
  - `web/modules/contrib/canvas/ui/src/features/layout/previewOverlay/ComponentDropZone.tsx` — **Drop zone restrictions**
  - `web/modules/contrib/canvas/ui/src/features/layout/previewOverlay/PreviewOverlay.module.css` — **Add locked/dimmed styles**
- **Details:** When `templateContext` is present (per-content editing mode), components with `editable === false` should be locked. The `editable` property is read directly from each `ComponentNode` object in the layout model (set by the API response per Step 7.2).

  1. **`ComponentOverlay.tsx` (primary integration file, 257 lines):**
     - **`useDraggable` hook (line 91-99)**: Conditionally disable dragging for template-owned components. Set `disabled: component.editable === false` in the `useDraggable` options (or do not spread `listeners`/`attributes` when locked).
     - **`handleComponentClick` (line 132-135)**: When `component.editable === false`, prevent selection — either return early or dispatch a different action that shows an informational message instead.
     - **`handleItemMouseOver` (line 137-141)**: When `component.editable === false`, either skip hover dispatch or dispatch with a "locked" flag so the hover visual is different (dimmed vs highlighted).
     - **CSS class for locked state**: Add a CSS class `styles.locked` when `component.editable === false`. Apply dimmed/grayed visual treatment (e.g., `opacity: 0.5`, no border highlight on hover).
     - **`ComponentContextMenu` wrapper (line 202)**: When `component.editable === false`, do not render the context menu (or render a read-only version showing "This component is part of the template").
     - **`ComponentDropZone` rendering (lines 235-252)**: When `component.editable === false`, do not render drop zones around template-owned components.

  2. **`SlotOverlay.tsx` (179 lines) — Drop zone restrictions:**
     - Read `templateContext.exposedSlots` from Redux (via `selectTemplateContext` selector).
     - For non-exposed slots (those not listed in `exposedSlots`), either hide drop zones entirely or render them as non-interactive.
     - The slot is identified by the combination of `parentComponent.uuid` and `slot.name` — match against `exposedSlots[*].component_uuid` and `exposedSlots[*].slot_name`.

  3. **`PreviewOverlay.module.css` — Add locked styles:**
     - Add `.locked` class with `opacity: 0.5`, `pointer-events: none` (or `cursor: not-allowed`), no border/outline on hover.

- **Expected outcome:** Template-owned components are visually locked (dimmed, not interactive, no drag/select/drop), slot components behave normally.
- **Depends on:** Steps 12, 14

### Step 18: Implement component locking in tree sidebar

- **Type:** Code Change (Frontend)
- **Files:**
  - `web/modules/contrib/canvas/ui/src/features/layout/layers/ComponentLayer.tsx` (232 lines) — **Primary file for tree sidebar locking**
  - `web/modules/contrib/canvas/ui/src/features/layout/layers/SlotLayer.tsx` (150 lines) — **Slot-level drop zone restrictions**
  - `web/modules/contrib/canvas/ui/src/features/layout/layers/ComponentLayer.module.css` — **Add locked styles**
- **Details:** When `templateContext` is present, template-owned components (those where `component.editable === false`) should be locked in the tree sidebar.

  1. **`ComponentLayer.tsx` (primary integration file, 232 lines):**
     - **`useDraggable` hook (line 64-71)**: Set `disabled: component.editable === false` to prevent dragging template-owned components in the layers panel.
     - **`handleItemClick` (lines 73-79)**: When `component.editable === false`, prevent selection — return early or show informational message.
     - **`handleItemMouseEnter` (lines 81-89)**: When locked, either skip hover highlight or show a muted hover state.
     - **`SidebarNode` component (line 142-160)**: Set `draggable={component.editable !== false}` (line 148 currently hardcodes `draggable={true}`). Add a lock icon to `leadingContent` when `component.editable === false` (e.g., use Radix `LockClosedIcon`).
     - **`ComponentContextMenu` wrapper (line 135)**: When `component.editable === false`, do not render `ComponentContextMenuContent` in `dropdownMenuContent` (removes delete/duplicate options). Pass `null` or render a read-only menu.
     - **CSS class for locked state**: Add `styles.locked` class with muted text color and background.

  2. **`SlotLayer.tsx` (150 lines) — Drop zone restrictions in tree sidebar:**
     - Read `templateContext.exposedSlots` from Redux via `selectTemplateContext`.
     - For non-exposed slots, set `disableDrop={true}` on the `LayersDropZone` (line 138-143) and on child `ComponentLayer` components.

- **Expected outcome:** Tree sidebar shows locked indicators for template components (lock icon, muted styling), prevents drag/delete/duplicate interactions.
- **Depends on:** Steps 12, 14

### Step 19: Component instance form panel — locked component message

- **Type:** Code Change (Frontend)
- **File:** `web/modules/contrib/canvas/ui/src/components/ComponentInstanceForm.tsx` — **Modify**
- **Details:** When a user clicks/selects a template-owned component (`editable === false`):
  - Instead of showing the prop editing form, show an informational message: "This component is part of the template and cannot be edited here."
  - Read the `editable` property from the selected `ComponentNode` in the layout model.
  - **Preferred approach**: Prevent selection entirely in Steps 17-18 (return early from click handlers), so the form panel never opens for locked components. The informational message is a fallback if selection somehow occurs.
- **Expected outcome:** Users get clear feedback when interacting with template-owned components.
- **Depends on:** Steps 12, 17

### Step 20: Add exposed slot configuration UI in template editor

- **Type:** Code Change (Frontend)
- **Files (addresses reviewer Finding — Step 18 previously lacked file specificity):**
  - `web/modules/contrib/canvas/ui/src/features/layout/previewOverlay/SlotOverlay.tsx` (179 lines) — **Add "Expose this slot" right-click context menu on slot overlays in template editing mode**
  - `web/modules/contrib/canvas/ui/src/features/layout/layers/SlotLayer.tsx` (150 lines) — **Add "Expose this slot" dropdown menu item on slot nodes in layers panel during template editing**
  - `web/modules/contrib/canvas/ui/src/features/layout/layers/ComponentLayer.module.css` — **Exposed slot visual indicator styles**
  - `web/modules/contrib/canvas/ui/src/components/sidePanel/SidebarNode.tsx` (163 lines) — **Reference for adding dropdown menu to slot sidebar nodes (already supports `dropdownMenuContent` prop)**
  - `web/modules/contrib/canvas/ui/src/features/layout/preview/ComponentContextMenu.tsx` (244 lines) — **Pattern to follow for creating slot-specific context menu (uses Radix `ContextMenu.Root`)**
  - `web/modules/contrib/canvas/ui/src/components/Dialog.tsx` (195 lines) — **Base dialog component for the exposed slot naming dialog**
  - `web/modules/contrib/canvas/ui/src/features/pattern/SavePatternDialog.tsx` (165 lines) — **Pattern to follow for exposed slot naming dialog (uses `Dialog` wrapper + `TextField.Root`)**
  - `web/modules/contrib/canvas/ui/src/services/componentAndLayout.ts` (490 lines) — **Update `postTemplateLayout` mutation (line 181) to include `exposed_slots` in POST body**
  - `web/modules/contrib/canvas/ui/src/features/layout/preview/Preview.tsx` (133 lines) — **Update POST call (lines 73-76) to include `exposed_slots` when saving templates**
  - `web/modules/contrib/canvas/ui/src/features/layout/layoutModelSlice.ts` (891 lines) — **Optional: add `exposed?: boolean` to `SlotNode` interface for local state tracking**
- **Details:** When editing a template (`EditorFrameContext.TEMPLATE`), add UI affordance to mark slots as "exposed":

  1. **Slot context menu on `SlotOverlay.tsx`**: When `editorFrameContext === 'template'`, wrap the slot overlay in a Radix `ContextMenu.Root` (following the pattern in `ComponentContextMenu.tsx` at line 202). Add menu items:
     - "Expose this slot" — only shown for empty slots (per backend `ValidExposedSlotConstraintValidator` requirement that exposed slots must be empty)
     - "Remove exposed" — only shown for already-exposed slots

  2. **Slot dropdown menu on `SlotLayer.tsx`**: When `editorFrameContext === 'template'`, pass `dropdownMenuContent` to `SidebarNode` (which already supports this prop, see `SidebarNode.tsx` lines 127-141). Add the same menu items as above.

  3. **Exposed slot visual indicator**: In both `SlotOverlay.tsx` and `SlotLayer.tsx`, when a slot is marked as exposed, add a distinct CSS class (dashed border, icon badge) to visually differentiate it from normal slots.

  4. **Exposed slot naming dialog**: Create a new component following the `SavePatternDialog.tsx` pattern (165 lines). Use:
     - `Dialog` wrapper from `Dialog.tsx` with title "Expose Slot"
     - `TextField.Root` from Radix UI for label input
     - Auto-generate machine name from label (slugify)
     - Store the `{component_uuid, slot_name, label}` structure
     - **File path**: `web/modules/contrib/canvas/ui/src/features/layout/preview/ExposeSlotDialog.tsx` — **Create**

  5. **Persist `exposed_slots` on save**: Update `postTemplateLayout` mutation in `componentAndLayout.ts` (line 181) to include `exposed_slots` in the POST body when saving templates. Update `Preview.tsx` (lines 73-76) to pass `exposed_slots` from the Redux state when calling `postTemplatePreview`.

  6. **Single slot constraint**: The backend enforces `count(getExposedSlots()) === 1`. The UI should not allow exposing multiple slots. If one slot is already exposed, either:
     - Disable the "Expose this slot" option on other slots, OR
     - Show a confirmation dialog to replace the existing exposed slot

  7. **Full view mode only**: `ValidExposedSlot: full` means exposed slots only work for the `full` view mode. The UI should not offer slot exposure for other view modes. Check `editorFrameContext === 'template'` AND the template's view mode is `full`.

  8. **Validation feedback**: Surface backend validation errors (component UUID must exist, slot_name must be valid, slot must be empty) in the UI via the standard error handling in the POST response.

- **Expected outcome:** Site builders can expose slots on templates via the Canvas UI with naming dialog, visual indicators, and proper constraint enforcement.
- **Depends on:** Steps 6, 7, 13

### Step 21: Show exposed slots indicator in Template sidebar

- **Type:** Code Change (Frontend)
- **File:** `web/modules/contrib/canvas/ui/src/components/list/TemplateList.tsx` — **Modify**
- **Details:** In `TemplateListItem` (or `BundleListItem`), check the `exposedSlots` property from `TemplateViewMode` data. If `exposedSlots` is non-empty, show a badge/icon/subtitle text indicating the template has exposed slots (e.g., "1 exposed slot" or a small icon).
- **Expected outcome:** Template sidebar shows which templates have exposed slots.
- **Depends on:** Step 13

### Step 22: Verify "Layout" tab rendering on node pages

- **Type:** Verification
- **Script:** N/A (manual verification + existing test coverage)
- **Details:** The `canvas.entity.node.layout` task link (Step 9) should render the "Layout" tab on node view pages. Verify:
  1. Tab appears only when a ContentTemplate with exposed slots exists for that bundle
  2. Tab leads to `/node/{node}/layout`
  3. Access check works (forbidden for users without update access, forbidden for nodes without Canvas field)
  4. Run existing kernel tests: `ddev exec vendor/bin/phpunit web/modules/contrib/canvas/tests/src/Kernel/NodeTemplatesTest.php`
- **Expected outcome:** Layout tab works end-to-end.
- **Depends on:** Steps 1-9

### Step 23: Auto-provisioning of Canvas field on content type

- **Type:** Code Change (Backend)
- **File:** `web/modules/contrib/canvas/src/Entity/ContentTemplate.php` — **Modify** (in `postSave()` or a new event subscriber)
- **Details:** When a site builder saves exposed slots on a ContentTemplate, auto-create the `component_tree` field on the target content type if it doesn't exist:
  1. In `ContentTemplate::postSave()` (preferred over `preSave()` to avoid nested entity save issues), after the entity is saved:
     - Get the target entity type ID via `$this->getTargetEntityTypeId()` and bundle via `$this->getTargetBundle()`
     - Check if the target bundle already has a field of type `ComponentTreeItem::PLUGIN_ID` (use `EntityFieldManager::getFieldMapByFieldType()`)
     - If not, create `FieldStorageConfig` for field type `component_tree` on the target entity type:
       ```php
       FieldStorageConfig::create([
         'field_name' => 'field_component_tree',
         'entity_type' => $this->getTargetEntityTypeId(),
         'type' => ComponentTreeItem::PLUGIN_ID,
       ])->save();
       ```
     - Create `FieldConfig` attached to the template's target bundle:
       ```php
       FieldConfig::create([
         'field_name' => 'field_component_tree',
         'entity_type' => $this->getTargetEntityTypeId(),
         'bundle' => $this->getTargetBundle(),
         'label' => 'Canvas Layout',
       ])->save();
       ```
     - Only create if the field doesn't already exist
  2. If auto-provisioning is not implemented, show a clear error in the UI when a site builder tries to expose a slot on a template whose content type lacks a Canvas field.

  **Why `postSave()` over `preSave()`**: Creating fields in `preSave()` triggers schema changes and potentially other entity save hooks, which is risky inside a save hook. `postSave()` runs after the entity is committed, so it's safer for side-effect operations like field creation.

- **Expected outcome:** Exposing a slot on a ContentTemplate automatically ensures the target content type has a Canvas field.
- **Depends on:** Steps 6, 20

### Step 24: Restore deleted per-content editing kernel tests

- **Type:** Code Change (addresses reviewer concern about ~1000 lines of deleted tests)
- **File:** `web/modules/contrib/canvas/tests/src/Kernel/NodeTemplatesTest.php` — **Modify**
- **Details:** Restore the 15 deleted test methods from the `main` branch. Use `git checkout main -- web/modules/contrib/canvas/tests/src/Kernel/NodeTemplatesTest.php` to get the complete file, then verify all tests pass.

  **Deleted test methods to restore:**
  1. `testGetCanvasFieldNameWithExposedSlots` — Verifies `ComponentTreeLoader::getCanvasFieldName()` works for nodes with enabled templates + exposed slots
  2. `testGetCanvasFieldNameThrowsWithoutExposedSlots` — Verifies `LogicException` for nodes without exposed slots
  3. `testGetCanvasFieldNameThrowsForDisabledTemplate` — Verifies `LogicException` for nodes with disabled templates
  4. `testSlotTreeExtractor` — Tests `SlotTreeExtractor::extractSlotSubtree()` and `filterLayoutToSlotComponents()`
  5. `testGetMergedComponentTree` — Tests `ContentTemplate::getMergedComponentTree()` returns correctly merged tree
  6. `testHasContentTemplateWithExposedSlots` — Tests `ComponentTreeLoader::hasContentTemplateWithExposedSlots()`
  7. `testAccessCheckerReturns403ForIneligibleEntities` — Tests `ComponentTreeEditAccessCheck` returns 403 (not 500) for non-eligible entities
  8. `testAccessCheckerReturns403ForDisabledTemplateWithSlots` — Tests access check for disabled template scenario
  9. `testSlotAwarePostSavePersistsOnlySlotSubtree` — Integration test: POST with full merged tree, verify only slot components stored on node
  10. `testSlotAwarePatchGuardRejectsTemplateComponents` — Integration test: PATCH template-owned component → 403
  11. `testGetMergedModeEditableMetadata` — Tests `editable` flag annotation on API response
  12. `testAutoSaveRoundTripSlotData` — Tests auto-save data round-trip for slot content
  13. `testNodeLayoutAccessCheck` — Tests `NodeLayoutAccessCheck::access()` for eligible and ineligible nodes
  14. `testNodeLayoutAccessCheckForbiddenWithoutCanvasField` — Tests access check when Canvas field is missing
  15. `testTemplateContextLogic` — Tests `CanvasController::getTemplateContext()` returns correct data

  These tests provide comprehensive coverage of all backend per-content editing code paths including the critical access check behavior (Finding 1), slot tree extraction, API controller logic, and access checks.

- **Expected outcome:** All 15 restored test methods pass. Combined with the 2 existing test methods, this provides 17 test methods with comprehensive coverage.
- **Depends on:** Steps 1-9

### Step 25: PHPCS compliance check

- **Type:** Verification
- **Details:** Run PHPCS on all modified files:
  ```bash
  ddev exec vendor/bin/phpcbf web/modules/contrib/canvas/src/Storage/SlotTreeExtractor.php web/modules/contrib/canvas/src/Access/NodeLayoutAccessCheck.php web/modules/contrib/canvas/src/Access/ComponentTreeEditAccessCheck.php
  ddev exec vendor/bin/phpcs web/modules/contrib/canvas/src/Storage/SlotTreeExtractor.php web/modules/contrib/canvas/src/Access/NodeLayoutAccessCheck.php web/modules/contrib/canvas/src/Access/ComponentTreeEditAccessCheck.php web/modules/contrib/canvas/src/Controller/ApiLayoutController.php web/modules/contrib/canvas/src/Controller/CanvasController.php web/modules/contrib/canvas/src/Storage/ComponentTreeLoader.php web/modules/contrib/canvas/src/Entity/ContentTemplate.php
  ```
  Note: This is a contrib module, so PHPCS scope may not cover it by default. Run with explicit file paths.
- **Expected outcome:** All PHP files pass Drupal coding standards.
- **Depends on:** Steps 1-9, 23

### Step 26: Run kernel tests and verify coverage

- **Type:** Verification
- **Details:** Run the Canvas module's kernel tests to verify backend changes:
  ```bash
  ddev exec vendor/bin/phpunit web/modules/contrib/canvas/tests/src/Kernel/NodeTemplatesTest.php
  ```
  After Step 24 (test restoration), this should run **17 test methods** covering:
  - `testOptContentTypeIntoCanvas` with 2 `#[TestWith]` data providers (existing)
  - `testExposedSlotsAreFilledByEntity` (existing)
  - 15 restored per-content editing test methods (Step 24)

  Key test assertions to verify:
  - `testAccessCheckerReturns403ForIneligibleEntities` — confirms Step 1's try-catch fix works
  - `testSlotTreeExtractor` — confirms Step 2's restored service works
  - `testGetCanvasFieldNameWithExposedSlots` — confirms Step 4's scope expansion works
  - `testNodeLayoutAccessCheck` — confirms Step 9's access check works
  - `testSlotAwarePostSavePersistsOnlySlotSubtree` — confirms Step 7's save logic works

- **Expected outcome:** All 17 test methods pass. No regressions.
- **Depends on:** Steps 1-9, 24

---

## Code Changes Summary

| File | Change Type | Description |
|------|------------|-------------|
| `web/modules/contrib/canvas/src/Access/ComponentTreeEditAccessCheck.php` | **Modify** | Restore try-catch around `$this->componentTreeLoader->load($entity)` — prevents 500 errors for non-eligible entities |
| `web/modules/contrib/canvas/src/Storage/SlotTreeExtractor.php` | **Create** | Restore from main — UUID-based filter for extracting slot components from merged trees |
| `web/modules/contrib/canvas/src/Access/NodeLayoutAccessCheck.php` | **Create** | Restore from main — access check for node layout route (template + exposed slots + Canvas field + update access) |
| `web/modules/contrib/canvas/canvas.services.yml` | Modify | Register SlotTreeExtractor (`{}`) + NodeLayoutAccessCheck (`{}`, no tags) services |
| `web/modules/contrib/canvas/canvas.routing.yml` | Modify | Add `canvas.node.layout` route with `_custom_access: 'Drupal\canvas\Access\NodeLayoutAccessCheck::access'` |
| `web/modules/contrib/canvas/canvas.links.task.yml` | Modify | Add "Layout" tab for nodes with **weight: 20** |
| `web/modules/contrib/canvas/src/Storage/ComponentTreeLoader.php` | Modify | Restore `hasContentTemplateWithExposedSlots()`, update `getCanvasFieldName()` to allow nodes with exposed-slot templates |
| `web/modules/contrib/canvas/src/Entity/ContentTemplate.php` | Modify | Restore `getMergedComponentTree()`, add `exposedSlots` to `normalizeForClientSide()`, auto-provisioning in `postSave()` |
| `web/modules/contrib/canvas/src/Controller/ApiLayoutController.php` | Modify | Restore per-content editing in `get()`/`patch()`/`post()`/`updateEntity()`/`buildPreviewRenderable()`/`buildLayoutAndModel()`, restore `getPerContentTemplate()`, add `SlotTreeExtractor` dependency |
| `web/modules/contrib/canvas/src/Controller/CanvasController.php` | Modify | Restore `nodeLayout()`, `getTemplateContext()`, `templateContext` in drupalSettings, `bundle` in create links |
| `web/modules/contrib/canvas/tests/src/Kernel/NodeTemplatesTest.php` | Modify | Restore 15 deleted per-content editing test methods from main |
| `web/modules/contrib/canvas/ui/src/types/DrupalSettings.ts` | Modify | Add `templateContext`, `permissions`, `contentEntityCreateOperations`, `homepagePath` |
| `web/modules/contrib/canvas/ui/src/features/layout/layoutModelSlice.ts` | Modify | Add `editable?: boolean` to `ComponentNode` |
| `web/modules/contrib/canvas/ui/src/services/componentAndLayout.ts` | Modify | Add `exposedSlots`/`contentTemplateId` to `LayoutApiResponse`, `exposedSlots` to `TemplateViewMode`, update `getPageLayout.onQueryStarted` to dispatch API response per-content data, update `postTemplateLayout` to send `exposed_slots` |
| `web/modules/contrib/canvas/ui/src/features/ui/uiSlice.ts` | Modify | Add `templateContext` state, `setTemplateContext` reducer, `selectTemplateContext` selector |
| `web/modules/contrib/canvas/ui/src/app/AppRoutes.tsx` | Modify | Verify existing route works for per-content editing; add `/node-layout/:entityId` route only if needed |
| `web/modules/contrib/canvas/ui/src/features/layout/previewOverlay/ComponentOverlay.tsx` | Modify | Add locking: disable drag (useDraggable disabled), skip selection on click, dim/gray locked components, hide drop zones and context menu for template-owned components |
| `web/modules/contrib/canvas/ui/src/features/layout/previewOverlay/SlotOverlay.tsx` | Modify | Restrict drops to exposed slots only; add "Expose this slot" context menu in template editing mode |
| `web/modules/contrib/canvas/ui/src/features/layout/previewOverlay/PreviewOverlay.module.css` | Modify | Add `.locked` CSS class for dimmed template-owned components |
| `web/modules/contrib/canvas/ui/src/features/layout/layers/ComponentLayer.tsx` | Modify | Add locking: disable useDraggable (line 64), skip selection (line 73), set `draggable={false}` on SidebarNode (line 148), add lock icon, remove context menu for locked components |
| `web/modules/contrib/canvas/ui/src/features/layout/layers/SlotLayer.tsx` | Modify | Disable drops on non-exposed slots; add "Expose this slot" dropdown menu item in template editing mode |
| `web/modules/contrib/canvas/ui/src/features/layout/layers/ComponentLayer.module.css` | Modify | Add `.locked` CSS class for muted tree node styling |
| `web/modules/contrib/canvas/ui/src/components/list/TemplateList.tsx` | Modify | Show exposed slots badge on templates |
| `web/modules/contrib/canvas/ui/src/components/ComponentInstanceForm.tsx` | Modify | Show locked message for template-owned components (fallback if selection prevention fails) |
| `web/modules/contrib/canvas/ui/src/features/layout/preview/ExposeSlotDialog.tsx` | **Create** | New dialog component for exposed slot naming — follows `SavePatternDialog.tsx` pattern |
| `web/modules/contrib/canvas/ui/src/features/layout/preview/Preview.tsx` | Modify | Pass `exposed_slots` in POST body when saving templates |

## New Scripts Needed

| Script Name | Purpose | Parameters | Why No Existing Script |
|-------------|---------|------------|------------------------|
| None | — | — | All structural/config changes are handled by direct code changes to the contrib module. The `update-article-content-template.drush.php` capability script already exists to set up test data. |

## Risks & Dependencies

1. **Branch state complexity**: The current branch has reverted ATWEB-7 backend changes. Steps 1-9 re-implement them. Risk: merge conflicts with other branches that may have these changes. **Mitigation**: Use `git checkout main -- <file>` to restore files cleanly, then verify with `git diff`.

2. **Single exposed slot constraint**: The backend enforces `assert(count($this->getExposedSlots()) === 1)`. The frontend must respect this. **Mitigation**: UI prevents exposing more than one slot (Step 20.6).

3. **`ValidExposedSlot: full` view mode restriction**: Exposed slots only work for the `full` view mode. **Mitigation**: Frontend only offers slot exposure when editing a template with `viewMode === 'full'` (Step 20.7).

4. **Translation implications**: Per-content slot editing is untested for translated content. **Mitigation**: Flag as known gap, do not attempt to support translations in this ticket.

5. **Canvas field auto-provisioning**: Creating fields programmatically in `postSave()` is sensitive — triggers schema changes. **Mitigation**: Use `postSave()` (not `preSave()`) for safety. Always check if field already exists before creating.

6. **`getCanvasFieldName()` scope expansion** (Step 4): Removing the `drupal_valid_test_ua()` guard opens Canvas fields to all node bundles with exposed-slot templates in production. **Mitigation**: This is intentional (implements https://drupal.org/i/3498525). `NodeLayoutAccessCheck` (Step 9) and `ComponentTreeEditAccessCheck` (Step 1) provide multi-layer access control.

7. **Frontend scope**: Work streams 2, 3, and 4 (~1400-2000 lines of frontend changes) are the highest-complexity items. **Mitigation**: Break into sub-steps with specific file references and line numbers (Steps 17-20).

8. **`ApiContentControllers` is out of scope** (Step 10): The Canvas content listing sidebar will only show `canvas_page` entities. Node-based per-content editing is accessed via the Drupal admin node view page "Layout" tab. This is an acceptable limitation for this ticket — the `ApiContentControllers` generalization is tracked separately.

## Verification Criteria

1. **Backend**: `ddev exec vendor/bin/phpunit web/modules/contrib/canvas/tests/src/Kernel/NodeTemplatesTest.php` — all 17 test methods pass (2 existing + 15 restored)
2. **Backend**: `GET /canvas/api/v0/layout/node/{node_id}` returns merged tree with `editable`, `exposedSlots`, `contentTemplateId` for nodes with enabled templates
3. **Backend**: `PATCH` to template-owned component UUID returns 403
4. **Backend**: `POST` correctly extracts slot subtree when saving (only slot components stored on node's Canvas field)
5. **Backend**: `/node/{node}/layout` renders Canvas UI with templateContext in drupalSettings
6. **Backend**: Layout tab appears on node view pages for eligible content types (weight 20)
7. **Backend**: Layout API routes return 403 (not 500) for non-eligible entities (Step 1 verification)
8. **Frontend**: `DrupalSettings` TypeScript compiles without errors
9. **Frontend**: Template sidebar shows exposed slot indicators
10. **Frontend**: Per-content editor dims/locks template-owned components in both preview overlay (`ComponentOverlay.tsx`) and tree sidebar (`ComponentLayer.tsx`)
11. **Frontend**: Only exposed slot drop zones accept new component drops (both `SlotOverlay.tsx` and `SlotLayer.tsx`)
12. **Frontend**: Template editor allows exposing a slot with naming dialog (`ExposeSlotDialog.tsx`)
13. **Frontend**: Saving template with exposed_slots persists to backend
14. **Frontend**: `getPageLayout` dispatches `exposedSlots`/`contentTemplateId` to Redux on API response
15. **PHPCS**: All modified PHP files pass coding standards
16. **Manual**: End-to-end: create template → expose slot → navigate to node → edit slot content → save → view node renders correctly

## Reviewer Feedback Resolution Summary

| Reviewer Concern | Resolution | Step |
|------------------|------------|------|
| ComponentTreeEditAccessCheck try-catch removal → 500 errors | **Fixed**: Added explicit Step 1 to restore try-catch | Step 1 |
| NodeLayoutAccessCheck tagged access check pattern mismatch | **Fixed**: Changed to `_custom_access` pattern matching original code; service registered without tags | Step 9 |
| Task link weight 100 → should be 20 | **Fixed**: Changed to weight 20 | Step 9 |
| ~1000 lines of deleted tests not restored | **Fixed**: Added explicit Step 24 to restore all 15 deleted test methods from main | Step 24 |
| ApiContentControllers reverted changes not addressed | **Fixed**: Added explicit Step 10 documenting scope decision — out of scope for this ticket, with justification | Step 10 |
| Step 18 (exposed slot UI) lacks specific file references | **Fixed**: Step 20 now lists 10 specific files with line counts and explicit descriptions of what changes in each | Step 20 |
| SlotTreeExtractor over-engineers client/server distinction | **Fixed**: Step 2 now restores original code from main with original API, no client/server framing | Step 2 |
