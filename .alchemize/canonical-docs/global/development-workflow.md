# Development Workflow

## Purpose

This document describes Drush commands, Composer dependency management, and deployment procedures for the project.

## Overview

The project uses Drush for Drupal CLI operations and Composer for dependency management. All development happens in DDEV containers.

## Drush

### Version

- **Drush version**: 13.7
- **Package**: `drush/drush: ^13.7`
- **Location**: Installed via Composer

### Running Drush Commands

All Drush commands run inside DDEV:

```bash
ddev drush <command>
```

### Common Drush Commands

#### Cache Management

```bash
# Clear all caches
ddev drush cache:rebuild

# Clear specific cache
ddev drush cache:clear cache_config
```

#### Configuration Management

```bash
# Export configuration
ddev drush config:export -y

# Import configuration
ddev drush config:import -y

# Check configuration status
ddev drush config:status
```

#### Database Operations

```bash
# Run database updates
ddev drush updatedb -y

# Run post-update hooks
ddev drush post-update -y
```

#### Site Management

```bash
# Install site
ddev drush site:install standard --account-name=admin --account-pass=admin

# Status check
ddev drush status

# User login URL
ddev drush user:login
```

#### Module Management

```bash
# Enable module
ddev drush pm:enable <module_name> -y

# Disable module
ddev drush pm:uninstall <module_name> -y

# List modules
ddev drush pm:list
```

### Capability Scripts

Run capability scripts via Drush:

```bash
ddev drush php:script .alchemize/drupal/capabilities/<script-name>.drush.php
```

See `infrastructure/developer-tools.md` for the full capability scripts catalog and guidance on when to use each tool (`drush generate`, `drush field:create`, Canvas CLI, capability scripts, contrib patching, etc.).

**Key generators:**
```bash
# Preset-based page builders (USE THESE AS TEMPLATES for new work):
ddev drush php:script .alchemize/drupal/capabilities/generators/canvas-build-page.drush.php
ddev drush php:script .alchemize/drupal/capabilities/generators/build-demo-page.drush.php

# Preset Showcase — demonstrates all component presets as living documentation
ddev drush php:script .alchemize/drupal/capabilities/generators/build-preset-demo-page.drush.php

# Component Showcase — PROP REFERENCE ONLY, not a template for new pages
ddev drush php:script .alchemize/drupal/capabilities/generators/build-component-showcase-page.drush.php

# Content generation utilities
ddev drush php:script .alchemize/drupal/capabilities/generators/create-placeholder-media.drush.php
ddev drush php:script .alchemize/drupal/capabilities/generators/create-test-articles.drush.php
```

**For building new pages**, use `canvas-build-page` or `build-demo-page` as templates — they demonstrate the preset-first design system approach. The Component Showcase page exists solely as a prop API reference (every individual prop on every component) — do not copy its manual-prop patterns into new scripts. The Preset Showcase (`/node/6`) demonstrates all Wrapper, Heading, Paragraph, and Card presets — see `component-strategy.md` → "The Preset System". Re-run generators after theme changes to keep references current.

### Code Generation

Scaffold boilerplate code for modules, plugins, services, SDC components, and more:

```bash
ddev drush generate           # Interactive — pick from all generators
ddev drush generate sdc       # Scaffold an SDC component
ddev drush generate module    # Scaffold a custom module
```

> **SDC-first workflow note:** When creating new components, prefer creating purpose-built Tier 1 SDCs in `web/themes/custom/alchemize_forge/components/` following the `hero-carousel` gold-standard pattern. See `component-strategy.md` for conventions and `canvas-sdc-example.md` for the full worked example (design analysis → YAML → Twig → SCSS → JS → Canvas integration).

See `infrastructure/developer-tools.md` for the complete 63-generator catalog, non-interactive usage patterns (`--answer` flags, stdin pipe), and known limitations.

### Code Quality

Check custom code against Drupal coding standards:

