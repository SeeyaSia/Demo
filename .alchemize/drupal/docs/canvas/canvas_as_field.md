# Canvas as Field: Extending Canvas for Content Type Integration

## Purpose

Developer guide for using the Canvas `component_tree` field type on content types, enabling per-content slot editing via Content Templates. Covers what was contributed vs. custom, the integration gaps that were resolved, key implementation references, and practical guidance for adding Canvas to new content types.

This work was completed across tickets ATWEB-7 (backend) and ATWEB-9 (frontend), extending Canvas's per-content editing capability beyond `canvas_page` entities to standard node content types.

## What Is Canvas-as-Field?

Canvas natively provides standalone pages via `canvas_page` entities. The "Canvas as Field" extension enables any node content type to use Canvas for visual layout through:

1. A `component_tree` field on the content type (stores per-entity slot content)
2. A ContentTemplate with exposed slots (defines the template shell)
3. A merged rendering pipeline (template + entity slot content at render time)

This gives content editors a **Layout tab** on nodes where they can drag and drop components into the template's exposed slots, while the template itself controls the overall page structure.

## Contributed vs. Custom Changes

### Contributed to Canvas module (in `web/modules/contrib/canvas/`)

These changes modify the Canvas module directly and are intended for upstream contribution:

| Area | What changed | Key files |
|------|-------------|-----------|
| **Field type visibility** | Removed `no_ui: TRUE` from `ComponentTreeItem` so the field appears in Drupal's Field UI | `Plugin/Field/FieldType/ComponentTreeItem.php` |
| **Field widget** | Created `ComponentTreeWidget` — minimal widget showing "managed by Canvas" placeholder | `Plugin/Field/FieldWidget/ComponentTreeWidget.php` (new) |
| **Expose slot UX** | Added "Expose slot" to component context menu (workaround for unreachable slot overlay) | `ui/src/features/layout/preview/ComponentContextMenu.tsx` |
| **Expose slot dialog** | Replaced free-text with dropdown of available canvas fields on the content type | `ui/src/features/layout/preview/ExposeSlotDialog.tsx` |
| **Canvas fields API** | New endpoint returning `component_tree` fields for an entity type + bundle | `Controller/ApiUiContentTemplateControllers.php`, `canvas.routing.yml` |
| **RTK Query hook** | `useGetCanvasFieldsQuery` for the frontend to fetch available fields | `ui/src/services/componentAndLayout.ts` |

### Project-specific configuration (in `config/alchemizetechwebsite/`)

| Content type | Canvas field name | Template ID | Notes |
|-------------|------------------|-------------|-------|
| Article | `field_component_tree` | `node.article.full` | Auto-provisioned by Canvas; form display + view display configured |
| Basic page | `field_canvas_body` | `node.page.full` | Added via Field UI with custom name |
| Project | `field_project_canvas` | `node.project.full` | Added via Field UI with custom name |

## Adding Canvas to a New Content Type

### Step-by-step

1. **Add the field** — Go to `/admin/structure/types/manage/{bundle}/fields` → Add field → "Re-use an existing field" or "Add a new field" → select "Canvas (component_tree)". Name it descriptively (e.g., `field_canvas_body`).

2. **Configure form display** — Go to Manage form display → set the widget to `Drupal Canvas` (`canvas_component_tree_widget`). This shows a placeholder message on the node edit form.

3. **Configure view display** — Go to Manage display → drag the field into the "Content" region → set the formatter to `Canvas naive render SDC tree`. This enables rendering of the stored component tree.

4. **Create the ContentTemplate** — In the Canvas editor (`/canvas`), go to Templates → Content Types → "+ Add new template" → select your content type + Full content view mode → Add.

5. **Build the template** — Add components to the template. For the slot where content editors will add per-entity components, use a Wrapper (or any component with slots).

6. **Expose the slot** — Right-click the wrapper component → "Expose slot" → select the canvas field from the dropdown → confirm. This links the slot to the field.

7. **Publish the template** — Click Publish to activate. The "Layout" tab will now appear on nodes of this content type.

### What happens automatically

- `ContentTemplate::postSave()` auto-provisions a `field_component_tree` field if no `component_tree` field exists on the bundle when exposed slots are set. If you already added a field with a different name, it uses your existing field.
- `NodeLayoutAccessCheck` gates the Layout tab — requires both an enabled template with exposed slots AND a canvas field on the bundle.
- `ContentTemplateAwareViewBuilder` intercepts node rendering and uses the template's merged component tree.

