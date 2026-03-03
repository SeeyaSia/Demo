# Add API endpoint to list component_tree fields for a given entity type and bundle

**Project:** Canvas (Experience Builder)
**Component:** API / Content templates
**Category:** Feature request
**Priority:** Normal
**Canvas Version:** 1.x-dev
**Existing Issue:** New issue (new API endpoint)

## Problem/Motivation

The Canvas React application needs to know which `component_tree` fields exist on a given entity type and bundle. This information is required by the ExposeSlotDialog UI, which allows template editors to choose which canvas field an exposed slot should map to.

Currently, there is no API endpoint that returns the list of `component_tree` fields for a specific entity type/bundle combination. The frontend has no way to discover available canvas fields without this endpoint, forcing template editors to manually type field machine names or rely on hardcoded assumptions.

## Proposed Resolution

1. **Add a new REST API route** in `canvas.routing.yml`:
   - Path: `/canvas/api/v0/ui/content_template/canvas-fields/{entity_type_id}/{bundle}`
   - Method: GET
   - Controller: `ApiUiContentTemplateControllers::canvasFields`
   - Returns JSON array of `{name, label}` objects for each `component_tree` field on the specified entity type and bundle.

2. **Add controller method** `canvasFields()` to `src/Controller/ApiUiContentTemplateControllers.php`:
   - Accepts `entity_type_id` and `bundle` route parameters.
   - Uses the Entity Field Manager to load field definitions for the bundle.
   - Filters for fields of type `component_tree`.
   - Returns a JSON response with field name and label for each matching field.

3. **Add frontend RTK Query endpoint** in `ui/src/services/componentAndLayout.ts`:
   - New `getCanvasFields` endpoint using RTK Query's `builder.query`.
   - Accepts `{entityTypeId, bundle}` parameters.
   - Calls the new backend route.
   - Exports a `useGetCanvasFieldsQuery` hook for use in React components.

## Remaining Tasks

- [ ] Code review
- [ ] Test coverage
- [ ] Commit

## User Interface Changes

None directly. This endpoint provides data to the ExposeSlotDialog component (implemented separately). The dialog uses the `useGetCanvasFieldsQuery` hook to populate a dropdown of available canvas fields.

## API Changes

- **New REST endpoint:** `GET /canvas/api/v0/ui/content_template/canvas-fields/{entity_type_id}/{bundle}`
  - Response format: `[{"name": "field_hero_content", "label": "Hero Content"}, ...]`
  - Access: requires Canvas admin permission (consistent with other `/canvas/api/v0/ui/` routes).

## Data Model Changes

None.

## Release Notes Snippet

Added a REST API endpoint for listing component_tree fields on a given entity type and bundle, enabling the ExposeSlotDialog to discover available canvas fields.
