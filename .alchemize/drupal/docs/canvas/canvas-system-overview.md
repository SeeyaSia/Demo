# Canvas System Overview

## Purpose

Entry point for all Canvas documentation. Explains what Drupal Canvas is, the module stack, the three component types, the key entity types, and how Canvas relates to the rest of the Drupal architecture. Read this first before any other Canvas doc.

## System Overview

Drupal Canvas is a visual page builder for Drupal. It enables content creators to compose pages by dragging and dropping components in a browser-based editor, without writing code. Canvas can replace Layout Builder and the Block Layout system as the primary tool for page building and site-wide layout.

Canvas works with three kinds of components:

| Component type | Prefix | Source | Rendering |
|---------------|--------|--------|-----------|
| **SDC** (Single Directory Components) | `sdc.*` | Themes and modules (`components/` dirs) | Server-side Twig |
| **Block** | `block.*` | Any Drupal block plugin | Server-side PHP |
| **JS** (code components) | `js.*` | Canvas UI or `@drupal-canvas/cli` | Client-side Preact/React |

## Module Stack

### Core module
- **`canvas`** (1.1.0) — The main module. Provides Canvas Pages, component discovery, the visual editor, component tree field type, config entities, and the HTTP API.

### Submodules (shipped with `canvas`)
| Submodule | Purpose |
|-----------|---------|
| `canvas_dev_mode` | Development mode — disables caching, enables debug info in the Canvas editor |
| `canvas_oauth` | OAuth2 authentication for the external HTTP API (required for `@drupal-canvas/cli`). Needs RSA keys and an OAuth consumer. |
| `canvas_ai` | AI-powered page building via multi-agent orchestrator (requires `ai_agents` module) |
| `canvas_personalization` | Conditional content personalization |
| `canvas_vite` | Vite integration for code component development |

### Companion module
- **`canvas_bootstrap`** (1.0.1) — Integrates Bootstrap 5 components into Canvas. Provides theme-aware SDC deduplication and form UI grouping for component props. See `canvas-bootstrap-integration.md`.

### Theme
- **`canvas_stark`** — Internal Canvas theme used for editor rendering. Ships with the `canvas` module.

## Key Entity Types

### Content entities (stored in database)
| Entity type | Machine name | Purpose |
|------------|-------------|---------|
| Canvas Page | `canvas_page` | Standalone visual pages built with Canvas components. NOT nodes. Accessible at `/canvas` or Content > Pages. Front-end path: `/page/{id}` |

### Config entities (stored in YAML, version-controlled)
| Entity type | Entity type ID | Config prefix | Purpose |
|------------|---------------|--------------|---------|
| Component | `component` | `canvas.component.*` | Tracks every component available to Canvas — one per eligible SDC, Block, or JS component. Controls enabled/disabled status |
| JavaScriptComponent | `js_component` | `canvas.js_component.*` | Stores code component source (JSX, CSS, props schema). Can be "internal" (draft) or "exposed" (available to editors) |
| AssetLibrary | `asset_library` | `canvas.asset_library.*` | In-browser code libraries — shared CSS/JS code that code components can import. Has a special `global` library |
| PageRegion | `page_region` | `canvas.page_region.*` | Stores a component tree for a theme region (header, footer, etc.). Site-wide — not per-page |
| ContentTemplate | `content_template` | `canvas.content_template.*` | Controls how a node content type renders using Canvas components (replaces Manage Display) |
| Pattern | `pattern` | `canvas.pattern.*` | Reusable component composition patterns saved by site builders |
| Folder | `folder` | `canvas.folder.*` | Organizes components, patterns, and code components into folders in the Canvas UI |
| StagedConfigUpdate | `staged_config_update` | `canvas.staged_config_update.*` | Internal: batched config entity changes staged for publishing. Disabled by default |

**Important:** The entity type ID (used in `getStorage()`) is NOT prefixed with `canvas_`. Use `\Drupal::entityTypeManager()->getStorage('component')`, not `getStorage('canvas_component')`. The `canvas.component.*` prefix is only used for config file naming.

### The `component_tree` field type

Canvas stores per-entity component trees in a custom field type: `component_tree` (plugin class: `ComponentTreeItem`). This field stores the component tree structure, inputs, and parent/slot relationships for components that belong to a specific content entity (as opposed to ContentTemplate or PageRegion config entities, which store their trees directly).

**Key classes:**

| Class | Role |
|-------|------|
| `ComponentTreeItem` | FieldType plugin — defines the storage schema (uuid, component_id, version, inputs, parent_uuid, slot) |
| `ComponentTreeItemList` | TypedData list class — provides tree operations (merge, inject subtrees, traverse) |
| `ComponentTreeWidget` | FieldWidget plugin — minimal widget showing "managed by Canvas editor" placeholder |
| `NaiveComponentTreeFormatter` | FieldFormatter plugin — renders the stored tree using the SDC rendering pipeline |

