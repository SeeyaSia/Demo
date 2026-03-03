# DCANV-3: Refactor Canvas Per-Content Editing — Layout Tab to Field Widget Link

## Ticket Analysis

- **Core request:** Replace the per-node "Layout" tab (`/node/{node}/layout`) with a per-field "Edit in Canvas" link rendered inside the `ComponentTreeWidget` on the node edit form. Support N canvas fields per content type, switch to `hook_entity_bundle_field_info()` for field provisioning, and add workspace-compatible slot lifecycle.
- **Affected areas:** Canvas contrib module (`drupal/canvas` 1.1.0) — routing, services, access checks, field widget, entity, config schema, storage, controller, hooks, tests.
- **Module version:** `drupal/canvas` 1.1.0 (from `composer.lock`)
- **Phase 0 status:** The preparatory shape matcher refactoring (stricter required-prop-to-required-field matching, removal of fallback infrastructure) was committed in `055a8f3d`.
- **Deferred: machine-name-based slot references (#3528458):** The ticket lists "adopt machine-name-based slot references" as goal #4. After analysis, this is **deferred to a dedicated #3528458 ticket** for the following reasons:
  1. The existing `exposed_slots` schema already uses the Drupal field machine name as the key (e.g., `field_canvas_body`). This key IS a stable machine-name identifier for the field-to-slot mapping — it does not change when the template tree is reorganized.
  2. The #3528458 concern is specifically about decoupling the *template tree position* (`component_uuid` + `slot_name`) from the slot identity, so that reorganizing a template's wrapper components doesn't break existing slot mappings. This is an important but independent enhancement.
  3. This plan fully implements the field-scoped architecture (N fields, field widget link, hook provisioning, workspaces compatibility) that #3528458 would build upon. Adding `slot_machine_name` resolution logic to `injectSubTreeItemList()` and `SlotTreeExtractor` is cleanly additive and doesn't require changes to the architecture established here.
  4. Implementing #3528458 alongside this refactor would increase scope and risk without a concrete immediate need — the current UUID-based resolution works correctly as long as templates are not reorganized, and the field-to-slot mapping is already stable via the slot key.

---

## Capability Script Catalog

| Script | Purpose | Parameters |
|--------|---------|------------|
| `setup/add-canvas-field.drush.php` | Adds Canvas `component_tree` field to a content type (storage + instance + form/view display) | `CONTENT_TYPE`, `FIELD_NAME`, `FIELD_LABEL` |
| `setup/create-content-template.drush.php` | Creates a ContentTemplate with root wrapper and exposed slot | `CONTENT_TYPE`, `VIEW_MODE`, `FIELD_NAME`, `SLOT_LABEL` |
| `tests/canvas-test-per-content-editing.drush.php` | Tests per-content editing workflow (exposed slots, field, injection, merging, rendering, extractor) | — |
| `tests/canvas-test-run-all.drush.php` | Runs full Canvas test suite (5 tests) | — |
| `diagnostics/canvas-setup-overview.drush.php` | Full Canvas environment diagnostic | — |

No new capability scripts are needed — all changes are code-level modifications to the Canvas contrib module.

---

## Key Codebase Context

### Current exposed_slots schema
```yaml
# In canvas.content_template.node.page.full.yml
exposed_slots:
  field_canvas_body:                    # ← KEY = the field machine name
    component_uuid: c3bd808c-...       # ← UUID of the wrapper component in the template tree
    slot_name: content                 # ← which slot on that component
    label: 'Page content'              # ← human label
```
**Critical:** The exposed slot KEY is already the Drupal field machine name (e.g., `field_canvas_body`). The key IS the machine name — do NOT prefix with `field_` again.

### Current auto-provisioning
`ContentTemplate::ensureCanvasFieldExists()` (line 184) hardcodes `field_component_tree` as the field name, BUT first checks if ANY `component_tree` field already exists on the bundle (line 196-202) and returns early if so. Since the capability script `add-canvas-field.drush.php` creates the field with the correct name (e.g., `field_canvas_body`) before template creation, the hardcoded name is typically never used.

### Current N-field limitation
`getMergedComponentTree()` (line 432) has `\assert(count($this->getExposedSlots()) === 1)` and `getCanvasFieldName()` returns the FIRST `component_tree` field found. Both assume a single field per entity. The `@todo` at line 430-431 explicitly references drupal.org #3526189.

### injectSubTreeItemList behavior
The method (line 525-581) validates that target slots are empty, then collects items from the sub-tree whose ancestry traces back to exposed slot UUIDs. For N-slot merging: calling it once per slot with that slot's subset of `exposed_slot_info` and that slot's field data works correctly — each call finds its target slot empty and injects only into that slot.

---

## Implementation Plan

### Phase 1: Field Widget Link (Replaces Layout Tab)

#### Step 1.1: Create new route `canvas.entity.field_layout`

- **Type:** Code change
- **File:** `web/modules/contrib/canvas/canvas.routing.yml`
- **Details:** Add a new route after `canvas.node.layout` (line 565) that includes `{field_name}` in the path. This route is entity-type-agnostic and field-scoped.
- **Add:**
  ```yaml
  canvas.entity.field_layout:
    path: '/canvas/layout/{entity_type}/{entity}/{field_name}'
    defaults:
      _controller: 'Drupal\canvas\Controller\CanvasController::fieldLayout'
      _title: 'Layout'
    requirements:
      _custom_access: 'Drupal\canvas\Access\FieldLayoutAccessCheck::access'
    options:
      parameters:
        entity:
          type: entity:{entity_type}
  ```
- **Expected outcome:** Route registered for per-field Canvas editor access.
- **Depends on:** None

#### Step 1.2: Create `FieldLayoutAccessCheck` class

- **Type:** Code change (new file)
- **File:** `web/modules/contrib/canvas/src/Access/FieldLayoutAccessCheck.php`
- **Details:** Create a generalized access checker that replaces `NodeLayoutAccessCheck`. Follow the existing class at `src/Access/NodeLayoutAccessCheck.php` (48 lines) as a template.
- **Class structure:**
  ```php
  <?php
  declare(strict_types=1);

  namespace Drupal\canvas\Access;

  use Drupal\Core\Access\AccessResult;
  use Drupal\Core\Access\AccessResultInterface;
  use Drupal\Core\Session\AccountInterface;
  use Drupal\canvas\Entity\ContentTemplate;
  use Drupal\Core\Entity\FieldableEntityInterface;

  /**
   * Access check for field-scoped Canvas layout editing.
   *
   * @internal
   */
  final class FieldLayoutAccessCheck {

    public function access(
      FieldableEntityInterface $entity,
      string $field_name,
      AccountInterface $account,
    ): AccessResultInterface {
      // 1. Verify entity has the specified field and it is component_tree type.
      if (!$entity->hasField($field_name)) {
        return AccessResult::forbidden("Entity does not have field '$field_name'.");
      }
      $field_def = $entity->getFieldDefinition($field_name);
      if ($field_def->getType() !== 'component_tree') {
        return AccessResult::forbidden("Field '$field_name' is not a component_tree field.");
      }

      // 2. Load ContentTemplate for entity bundle + full view mode.
      $template = ContentTemplate::loadForEntity($entity, 'full');
      if (!$template || !$template->status()) {
        return AccessResult::forbidden('No enabled content template.')
          ->addCacheableDependency($template ?? AccessResult::neutral());
      }

      // 3. Check exposed_slots has a key matching $field_name.
      $slots = $template->getExposedSlots();
      if (!isset($slots[$field_name])) {
        return AccessResult::forbidden("Field '$field_name' is not mapped to an exposed slot.")
          ->addCacheableDependency($template);
      }

      // 4. Check entity update access.
      return $entity->access('update', $account, TRUE)
        ->addCacheableDependency($template);
    }
  }
  ```
- **Expected outcome:** Entity-agnostic, field-scoped access checker.
- **Depends on:** Step 1.1

#### Step 1.3: Register `FieldLayoutAccessCheck` as a service

- **Type:** Code change
- **File:** `web/modules/contrib/canvas/canvas.services.yml`
- **Details:** Add service registration near the existing `NodeLayoutAccessCheck` line (~line 149):
  ```yaml
  Drupal\canvas\Access\FieldLayoutAccessCheck: {}
  ```
- **Expected outcome:** Service available for route access checking via autowiring.
- **Depends on:** Step 1.2

#### Step 1.4: Extend `__invoke()` and `getTemplateContext()` to pass `targetFieldName`

> **Implementation order note:** This step (formerly 1.5) must be implemented **before** Step 1.5 (formerly 1.4), because `fieldLayout()` passes `$field_name` as the 4th argument to `__invoke()`, which only accepts that parameter after this step is applied.

- **Type:** Code change
- **File:** `web/modules/contrib/canvas/src/Controller/CanvasController.php`
- **Details:** The Canvas React frontend needs to know which field/slot is being edited to scope the editor correctly. Extend `__invoke()` with an optional `$target_field_name` parameter and merge it into the template context.
- **Changes:**
  1. Add `?string $target_field_name = NULL` as 4th parameter to `__invoke()` (line ~92).
  2. At line ~209 where `templateContext` is built, extract and extend:
     ```php
     $templateContext = $entity !== NULL && $entity instanceof FieldableEntityInterface
       ? $this->getTemplateContext($entity)
       : NULL;
     if ($target_field_name !== NULL && $templateContext !== NULL) {
       $templateContext['targetFieldName'] = $target_field_name;
     }
     ```
  3. Use the extracted `$templateContext` variable in the drupalSettings array instead of the inline call.
- **Expected outcome:** Frontend receives `drupalSettings.canvas.templateContext.targetFieldName` to scope the editor to a specific slot.
- **Depends on:** Step 1.1

#### Step 1.5: Add `CanvasController::fieldLayout()` method

- **Type:** Code change
- **File:** `web/modules/contrib/canvas/src/Controller/CanvasController.php`
- **Details:** Add a new public method after `nodeLayout()` (line 411). It accepts `string $entity_type`, `FieldableEntityInterface $entity`, `string $field_name` and delegates to `__invoke()`, passing the field name for frontend scoping.
- **Implementation:**
  ```php
  /**
   * Renders the Canvas editor for per-field layout editing.
   */
  public function fieldLayout(string $entity_type, FieldableEntityInterface $entity, string $field_name): HtmlResponse {
    return $this->__invoke(
      $entity_type,
      $entity,
      Url::fromRoute('canvas.entity.field_layout', [
        'entity_type' => $entity_type,
        'entity' => $entity->id(),
        'field_name' => $field_name,
      ])->getInternalPath(),
      $field_name,
    );
  }
  ```
- **Add import:** `use Drupal\Core\Url;` (if not already present — check existing imports).
- **Expected outcome:** Controller method handles per-field Canvas editor rendering.
- **Depends on:** Steps 1.1, 1.4

#### Step 1.6: Refactor `ComponentTreeWidget::formElement()` to render editor link

- **Type:** Code change
- **File:** `web/modules/contrib/canvas/src/Plugin/Field/FieldWidget/ComponentTreeWidget.php`
- **Details:** Replace the passive placeholder markup (line ~52) with an active "Edit in Canvas" link. The widget should:
  1. Get the entity from `$items->getEntity()` and field name from `$items->getName()`.
  2. If entity is new (no ID): render "Save this content first, then edit in Canvas."
  3. Load `ContentTemplate::loadForEntity($entity, 'full')` and check `getExposedSlots()` for this field name.
  4. If template + slot found: render a `#type => 'link'` to `canvas.entity.field_layout` with `target: _blank`.
  5. If no template/slot found: render "This canvas field is not yet assigned to a slot in the content template."
  6. Keep `extractFormValues()` as a no-op (unchanged).
- **Implementation:**
  ```php
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $entity = $items->getEntity();
    $field_name = $items->getName();

    $element['#type'] = 'container';

    // Entity must be saved before Canvas editing is available.
    if ($entity->isNew()) {
      $element['message'] = [
        '#markup' => '<p>' . $this->t('Save this content first, then edit in Canvas.') . '</p>',
      ];
      return $element;
    }

    // Check for an enabled content template with this field mapped to a slot.
    $template = ContentTemplate::loadForEntity($entity, 'full');
    if ($template && $template->status() && isset($template->getExposedSlots()[$field_name])) {
      $slot = $template->getExposedSlots()[$field_name];
      $element['link'] = [
        '#type' => 'link',
        '#title' => $this->t('Edit in Canvas'),
        '#url' => Url::fromRoute('canvas.entity.field_layout', [
          'entity_type' => $entity->getEntityTypeId(),
          'entity' => $entity->id(),
          'field_name' => $field_name,
        ]),
        '#attributes' => [
          'class' => ['button', 'button--primary', 'canvas-editor-link'],
          'target' => '_blank',
        ],
      ];
      $element['description'] = [
        '#markup' => '<p class="description">' . $this->t('Opens the Canvas editor for the %slot_label slot.', ['%slot_label' => $slot['label']]) . '</p>',
      ];
    }
    else {
      $element['message'] = [
        '#markup' => '<p>' . $this->t('This canvas field is not yet assigned to a slot in the content template.') . '</p>',
      ];
    }

    return $element;
  }
  ```
- **Add imports:**
  ```php
  use Drupal\canvas\Entity\ContentTemplate;
  use Drupal\Core\Url;
  ```
- **Expected outcome:** Node edit form shows a clickable "Edit in Canvas" link per canvas field.
- **Depends on:** Steps 1.1, 1.5

#### Step 1.7: Update `ContentTemplate::ensureCanvasFieldExists()` to configure form display

- **Type:** Code change
- **File:** `web/modules/contrib/canvas/src/Entity/ContentTemplate.php`
- **Details:** The current `ensureCanvasFieldExists()` (line 184-231) creates field storage and field config but does NOT configure the form display widget. For the field widget link to appear on the node edit form, the form display must have `canvas_component_tree_widget` configured. Add form display configuration after field creation (after line 229):
  ```php
  // Configure form display widget so the editor link appears on the edit form.
  $form_display = EntityFormDisplay::load($entity_type_id . '.' . $bundle . '.default');
  if ($form_display && !$form_display->getComponent($field_name)) {
    $form_display->setComponent($field_name, [
      'type' => 'canvas_component_tree_widget',
      'weight' => 100,
    ])->save();
  }
  ```
- **Add import:** `use Drupal\Core\Entity\Entity\EntityFormDisplay;`
- **Note:** This step is superseded by Step 2.1 which rebuilds `ensureCanvasFieldExists()` entirely. Include the form display logic in the Phase 1 commit for immediate functionality, then the Phase 2 rewrite replaces it.
- **Expected outcome:** Auto-provisioned canvas fields automatically appear on the node edit form with the correct widget.
- **Depends on:** None (can be done in parallel with other Phase 1 steps)

#### Step 1.8: Remove Layout Tab artifacts

- **Type:** Code change (deletions)
- **Files to modify:**
  1. **`canvas.links.task.yml`** — Remove the `canvas.entity.node.layout` entry (lines 32-36):
     ```yaml
     canvas.entity.node.layout:
       route_name: canvas.node.layout
       title: 'Layout'
       base_route: entity.node.canonical
       weight: 20
     ```
  2. **`canvas.routing.yml`** — Remove the `canvas.node.layout` route (lines 565-575):
     ```yaml
     canvas.node.layout:
       path: '/node/{node}/layout'
       # ... entire block ...
     ```
  3. **`canvas.services.yml`** — Remove `Drupal\canvas\Access\NodeLayoutAccessCheck: {}` (~line 149).
  4. **`src/Controller/CanvasController.php`** — Remove the `nodeLayout()` method (lines 406-411) and its docblock.
  5. **`src/Access/NodeLayoutAccessCheck.php`** — Delete this file entirely.
- **Expected outcome:** Layout tab is completely removed; no orphan routes/services/code remain.
- **Depends on:** Steps 1.2, 1.3, 1.5 (new route + access check + controller must be in place first)

#### Step 1.9: Update `ComponentTreeLoader::getCanvasFieldName()` to support named lookup

- **Type:** Code change
- **File:** `web/modules/contrib/canvas/src/Storage/ComponentTreeLoader.php`
- **Details:** The current method (lines 57-79) returns the FIRST `component_tree` field found. Add an optional `$field_name` parameter for explicit field lookup:
  ```php
  public function getCanvasFieldName(FieldableEntityInterface $entity, ?string $field_name = NULL): string {
    // Existing guard: entity must be canvas_page or have content template with exposed slots.
    if ($entity->getEntityTypeId() !== Page::ENTITY_TYPE_ID && !$this->hasContentTemplateWithExposedSlots($entity)) {
      throw new \LogicException(sprintf(
        'Entity type "%s" bundle "%s" does not support Canvas component tree editing.',
        $entity->getEntityTypeId(),
        $entity->bundle(),
      ));
    }

    // When a specific field is requested, verify it exists and is component_tree.
    if ($field_name !== NULL) {
      $map = $this->entityFieldManager->getFieldMapByFieldType(ComponentTreeItem::PLUGIN_ID);
      if (isset($map[$entity->getEntityTypeId()][$field_name])
        && in_array($entity->bundle(), $map[$entity->getEntityTypeId()][$field_name]['bundles'], TRUE)) {
        return $field_name;
      }
      throw new \LogicException("Field '$field_name' is not a component_tree field on this entity.");
    }

    // Existing fallback: return first component_tree field found.
    $map = $this->entityFieldManager->getFieldMapByFieldType(ComponentTreeItem::PLUGIN_ID);
    foreach ($map[$entity->getEntityTypeId()] ?? [] as $found_field_name => $info) {
      if (in_array($entity->bundle(), $info['bundles'], TRUE)) {
        return $found_field_name;
      }
    }
    throw new \LogicException("This entity does not have a Canvas field!");
  }
  ```
- **Expected outcome:** `ComponentTreeLoader` can resolve specific canvas fields by name while maintaining backward compatibility.
- **Depends on:** None

---

### Phase 2: N-Slot Field Architecture

#### Step 2.1: Update `ensureCanvasFieldExists()` for per-slot field names

- **Type:** Code change
- **File:** `web/modules/contrib/canvas/src/Entity/ContentTemplate.php`
- **Details:** Replace the hardcoded `field_component_tree` with per-slot field creation. The exposed slot KEY is already the field machine name (e.g., `field_canvas_body`). **Do NOT add a `field_` prefix — the key already includes it.**
- **Replace** the method body (lines 184-231) with:
  ```php
  private function ensureCanvasFieldExists(): void {
    $entity_type_id = $this->getTargetEntityTypeId();
    $bundle = $this->getTargetBundle();

    foreach ($this->getExposedSlots() as $field_name => $slot_detail) {
      // The exposed slot key IS the field machine name (e.g., field_canvas_body).
      // Check if this specific field already exists on the bundle.
      $config = FieldConfig::loadByName($entity_type_id, $bundle, $field_name);
      if ($config !== NULL) {
        continue;
      }

      // Create field storage if it doesn't exist.
      $storage = FieldStorageConfig::loadByName($entity_type_id, $field_name);
      if ($storage === NULL) {
        FieldStorageConfig::create([
          'field_name' => $field_name,
          'entity_type' => $entity_type_id,
          'type' => ComponentTreeItem::PLUGIN_ID,
        ])->save();
      }

      // Create field instance on the bundle.
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type_id,
        'bundle' => $bundle,
        'label' => $slot_detail['label'] ?? 'Canvas Layout',
      ])->save();

      // Configure form display widget.
      $form_display = EntityFormDisplay::load($entity_type_id . '.' . $bundle . '.default');
      if ($form_display && !$form_display->getComponent($field_name)) {
        $form_display->setComponent($field_name, [
          'type' => 'canvas_component_tree_widget',
          'weight' => 100,
        ])->save();
      }
    }
  }
  ```
- **Note:** This replaces the Phase 1 interim version (Step 1.7).
- **Expected outcome:** Each exposed slot gets its own canvas field on the entity bundle, named by the slot key.
- **Depends on:** Phase 1

#### Step 2.2: Update `getMergedComponentTree()` for N fields

- **Type:** Code change
- **File:** `web/modules/contrib/canvas/src/Entity/ContentTemplate.php`
- **Details:** The current method (lines 426-439) has `\assert(count($this->getExposedSlots()) === 1)` and loads a single canvas field. Update to iterate over all exposed slots:
  ```php
  public function getMergedComponentTree(FieldableEntityInterface $entity): ComponentTreeItemList {
    if ($entity instanceof ComponentTreeEntityInterface) {
      throw new \LogicException('Content templates cannot be applied to entities that have their own component trees.');
    }

    $merged = $this->getComponentTree($entity);
    foreach ($this->getExposedSlots() as $slot_key => $slot_detail) {
      // The slot key IS the field machine name (e.g., field_canvas_body).
      if ($entity->hasField($slot_key)) {
        $sub_tree = $entity->get($slot_key);
        \assert($sub_tree instanceof ComponentTreeItemList);
        $merged = $merged->injectSubTreeItemList(
          [$slot_key => $slot_detail],
          $sub_tree,
        );
      }
    }
    return $merged;
  }
  ```
- **Key behavior:** Each `injectSubTreeItemList()` call injects into one slot. The "target slot must be empty" validation (line 535-540 of `ComponentTreeItemList.php`) passes because each slot starts empty in the template tree, and each call only injects into its own slot.
- **Expected outcome:** Merged tree correctly composes N slot fields into the template.
- **Depends on:** Step 2.1

#### Step 2.3: Update `ApiLayoutController::updateEntity()` for N fields

- **Type:** Code change
- **File:** `web/modules/contrib/canvas/src/Controller/ApiLayoutController.php`
- **Details:** The `updateEntity()` method's per-content editing branch (inside `if ($perContentTemplate !== NULL)`, ~line 664) saves slot components to a single canvas field. For N fields, the save logic must partition slot components by which exposed slot they belong to.
- **Replaces:** Lines 687-688 of the current `updateEntity()` method — the existing single-field save pattern (`$item_list = $this->componentTreeLoader->load($entity); $item_list->setValue($slot_items);`) is **entirely replaced** by the per-slot partition loop below. The `load()` + `setValue()` pattern is removed because it targets only the first `component_tree` field; the new code saves to each field independently.
- **Updated per-content editing logic:**
  1. Convert the full merged layout to server-side tree items (existing behavior).
  2. Filter out template-owned components using `SlotTreeExtractor::getTemplateUuidMap()` (existing behavior).
  3. **New:** For each exposed slot, filter the remaining slot items to those that are descendants of that slot's `component_uuid`/`slot_name` position. Use parent-chain traversal (same algorithm as `injectSubTreeItemList()`'s second pass at lines 556-568 of `ComponentTreeItemList.php`, but in reverse).
  4. Save each partition to its corresponding field (the slot key = field name).
