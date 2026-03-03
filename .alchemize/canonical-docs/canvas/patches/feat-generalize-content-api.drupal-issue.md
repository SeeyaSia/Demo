# Generalize Canvas content API from hardcoded canvas_page to any content entity type

**Project:** Canvas (Experience Builder)
**Component:** API controllers / Routing
**Category:** Feature request
**Priority:** Major
**Canvas Version:** 1.x-dev
**Existing Issue:** Related to [META #3541000](https://www.drupal.org/project/experience_builder/issues/3541000) which limits Canvas 1.0 to nodes only. This generalizes the content API so it can work with any content entity type, laying groundwork for post-1.0 entity type support.

## Problem/Motivation

The Canvas content CRUD API (`ApiContentControllers`) and related routes are hardcoded to work only with the `canvas_page` entity type. Route paths, controller methods, and access checks all reference `canvas_page` directly. This creates several problems:

1. **Entity type lock-in:** Canvas cannot manage content for nodes, media, taxonomy terms, or any other content entity type -- only the custom `canvas_page` type.
2. **Code duplication risk:** Supporting additional entity types under the current architecture would require duplicating routes and controller logic for each type.
3. **Exposed slots limitation:** Per-slot content on arbitrary entity types cannot be managed through the existing API because the API does not accept entity type as a parameter.
4. **CanvasController duplication:** The `CanvasController` (admin UI) generates hardcoded create links that would need to be duplicated for each entity type.

META #3541000 explicitly limits 1.0 scope to nodes, but the architecture should be ready for generalization without requiring a rewrite.

## Proposed Resolution

1. **Generalize API routes** in `canvas.routing.yml`:
   - Replace hardcoded `canvas_page` references with `{entity_type_id}` route parameters.
   - Update route paths from `/canvas/api/v0/content/...` to `/canvas/api/v0/content/{entity_type_id}/...` (or similar parameterized pattern).
   - Add `{bundle}` parameter where needed for entity types that use bundles.

2. **Refactor `ApiContentControllers`:**
   - `post()`: Handle entity types with bundle keys by looking up the bundle key from the entity type definition and setting it correctly on the new entity. No longer assumes the entity type is `canvas_page`.
   - `delete()`: Rename variable from `$canvas_page` to `$entity` and accept any content entity type.
   - `list()`: Accept any content entity type with revision tracking. Generalize the query to use the entity type's storage handler.
   - `createAccess()`: New custom access check method that verifies the current user has create access for the given entity type and bundle.
   - `listAccess()`: New custom access check method for list operations.
   - Inject `EntityTypeBundleInfoInterface` to resolve bundle information for entity types.

3. **Refactor `CanvasController`:**
   - Deduplicate create links across multiple `component_tree` fields on the same bundle. Previously, if a bundle had multiple canvas fields, it would generate duplicate create links in the admin UI. The new logic ensures one create link per bundle regardless of how many canvas fields exist.

4. **Expand `ComponentTreeLoader`:**
   - Generalize loading logic to work with any content entity type, not just `canvas_page`.

## Remaining Tasks

- [ ] Code review
- [ ] Test coverage
- [ ] Commit

## User Interface Changes

- The Canvas admin dashboard (CanvasController) deduplicates create links when a bundle has multiple `component_tree` fields, showing one create link per bundle instead of one per field.

## API Changes

- **Breaking change to route paths:** Content API routes change from hardcoded `canvas_page` paths to parameterized `{entity_type_id}` paths. Frontend consumers must update their API calls accordingly.
- New `createAccess()` and `listAccess()` custom access methods on `ApiContentControllers`.
- `ApiContentControllers` now depends on `EntityTypeBundleInfoInterface`.
- `ComponentTreeLoader` methods accept entity type as a parameter.

## Data Model Changes

None. The data model is unchanged; only the API layer is generalized.

## Release Notes Snippet

Canvas content CRUD API is now generalized to work with any content entity type, replacing the hardcoded canvas_page assumption and preparing for multi-entity-type support.
