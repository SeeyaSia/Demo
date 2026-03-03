# Documentation Architecture

## Purpose

This document defines:
1. **How canonical documentation is structured and written** (so agents can create and review docs consistently).
2. **The project layer model (Drupal + Canvas)** used to classify changes and decide *where to work first* in the codebase.
3. **How cross-layer Drupal features are assembled**, via a Drupal-native "Feature Composition" doc type.

**Audience:** Agents (developer + reviewer). Docs are short, scannable, and grounded in real paths and code.

---

## Project Layer Model (Drupal + Canvas)

Use this to categorize where work lives. When documenting or implementing, identify the **layer(s)** involved so agents can open the right files and docs.

| Layer | What it covers | Where it lives (typical paths) | When to look here |
|-------|----------------|--------------------------------|-------------------|
| **1. Front end** | Styles (SCSS/CSS), client-side JS, design tokens, assets | `web/themes/contrib/bootstrap_forge/` (scss, js, images); `web/libraries/` | Styling, animations, client behavior, assets |
| **2. Theming** | Twig templates, theme hooks, libraries, render arrays, view modes, SDC components | `web/themes/contrib/bootstrap_forge/templates/`, `*.theme`, `libraries.yml`, `components/` | Markup, layout, which template runs where, view mode display, Canvas SDC components |
| **3. Canvas** | Canvas Pages, Content Templates, Page Regions, Canvas component config, code components | `config/alchemizetechwebsite/canvas.*`, Canvas UI (`/canvas`), `web/modules/contrib/canvas/` | Page building, visual layout, component configuration, site-wide chrome |
| **4. Configuration** | Config YAML, module install/config, field/entity/config, DB-config mapping | `config/alchemizetechwebsite/`; `*.install` hooks; `*.schema.yml`; CMI exports | Content types, fields, form/view display, vocabularies, site settings |
| **5. Back-end** | Custom modules, business logic, integrations, APIs, data model | `web/modules/custom/`; services, plugins, entities, event subscribers | New functionality, integrations, custom logic |
| **6. Infrastructure** | Server, hosting, env, DB, runtimes, CI/CD | `.ddev/`, `composer.json`, env files, deployment config | Local dev, deploy, DB, PHP/node versions, hosting |

**Important (Configuration layer):**
- Configuration describes *structure*, not content. Content lives in the database.
- YAML changes often require corresponding update hooks (`*.install`) and/or a standardized mutation/export step. Never assume "edit YAML" is sufficient unless confirmed by an example or existing workflow.
- Config directory: `config/alchemizetechwebsite/`. Always export via `ddev drush cex -y` after changes.

**Important (Canvas layer):**
- Canvas Pages (`canvas_page`) are separate entities from nodes. They are **not** content types.
- Canvas Page Regions are site-wide config; editing a header in the Canvas editor changes it globally.
- Canvas components come from SDC (themes/modules) and Block plugins. Component config entities live in `config/alchemizetechwebsite/canvas.component.*.yml`.
- Content Templates control how node content types render in Canvas. They are config entities, not content.

### How to Use the Layer Model

When starting a task or reviewing a change:

1. **Classify the primary intent** of the task:
   - Visual only? -> Front end / Theming
   - Page layout or component arrangement? -> Canvas
   - Data shape or site structure? -> Configuration
   - Behavior, logic, integrations? -> Back-end
   - Runtime, DB, deploy, tooling? -> Infrastructure

2. Identify **secondary layers** only if necessary.
   - Example: "Change field output markup" -> Theming + Front end
   - Example: "Add new field with default value" -> Configuration (+ Back-end if custom logic)
   - Example: "Create a landing page with hero and cards" -> Canvas + Configuration (if new content type/fields needed)
   - Example: "Style the accordion component differently" -> Front end (SCSS in bootstrap_forge)

3. Open files in **layer order**:
   Configuration -> Canvas -> Back-end -> Theming -> Front end
   (Infrastructure only when required)

Most tasks should not touch more than **two layers**. Paths above are indicative; see `architecture.md` for this repo's actual layout.

---

## Feature Composition Docs (Drupal Cross-Layer Wiring)

The layer model classifies **where to change things**.

For Drupal, we also need a model that explains **how user-facing features are assembled across layers** (entities -> fields -> view modes -> config -> preprocess -> templates -> libraries -> Canvas components).

**Feature Composition** is a Drupal-specific doc type for that purpose. It does not replace the layer model; it complements it.

**Rule (one sentence that matters):** For cross-layer Drupal features, documentation must include a Feature Composition section that enumerates entities, fields, view modes, config, code, theme assets, and Canvas components involved.

### When to Write a Feature Composition Doc

Write one when a feature:
- spans **3+ layers**, or
- is **user-facing and reusable**, or
- has **non-obvious Drupal wiring** (e.g. view modes, Views blocks, preprocess attaching libraries, entity reference chains, config-driven behavior, Canvas Content Templates mapping fields to components).

Examples:
- Hero with slider
- Card grid paragraph
- Landing page built in Canvas with embedded Views
- Blog listing with Content Template
- Contact form (Webform) embedded in a Canvas Page

### Where Feature Composition Docs Live

Put these under `.alchemize/canonical-docs/features/` (one file per feature), for example:
- `.alchemize/canonical-docs/features/hero-slider.md`

### Feature Composition Template (Additive to the Standard Template)

Use the standard doc structure below, and add the Drupal-specific sections **Feature overview** and **Composition map**.

#### Feature Overview (Drupal-centric)

One paragraph answering:
- What entity "owns" this feature (Node? Canvas Page? Paragraph? Block? View? Media?)?
- Is it reusable?
- Is it editorial (config/content-driven) or hard-coded (code-driven)?

#### Composition Map (required; Drupal wiring checklist)

