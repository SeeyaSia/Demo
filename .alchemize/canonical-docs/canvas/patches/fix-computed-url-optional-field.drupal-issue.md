# ComputedUrlWithQueryString crashes on empty optional fields

**Project:** Canvas (Experience Builder)
**Component:** Prop expressions / Data types
**Category:** Bug report
**Priority:** Major
**Canvas Version:** 1.x-dev
**Existing Issue:** New issue

## Problem/Motivation

When a Canvas component is mapped to a URL field that is optional and empty, two things go wrong:

1. `ComputedUrlWithQueryString` declares `is_required: TRUE` in its data definition, which causes the prop expression evaluator to expect a non-NULL value. When the field is empty, this mismatch triggers errors downstream.
2. The evaluator for structured data prop expressions does not handle the case where an unlimited-cardinality field is empty and `is_required` is false -- it proceeds to evaluate the field and crashes when no items exist.

### Steps to Reproduce

1. Create a content type with an optional Link field (`field_cta`).
2. Create a Canvas template that maps `field_cta` to a component's `url` prop.
3. Create a node of that content type, leaving `field_cta` empty.
4. View the node.
5. **Expected:** The component renders with an empty/missing URL.
6. **Actual:** PHP error or empty `GeneratedUrl` assertion failure.

## Proposed Resolution

Two changes:

1. **`ComputedUrlWithQueryString.php`:** Change `is_required: TRUE` to `is_required: FALSE` and add a NULL guard that returns an empty `GeneratedUrl` object when the underlying field value is empty. This prevents downstream code from receiving an unexpected NULL.

2. **`Evaluator.php`:** Add an early return of NULL when evaluating a prop expression that targets an unlimited-cardinality field that has zero items and `is_required` is false. This short-circuits the evaluation before any item-level access occurs.

## Remaining Tasks

- [ ] Code review
- [ ] Test coverage
- [ ] Commit

## API Changes

`ComputedUrlWithQueryString` now declares `is_required: FALSE` instead of `TRUE`. Any code that relied on the computed URL always being non-NULL must handle the empty case.

## Data Model Changes

None.

## Release Notes Snippet

Fixed a crash when Canvas components are mapped to empty optional URL fields.