- **Partition implementation pattern:**
  ```php
  foreach ($template->getExposedSlots() as $slot_key => $slot_detail) {
    // Collect direct children of the slot's target position.
    $slot_items = [];
    $slot_uuids = [];
    foreach ($remaining_items as $item) {
      if (($item['parent_uuid'] ?? NULL) === $slot_detail['component_uuid']
        && ($item['slot'] ?? NULL) === $slot_detail['slot_name']) {
        $slot_items[] = $item;
        $slot_uuids[$item['uuid']] = TRUE;
      }
    }
    // Transitively collect all descendants.
    $changed = TRUE;
    while ($changed) {
      $changed = FALSE;
      foreach ($remaining_items as $item) {
        if (!isset($slot_uuids[$item['uuid']]) && isset($slot_uuids[$item['parent_uuid'] ?? ''])) {
          $slot_items[] = $item;
          $slot_uuids[$item['uuid']] = TRUE;
          $changed = TRUE;
        }
      }
    }
    if ($entity->hasField($slot_key)) {
      $entity->set($slot_key, $slot_items);
    }
  }
  ```
- **Expected outcome:** Per-content editing saves components to the correct per-slot field.
- **Depends on:** Step 2.2

#### Step 2.4: Review and update related controller methods

- **Type:** Code change
- **Files:**
  - `web/modules/contrib/canvas/src/Controller/ApiLayoutController.php`
  - `web/modules/contrib/canvas/src/Controller/CanvasController.php`
