# Component tree hydration crashes when parent_uuid references missing template component

**Project:** Canvas (Experience Builder)
**Component:** Field types / Component tree
**Category:** Bug report
**Priority:** Major
**Canvas Version:** 1.x-dev
**Existing Issue:** New issue

## Problem/Motivation

During component tree hydration in `ComponentTreeItemList`, when a component's `parent_uuid` references a template component that is not present in the current field's tree, the code hits an assertion failure. This occurs in contexts where the full template tree is not available, such as:

- **Search indexing:** Drupal's search module renders entities to extract text, but the render context does not include the full template tree.
- **Views rendering:** When a View renders entity fields, the Canvas component tree is hydrated without the template context.
- **REST/JSON:API serialization:** Entity serialization may trigger field hydration outside the normal page render context.

In these contexts, the `parent_uuid` points to a component UUID that simply does not exist in the hydration scope, causing a fatal error instead of a graceful fallback.

### Steps to Reproduce

1. Create a Canvas ContentTemplate with a slot that contains child components.
2. Create a node using that ContentTemplate, adding components into the template's slots.
3. Trigger a search index rebuild (`drush search:index`).
4. **Expected:** Search indexing completes, extracting text from the node's Canvas components.
5. **Actual:** Assertion failure in `ComponentTreeItemList` because the template's parent component is not in the hydration scope.

## Proposed Resolution

In `ComponentTreeItemList`, during the hydration loop where `parent_uuid` is resolved, add a guard that checks whether the referenced parent UUID exists in the current tree. If it does not (i.e., it references a template component not present in the hydration context), treat the component as root-level instead of crashing.

The change adds 10 lines: a conditional check around the parent resolution logic that falls back to treating the component as a root-level item when the parent is not found.

## Remaining Tasks

- [ ] Code review
- [ ] Test coverage
- [ ] Commit

## API Changes

None. Components with unresolvable `parent_uuid` references are now silently treated as root-level during hydration. The stored data is not modified.

## Data Model Changes

None.

## Release Notes Snippet

Fixed a crash during search indexing and Views rendering when Canvas component trees reference template components not in the current hydration context.
