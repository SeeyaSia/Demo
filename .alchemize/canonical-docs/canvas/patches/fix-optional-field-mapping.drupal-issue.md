# Allow optional Drupal fields to match required component props in shape matching

**Project:** Canvas (Experience Builder)
**Component:** Shape matching
**Category:** Bug report
**Priority:** Major
**Canvas Version:** 1.x-dev
**Existing Issue:** Related to [#3541361](https://www.drupal.org/project/experience_builder/issues/3541361) "Find optional field instance matches for `type: object` props" (closed/fixed, but only addressed object-type props for images/videos). Also relates to [#3563309](https://www.drupal.org/project/experience_builder/issues/3563309) (multi-bundle reference matching mentions optional props).

## Problem/Motivation

When a site builder maps Drupal fields to component props in the Canvas UI, optional fields are silently excluded from the suggestion list when the target prop is marked as required in the component's JSON schema. This means common workflows fail:

- An optional `body` field cannot be mapped to a required `content` prop on a Paragraph component.
- An optional `field_subtitle` cannot be mapped to a required `subtitle` prop on a Hero component.

The root cause is two `isRequired()` guard clauses in `JsonSchemaFieldInstanceMatcher` (around lines 522 and 847) that filter out any field instance where `isRequired()` returns `FALSE`. These guards were added as a correctness measure, but they are too aggressive. The Canvas rendering pipeline already handles NULL values gracefully when an optional field is empty -- it simply renders nothing or an empty string, which is perfectly acceptable behavior for most component props.

Issue #3541361 partially addressed this for `type: object` props (images, videos) but did not remove the broader restriction for scalar and other prop types.

### Steps to Reproduce

1. Create a content type with an optional `body` field (not required in field settings).
2. Create a Canvas template for that content type.
3. Add a component that has a required `content` prop (e.g., a Paragraph component).
4. Open the prop mapping UI and attempt to map the `body` field to the `content` prop.
5. **Expected:** `body` appears in the suggestion list.
6. **Actual:** `body` is not listed because `isRequired()` returns `FALSE`.

## Proposed Resolution

Remove the two `isRequired()` guard checks in `JsonSchemaFieldInstanceMatcher.php` that prevent optional fields from being suggested for required component props.

Specifically:
- Remove the guard at ~line 522 that skips optional fields for scalar/string prop matching.
- Remove the guard at ~line 847 that skips optional fields for general prop matching.

The rendering pipeline already handles NULL/empty values from optional fields without error, so there is no functional reason to exclude them. This approach is consistent with how #3541361 solved the narrower object-type case.

Alternative approaches considered:
- Adding a "force include" flag per field: rejected as unnecessary complexity.
- Making the filter configurable via admin UI: rejected; the default behavior should just work.

## Remaining Tasks

- [ ] Code review
- [ ] Test coverage
- [ ] Commit

## API Changes

None. The shape matching API remains the same; it simply returns a broader set of valid matches.

## Data Model Changes

None.

## Release Notes Snippet

Optional Drupal fields now appear as mapping suggestions for required component props in Canvas shape matching.