- **Details:** Review all methods that interact with per-content editing data:
  1. **`ApiLayoutController::get()` — field-scoped editability via query parameter:**
     - **Query parameter:** `canvas_target_field` (string, optional). The React frontend reads `drupalSettings.canvas.templateContext.targetFieldName` (set in Step 1.4) and appends it as `?canvas_target_field={field_name}` on API requests to the layout endpoint.
     - **Controller code:** In `ApiLayoutController::get()` (where `annotateEditableRecursive()` is called at ~line 104), read the query parameter:
       ```php
       $target_field = $this->requestStack->getCurrentRequest()->query->get('canvas_target_field');
       ```
       Then pass it to `annotateEditableRecursive()` as a new optional parameter. When `$target_field` is set and a `$perContentTemplate` exists:
       - Resolve which component UUIDs belong to the target field's slot (using the slot's `component_uuid`/`slot_name` from `getExposedSlots()[$target_field]`).
       - Only mark those components (and their descendants) as `editable: true`.
       - Mark all other slot components as `editable: false` (they belong to a different field/slot).
       - Template-owned components remain `editable: false` (unchanged behavior).
     - **Auto-save path (~line 519):** The auto-save endpoint also calls `annotateEditableRecursive()`. Apply the same `canvas_target_field` query parameter reading here. The React app must include the parameter on both GET and auto-save requests.
     - **Backward compatibility:** When `canvas_target_field` is absent (e.g., canvas page editing, legacy callers), all slot content is editable — preserving current behavior.
  2. **`ApiLayoutController::buildPreviewRenderable()`** — Uses `getMergedComponentTree()`. No changes needed after Step 2.2.
  3. **`ApiLayoutController::getPerContentTemplate()`** — Checks `!empty($template->getExposedSlots())`. No changes needed.
  4. **`CanvasController::getAllContentEntityCreateLinks()`** (line 376) — Has comment "This assumes one component tree field per bundle/entity" referencing #3526189. Update the logic to not break with N component_tree fields per bundle.
