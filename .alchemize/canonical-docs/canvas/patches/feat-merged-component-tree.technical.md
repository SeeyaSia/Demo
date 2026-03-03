# Merged Component Tree and Slot Tree Extraction -- Technical Specification

## Summary

This branch introduces the ability to merge a content template's base component tree with per-entity slot content, producing a unified tree for rendering. It creates a new `SlotTreeExtractor` service for decomposing merged trees back into per-slot subtrees, refactors `ComponentTreeItemList::injectSubTreeItemList()` from a single-slot to a multi-slot reachability-based approach, and removes the single-slot assertion from `build()`. Together, these changes provide the core tree manipulation infrastructure for the exposed slots feature.

## Branch

`local/feat/merged-component-tree` based on `origin/1.x`

## Files Changed

| File | Change Type | Description |
|------|-------------|-------------|
| `canvas.services.yml` | Modified | Registers `canvas.slot_tree_extractor` service |
| `src/Entity/ContentTemplate.php` | Modified (+method) | Adds `getMergedComponentTree()`, removes single-slot assertion from `build()` |
| `src/Plugin/Field/FieldType/ComponentTreeItemList.php` | Modified | Refactors `injectSubTreeItemList()` to reachability-based multi-slot approach |
| `src/Storage/SlotTreeExtractor.php` | New | Service for extracting slot subtrees from merged trees |

**Total:** 4 files, +173/-15 lines

## Detailed Changes

### `canvas.services.yml`

Registers the new service:

```yaml
canvas.slot_tree_extractor:
  class: Drupal\canvas\Storage\SlotTreeExtractor
```

No constructor dependencies are required for the initial implementation. The service is stateless and operates purely on the tree data structures passed to its methods.

### `src/Entity/ContentTemplate.php`

**New method: `getMergedComponentTree(array $slotFieldValues): array`**

Takes an associative array of slot field values keyed by slot identifier and returns a single merged component tree array.

Algorithm:
1. Start with the template's base component tree.
2. For each active exposed slot (using `getActiveExposedSlots()`), locate the slot's injection point in the base tree by UUID.
3. Insert the entity's slot content (from `$slotFieldValues`) as children at the injection point.
4. Return the complete merged tree.

**Modified: `build()` method**

Removes the `assert(count(...) === 1)` that enforced the single-slot assumption. The method now iterates over all active exposed slots when constructing the render array, calling the refactored injection logic for each slot.

### `src/Plugin/Field/FieldType/ComponentTreeItemList.php`

**Refactored: `injectSubTreeItemList()`**

The previous implementation assumed exactly one exposed slot and performed a simple splice of the slot content into the tree at a single known position.

The new implementation uses a **reachability-based approach**:

1. Walks the component tree from the root.
2. At each node, checks whether the node's UUID is a slot boundary (i.e., one of the template's exposed slot UUIDs).
3. If a slot boundary is found, the entity's content for that slot is injected as children of the boundary node.
4. Continues walking to support nested components -- a slot's injected content may itself contain component references that need resolution.
5. Handles the case where a slot has no entity content (empty slot) gracefully.

This approach supports:
- Multiple exposed slots in a single template.
- Slots at different nesting depths.
- Slots whose injected content contains further component references.

### `src/Storage/SlotTreeExtractor.php` (New)

A service class responsible for the reverse operation: given a merged tree and a template's base tree, extract the per-slot subtrees.

**Primary method:**

```php
public function extractSlotSubtrees(array $mergedTree, array $templateTree, array $exposedSlots): array
```

Algorithm:
1. Build a set of all UUIDs present in the template's base tree (the "template UUID set").
2. Walk the merged tree.
3. At each slot boundary node, collect all child nodes whose UUIDs are NOT in the template UUID set. These are the entity's per-slot content.
4. Return an associative array keyed by slot identifier, where each value is the subtree of entity content for that slot.

This extraction is used when saving: the Canvas React app sends a complete merged tree, and the backend must decompose it to store entity-specific content in the correct per-slot fields.

## Testing

### Manual Verification

1. Create a content template with two exposed slots (e.g., "hero_content" and "sidebar_content").
2. Create an entity that uses this template and populate both slots with component content via the Canvas editor.
3. Verify that `getMergedComponentTree()` produces a single tree containing both the template structure and the entity's slot content.
4. Verify that `SlotTreeExtractor::extractSlotSubtrees()` correctly decomposes the merged tree back into two separate slot subtrees.
5. Render the entity and verify both slots display their content.
6. Test with an empty slot (no entity content) and verify graceful handling.

### Automated Tests

- **Unit tests for `SlotTreeExtractor`:**
  - Merged tree with no entity content returns empty subtrees.
  - Merged tree with content in one slot returns correct subtree.
  - Merged tree with content in multiple slots returns correct subtrees.
  - Nested component UUIDs are correctly classified as entity content (not template content).
- **Unit tests for `injectSubTreeItemList()`:**
  - Single slot injection still works (backward compatibility).
  - Multiple slot injection places content at correct boundaries.
  - Empty slot content does not cause errors.
- **Kernel test for `getMergedComponentTree()`:**
  - Integration test with actual `ContentTemplate` entity and field values.

## Dependencies

- `feat/active-exposed-slots` -- required for `getActiveExposedSlots()` method used by `getMergedComponentTree()` and `build()`.
