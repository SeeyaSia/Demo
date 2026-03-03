# Component tree validator fails to scope parent_uuid lookup to specific template component

**Project:** Canvas (Experience Builder)
**Component:** Validation
**Category:** Bug report
**Priority:** Normal
**Canvas Version:** 1.x-dev
**Existing Issue:** New issue

## Problem/Motivation

When validating a component tree that uses a ContentTemplate, the `ComponentTreeStructureConstraintValidator` resolves `parent_uuid` references by merging the entire template tree into the lookup pool. This means a component can reference a `parent_uuid` that belongs to a completely unrelated branch of the template tree and still pass validation.

This is a correctness issue: the validator should only allow `parent_uuid` references that point to components within the same structural branch. The current behavior can mask structural errors in the component tree that would lead to rendering issues or orphaned components.

### Steps to Reproduce

1. Create a Canvas ContentTemplate with two distinct slot regions (e.g., "Header" and "Footer").
2. Programmatically (or through a race condition) create a component tree item whose `parent_uuid` points to a component in the "Header" template region, but the item itself is placed in the "Footer" region.
3. Validate the entity.
4. **Expected:** Validation error -- parent_uuid references a component not in the current tree context.
5. **Actual:** Validation passes because the entire template tree is merged into the lookup.

## Proposed Resolution

In `ComponentTreeStructureConstraintValidator`, when resolving `parent_uuid` references against the ContentTemplate tree, scope the lookup to only the specific parent component's subtree rather than merging the entire template tree. This is done by passing the parent UUID as a filter parameter to the template tree lookup method.

The change is minimal (+5/-1): replace the unscoped tree merge with a scoped lookup that only includes template components that are ancestors or siblings of the referenced parent.

## Remaining Tasks

- [ ] Code review
- [ ] Test coverage
- [ ] Commit

## API Changes

None. The validation constraint behavior is tightened but the API surface is unchanged.

## Data Model Changes

None.

## Release Notes Snippet

Fixed component tree validator to properly scope parent_uuid references when ContentTemplates are used.