- **Expected outcome:** API controller handles N-field entities and field-scoped editing correctly. `canvas_target_field` query parameter scopes editability to a single slot.
- **Depends on:** Steps 2.2, 2.3

---

### Phase 3: `hook_entity_bundle_field_info()` for Field Provisioning

#### Step 3.0: Experimental verification — `FieldConfig` to `BundleFieldDefinition` migration

- **Type:** Verification (must complete before proceeding with Steps 3.1-3.3)
- **Purpose:** Switching from `FieldConfig`/`FieldStorageConfig`-provisioned fields to `BundleFieldDefinition`-provisioned fields (via `hook_entity_bundle_field_info()`) with the **same field name** may cause SQL errors or data loss. This step verifies the migration path before committing to the approach.
- **Procedure:**
  1. **Baseline:** Start with the Phase 2 state: entity has a `FieldConfig`-based `component_tree` field (e.g., `field_canvas_body`) with stored data in `node__field_canvas_body` table.
  2. **Test A — Direct switch:** Implement `canvas_entity_bundle_field_info()` returning a `BundleFieldDefinition` for `field_canvas_body` while the `FieldConfig` still exists. Run `ddev drush entity:updates`. Document whether Drupal detects a conflict, creates duplicate storage, or errors out.
  3. **Test B — Config removal first:** Delete the `FieldConfig` and `FieldStorageConfig` config entities (via `drush config:delete`), then enable the hook. Run `ddev drush entity:updates`. Document whether: (a) existing data tables are preserved or dropped, (b) `EntityDefinitionUpdateManager` creates new tables or reuses existing ones, (c) data is accessible after the switch.
  4. **Test C — Data integrity:** After successful switch, load an entity with existing `component_tree` data and verify the field values are intact and renderable.
