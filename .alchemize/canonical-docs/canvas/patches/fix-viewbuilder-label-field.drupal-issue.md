# Canvas ContentTemplate ViewBuilder strips entity label field, breaking page titles

**Project:** Canvas (Experience Builder)
**Component:** Entity rendering
**Category:** Bug report
**Priority:** Major
**Canvas Version:** 1.x-dev
**Existing Issue:** Related to [#3567116](https://www.drupal.org/project/experience_builder/issues/3567116) "Contextual links are not displayed for nodes using Canvas ContentTemplates" -- same ViewBuilder file, similar root cause where the ViewBuilder strips too much from the default render array.

## Problem/Motivation

When an entity uses a Canvas ContentTemplate, the `ContentTemplateAwareViewBuilder` replaces the standard entity render array with the Canvas component tree output. However, it does not preserve the entity's label field (e.g., `title` for nodes) in the render array. This causes `EntityViewController::buildTitle()` to fail to extract the page title, resulting in:

- Missing or empty `<title>` tag on Canvas-rendered pages.
- Missing page title on revision view routes (`/node/{nid}/revisions/{vid}/view`), which can cause a crash if the title callback expects the label field to be present.

### Steps to Reproduce

1. Create a Canvas ContentTemplate for the `article` content type.
2. Create an article node with the title "My Article".
3. View the node at `/node/1`.
4. **Expected:** Page title is "My Article".
5. **Actual:** Page title is empty or the route title callback throws an error.
6. Additionally, visit `/node/1/revisions` and click "View" on a revision -- this triggers a more visible crash.

## Proposed Resolution

In `ContentTemplateAwareViewBuilder::buildMultiple()` (or the relevant build method), explicitly include the entity's label field in the render array alongside the Canvas component tree output. This ensures that `EntityViewController::buildTitle()` and other title-resolution mechanisms can access the label.

The implementation:
1. Checks if the entity type has a label key via `$entity_type->getKey('label')`.
2. If a label key exists, renders the label field using the entity's field view builder.
3. Includes it in the render array at the appropriate key.

This is a minimal addition (+19/-1) that preserves the Canvas rendering pipeline while restoring compatibility with Drupal's title system.

## Remaining Tasks

- [ ] Code review
- [ ] Test coverage
- [ ] Commit

## API Changes

None. The render array now includes an additional element for the label field, which is standard Drupal behavior that was previously missing.

## Data Model Changes

None.

## Release Notes Snippet

Fixed missing page titles on entities rendered with Canvas ContentTemplates, including revision view pages.
