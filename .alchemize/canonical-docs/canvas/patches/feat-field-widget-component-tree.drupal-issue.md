# Add field widget for component_tree fields ("Edit in Canvas" link)

**Project:** Canvas (Experience Builder)
**Component:** Field types / Widgets
**Category:** Feature request
**Priority:** Normal
**Canvas Version:** 1.x-dev
**Existing Issue:** New issue

## Problem/Motivation

The `component_tree` field type (provided by `ComponentTreeItem`) is currently declared with `no_ui: TRUE` in its field type annotation. This means the field never appears on entity edit forms and has no corresponding field widget. While editing component tree data happens through the Canvas React application rather than through Drupal's standard form API, there is currently no way for site builders to:

1. See that a `component_tree` field exists on an entity's edit form.
2. Navigate directly from an entity's edit form to the Canvas editor for that entity.
3. View metadata about exposed slots configured for a content template.

Without a widget, the field is completely invisible in the Drupal admin UI, which creates a confusing experience for site builders who expect to see all fields on the entity form. There is also no affordance for navigating to the Canvas editor from the standard Drupal content editing workflow.

## Proposed Resolution

1. **Remove `no_ui: TRUE`** from the `ComponentTreeItem` field type plugin annotation so the field can appear in entity forms.

2. **Set a default widget** in `ComponentTreeItem` by adding a `default_widget` key pointing to the new `component_tree_widget` plugin.

3. **Create a new `ComponentTreeWidget`** field widget plugin (`src/Plugin/Field/FieldWidget/ComponentTreeWidget.php`, approximately 109 lines) that:
   - Renders an "Edit in Canvas" link in the entity form that navigates to the Canvas editor for the current entity.
   - Displays exposed slot information when available.
   - Implements `extractFormValues()` as an intentionally empty method, since the actual component tree data is saved through the Canvas React API, not through Drupal's form submission process.

This approach maintains the existing Canvas editing workflow (all visual editing happens in the React app) while giving site builders a visible presence of the field in entity forms and a clear navigation path to the Canvas editor.

## Remaining Tasks

- [ ] Code review
- [ ] Test coverage
- [ ] Commit

## User Interface Changes

- Entity edit forms will now show the `component_tree` field with an "Edit in Canvas" link instead of hiding the field entirely.
- Exposed slot metadata is displayed within the widget area.

## API Changes

- `ComponentTreeItem` field type annotation changes: `no_ui: TRUE` removed, `default_widget` added.
- New field widget plugin `component_tree_widget` is registered and can be configured via Manage Form Display.

## Data Model Changes

None. The underlying field storage and data model are unchanged. The widget is display-only and does not alter how data is persisted.

## Release Notes Snippet

Added a field widget for component_tree fields that renders an "Edit in Canvas" link on entity forms, replacing the previous hidden-field behavior.
