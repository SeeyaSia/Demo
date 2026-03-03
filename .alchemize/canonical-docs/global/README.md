# Global Documentation

**These are the most important docs in the project.** Up to 5 files that are revealed directly to agents. Read these before any other documentation.

## Documents

| File | Purpose |
|------|---------|
| `architecture.md` | **System Blueprint.** Invariant rules, composition patterns, developer rules, design smell detection. Read this first. |
| `component-strategy.md` | **Design System & Component Strategy.** 8-layer architecture (tokens → Bootstrap overrides → typography roles → semantic tokens → layout presets → global overrides → SDC styles → Canvas composition), brand type scale, `.role-*` and `.preset-*` SCSS classes, SDC-first methodology, gold-standard pattern. Read before building any page or writing any SCSS. See also `canvas-sdc-example.md` for the Tier 1 SDC worked example. |
| `css-strategy.md` | **CSS Authoring Rules.** Bootstrap utility ownership vs custom SCSS vs preset classes, specificity model, token pipeline, full-bleed pattern, SDC SCSS rules. Read before writing any CSS. |
| `document-architecture.md` | Doc standards, project layer model, Feature Composition template. |
| `drush-config-workflow.md` | Drush commands, config import/export, capability scripts. |
| `development-workflow.md` | Composer, deployment, shared dev processes. |
| `contrib-workflow.md` | Contributing to upstream Drupal contrib modules (issue forks, branch management, integration). |

## Reading Paths

After reading these global docs, see the [root README](../README.md) for role-based reading paths:

- **Building a content type + listing feature** — `drush field:create`, capability scripts, Views, Canvas page wiring
- **Themers / front-end work** — `component-strategy.md` first, then SCSS, SDC components, Canvas build guide
- **Content modeling / architecture** — content types, Views, ContentTemplates
- **Custom module / tool development** — `drush generate`, `drush field:create`, Canvas CLI, capability scripts, PHPCS
- **Contrib module work** — `contrib-workflow.md` for upstream contribution (issue forks, merge requests); `development-workflow.md` for Composer patching
- **Canvas page building** — `component-strategy.md` first, then system overview, SDC components (Tier 1 + Tier 2), build methodology (Phase 0: SDC identification)

> **Start with `infrastructure/developer-tools.md`** for the comprehensive "which tool when" reference covering all tools: `drush generate`, `drush field:create`, Canvas CLI (`npx canvas`), capability scripts, Drush config commands, contrib patching, Pathauto, and PHPCS.