```bash
ddev exec vendor/bin/phpcs           # Check all custom code
ddev exec vendor/bin/phpcbf          # Auto-fix violations
```

See `infrastructure/developer-tools.md` for PHPCS configuration and workflow.

### Theme Build (SCSS → CSS)

The `alchemize_forge` theme compiles SCSS to CSS via Webpack. **CSS files must never be edited directly** — always edit the SCSS source and rebuild.

#### Quick Start: `ddev theme-build`

A single DDEV command handles the entire build pipeline (npm install → copy Bootstrap JS → webpack compile). Run it from **anywhere** in the project:

```bash
# Production build (minified) — before commits and deployment
ddev theme-build

# Development build (source maps, not minified) — for debugging
ddev theme-build --dev

# Watch mode (auto-rebuild on SCSS changes) — during active development
ddev theme-build --watch

# Clean compiled CSS before building
ddev theme-build --clean

# CI mode: skip npm install if node_modules already exists
ddev theme-build --ci

# Combine flags
ddev theme-build --clean --dev
```

#### What the build does

1. **`npm install`** — installs/updates Node dependencies (skipped with `--ci` if `node_modules/` exists)
2. **Copy Bootstrap JS** — copies `bootstrap.bundle.min.js` (BS 5.3 + Popper) from `node_modules/` into `js/`
3. **Webpack compile** — compiles SCSS → CSS via sass-loader → postcss-loader → MiniCssExtractPlugin

#### Manual npm commands (alternative)

You can also run individual npm scripts directly inside the theme directory:

```bash
# Production build (minified)
ddev exec "cd web/themes/custom/alchemize_forge && npm run build"

# Development build (with source maps)
ddev exec "cd web/themes/custom/alchemize_forge && npm run build:dev"

# Watch mode
ddev exec "cd web/themes/custom/alchemize_forge && npm run watch"

# Clean compiled CSS
ddev exec "cd web/themes/custom/alchemize_forge && npm run clean"
```

> **Note:** The `prebuild` npm hook automatically copies the Bootstrap JS bundle before every `npm run build` or `npm run build:dev`.

**After any SCSS change**, rebuild and clear Drupal cache:
```bash
ddev theme-build && ddev drush cr
```

**Key SCSS files (design system layers):**
- `scss/_tokens.scss` — Raw brand palette ($brand-*, $neutral-*, $state-*)
- `scss/_variables.scss` — Bootstrap variable overrides mapping tokens to semantic names
- `scss/_typography.scss` — Font families, weights, brand type scale (`$type-scale`), Bootstrap typography overrides
- `scss/_typography-roles.scss` — Typography role classes (`.role-heading-hero`, `.role-text-lead`, `.role-label`)
- `scss/_layout-presets.scss` — Layout preset classes (`.preset-section-hero-dark`, `.preset-card-elevated`)
- `scss/_semantic.scss` — CSS custom properties for light/dark mode
- `scss/_mixins.scss` — Elevation and transition mixins
- `scss/_component-base.scss` — Zero-CSS import chain for SDC component SCSS
- `scss/style.scss` — Imports all layers + global Bootstrap overrides

**Output files (generated, do not edit):**
- `css/bootstrap.css` — Full Bootstrap 5 with custom variable overrides
- `css/style.css` — Design system layers + global overrides

See `component-strategy.md` for the full 8-layer design system architecture, and `css-strategy.md` for SCSS authoring rules.

## Composer

### Dependency Management

All Drupal modules, themes, and libraries are managed via Composer.

### Common Composer Commands

#### Install Dependencies

```bash
# Install all dependencies
ddev composer install

# Update dependencies
ddev composer update

# Update specific package
ddev composer update drupal/canvas
```

#### Add Dependencies

```bash
# Add a module
ddev composer require drupal/module_name

# Add a theme
ddev composer require drupal/theme_name

# Add a library
ddev composer require vendor/library_name
```