- **Expected outcomes to document:**
  - Which migration order works (A or B)?
  - Are the storage table schemas identical between `FieldConfig` and `BundleFieldDefinition` for `component_tree` fields?
  - Does `entity:updates` handle the transition safely, or are manual SQL operations needed?
- **Impact on Steps 3.1-3.3:** Results may require adjusting the migration approach in Step 3.3. If the transition is not seamless, consider keeping `FieldConfig`-based provisioning (Phase 2 approach) as the permanent strategy and deferring the `hook_entity_bundle_field_info()` migration to a future ticket.
- **Depends on:** Phase 2

#### Step 3.1: Implement `canvas_entity_bundle_field_info()` in `canvas.module`

- **Type:** Code change
- **File:** `web/modules/contrib/canvas/canvas.module`
- **Details:** Add a `canvas_entity_bundle_field_info()` implementation that auto-provisions canvas fields for exposed slots:
  ```php
  use Drupal\Core\Entity\EntityTypeInterface;
  use Drupal\Core\Field\BundleFieldDefinition;
  use Drupal\canvas\Entity\ContentTemplate;
  use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;

  /**
   * Implements hook_entity_bundle_field_info().
   *
   * Provisions Canvas component_tree fields for each exposed slot defined
   * in enabled ContentTemplate config entities.
   */
  function canvas_entity_bundle_field_info(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $fields = [];

    $templates = \Drupal::entityTypeManager()
      ->getStorage('content_template')
      ->loadByProperties([
        'content_entity_type_id' => $entity_type->id(),
        'content_entity_type_bundle' => $bundle,
        'status' => TRUE,
      ]);

    foreach ($templates as $template) {
      \assert($template instanceof ContentTemplate);
      foreach ($template->getExposedSlots() as $slot_key => $slot_detail) {
        // The slot key IS the field machine name (e.g., field_canvas_body).
        $fields[$slot_key] = BundleFieldDefinition::create(ComponentTreeItem::PLUGIN_ID)
          ->setLabel($slot_detail['label'] ?? 'Canvas Layout')
          ->setDisplayConfigurable('form', FALSE)
          ->setDisplayConfigurable('view', FALSE)
          ->setDisplayOptions('form', [
            'type' => 'canvas_component_tree_widget',
            'weight' => 100,
          ]);
      }
    }

    return $fields;
  }
  ```
