# ComponentTreeEditAccessCheck throws 500 error instead of 403 for unsupported entities

**Project:** Canvas (Experience Builder)
**Component:** Access control
**Category:** Bug report
**Priority:** Normal
**Canvas Version:** 1.x-dev
**Existing Issue:** New issue

## Problem/Motivation

`ComponentTreeEditAccessCheck` calls `$this->componentTreeLoader->load($entity)` without catching the `\LogicException` that is thrown when the entity does not support Canvas editing (e.g., an entity type that does not have a Canvas component tree field, or an entity in a state that prevents Canvas editing).

When this exception is not caught, it bubbles up through Drupal's access checking layer and results in a 500 Internal Server Error being shown to the user. The correct behavior is to return a 403 Forbidden response -- the user does not have "access" to edit a Canvas component tree on an entity that does not support one.

### Steps to Reproduce

1. Navigate to a Canvas editing route for an entity that does not support Canvas (e.g., a manually constructed URL like `/canvas/edit/taxonomy_term/1` for a taxonomy term without a Canvas field).
2. **Expected:** 403 Access Denied.
3. **Actual:** 500 Internal Server Error with a `\LogicException` in the logs.

This can also occur during route matching when Drupal evaluates access checks for all candidate routes -- even routes the user did not intend to visit.

## Proposed Resolution

Wrap the `$this->componentTreeLoader->load($entity)` call in a try/catch block that catches `\LogicException` and returns `AccessResult::forbidden()` with a descriptive reason message. This converts the unhandled exception into a proper access denial.

The change is minimal (+6/-1): adding a try/catch around the existing load call and returning a forbidden access result in the catch block.

## Remaining Tasks

- [ ] Code review
- [ ] Test coverage
- [ ] Commit

## API Changes

None. The access check now returns `AccessResult::forbidden()` where it previously threw an exception. This is the correct behavior per Drupal's access system contract.

## Data Model Changes

None.

## Release Notes Snippet

Fixed a 500 error when accessing Canvas editing routes for entities that do not support Canvas editing; now correctly returns 403 Forbidden.