**Developer notes:**
- The field type uses `default_widget: "canvas_component_tree_widget"` — always pair new field types with a widget, even a minimal one, so forms render correctly.
- `ComponentTreeWidget::extractFormValues()` is intentionally empty. Canvas manages field data via its own API endpoints (`/canvas/api/v0/layout/*`), not through standard Drupal form submission.
- The field is visible in the Drupal admin UI "Add field" form and can be attached to any fieldable entity type via the standard Field UI.
- `ComponentTreeLoader::getCanvasFieldName()` finds the first `component_tree` field on a bundle — field names are not hardcoded beyond the auto-provisioned default (`field_component_tree`). Different bundles can use different field names.

## Component Lifecycle

1. **Discovery** — Canvas discovers components from SDCs (themes/modules), block plugins, and JavaScriptComponent config entities
2. **Requirements check** — Each component must meet criteria (schema, titles, examples for SDCs; fully validatable config schema for blocks; valid prop shapes for all)
3. **Component config entity created** — One `canvas.component.*` config entity per eligible component, auto-generated
4. **Enabled/disabled** — Site builders control which components are available to editors via Appearance > Components (`/admin/appearance/component`)
5. **Instantiation** — Editors drag components into a Canvas Page, PageRegion, or ContentTemplate, populating props and slots

## Config Storage

All Canvas config entities live in `config/<site>/`:

```
config/<site>/
├── canvas.component.block.*           # Block components
├── canvas.component.sdc.<theme>.*     # Theme SDC components
├── canvas.component.sdc.<module>.*    # Module SDC components (may be auto-disabled by deduplication)
├── canvas.page_region.<theme>.*       # Page regions (one per theme region)
├── canvas.content_template.*          # Content templates (one per content type + view mode)
├── canvas.js_component.*             # Code components
├── canvas.asset_library.*            # In-browser code libraries
├── canvas.folder.*                   # Component folder organization
└── canvas.pattern.*                  # Saved component patterns
```

## Additional Canvas Infrastructure

- **Component folders** — Organize components into groups in the Canvas editor UI (e.g., "Lists (Views)", "System", "Menus"). Stored as `canvas.folder.*` config entities.
- **Global asset library** — Empty CSS/JS library (`canvas.asset_library.global`) for shared code component assets. Available for code components to import shared CSS/JS.
- **Canvas text formats** — Two Canvas-specific text formats for rich text editing in component props: `canvas_html_block` (block-level HTML) and `canvas_html_inline` (inline HTML). Both use CKEditor 5 with restricted HTML tags. See `configuration/text-formats.md`.

## Integration Points

- **Alchemize Forge theme** (`web/themes/custom/alchemize_forge`) — Custom sub-theme providing SDC components (`sdc.alchemize_forge.*`) and a `page.html.twig` that renders page chrome via `drupal_block()`. Canvas manages only the content area — the theme owns header, navigation, and footer. See `canvas-page-regions.md` for the architecture.
- **Twig Tweak module** — Provides `drupal_block()` for rendering blocks directly in Twig templates
- **canvas_bootstrap module** — Deduplicates SDC components between theme and module; provides form UI grouping
- **Configuration Management** — All Canvas config flows through `ddev drush cex/cim`. See `drush-config-workflow.md`
- **`@drupal-canvas/cli`** — External CLI for code component development. Requires `canvas_oauth` submodule. See `canvas-cli.md`
- **Canvas HTTP API** — Internal API (`/canvas/api/v0/*`) for the editor UI; external API endpoints marked with `canvas_external_api: true` for CLI operations. External routes support: `component`, `js_component`, `asset_library`, `folder`, `pattern`, `content_template`

## Related Documentation

| Document | What it covers |
|----------|---------------|
| `canvas-cli.md` | `@drupal-canvas/cli` command reference and OAuth setup |
| `canvas-sdc-components.md` | SDC component schema, props, slots, Bootstrap component catalog |
| `canvas-code-components.md` | Code components — JSX, packages, ESLint, CLI workflow |
| `canvas-page-regions.md` | Site-wide layout via PageRegion config entities |
| `canvas-content-templates.md` | Node rendering via ContentTemplate config entities |
| `canvas-bootstrap-integration.md` | Theme-aware deduplication and form UI groups |
| `canvas-ai-assistant.md` | AI page building agents (future enablement) |
| `canvas-build-guide.md` | Agent workflow: decomposing designs into Canvas component trees |
| `canvas-build-example.md` | Worked example: homepage hero + product grid decomposition |
