# Per-content editing frontend: locked components, exposed slot targeting, and template context

**Project:** Canvas (Experience Builder)
**Component:** UI / Editor
**Category:** Feature request
**Priority:** Major
**Canvas Version:** 1.x-dev
**Existing Issue:** New issue (per-content editing frontend per post-1.0 roadmap, related to META #3541000 and #3520487 which "refuses editing an individual node's component tree" until exposed slots are available)

## Problem/Motivation

With the backend now capable of serving merged component trees with editability annotations and saving slot-scoped data, the Canvas frontend needs to understand and enforce the per-content editing paradigm. Currently the editor treats all components equally -- there is no concept of "locked" template components vs. "editable" per-content components, and the SPA has no awareness of which slots accept content drops in per-content mode.

Issue #3520487 deliberately disabled per-content component tree editing because the frontend had no way to distinguish template-owned components from editor-added components. This branch resolves that limitation.

## Proposed Resolution

Full frontend implementation across 17 files (~287 lines added) covering four key areas:

### 1. Template context awareness

A new `TemplateContext` interface in `uiSlice` tracks `contentTemplateId`, `hasExposedSlots`, and `exposedSlots`. This context is initialized from `drupalSettings` on SPA boot and updated from `getPageLayout` API responses. The SPA knows at all times whether it is in per-content mode and which slots are exposed.

### 2. Locked component enforcement

Components with `editable: false` (from the backend) are visually and functionally locked:
- **ComponentLayer**: Shows a lock icon, disables drag handles, selection, hover effects, and context menus.
- **ComponentOverlay**: Disables click, hover, and drag interactions.
- **ComponentInstanceForm**: Shows an informational message ("This component is part of the template") instead of editable form fields.
- **CSS**: Locked components get 0.6 opacity, `not-allowed` cursor, and a gray dashed border.

### 3. Exposed slot targeting

In per-content mode, only exposed slots accept component drops:
- **SlotLayer/SlotOverlay**: Compute `isSlotExposed` from the template context. Non-exposed slots set `slotDisableDrop = true`. Exposed slots get a green dashed border (`.exposedPerContent` class, no background fill).

### 4. Contextual panel adaptation

In per-content mode, the ContextualPanel replaces the PageDataForm (title, body, etc.) with an "Edit content" link pointing to the Drupal entity edit form. Content editors edit page fields through Drupal's normal entity form, not through Canvas.

### 5. Routing and API integration

- **AppRoutes**: When `entityType` and `entity` are present in `drupalSettings`, redirects directly to the entity editor route instead of the template list.
- **baseQuery**: Extended `extractEntityParams()` with generic `/editor/` and `/template/` fallback regexes for when the SPA is mounted outside `/canvas/`. Also reads from `drupalSettings` as a final fallback.
- **componentAndLayout.ts**: `getPageLayout` dispatches `setTemplateContext` when the response contains `contentTemplateId` and `exposedSlots`.
- **main.tsx**: Reads template context from `drupalSettings.canvas.templateContext` on app initialization.

## Remaining Tasks

- [ ] Code review
- [ ] Test coverage
- [ ] Commit

## User Interface Changes

- Template-owned components appear visually locked: 0.6 opacity, gray dashed border, lock icon, `not-allowed` cursor. They cannot be selected, dragged, copied, or deleted.
- Only exposed slots show drop zones in per-content mode. Non-exposed slots do not accept drops.
- Exposed slots have a green dashed border without background fill.
- The contextual panel shows an "Edit content" link instead of page data fields in per-content mode.
- The SPA auto-navigates to the entity editor when launched from the entity Layout tab.

## API Changes

- The frontend now expects `contentTemplateId`, `exposedSlots`, and per-component `editable` flags in the layout GET response (provided by the companion backend branch).
- `DrupalSettings` type extended with `entityType`, `entity`, and `templateContext` properties.
- `LayoutApiResponse` type extended with `contentTemplateId`.

## Data Model Changes

None.

## Release Notes Snippet

Added frontend support for per-content editing with locked template components, exposed slot drop targeting, and template context awareness.
