# Add "Layout" tab on content entity pages for per-content editing

**Project:** Canvas (Experience Builder)
**Component:** Routing / Access control / Menu
**Category:** Feature request
**Priority:** Normal
**Canvas Version:** 1.x-dev
**Existing Issue:** New issue (related to META #3541000 post-1.0 roadmap)

## Problem/Motivation

Canvas templates define the layout for content types, but there is currently no way for content editors to customize the layout of an individual content entity (node). The broader per-content editing feature requires a dedicated entry point on each entity page where editors can open the Canvas SPA scoped to that specific entity.

Per META issue #3541000, exposed slots and per-content editing are intentionally deferred to post-1.0. Issue #3520487 implements logic to "refuse editing an individual node's component tree" with plans to restore after 1.0. This branch provides the forward-looking routing and access control infrastructure that will be needed once per-content editing is enabled.

## Proposed Resolution

Add a "Layout" local task (tab) on content entity canonical pages. The tab:

1. **Only appears** when the entity has a ContentTemplate with active exposed slots AND the user has update access to the entity.
2. **Routes to** `/canvas/layout/{entity_type}/{entity}`, a new Drupal route that boots the Canvas SPA with a `base_path_override` pointing to the entity-specific path.
3. **Uses a PathProcessor** to exclude `/canvas/layout/` from the SPA's path rewriting so that Drupal's standard access checks run correctly.
4. **Maps route parameters** via a custom `EntityLayoutTaskLink` that translates `{node}` to the generic `{entity}` route parameter expected by the layout route.

### Implementation details

- **`EntityLayoutAccessCheck`** (new service): Checks that the entity has a ContentTemplate with active exposed slots and that the current user has update access to the entity.
- **`EntityLayoutTaskLink`** (new deriver): Provides the local task link on node canonical pages, mapping the `{node}` parameter to `{entity}`.
- **`CanvasController::entityLayout()`**: Boots the Canvas SPA in per-content mode with the entity context injected into `drupalSettings`.
- **`CanvasPathProcessor`**: Ensures that requests to `/canvas/layout/` are not rewritten by the SPA path processor, allowing Drupal routing and access checks to function normally.

## Remaining Tasks

- [ ] Code review
- [ ] Test coverage
- [ ] Commit

## User Interface Changes

A new "Layout" tab appears on node pages (and potentially other content entity types) when the entity's template has active exposed slots and the user has update access. Clicking the tab opens the Canvas SPA scoped to that entity.

## API Changes

- New route: `canvas.entity_layout` at `/canvas/layout/{entity_type}/{entity}`
- New access check service: `canvas.entity_layout_access_check`
- New local task link deriver: `EntityLayoutTaskLink`

## Data Model Changes

None.

## Release Notes Snippet

Added a "Layout" tab on content entity pages that opens the Canvas editor scoped to per-content editing when exposed slots are available.