- **Performance consideration:** `hook_entity_bundle_field_info()` results are cached by `EntityFieldManager`. Add a static cache variable for repeated calls within a single request.
- **Storage tables:** `BundleFieldDefinition` fields get dedicated storage tables via `EntityDefinitionUpdateManager`. After enabling, run `ddev drush entity:updates` to create the tables.
- **Expected outcome:** Canvas fields are auto-provisioned by the hook and hidden from Field UI.
- **Depends on:** Step 2.1

#### Step 3.2: Remove `ensureCanvasFieldExists()` from `ContentTemplate::postSave()`

- **Type:** Code change
- **File:** `web/modules/contrib/canvas/src/Entity/ContentTemplate.php`
- **Details:**
  1. Remove the `ensureCanvasFieldExists()` method entirely (lines 184-231).
  2. Remove the call in `postSave()` (lines 172-174).
  3. In `postSave()`, add field definition cache invalidation:
     ```php
     public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
       parent::postSave($storage, $update);
       // Clear field definition cache so hook_entity_bundle_field_info() picks up
       // any exposed slot changes.
       \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
     }
     ```
  4. Remove the `use` statements for `FieldStorageConfig`, `FieldConfig`, and `EntityFormDisplay` if no longer referenced elsewhere in the class.
- **Expected outcome:** Field provisioning moved from config entity to hook.
- **Depends on:** Step 3.1

#### Step 3.3: Handle migration of existing configurable fields

- **Type:** Code change
- **File:** `web/modules/contrib/canvas/canvas.install` (new or existing install file)
- **Details:** Existing sites may have configurable fields (created by `ensureCanvasFieldExists()` or capability scripts) that need migration to hook-defined bundle fields. The exact migration procedure should be informed by Step 3.0's experimental results. The **expected default approach** (assuming Step 3.0 confirms config-removal-first works):
  1. Check for existing `FieldConfig` entities of type `component_tree` on content type bundles.
  2. For each, verify a corresponding exposed slot key exists in a `ContentTemplate`.
  3. If the field name already matches the slot key (which it should — the capability scripts create fields matching the slot key, e.g., `field_canvas_body`), the data is already in the correct storage table.
  4. Delete the `FieldConfig` and `FieldStorageConfig` config entities (the config, not the data tables).
  5. Run `ddev drush entity:updates` to reconcile storage schema.
- **Fallback approach:** If Step 3.0's experiments reveal that the `FieldConfig` → `BundleFieldDefinition` switch does not preserve data tables, keep the `FieldConfig`-based provisioning from Phase 2 as the permanent strategy and defer `hook_entity_bundle_field_info()` migration to a separate ticket.
- **For development environments:** Sites with only test data can use the simpler path of clearing field data and re-provisioning via the template.
- **Expected outcome:** Existing sites can upgrade without data loss (verified by Step 3.0).
- **Depends on:** Steps 3.0, 3.1, 3.2

---

### Phase 4: Workspaces Compatibility (Two-Stage Slot Lifecycle)

#### Step 4.1: Add `disabled` state to exposed slot schema

- **Type:** Code change
- **Files:**
  - `web/modules/contrib/canvas/config/schema/canvas.schema.yml` — Add optional `disabled` boolean to the exposed slot mapping (~line 820):
    ```yaml
    disabled:
      type: boolean
      label: 'Whether this slot is disabled (pending workspace publish)'
      requiredKey: false
    ```
  - `web/modules/contrib/canvas/src/Entity/ContentTemplate.php` — Add `getActiveExposedSlots()` method:
    ```php
    /**
     * Returns only non-disabled exposed slots.
     *
     * @phpstan-return ExposedSlotDefinitions
     */
    public function getActiveExposedSlots(): array {
      return array_filter(
        $this->getExposedSlots(),
        static fn(array $slot): bool => empty($slot['disabled']),
      );
    }
    ```
- **Expected outcome:** Exposed slots can be marked as disabled without being removed.
- **Depends on:** Phase 2

#### Step 4.2: Update rendering and provisioning to use `getActiveExposedSlots()`

- **Type:** Code change
- **Files to update — replace `getExposedSlots()` with `getActiveExposedSlots()` in these specific locations:**
  1. **`ContentTemplate.php`** — `getMergedComponentTree()`: iterate `getActiveExposedSlots()`.
  2. **`ContentTemplate.php`** — `build()` (line 453): check `empty($this->getActiveExposedSlots())`.
  3. **`ApiLayoutController.php`** — `getPerContentTemplate()`: check `getActiveExposedSlots()`.
  4. **`canvas.module`** — `canvas_entity_bundle_field_info()`: iterate `getActiveExposedSlots()`.
  5. **`CanvasController.php`** — `getTemplateContext()` (line 421): check and return `getActiveExposedSlots()`.
  6. **`ComponentTreeLoader.php`** — `hasContentTemplateWithExposedSlots()` (line 82): check `getActiveExposedSlots()`.
