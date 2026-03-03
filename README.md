# AlchemizeDev Site

A **Canvas-first Drupal 11** site built on Bootstrap 5 via the Alchemize Forge custom theme. Canvas provides visual page building with drag-and-drop SDC components, content templates with per-content slot editing, and site-wide page regions.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| CMS | Drupal 11 (standard profile) |
| Page builder | [Drupal Canvas](https://www.drupal.org/project/canvas) |
| Theme | `alchemize_forge` (custom sub-theme of Bootstrap Barrio) |
| CSS framework | Bootstrap 5.3 |
| Build | Webpack + SCSS + PostCSS (autoprefixer, pxtorem) |
| Local dev | DDEV (PHP 8.3, MySQL 8.0) |

## Quick Start

### Prerequisites

- [DDEV](https://ddev.readthedocs.io/en/stable/) installed
- Git

### Setup

```bash
# Clone the repo
git clone <repo-url> && cd demo

# Start DDEV
ddev start

# Install Composer dependencies
ddev composer install

# Build theme assets (SCSS -> CSS + Bootstrap JS)
ddev theme-build

# Import site configuration
ddev drush site:install standard --account-name=admin --account-pass=admin -y
ddev drush config:import -y

# Clear caches
ddev drush cr

# Open the site
ddev launch
```

### Existing Site (database already present)

```bash
ddev start
ddev composer install
ddev theme-build
ddev drush updatedb -y
ddev drush config:import -y
ddev drush cr
```

## Development

### Theme Build

The theme compiles SCSS to CSS via Webpack. **Never edit CSS directly.**

```bash
ddev theme-build            # Production build (minified)
ddev theme-build --dev      # Development build (source maps)
ddev theme-build --watch    # Watch mode (auto-rebuild)
ddev theme-build --clean    # Clean + rebuild
```

After SCSS changes: `ddev theme-build && ddev drush cr`

### Key Theme Files

| File | Purpose |
|------|---------|
| `scss/_variables.scss` | Brand colors, Bootstrap variable overrides |
| `scss/_typography.scss` | Font families, heading sizes |
| `scss/style.scss` | Custom theme styles |
| `components/` | SDC components (12 components) |

### Common Commands

```bash
ddev drush cr                    # Clear all caches
ddev drush cex -y                # Export config
ddev drush cim -y                # Import config
ddev drush uli                   # Admin login URL
```

## Content Types

| Type | Canvas Field | Description |
|------|-------------|-------------|
| Page | `field_canvas_body` | General pages with Canvas visual editing |
| Article | `field_component_tree` | Blog/news with per-content Canvas slots |
| Project | `field_project_canvas` | Portfolio projects with media + Canvas |

## Project Structure

```
.alchemize/                    # Docs, capability scripts, ticket worklogs
config/demo/         # Drupal config sync directory
web/
  modules/custom/              # alchemize_components (hero slider SDC)
  themes/custom/               # alchemize_forge (default front-end theme)
    scss/                      # SCSS source (edit these, not CSS)
    css/                       # Compiled CSS (generated, committed)
    js/                        # Bootstrap bundle + custom JS
    components/                # SDC components
    scripts/build.sh           # Theme build pipeline
```

## Documentation

Detailed docs live in `.alchemize/`:

- **Architecture**: `.alchemize/canonical-docs/global/architecture.md`
- **Development Workflow**: `.alchemize/canonical-docs/global/development-workflow.md`
- **Theming Guide**: `.alchemize/drupal/docs/theming/theming.md`
- **Canvas Components**: `.alchemize/drupal/docs/canvas/canvas-sdc-components.md`
- **Developer Tools**: `.alchemize/drupal/docs/infrastructure/developer-tools.md`

## Capability Scripts

Run diagnostics, generators, and tests via Drush:

```bash
# Generate/update the Component Showcase page
ddev drush php:script .alchemize/drupal/capabilities/generators/build-component-showcase-page.drush.php

# Run all Canvas tests
ddev drush php:script .alchemize/drupal/capabilities/tests/canvas-test-run-all.drush.php
```

See `.alchemize/drupal/capabilities/` for the full catalog.