This section is intentionally mechanical. It's a wiring diagram so agents don't guess.

**This is not a fixed checklist.** If the feature uses additional Drupal subsystems, add subsections until the wiring is complete (e.g. permissions/roles, routes/controllers, blocks/placements, Canvas Page Regions, Canvas Content Templates, Webform handlers, Search API indexes/servers, cron/queues, media types/view modes, taxonomy dependencies, third-party settings, external libraries).

**Entity & data model**
- Entity type + bundle (example: `paragraph.hero_slider`, or `canvas_page`)
- Parent usage (example: `node.landing_page` -> `field_sections`, or standalone Canvas Page)
- Cardinality
- Revisioned?

**Fields**
- List the feature's fields and what they reference (text/media/node/link/list/etc.)

**View modes**
- List relevant view modes
- State where they're used (node display, paragraph display, Views row plugin/view mode, Canvas Content Template, etc.)

**Canvas components (if applicable)**
- Which SDC or block components are used
- How they're arranged in the Canvas component tree
- Any component props or slot mappings

**Canvas Content Template (if applicable)**
- Template name and target entity type + bundle + view mode
- Field-to-slot mappings

**Views (if applicable)**
- View name + display(s)
- What it provides (block/page)
- Contextual filters/relationships that matter
- Whether it's exposed as a Canvas block component

**Configuration (CMI)**
- Field storage: `config/alchemizetechwebsite/field.storage.*.yml`
- Field instances: `config/alchemizetechwebsite/field.field.*.yml`
- Form display: `config/alchemizetechwebsite/core.entity_form_display.*.yml`
- View display: `config/alchemizetechwebsite/core.entity_view_display.*.yml`
- Canvas component config: `config/alchemizetechwebsite/canvas.component.*.yml`
- Canvas page region config: `config/alchemizetechwebsite/canvas.page_region.*.yml`
- Any module settings that materially affect the feature

**Custom code (if any)**
- Preprocess hooks (example: `mymodule_preprocess_paragraph__hero_slider()`)
- Plugins/services used
- Paths to key classes

**Theme**
- Twig templates (example: `web/themes/contrib/bootstrap_forge/templates/paragraph/paragraph--hero-slider.html.twig`)
- Libraries attached (example: `bootstrap_forge.libraries.yml` entry)
- Asset entrypoints (JS/CSS) that implement behavior

**Image styles / responsive images (if applicable)**
- Image styles used
- Responsive image styles/mappings used

**Relationships**
- Where it's used (content types, Canvas Pages, paragraphs, blocks, views)
- What it depends on (modules, media types, view modes, image styles, Canvas components)
- "Breaks if ..." statements (missing image style, disabled view mode, view disabled, library not attached, Canvas component disabled)

---

## Document Structure (Template for All Docs)

Every canonical document follows this structure. Keep sections short; use bullets and real paths.

### Purpose
- What the document is for and when to read it.
- One or two sentences; avoid vague phrasing ("describes X" -> prefer "Explains how configuration flows from YAML to runtime services").

### System Overview
- High-level boundaries and responsibilities, not implementation detail.

### Change Surface (what typically changes here)
- The specific files, config keys, or services that usually change in this area.
- Helps agents focus diffs and reviewers spot unrelated edits.

### Concrete Examples (from code)

**Required.** Every document must include:
- At least one real file path
- At least one real config key, service, or class

If an example no longer exists, the document is considered **stale** and must be updated.

### Integration Points
- Where this system or area connects to others.
- Inputs/outputs, upstream/downstream dependencies, cross-module impact.

### Constraints and Tradeoffs
- Known compromises, legacy decisions, intentional debt.
- Stops agents from "fixing" things that are intentionally imperfect.

### Failure Modes (what breaks if this is wrong)
- Concrete symptoms: errors, UI regressions, data corruption, silent misbehavior.
- Helps QA and reviewers know what to test or re-check.

### Notes for Future Changes
- Where refactors would likely land; what not to duplicate; what usually breaks first.

---

## Document Placement and Metadata

- **One level of subdirs only:** `.alchemize/canonical-docs/<category>/<doc>.md`. No nesting under `<category>`.
- **Categories are not fixed.** The list below is a starting point, not a constraint. Create new categories when they make features clearer (e.g. `entities/`, `integrations/`, `workflows/`, `ops/`, `ui/`). If a feature spans multiple areas, prefer a `features/<feature>.md` Feature Composition doc and link out to supporting docs.
- **Categories** in this repo:
  - **`global/`**: Project-wide documentation (architecture, workflows, component strategy, CSS strategy, this document)
  - **`features/`**: Drupal Feature Composition docs (cross-layer "wiring maps" for user-facing features)
- **Scannability:** Start each doc with a clear **Purpose** and, if helpful, a one-line **Scope** (e.g. "Scope: Canvas page building and component management").

### Naming Rules
- Filenames describe **what the system does**, not how it's implemented.
  - Good: `user-sync.md`, `blog-listing.md`, `canvas-page-building.md`
  - Bad: `services.md`, `logic.md`, `implementation-notes.md`

Agents search by intent, not architecture terms.

---

## Summary

- **Layer model:** Front end -> Theming -> Canvas -> Configuration -> Back-end -> Infrastructure. Classify -> act; open files in layer order; most tasks touch 1-2 layers.
- **Feature Composition (Drupal):** For cross-layer user-facing features, write a `features/<feature>.md` doc with a required Composition Map enumerating entities, fields, view modes, config, code, theme assets, and Canvas components.
- **Doc template:** Purpose, System overview, Change surface, Concrete examples (required), Integration points, Constraints, Failure modes, Notes for future changes.
- **Placement:** One level of subdirs; filenames by intent; Purpose + optional Scope for scanning.
