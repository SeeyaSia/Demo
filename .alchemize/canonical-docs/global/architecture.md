# Architecture

## Purpose

Describes the current architecture of the AlchemizeTech website: Drupal version, theme stack, module landscape, page building approach, configuration management, and local development environment. Read this first when onboarding or before making structural changes.

## System Overview

AlchemizeTech is a **Drupal 11** site using the **standard** install profile. It is a **single-site** installation (not multisite). The site uses **Drupal Canvas** as its visual page builder and **Bootstrap 5** (via Bootstrap Barrio + Alchemize Forge custom theme) for front-end styling. Local development runs on **DDEV**.

The site has a custom theme (`alchemize_forge`) with a Webpack-based SCSS build pipeline, a custom module (`alchemize_components`) providing an SDC hero slider component, and a comprehensive set of Canvas content templates and page regions.

> **Note:** `web/modules/contrib/canvas/` contains local modifications being contributed back to drupal.org (issues [#3498525](https://www.drupal.org/project/canvas/issues/3498525), [#3518248](https://www.drupal.org/project/canvas/issues/3518248)). These changes enable per-content slot editing for node entities. This is an authorized exception to the "never edit contrib directly" policy — see the ATWEB-7 ticket for details.

## Theme Stack

| Layer | Theme | Location | Role |
|-------|-------|----------|------|
| Base theme | `bootstrap_barrio` | `web/themes/contrib/bootstrap_barrio/` | Provides Bootstrap 5 integration with Drupal's render system |
| Custom theme | `alchemize_forge` | `web/themes/custom/alchemize_forge/` | Sub-theme of Barrio with Webpack, SCSS, Canvas SDC components, and custom `page.html.twig`. **Default front-end theme.** |
| Admin theme | `claro` | Core | Core admin theme |
| Canvas internal | `canvas_stark` | Ships with `canvas` module | Used internally by Canvas for editor rendering |

**Alchemize Forge is the active default theme.** It was generated from the `bootstrap_forge` starter kit and is now an independent custom theme. Canvas handles component-level customization visually; further sub-theming is not needed.

### Theme build pipeline
The theme uses Webpack to compile SCSS → CSS. Build with `ddev theme-build` (see `development-workflow.md` for full details). **Never edit CSS files directly.**

## Rendering Model

This is the foundational mental model for how pages are built. Every agent and developer must understand these layers before making changes.

### The Four Layers

```
┌──────────────────────────────────────────────────────────────┐
│ LAYER 1: Theme Chrome (page.html.twig + drupal_block())     │ ← Theme-owned, not Canvas
│   Header: site branding, main menu, account menu            │
│   Footer: footer content                                    │
│   Admin: local tasks, local actions                         │
├──────────────────────────────────────────────────────────────┤
│ LAYER 2: Canvas PageRegions (minimal — activation anchor)   │ ← Just keeps Canvas active
│   Only "none" region enabled. Infrastructure is NOT here.   │
├──────────────────────────────────────────────────────────────┤
│ LAYER 3: Content Area ({{ page.content }})                  │ ← Route-determined
│   Renders EITHER:                                           │
│     A) Canvas Page entity (landing/unique pages)            │
│     B) Node rendered via Canvas Content Template            │
├──────────────────────────────────────────────────────────────┤
│ LAYER 4: Per-Content Canvas (nested inside Layer 3B)        │ ← Optional, node-specific
│   Canvas field on node → editable slot inside the template  │
│   Editors drag components into exposed slots per-node       │
└──────────────────────────────────────────────────────────────┘
```

### Rule 1: Theme Owns Chrome, Canvas Owns Content

The theme's `page.html.twig` renders the page frame (header, navigation, footer) directly using `drupal_block()` from Twig Tweak. Canvas does **not** manage site chrome. Canvas manages only the content area.

**Why:** Infrastructure blocks (menus, site branding, tabs) break when managed as Canvas components — they have rendering context requirements that fail in the Canvas editor preview. The theme provides a stable, predictable frame. Canvas provides flexible, editor-controlled content.

**To change the header/nav/footer:** Edit `page.html.twig`, not Canvas PageRegions.

### Rule 2: Two Types of Pages

| Page Type | Entity | When to Use | How It Works |
|-----------|--------|-------------|-------------|
| **Canvas Page** | `canvas_page` | Unique / landing / special pages (homepage, about, etc.) | Standalone Canvas entity. Full drag-and-drop. No content type, no fields. Lives at `/page/{id}`. |
| **Content Page** | `node` | Instances of a content type (articles, projects, etc.) | Node with structured fields + Canvas Content Template controlling the full-view layout. |

**Decision tree for agents:**
- Need a one-off page with custom layout? → **Canvas Page**
- Need many pages with the same structure but different content? → **Content type + Content Template**
- The homepage (`/homepage`) is a Canvas Page.

### Rule 3: Fields Are Metadata, Canvas Owns Display

For any content type with a Canvas content template:

- **The Canvas content template controls all full-view rendering.** Standard Drupal field formatters are NOT used in the `full` view mode — Canvas replaces the entire display pipeline.
- **Node fields (title, tags, media, dates, URLs) are structured metadata.** They exist for: programmatic use (views, search API, JSON API), other view modes (teaser, card), backend logic, and as dynamic prop sources fed into the Canvas template.
- **The only "visual" field is the canvas field** (`field_canvas_body`, `field_component_tree`, etc.). This stores per-node component overrides for the template's exposed slots.
- **Do not add fields to a content type for display purposes.** If you need visual content on a page, add it as Canvas components in the template or the per-content canvas slot.

> **Note:** We are currently exploring making this an absolute rule for all Canvas-enabled content types. For now, treat it as the default — only deviate if specifically asked to for a particular content type.

### Rule 4: Canvas PageRegions Are Activation Anchors Only

Canvas requires ≥1 enabled PageRegion to activate `CanvasPageVariant` (which enables content templates). We maintain a minimal `alchemize_forge.none` PageRegion for this purpose. **Do not create PageRegions for header, footer, nav, or other infrastructure regions.**

See `drupal/docs/canvas/canvas-page-regions.md` for the full rationale and operational rules.

## Page Building: Drupal Canvas

Canvas is the primary page building system. It provides the mechanisms described in the Rendering Model above.

### Canvas Pages (`canvas_page` entity)
- Standalone content entity type, **not nodes**
- Built entirely with dragged-in components in a visual editor
- Accessible at `/canvas` or **Content > Pages** tab
- Each page lives at `/page/{id}` on the front end
- Supports revisions, publishing workflow, auto-save
- **Use for:** Homepage, landing pages, about page, any unique one-off page

### Content Templates (config entities)
- Control how **node content types** render their `full` view mode using Canvas components
- Replace traditional Manage Display / Layout Builder for full-view display
- Structured field data stays in nodes; Canvas controls the visual layout
- **Active templates:** `node.article.full`, `node.page.full`, `node.project.full` — each with per-content slot editing via exposed slots
- Per-content editing allows each node to have its own components inside a template-defined structure (template shell + editable slots)
- **Use for:** Any content type where you want consistent structure across all instances

### Per-Content Canvas Fields (nested editing)
- Content types may have a `component_tree` field (e.g., `field_canvas_body`)
- This field stores per-node components that fill exposed slots in the content template
- Creates a **nested Canvas**: the template defines the outer structure, the node's canvas field defines the inner content
- Editors access this via the **Layout tab** on nodes
- **Use for:** Giving editors per-page customization within a consistent template frame

### Page Chrome (theme-managed, NOT Canvas)
- The theme's `page.html.twig` renders header, navigation, footer via `drupal_block()` + Twig Tweak
- Infrastructure blocks are placed to region `none` in block layout (bypassed by Canvas)
- A minimal PageRegion (`alchemize_forge.none`) keeps Canvas active without managing any visible regions
- **To change chrome:** Edit `page.html.twig` or theme SCSS. Do not use Canvas PageRegions for infrastructure.

### Canvas Components

Components follow a **two-tier SDC architecture** within an **8-layer design system** (see `component-strategy.md` for the full architecture, brand type scale, role/preset SCSS classes, and gold-standard SDC pattern):

1. **Tier 1: Purpose-Built SDCs** (primary — reusable patterns) — Reusable, design-specific components
   - `hero-carousel`, `carousel-slide` — Full-viewport hero with crossfade transitions (gold-standard pattern)
   - New SDCs are created in `web/themes/custom/alchemize_forge/components/` as designs require them

2. **Tier 2: Primitive SDCs** (supporting — glue/one-off content) — Bootstrap building blocks
   - `alchemize_forge` provides 12 Bootstrap primitives (wrapper, heading, paragraph, button, link, image, card, row, column, accordion, accordion-container, blockquote)
   - These have **presets** (Wrapper, Heading, Paragraph, Card) that map to single semantic SCSS classes (`.role-*` for typography roles, `.preset-*` for layout composition) — always use presets before manual props
   - `bootstrap_barrio` provides additional components (badge, container, div, figure, modal, teaser, toasts)
   - `canvas_bootstrap` module provides duplicate components that are **auto-disabled** by theme-aware deduplication

3. **Block components** — any Drupal block plugin is available as a Canvas component
   - Core blocks: site branding, breadcrumbs, menus, messages, powered-by
   - Contrib blocks: webform block
   - Views blocks: automatically discovered from any View with a Block display
   - Most core-provided blocks are disabled by default; enable via Appearance > Components

## Enabled Modules

### Contributed (non-core)
| Module | Purpose |
|--------|---------|
| `canvas` | Visual page builder (Canvas core) |
| `canvas_bootstrap` | Bootstrap component deduplication + UI form groups for Canvas |
| `better_exposed_filters` | Enhanced Views exposed filter widgets |
| `metatag` | SEO meta tags |
| `pathauto` | Automatic URL alias generation |
| `rabbit_hole` | Control entity display behavior (redirect, page not found, etc.) |
| `redirect` | URL redirects |
| `search_api` | Search framework |
| `search_api_db` | Database backend for Search API |
| `token` | Token system (dependency for pathauto and metatag) |
| `twig_tweak` | Twig template utility functions |
| `webform` | Form builder |
| `webform_ui` | Webform admin UI |

### Notable core modules enabled
- `content_moderation` + `workflows` — Editorial workflow
- `layout_builder` + `layout_discovery` — Enabled but Canvas is the primary page builder
- `media` + `media_library` — Media management
- `ckeditor5` — Rich text editing
- `navigation` — Admin navigation

## Content Types

| Machine name | Label | Canvas field | Canvas template | Notes |
|-------------|-------|-------------|-----------------|-------|
| `article` | Article | `field_component_tree` | `node.article.full` (with exposed slot) | Standard install profile default. Full per-content Canvas slot editing. |
| `page` | Basic page | `field_canvas_body` | `node.page.full` | Standard install profile default. Canvas field added for template slot editing. |
| `project` | Project | `field_project_canvas` | `node.project.full` | Custom content type for portfolio projects. Canvas field added for template slot editing. |

Each content type with a canvas field (`component_tree` type) can have per-content slot editing when its ContentTemplate has exposed slots configured. Different content types may have differently named canvas fields — `ComponentTreeLoader::getCanvasFieldName()` discovers the first `component_tree` field on the bundle automatically. See `drupal/docs/canvas/canvas_as_field.md` for the full developer guide.

## Configuration Management

- **Single site** with config sync directory at `config/alchemizetechwebsite/`
- Configured in `web/sites/default/settings.php` as `../config/alchemizetechwebsite`
- All config changes flow through `ddev drush cex -y` (export) and `ddev drush cim -y` (import)
- Config files are version-controlled in git

See `drush-config-workflow.md` for the complete workflow.

## Infrastructure

| Component | Version/Details |
|-----------|----------------|
| Drupal | 11.x |
| PHP | 8.3 |
| Database | MySQL 8.0 |
| Local dev | DDEV |
| DDEV project name | `alch-alchemizetechwebsite-main-dev` |
| Site URL (local) | `https://alch-alchemizetechwebsite-main-dev.ddev.site` |

### Key paths
- Drupal root: `web/`
- Config sync: `config/alchemizetechwebsite/`
- Contrib modules: `web/modules/contrib/`
- Custom modules: `web/modules/custom/` (`alchemize_components`)
- Contrib themes: `web/themes/contrib/`
- Custom themes: `web/themes/custom/` (`alchemize_forge`)
- Public files: `web/sites/default/files/`
- DDEV config: `.ddev/config.yaml`
- Composer: `composer.json`, `composer.lock`

## Change Surface (what typically changes)

- `composer.json` / `composer.lock` — Adding/updating modules and themes
- `config/alchemizetechwebsite/*.yml` — All Drupal config (content types, fields, views, Canvas components, page regions, etc.)
- `web/themes/custom/alchemize_forge/templates/layout/page.html.twig` — Page chrome (header, nav, footer). **This is the outermost rendering layer.**
- `web/themes/custom/alchemize_forge/scss/` — Design system SCSS: tokens, variables, typography, role classes (`_typography-roles.scss`), layout presets (`_layout-presets.scss`), global overrides (`style.scss`). Rebuild with `npm run build` or `ddev theme-build`. See `component-strategy.md` for the 8-layer architecture.
- `web/themes/custom/alchemize_forge/components/` — SDC component templates and schemas
- `web/modules/custom/alchemize_components/` — Custom SDC components (hero slider, etc.)

## Constraints and Tradeoffs

- **Rendering model is law**: See the Rendering Model section above. Theme owns chrome, Canvas owns content. Do not create Canvas PageRegions for infrastructure. Do not use field formatters for full-view rendering on Canvas-enabled content types.
- **Custom theme is the customization layer**: `alchemize_forge` is a custom sub-theme of Bootstrap Barrio. Edit SCSS variables, add custom components, and override templates here. Edit `page.html.twig` to change the page frame (header, nav, footer).
- **Canvas is new (1.x)**: Canvas Pages are a separate entity type from nodes. Per-content slot editing for node entities is implemented (ATWEB-7/ATWEB-9) and functional across article, page, and project content types. The `component_tree` field type is available in the Drupal Field UI for attaching to any content type. Exposed slot configuration is available in the template editor's component context menu.
- **Layout Builder is enabled but not primary**: Layout Builder is installed (likely from the standard profile) but Canvas is the intended page building tool. These could conflict if both are used for the same display.
- **canvas_bootstrap module must stay enabled**: Despite its components being auto-disabled in favor of `alchemize_forge`'s identical components, the module provides essential deduplication logic (`ThemeAwareSingleDirectoryComponentDiscovery`) and Canvas form UI enhancements.
- **Twig Tweak is required**: The `drupal_block()` calls in `page.html.twig` depend on the `twig_tweak` module. Do not uninstall it.

## Failure Modes

- **Duplicate Canvas components**: If `canvas_bootstrap` module's deduplication fails (e.g., `alchemize_forge` is not the default theme, or components were generated before the hook ran), run `ddev drush php-eval "\Drupal::service('Drupal\canvas\ComponentSource\ComponentSourceManager')->generateComponents();"` followed by `ddev drush cr`.
- **Missing config export**: If config changes are made in the UI but not exported (`ddev drush cex`), they will be lost on the next `ddev drush cim` or environment rebuild.
- **DDEV database not running**: Drush commands run outside DDEV (`vendor/bin/drush`) will fail because the database is inside the DDEV container. Always use `ddev drush`.
- **Image component with empty media**: Passing an empty array `[]` or NULL for the image `media` prop causes `AssertionError` in `StaticPropSource::isMinimalRepresentation()`. Either omit the image component entirely or provide a complete media object with `src`, `alt`, `width`, `height`.
- **ComponentTreeItemList vs array**: `getComponentTree()` returns a `ComponentTreeItemList` object. Passing it to `setComponentTree()` or `set('component_tree', ...)` causes a `TypeError` in `generateComponentTreeKeys()`. Use `get('component_tree')` for the raw array on config entities, or `->getValue()` on content entity field lists.

## Notes for Future Changes

- **New unique/landing page**: Create a Canvas Page (`/canvas` → Pages → New). This is a standalone entity with full Canvas control — no content type needed.
- **New content type**: Create via UI → export config → create a Canvas Content Template for the `full` view mode → add a `component_tree` field if per-content editing is needed. Add standard fields for metadata (title, tags, media, dates) but do NOT add fields for display — Canvas handles rendering. **Always use media reference fields** (`entity_reference` → `media:image`) for images — never direct image fields. When generating demo content, use `media-lib.php` to create placeholder media. See `drupal/docs/data-model/media-handling.md` for the complete media workflow and `drupal/docs/canvas/canvas_as_field.md` for Canvas field setup.
- **Changing the header/nav/footer**: Edit `web/themes/custom/alchemize_forge/templates/layout/page.html.twig`. Use `drupal_block()` to render blocks. Do NOT use Canvas PageRegions for infrastructure.
- **Custom modules**: Place in `web/modules/custom/`. Follow Drupal naming conventions. See `alchemize_components` for an example.
- **New SDC components**: Follow the SDC-first methodology in `component-strategy.md`. Add to the theme (`web/themes/custom/alchemize_forge/components/`), following the `hero-carousel` gold-standard pattern (`.component.yml` + `.twig` + `.scss` + optional `.js`). Run `ddev drush cr` to discover, then `ddev drush cex -y` to export.
- **JavaScript Code Components**: Can also be created directly in the Canvas UI editor.
- **Views as Canvas components**: Create a View with a Block display; it will auto-discover as a Canvas component. Enable it via Appearance > Components.