- **Keep `getExposedSlots()` unchanged for:**
  - Schema validation (all slots validate).
  - Config export/import (all slots are serialized).
  - Admin UI display (show disabled slots with a badge).
- **Expected outcome:** Disabled slots are invisible to rendering, field provisioning, and access checks.
- **Depends on:** Step 4.1

#### Step 4.3: Update access check to deny disabled slots

- **Type:** Code change
- **File:** `web/modules/contrib/canvas/src/Access/FieldLayoutAccessCheck.php`
- **Details:** In the access check (from Step 1.2), use `getActiveExposedSlots()` instead of `getExposedSlots()`:
  ```php
  $slots = $template->getActiveExposedSlots();
  if (!isset($slots[$field_name])) {
    return AccessResult::forbidden("Field '$field_name' is not mapped to an active exposed slot.")
      ->addCacheableDependency($template);
  }
  ```
- **Expected outcome:** Canvas editor access denied for disabled slots.
- **Depends on:** Steps 1.2, 4.1

---

### Phase 5: Testing and Verification

#### Step 5.1: Update existing kernel tests

- **Type:** Code change
- **File:** `web/modules/contrib/canvas/tests/src/Kernel/NodeTemplatesTest.php`
- **Details:** Update tests to reflect the refactored architecture:
  1. **`testNodeLayoutAccessCheck`** — Replace with tests for `FieldLayoutAccessCheck`. Test scenarios:
     - No template → forbidden
     - Disabled template → forbidden
     - Enabled template with matching slot → allowed (with entity update access)
     - Enabled template but field not in exposed slots → forbidden
     - Disabled slot → forbidden (Phase 4)
     - User without update permission → forbidden
  2. **`testNodeLayoutAccessCheckWithAutoProvisionedCanvasField`** — Update to verify per-slot field provisioning (field name matches slot key, not hardcoded `field_component_tree`).
  3. **`testGetCanvasFieldNameWithExposedSlots`** — Add test case for named field lookup via optional parameter.
  4. **`testGetMergedComponentTree`** — Add test case with 2 exposed slots to verify N-slot merging.
  5. **`testSlotAwarePostSavePersistsOnlySlotSubtree`** — Update to test N-slot partitioning.
  6. Remove all references to `NodeLayoutAccessCheck` class.
- **Expected outcome:** All existing tests pass with updated expectations.
- **Depends on:** All previous phases

#### Step 5.2: Add new kernel test for field widget link

- **Type:** Code change (new file)
- **File:** `web/modules/contrib/canvas/tests/src/Kernel/FieldWidgetLinkTest.php`
- **Scaffold with:** `ddev drush generate test:kernel` for canvas module, then customize.
- **Test cases:**
  1. Widget renders "Edit in Canvas" link when entity has an enabled ContentTemplate with matching exposed slot.
  2. Widget renders "not assigned" message when no template/slot exists.
  3. Widget renders "save first" message when entity is new (no ID).
  4. Link URL contains correct entity type, entity ID, and field name.
  5. Multiple canvas fields on one content type each render independent links.
- **Expected outcome:** Widget behavior is covered by automated tests.
- **Depends on:** Phase 1

#### Step 5.3: Update capability test script

- **Type:** Code change
- **File:** `.alchemize/drupal/capabilities/tests/canvas-test-per-content-editing.drush.php`
- **Details:** Update the test script to:
  1. Test the new route (`canvas.entity.field_layout`) replaces old route.
  2. Test access checks with `FieldLayoutAccessCheck`.
  3. Test N canvas fields per content type: create a template with 2 exposed slots, verify both fields are provisioned, verify independent editing.
  4. Test disabled slot behavior (Phase 4).
- **Expected outcome:** Integration test covers the full refactored workflow.
- **Depends on:** All previous phases

#### Step 5.4: Run full test suite and PHPCS

- **Type:** Verification
- **Commands:**
  ```bash
  ddev exec vendor/bin/phpunit web/modules/contrib/canvas/tests/src/Kernel/NodeTemplatesTest.php
  ddev exec vendor/bin/phpunit web/modules/contrib/canvas/tests/src/Kernel/FieldWidgetLinkTest.php
  ddev exec vendor/bin/phpunit web/modules/contrib/canvas/tests/
  ddev drush php:script .alchemize/drupal/capabilities/tests/canvas-test-run-all.drush.php
  ddev exec vendor/bin/phpcs web/modules/contrib/canvas/src/Access/FieldLayoutAccessCheck.php
  ddev exec vendor/bin/phpcs web/modules/contrib/canvas/src/Plugin/Field/FieldWidget/ComponentTreeWidget.php
  ```
- **Expected outcome:** All tests pass, no PHPCS violations in new code.
- **Depends on:** Steps 5.1-5.3

#### Step 5.5: Manual verification

- **Type:** Manual testing
- **Steps:**
  1. Navigate to a node edit form for a content type with an enabled ContentTemplate + exposed slot.
  2. Verify the "Edit in Canvas" link appears in the canvas field widget.
  3. Click the link — verify it opens the Canvas editor scoped to the correct slot.
  4. Edit content in the exposed slot and save.
  5. View the node — verify the saved slot content renders correctly within the template.
  6. Verify the Layout tab is no longer present on node view pages.
  7. If multiple exposed slots are configured: verify each field shows an independent link and editing one doesn't affect the other.
- **Expected outcome:** End-to-end per-content editing works via field widget link.
- **Depends on:** All previous phases

---

## Code Changes Summary