#### Remove Dependencies

```bash
# Remove a package
ddev composer remove drupal/module_name
```

### Composer Files

- **`composer.json`**: Dependency definitions and project configuration
- **`composer.lock`**: Locked dependency versions (commit this file)

### Best Practices

1. **Always commit `composer.lock`**: Ensures consistent dependency versions
2. **Run `composer install` after pull**: Ensures dependencies match lock file
3. **Use `composer require`**: Never manually install modules/themes
4. **Review updates**: Check changelogs before updating dependencies
5. **Never edit contrib code directly**: Apply patches via `cweagans/composer-patches` — see `infrastructure/developer-tools.md` "Contrib Module Policy". **Exception:** Direct contrib edits are allowed when explicitly authorized per-ticket for contribution back upstream. See `global/architecture.md` for currently authorized exceptions and `global/contrib-workflow.md` for the full upstream contribution workflow (issue forks, branch management, merge requests).
6. **Commit `patches.lock.json`**: Auto-generated by composer-patches with SHA256 hashes

## Deployment Notes

### Role responsibilities: config export

- **drupal_developer** — **MUST**, at the very end before the final commit on a task, run `ddev drush cex -y` to capture all configuration changes, then `git add` and commit all new or modified files under the config sync directory (e.g. `config/alchemizetechwebsite/`). Partial or missing config exports cause drift and broken deploys; this step is mandatory.
- **drupal_code_reviewer** — **MUST** run `ddev drush cex -y` before finishing the review to ensure no configuration was missed. If the export produces new or changed files, the reviewer must add and commit those files (or flag the author to do so) so the branch has a complete config export.

### Pre-Deployment Checklist

1. **Build theme assets** (if any SCSS files were changed):
   ```bash
   ddev theme-build
   ```
   This runs the full pipeline: npm install → copy Bootstrap JS → compile SCSS → CSS. The compiled `css/bootstrap.css`, `css/style.css`, and `js/bootstrap.bundle.min.js` must be committed.

2. **Export configuration** (required — developer must do this before last commit):
   ```bash
   ddev drush config:export -y
   ```

3. **Add and commit all config files and compiled CSS** (do not leave changes uncommitted):
   ```bash
   git add config/ web/themes/custom/alchemize_forge/css/
   git status   # confirm no other changes left behind
   git commit -m "Export config: [brief description]"
   ```

4. **Commit all remaining changes**:
   ```bash
   git add .
   git commit -m "Deployment: [description]"
   git push
   ```

5. **Build theme assets** (on target environment, if first deploy or after package.json changes):
   ```bash
   ddev theme-build --ci
   ```
   The `--ci` flag skips `npm install` if `node_modules/` already exists.

6. **Run database updates** (on target environment):
   ```bash
   drush updatedb -y
   ```

7. **Import configuration** (on target environment):
   ```bash
   drush config:import -y
   ```

6. **Clear caches** (on target environment):
   ```bash
   drush cache:rebuild
   ```

### Environment-Specific Considerations

- **Local (DDEV)**: Automatic database and settings configuration
- **Staging/Production**: May require manual `settings.php` configuration
- **Config sync directory**: Must be configured in `settings.php` on all environments

## Related Docs

- `global/architecture.md` — System blueprint, invariant rules, capability script policy (Rule 5)
- `global/drush-config-workflow.md` — Config import/export workflow
- `infrastructure/developer-tools.md` — **Comprehensive tool reference:** `drush generate`, `drush field:create`, Canvas CLI, capability scripts, contrib patching, Pathauto, PHPCS
- `infrastructure/environment-setup.md` — DDEV setup, database, accessing the site

## Notes

- All commands run inside DDEV containers — use `ddev` prefix
- Drush commands are site-aware — run from project root
- Composer dependencies are locked — use `composer.lock` for version control
- Configuration must be exported before deployment
- Database updates should be run before config import on target environments