## Key Architecture References

### Access control: why the Layout tab appears (or doesn't)

`NodeLayoutAccessCheck::access()` → `ComponentTreeLoader::hasContentTemplateWithExposedSlots()`:

```
Layout tab visible = ALL of:
  1. ContentTemplate exists for {entity_type}.{bundle}.full
  2. Template status === true (enabled/published)
  3. Template has non-empty exposed_slots
  4. Bundle has a component_tree field
  5. User has update access to the node
```

If any condition fails, the tab is hidden. The most common miss is empty `exposed_slots` — the template must have a slot exposed via the editor.

### Rendering: how the merge works

```
ContentTemplate::getMergedComponentTree($entity)
  → $this->getComponentTree()           // template's tree
  → ->injectSubTreeItemList(            // inject entity's slot content
       $this->getExposedSlots(),        // which slots to fill
       $entity->get($canvasFieldName)   // entity's stored components
     )
```

The exposed slot key maps to `{component_uuid, slot_name}` in the template tree. The entity's `component_tree` field stores the components that fill that slot. At render time, they're merged into a single tree.

### The overlay z-index issue

The Canvas editor has two overlay layers for each component:

| Layer | CSS | Purpose |
|-------|-----|---------|
| `SlotOverlay` | No `pointer-events` declaration | Shows slot boundaries, has "Expose slot" context menu |
| `ComponentOverlay` | `pointer-events: all` | Handles selection, drag, resize — covers the entire component including slots |

Because `ComponentOverlay` intercepts all pointer events, the `SlotOverlay` context menu is unreachable via right-click. The workaround: "Expose slot" options are added to the **component** context menu (`ComponentContextMenu.tsx`) in template mode.

### API: canvas fields endpoint

```
GET /canvas/api/v0/ui/content_template/canvas-fields/{entity_type_id}/{bundle}
→ [{ "name": "field_canvas_body", "label": "Canvas Body" }]
```

Used by `ExposeSlotDialog` to populate the field dropdown. The endpoint uses `EntityFieldManagerInterface::getFieldMapByFieldType('component_tree')` to discover fields.

## Developer Tips

### Always create a widget for new field types

Drupal requires a widget for any field type to render in forms. Even if the field is managed externally (like Canvas manages `component_tree` via API), create a minimal widget that:
- Shows an informational message to editors
- Has an empty `extractFormValues()` to prevent form submission from overwriting API-managed data
- Is referenced via `default_widget` in the field type annotation

### The `no_ui` flag on field types

The `#[FieldType(no_ui: TRUE)]` annotation prevents a field type from appearing in Drupal's Field UI ("Add field" and "Re-use an existing field" forms). This is enforced by `FieldTypePluginManager::getUiDefinitions()`. Remove it when you want the field to be admin-manageable.

### Canvas field names are not hardcoded

`ComponentTreeLoader::getCanvasFieldName()` finds canvas fields by type, not by name. The auto-provisioning in `ensureCanvasFieldExists()` defaults to `field_component_tree`, but you can name the field anything when adding it through the Field UI. Each content type can have a different field name.

### Exposed slot keys should match field names

When exposing a slot, the machine name (the key in `exposed_slots`) should be the canvas field name (e.g., `field_canvas_body`). While the merge logic doesn't use the key for field lookup (it uses `component_uuid` + `slot_name`), matching the key to the field name makes the connection explicit and traceable in config YAML.

### One exposed slot per template (current limitation)

`ContentTemplate::getMergedComponentTree()` asserts exactly one exposed slot. The UI enforces this by disabling the "Expose slot" option when one already exists. Multiple exposed slots are planned but not yet supported.

### Config export after template changes

After exposing a slot and publishing a template, always run `ddev drush cex -y` to capture:
- The updated `canvas.content_template.*.yml` (with `exposed_slots`)
- Any auto-provisioned field storage/config (`field.storage.node.field_component_tree.yml`, `field.field.node.{bundle}.field_component_tree.yml`)
- Updated form display and view display configs

## Related Documentation

| Document | What it covers |
|----------|---------------|
| `canvas-content-templates.md` | Full ContentTemplate reference: config schema, prop sources, field linking, programmatic creation |
| `canvas-system-overview.md` | Module stack, entity types, component_tree field type details |
| `canvas-shape-matching.md` | How entity fields map to component props via JSON Schema matching |
| `global/architecture.md` | Project-level content type inventory with canvas field names |