| File | Change Type | Phase | Description |
|------|------------|-------|-------------|
| `canvas.routing.yml` | Modify | 1 | Add `canvas.entity.field_layout` route; remove `canvas.node.layout` route |
| `canvas.links.task.yml` | Modify | 1 | Remove `canvas.entity.node.layout` task link |
| `canvas.services.yml` | Modify | 1 | Add `FieldLayoutAccessCheck` service; remove `NodeLayoutAccessCheck` service |
| `src/Access/FieldLayoutAccessCheck.php` | Create | 1 | New entity-agnostic, field-scoped access checker |
| `src/Access/NodeLayoutAccessCheck.php` | Delete | 1 | Replaced by `FieldLayoutAccessCheck` |
| `src/Controller/CanvasController.php` | Modify | 1 | Add `fieldLayout()`, add `targetFieldName` to `__invoke()`, remove `nodeLayout()` |
| `src/Plugin/Field/FieldWidget/ComponentTreeWidget.php` | Modify | 1 | Render "Edit in Canvas" link instead of passive placeholder |
| `src/Entity/ContentTemplate.php` | Modify | 1,2,3,4 | Form display config; per-slot field names; N-field merging; remove `ensureCanvasFieldExists()`; add `getActiveExposedSlots()` |
| `src/Storage/ComponentTreeLoader.php` | Modify | 1 | Add optional `$field_name` parameter to `getCanvasFieldName()` |
| `src/Controller/ApiLayoutController.php` | Modify | 2 | N-field save partitioning in `updateEntity()`; field-scoped editability in `get()` |
| `canvas.module` | Modify | 3 | Add `canvas_entity_bundle_field_info()` hook |
| `config/schema/canvas.schema.yml` | Modify | 4 | Add optional `disabled` boolean to exposed slot mapping |
| `tests/src/Kernel/NodeTemplatesTest.php` | Modify | 5 | Update tests for new routing/widget/access/N-slot patterns |
| `tests/src/Kernel/FieldWidgetLinkTest.php` | Create | 5 | New tests for field widget link behavior |
| `canvas-test-per-content-editing.drush.php` | Modify | 5 | Update integration tests |

---

## Risks & Dependencies

1. **N-field save partitioning complexity (Phase 2, Step 2.3):** The `updateEntity()` method must correctly partition slot components by exposed slot. This requires reversing the `injectSubTreeItemList()` logic — for each slot, collect items whose transitive parent chain traces back to that slot's `component_uuid`/`slot_name`. **Mitigation:** Extract a shared `partitionItemsBySlot()` helper. The parent-chain traversal is the same algorithm used in `injectSubTreeItemList()`'s second pass (lines 556-568 of `ComponentTreeItemList.php`) but applied in reverse.

2. **Frontend React app scoping (Phase 1, Step 1.5 + Phase 2, Step 2.4):** The Canvas React editor needs to know which field/slot is targeted to scope editability. `targetFieldName` is passed via `drupalSettings.canvas.templateContext`. The React app currently makes all slot content editable; it needs updating to scope to the target slot when `targetFieldName` is present. **This is a frontend change outside the Drupal plan scope** — coordinate with React team.

3. **`hook_entity_bundle_field_info()` storage tables (Phase 3, Step 3.1):** `BundleFieldDefinition` fields need storage tables created via `EntityDefinitionUpdateManager`. Run `ddev drush entity:updates` after enabling the hook. **Mitigation:** Add this as an explicit post-step command.

4. **Existing field migration (Phase 3, Step 3.3):** Sites with configurable fields need migration. If the field name already matches the slot key (which it should from capability scripts), data is in the right table — only the `FieldConfig`/`FieldStorageConfig` config entities need removal. **Mitigation:** For this project, the capability script creates fields matching the slot key, so migration is config-level only.

5. **Config schema backward compatibility (Phase 4):** Adding optional `disabled` boolean uses `requiredKey: false` so existing templates without the key validate successfully. **Mitigation:** Test import of existing template config that lacks the key.

6. **`getAllContentEntityCreateLinks()` single-field assumption (Phase 2, Step 2.4):** The comment at line 376 in `CanvasController.php` explicitly notes the single-field assumption referencing #3526189. Update this method to not break with N component_tree fields per bundle.

---

## Verification Criteria

| Phase | Verification |
|-------|-------------|
| 1 | Layout tab gone from node pages; "Edit in Canvas" link appears in field widget on node edit form; clicking link opens Canvas editor; access denied for unauthorized users; `targetFieldName` present in drupalSettings |
| 2 | Template with 2 exposed slots → 2 canvas fields on entity → each links to its own editor → saving slot A doesn't affect slot B data |
| 3 | Canvas fields don't appear in Field UI Manage Fields; fields auto-provision when template saved with exposed slots; `ddev drush entity:updates` creates storage tables; cache clear propagates changes |
| 4 | Disabled slot → field hidden from widget, rendering skips slot, access denied to editor |
| 5 | All kernel tests pass; capability test passes; PHPCS clean; manual end-to-end verified |

---

## Implementation Order

```
Phase 1 (Steps 1.1-1.9) — Field Widget Link
    ↓   Note: Step 1.4 (__invoke extension) must be implemented before Step 1.5 (fieldLayout)
Phase 2 (Steps 2.1-2.4) — N-Slot Field Architecture
    ↓
Phase 3 (Step 3.0 verification, then Steps 3.1-3.3) — Hook-Based Field Provisioning
    ↓   Note: Step 3.0 experimental results may gate or alter Steps 3.1-3.3
Phase 4 (Steps 4.1-4.3) — Workspaces Compatibility
    ↓
Phase 5 (Steps 5.1-5.5) — Testing
```

Phase 1 can be implemented and tested as a standalone improvement (single field per content type, new route + widget link). Phases 2-4 build incrementally. This allows per-phase commits and testing. Phase 3 has a verification gate (Step 3.0) — if `BundleFieldDefinition` migration proves problematic, the `FieldConfig`-based provisioning from Phase 2 remains the permanent strategy.
