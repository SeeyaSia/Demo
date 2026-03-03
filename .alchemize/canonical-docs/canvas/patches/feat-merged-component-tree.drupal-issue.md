# Add merged component tree and slot tree extraction for multi-slot content templates

**Project:** Canvas (Experience Builder)
**Component:** Content templates / Storage
**Category:** Feature request
**Priority:** Major
**Canvas Version:** 1.x-dev
**Existing Issue:** New issue. Foundational for exposed slots, deferred to post-1.0 per [META #3541000](https://www.drupal.org/project/experience_builder/issues/3541000).

## Problem/Motivation

Content templates define a base component tree, and exposed slots allow content editors to inject per-entity content into designated positions within that tree. Currently, there is no mechanism to:

1. **Merge** a template's component tree with an entity's per-slot content into a single unified tree for rendering.
2. **Extract** individual slot subtrees from a merged tree so that per-slot content can be saved back to the correct entity field.
3. **Support multiple exposed slots** -- the existing `ComponentTreeItemList::injectSubTreeItemList()` uses a simple single-slot injection approach and `build()` contains an `assert(count(...) === 1)` that enforces the single-slot assumption.

Without these capabilities, content templates are limited to at most one exposed slot, and there is no clean separation between template-owned and entity-owned content within the tree.

## Proposed Resolution

1. **Add `getMergedComponentTree()` to `ContentTemplate`** -- a method that takes an entity's per-slot field values and merges them into the template's base component tree, producing a single tree suitable for rendering.

2. **Create `SlotTreeExtractor` service** (`src/Storage/SlotTreeExtractor.php`, new file) -- a registered service that extracts slot subtrees from a merged component tree by comparing node UUIDs against the template's base tree. Nodes that do not belong to the template are identified as slot content and grouped by their parent slot.

3. **Refactor `ComponentTreeItemList::injectSubTreeItemList()`** -- replace the simple single-slot injection logic with a reachability-based approach that can handle nested components and multiple exposed slots. Instead of assuming a single injection point, the new logic walks the tree and injects content at each slot boundary identified by the template's exposed slot configuration.

4. **Remove the `assert(count(...) === 1)` single-slot assumption** from `build()` to allow templates with multiple exposed slots to render correctly.

5. **Register `SlotTreeExtractor` as a service** in `canvas.services.yml`.

## Remaining Tasks

- [ ] Code review
- [ ] Test coverage
- [ ] Commit

## User Interface Changes

None. This is a backend infrastructure change that enables multi-slot rendering.

## API Changes

- New public method `ContentTemplate::getMergedComponentTree(array $slotFieldValues): array` -- merges template tree with per-slot entity content.
- New service `canvas.slot_tree_extractor` (`SlotTreeExtractor`) with method(s) for extracting slot subtrees from merged trees.
- `ComponentTreeItemList::injectSubTreeItemList()` signature and behavior change to support multiple slots.

## Data Model Changes

None. The component tree data structure remains the same. The merge and extraction operations are performed at runtime.

## Release Notes Snippet

Content templates now support merging template and per-entity component trees across multiple exposed slots, removing the single-slot limitation.
