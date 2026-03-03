# Add Field Widget for component_tree Fields -- Technical Specification

## Summary

This branch introduces a Drupal field widget for `component_tree` fields, making them visible on entity edit forms. The widget renders an "Edit in Canvas" link that navigates users to the Canvas React editor. It also displays exposed slot information. The `extractFormValues()` method is intentionally left empty because component tree data is saved through the Canvas React API, not through Drupal's form submission pipeline. The `no_ui: TRUE` annotation on `ComponentTreeItem` is removed to allow the field to appear in entity forms.

## Branch

`local/feat/field-widget-component-tree` based on `origin/1.x`

## Files Changed

| File | Change Type | Description |
|------|-------------|-------------|
| `src/Plugin/Field/FieldType/ComponentTreeItem.php` | Modified | Removes `no_ui: TRUE` from annotation, adds `default_widget = "component_tree_widget"` |
| `src/Plugin/Field/FieldWidget/ComponentTreeWidget.php` | New (+109 lines) | New field widget plugin that renders "Edit in Canvas" link and slot info |

## Detailed Changes

### `src/Plugin/Field/FieldType/ComponentTreeItem.php`

**Annotation changes:**

- Removes `no_ui: TRUE` from the `@FieldType` annotation. This flag previously prevented the field from appearing in any entity form display configuration. Removing it allows the field to be managed through the standard "Manage form display" admin UI.
- Adds `default_widget = "component_tree_widget"` to the annotation so that newly created `component_tree` fields automatically use the new widget without manual configuration.

No changes to the field's schema, property definitions, or storage behavior.

### `src/Plugin/Field/FieldWidget/ComponentTreeWidget.php` (New)

A new Drupal field widget plugin class with approximately 109 lines.

**Plugin annotation:**
```
@FieldWidget(
  id = "component_tree_widget",
  label = @Translation("Component Tree (Canvas)"),
  field_types = {"component_tree"}
)
```

**Key methods:**

- `formElement()`: Builds the widget render array for the entity edit form. Constructs an "Edit in Canvas" link URL that points to the Canvas editor route for the current entity (using the entity type, ID, and field name). Displays exposed slot metadata (slot labels and configuration) when available on the associated content template.

- `extractFormValues()`: Intentionally empty (no-op). This is a deliberate design decision: component tree data is never submitted through Drupal's Form API. All editing and saving of component tree content happens through the Canvas React application's API endpoints. The widget is purely navigational and informational.

- `massageFormValues()`: Returns an empty array since no form values are processed.

**Link construction logic:**

The "Edit in Canvas" link is built by resolving the Canvas editor route and appending query parameters that identify the entity and field being edited. This allows the Canvas React app to load the correct component tree for editing.

## Testing

### Manual Verification

1. Ensure the Canvas module is installed and a content type has a `component_tree` field.
2. Navigate to Admin > Structure > Content types > [type] > Manage form display.
3. Verify the `component_tree` field is now listed (previously hidden by `no_ui: TRUE`).
4. Verify the widget is set to "Component Tree (Canvas)" by default.
5. Create or edit a node of this content type.
6. Verify the "Edit in Canvas" link appears in the form for the `component_tree` field.
7. Click the link and verify it navigates to the Canvas editor with the correct entity context.
8. Submit the entity form and verify no errors occur (empty `extractFormValues()` should not interfere with form submission).

### Automated Tests

- Unit test for `ComponentTreeWidget::formElement()` render array structure.
- Kernel test verifying that the `component_tree` field type no longer has `no_ui: TRUE` and that the default widget is `component_tree_widget`.
- Functional test verifying the "Edit in Canvas" link appears on an entity form and points to the correct URL.

## Dependencies

None. This branch has no dependencies on other feature branches.
