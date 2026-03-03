# Drupal + Canvas Developer Reference

Generic developer reference library for Drupal + Canvas development. These docs teach **how to work with Drupal** — content types, views, Canvas page building, theming, modules, developer tools, and configuration management.

**This is NOT project-specific documentation.** Project-specific state (this site's content types, brand colors, installed modules, etc.) belongs in `.alchemize/canonical-docs/`. Development workflow and config management docs live in `.alchemize/canonical-docs/global/`.

## Structure

| Directory | Scope |
|-----------|-------|
| `canvas/` | Canvas page builder: system overview, CLI, components, regions, templates, build guide, shape matching |
| `data-model/` | Content types, fields, entity types, taxonomy, views, **media handling** |
| `configuration/` | Site settings, users/roles, block layout, performance, text formats |
| `theming/` | Theme stack, SCSS architecture, token pipeline, build workflow, layout strategy. See also `canonical-docs/global/css-strategy.md` for CSS authoring rules. |
| `integrations/` | Metatag, Search API, Webform, Media, Redirects, Menus, OAuth |
| `infrastructure/` | Environment setup (DDEV), module architecture, developer tools (drush generate, field commands, Canvas CLI, capability scripts, contrib patching, PHPCS) |
| `features/` | Feature Composition docs: end-to-end wiring guides for cross-layer features |

## Reading Paths

### For Themers / Front-End Work

1. **`.alchemize/canonical-docs/global/component-strategy.md`** — **Read first.** 8-layer design system architecture, brand type scale, `.role-*` typography roles, `.preset-*` layout presets, SDC-first methodology, gold-standard pattern
2. **`theming/theming.md`** — SCSS architecture, token pipeline, build workflow, CSS custom properties reference
3. **`.alchemize/canonical-docs/global/css-strategy.md`** — **Read before writing any CSS.** Bootstrap utility vs custom SCSS vs preset classes, specificity model, token pipeline, SDC SCSS rules
4. **`theming/global-layout-strategy.md`** — Page template structure, container strategy, full-bleed breakout pattern, Wrapper component props
5. **`canvas/canvas-sdc-components.md`** — Tier 1 (purpose-built) + Tier 2 (primitive) SDC components with props, slots, presets
6. **`canvas/canvas-sdc-example.md`** — **Tier 1 SDC worked example** — Hero Carousel creation walkthrough
7. **`canvas/canvas-build-guide.md`** — Design-to-component methodology (starts with SDC identification)
7. **`canvas/canvas-page-regions.md`** — Theme-driven chrome architecture, Canvas PageRegion management
8. **`canvas/canvas-content-templates.md`** — One-template-per-content-type rule, field linking
9. **`canvas/canvas-shape-matching.md`** — How entity fields map to component props

### For Building a Content Type + Listing Feature

1. `infrastructure/developer-tools.md` — Which tool when: `drush field:create`, capability scripts, `drush generate`, Canvas CLI
2. `features/content-type-listing-pattern.md` — **End-to-end walkthrough** with implementation steps
3. `data-model/content-types.md` — Content type reference, field specs, view modes, PHP API patterns
4. `data-model/media-handling.md` — **Mandatory:** Media rules, placeholder images, `media-lib.php` helpers
5. `data-model/views.md` — Views + Canvas integration, creating Views via UI + export
6. `canvas/canvas-build-guide.md` — Programmatic Canvas page assembly

### For Canvas Page Building

1. `.alchemize/canonical-docs/global/component-strategy.md` — **Read first.** 8-layer design system, preset system, when to create SDCs vs use primitives
2. `canvas/canvas-system-overview.md` — Module stack, entity types, component lifecycle
3. `canvas/canvas-sdc-components.md` — Component catalog: Tier 1 (purpose-built) + Tier 2 (primitives) with presets
4. `canvas/canvas-build-guide.md` — Design-to-component methodology (Phase 0: SDC identification)
5. `canvas/canvas-sdc-example.md` — **Tier 1 SDC worked example** — Hero Carousel from design intent to Canvas integration
6. `canvas/canvas-build-example.md` — **Tier 2 primitive worked example** — ExampleHomepage with preset-based Drush script
7. `canvas/canvas-page-regions.md` — Site-wide chrome (header, footer, nav)
7. `canvas/canvas-code-components.md` — JSX components for gaps (Tier 3)
8. `canvas/canvas-cli.md` — CLI workflow for code components

### For Custom Module / Tool Development

1. `infrastructure/developer-tools.md` — `drush generate`, `drush field:create`, capability scripts, Canvas CLI, Drush config commands
2. `infrastructure/environment-setup.md` — DDEV setup, running commands

## Principles

- **Generic knowledge only.** These docs describe how Drupal/Canvas works, not what's on a specific site.
- **Flat categories.** One level deep. No site-name nesting.
- **Code is the authority.** Docs describe what the code does, not what it should do.
