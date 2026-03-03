# Developer Tools

## Purpose

Documents the code generation, code quality, and diagnostic tools available in this project. Guides developers on **which tool to use when** — standard Drupal generators for boilerplate, capability scripts for Canvas and structural configuration, and code quality tools for all custom code.

---

## Quick Reference: Which Tool When?

### Content Types & Fields

| Task | Tool | Details |
|------|------|---------|
| **Create a content type** | Capability script (PHP API) | `NodeType::create()` — structural config that should be reproducible |
| **Add a field to a content type** | `ddev drush field:create` | Fully non-interactive with flags. Handles all field types including media references |
| **Delete a field** | `ddev drush field:delete` | Non-interactive field removal |
| **List fields on a bundle** | `ddev drush field:info <entity_type> <bundle>` | Shows field names, types, cardinality |
| **Change a field display setting** | `ddev drush config:set` | e.g., `config:set core.entity_view_display.node.TYPE.default content.FIELD.label inline` |
| **Complex display configuration** | Capability script or Admin UI + export | Moving fields between regions, formatter settings, view mode setup |
| **Add body field to content type** | Capability script | Uses `node_add_body_field()` — not available via `drush field:create` |

### Views

| Task | Tool | Details |
|------|------|---------|
| **Create a View** | Admin UI + `ddev drush cex -y` | Views UI is the standard approach |
| **Create a View programmatically** | Capability script (`View::create()`) | Works and produces clean YAML; use for reproducible setups |
| **Modify a View** | Admin UI + `ddev drush cex -y` | Edit in UI, then export config |

### Canvas

| Task | Tool | Details |
|------|------|---------|
| **Build a Canvas page** | `canvas-build-page` capability or Canvas UI | Programmatic for reproducible pages; UI for one-offs |
| **Create a ContentTemplate** | Canvas UI | Prop expressions use special Unicode — UI is the right tool |
| **Scaffold a code component** | `npx canvas scaffold --name NAME` | Canvas CLI creates JSX component scaffold |
| **Validate/build code components** | `npx canvas validate/build --all --yes` | Canvas CLI local development |
| **Upload code components to site** | `npx canvas upload --all --yes` | Canvas CLI deploys to Drupal |
| **Download code components** | `npx canvas download --all --yes` | Canvas CLI pulls from Drupal |
| **Scaffold an SDC component** | `drush generate sdc` | Creates `.component.yml`, `.twig`, `.css` in theme |
| **Force component rediscovery** | `canvas-regenerate-components` capability | After adding/removing SDC components |
| **Check component status** | `canvas-component-status` capability | List enabled/disabled components |
| **Check page region status** | `canvas-page-region-status` capability | Debug global layout regions |

### Modules & Code

| Task | Tool | Details |
|------|------|---------|
| **Scaffold a new custom module** | `ddev drush generate module` | Creates `.info.yml`, `.module`, optional `.install` |
| **Add a plugin (block, field, etc.)** | `ddev drush generate plugin:*` | Standard Drupal plugin boilerplate |
| **Add a service** | `ddev drush generate service:*` | Event subscriber, middleware, etc. |
| **Scaffold a form** | `ddev drush generate form:*` | Config form, simple form, confirm form |
| **Scaffold tests** | `ddev drush generate test:*` | PHPUnit/Nightwatch test boilerplate |
| **Add a Drush command** | `ddev drush generate drush:command` | Generates Drush command file |
| **Generate YML files** | `ddev drush generate yml:*` | Routing, services, permissions, etc. |
| **Enable/disable a module** | `ddev drush en/pm:uninstall` | Standard Drush module management |

### Contrib Modules & Patches

| Task | Tool | Details |
|------|------|---------|
| **Install a contrib module** | `ddev composer require drupal/MODULE` | Always via Composer, never manual download |
| **Update a contrib module** | `ddev composer update drupal/MODULE` | Composer handles version constraints |
| **Apply a patch from drupal.org** | `composer.json` `extra.patches` | Uses `cweagans/composer-patches` v2 (installed) |
| **Create/update a custom patch** | `git diff ORIG_COMMIT HEAD -- ... \| sed ...` | Module-relative paths; **always `rm patches.lock.json`** before reinstall |
| **Remove a patch** | Remove from `composer.json` + delete `patches.lock.json` + `composer reinstall` | Must clean lock file |

> ⛔ **NEVER edit contrib module files directly.** The only acceptable way to modify contrib is via patches applied through Composer. Search drupal.org for existing patches before creating your own.
>
> ⚠️ **When updating a patch file, ALWAYS delete `patches.lock.json` first.** The lock hashes metadata (description + path), not file contents. Without deleting it, the plugin silently skips re-applying the updated patch.

### Taxonomy & URL Aliases

| Task | Tool | Details |
|------|------|---------|
| **Create a vocabulary** | Capability script (`Vocabulary::create()`) | Structural config |
| **Create taxonomy terms** | `ddev drush php-eval` or capability script | `Term::create()` |
| **Create a Pathauto pattern** | Capability script (`PathautoPattern::create()`) | Pattern + selection conditions |
| **Generate URL aliases** | `ddev drush pathauto:aliases-generate all all` | Bulk alias generation |
| **Delete URL aliases** | `ddev drush pathauto:aliases-delete` | Bulk alias cleanup |

### Configuration & Quality

| Task | Tool | Details |
|------|------|---------|
| **Export all config** | `ddev drush cex -y` | After ANY structural changes. **Developer:** MUST run before final commit and add/commit all config files. **Code reviewer:** MUST run before completing review; commit any new/changed config. See `global/development-workflow.md` and `global/drush-config-workflow.md`. |
| **Import config** | `ddev drush cim -y` | Apply config from files |
| **Change a scalar config value** | `ddev drush config:set CONFIG.NAME KEY VALUE` | Direct value changes |
| **View a config value** | `ddev drush config:get CONFIG.NAME` | Inspect current config |
| **Check code quality** | `ddev exec vendor/bin/phpcs` | Drupal coding standards. Add `--report=json` for machine-parseable output |
| **Check code quality (JSON)** | `ddev exec vendor/bin/phpcs --report=json --basepath=/var/www/html` | Structured output for agents: `totals.{errors,warnings,fixable}` + per-file messages with `line`, `column`, `source`, `fixable` |
| **Check only changed files** | `vendor/bin/phpcs --filter=GitStaged` | Delta lint. **Must run from host, not `ddev exec`** (needs `.git`) |
| **Preview auto-fixes** | `ddev exec vendor/bin/phpcs --report=diff` | Shows unified diff of what phpcbf would fix |
| **Auto-fix code style** | `ddev exec vendor/bin/phpcbf` | Automatic formatting fixes. Exit code 1 = fixes applied (success) |
| **Clear all caches** | `ddev drush cr` | After structural or code changes |

---

## Drush Generate (Code Generator)

**Package:** `chi-teck/drupal-code-generator` 4.2.0 (bundled with Drush 13.7)

Scaffolds boilerplate code for Drupal modules, themes, plugins, services, and more. Runs interactively — asks questions, then writes starter files.

### Usage

```bash
# Interactive — pick from all generators
ddev drush generate

# Run a specific generator
ddev drush generate <generator-name>

# Preview output without writing files
ddev drush generate <generator-name> --dry-run

# Pre-fill answers (non-interactive)
ddev drush generate <generator-name> --answer=Value1 --answer=Value2

# Verbose dry-run (shows all questions and answers)
ddev drush generate <generator-name> --dry-run -vvv
```

### Complete Generator Catalog

#### General (`_global`)

| Generator | Alias | What It Creates |
|-----------|-------|-----------------|
| `composer` | `composer.json` | `composer.json` file |
| `controller` | — | Controller class + routing entry |
| `field` | — | Field type plugin (PHP class, not a field instance on a content type) |
| `hook` | — | Hook implementation in `.module` file |
| `install-file` | — | `.install` file with schema/update hooks |
| `javascript` | — | JS file + library definition |
| `layout` | — | Layout plugin (definition + template) |
| `module` | — | Full module scaffold (`.info.yml`, `.module`, optional README, install) |
| `phpstorm-meta` | — | PhpStorm metadata for autocomplete |
| `readme` | — | `README.md` file |
| `render-element` | — | Render element plugin |
| `service-provider` | — | Service provider class |
| `single-directory-component` | `sdc` | SDC component (`.component.yml`, `.twig`, `.css`, `README.md`, `thumbnail.png`) |

#### Theme

| Generator | What It Creates |
|-----------|-----------------|
| `theme` | Full theme scaffold (`.info.yml`, directories) |
| `theme:settings` | `theme-settings.php` file |

#### Entity

| Generator | Alias | What It Creates |
|-----------|-------|-----------------|
| `entity:bundle-class` | `bundle-class` | Bundle class for a content entity |
| `entity:configuration` | `config-entity` | Configuration entity (class, schema, config, list builder) |
| `entity:content` | `content-entity` | Content entity (class, schema, handlers, templates) |

#### Form

| Generator | Alias | What It Creates |
|-----------|-------|-----------------|
| `form:config` | `config-form` | Configuration form |
| `form:confirm` | `confirm-form` | Confirmation form |
| `form:simple` | `form` | Simple form |

#### Plugin (27 generators)

| Generator | Alias | What It Creates |
|-----------|-------|-----------------|
| `plugin:action` | `action` | Action plugin |
| `plugin:block` | `block` | Block plugin |
| `plugin:condition` | `condition` | Condition plugin |
| `plugin:constraint` | `constraint` | Constraint plugin |
| `plugin:entity-reference-selection` | `entity-reference-selection` | Entity reference selection plugin |
| `plugin:field:formatter` | `field-formatter` | Field formatter plugin |
| `plugin:field:type` | `field-type` | Field type plugin |
| `plugin:field:widget` | `field-widget` | Field widget plugin |
| `plugin:filter` | `filter` | Text filter plugin |
| `plugin:manager` | — | Plugin manager |
| `plugin:menu-link` | `menu-link` | Menu link plugin |
| `plugin:migrate:destination` | `migrate-destination` | Migrate destination plugin |
| `plugin:migrate:process` | `migrate-process` | Migrate process plugin |
| `plugin:migrate:source` | `migrate-source` | Migrate source plugin |
| `plugin:queue-worker` | `queue-worker` | Queue worker plugin |
| `plugin:rest-resource` | `rest-resource` | REST resource plugin |
| `plugin:views:argument-default` | `views-argument-default` | Views default argument plugin |
| `plugin:views:field` | `views-field` | Views field plugin |
| `plugin:views:style` | `views-style` | Views style plugin |

#### Service (16 generators)

| Generator | Alias | What It Creates |
|-----------|-------|-----------------|
| `service:access-checker` | `access-checker` | Access checker service |
| `service:breadcrumb-builder` | `breadcrumb-builder` | Breadcrumb builder service |
| `service:cache-context` | `cache-context` | Cache context service |
| `service:custom` | `custom-service` | Custom service |
| `service:event-subscriber` | `event-subscriber` | Event subscriber |
| `service:logger` | `logger` | Logger service |
| `service:middleware` | `middleware` | HTTP middleware |
| `service:param-converter` | `param-converter` | Parameter converter service |
| `service:path-processor` | `path-processor` | Path processor service |
| `service:request-policy` | `request-policy` | Request policy service |
| `service:response-policy` | `response-policy` | Response policy service |
| `service:route-subscriber` | `route-subscriber` | Route subscriber |
| `service:theme-negotiator` | `theme-negotiator` | Theme negotiator service |
| `service:twig-extension` | `twig-extension` | Twig extension service |
| `service:uninstall-validator` | `uninstall-validator` | Uninstall validator service |

#### Test

| Generator | Alias | What It Creates |
|-----------|-------|-----------------|
| `test:browser` | `browser-test` | Browser-based test (BrowserTestBase) |
| `test:kernel` | `kernel-test` | Kernel test (KernelTestBase) |
| `test:nightwatch` | `nightwatch-test` | Nightwatch.js test |
| `test:unit` | `unit-test` | Unit test (UnitTestCase) |
| `test:webdriver` | `webdriver-test` | WebDriver test (JS support) |

#### Drush

| Generator | Alias | What It Creates |
|-----------|-------|-----------------|
| `drush:alias-file` | `daf` | Drush site alias file |
| `drush:command` | `dcf` | Drush command file |
| `drush:generator` | `dg` | Drush generator |
| `drush:symfony-command` | `symfony-command` | Symfony console command |

#### YML

| Generator | Alias | What It Creates |
|-----------|-------|-----------------|
| `yml:breakpoints` | `breakpoints` | Breakpoints `.yml` file |
| `yml:links:action` | `action-links` | `links.action.yml` file |
| `yml:links:contextual` | `contextual-links` | `links.contextual.yml` file |
| `yml:links:menu` | `menu-links` | `links.menu.yml` file |
| `yml:links:task` | `task-links` | `links.task.yml` file |
| `yml:migration` | `migration` | Migration `.yml` file |
| `yml:module-libraries` | `module-libraries` | Module `.libraries.yml` file |
| `yml:permissions` | `permissions` | `.permissions.yml` file |
| `yml:routing` | `routing` | `.routing.yml` file |
| `yml:services` | `services` | `.services.yml` file |
| `yml:theme-libraries` | `theme-libraries` | Theme `.libraries.yml` file |

### Most Relevant Generators for This Project

#### `drush generate sdc` — SDC Component Scaffolding

Scaffolds a Canvas-compatible Single Directory Component in the theme. This is the starting point for creating new visual components.

**What it generates:**
```
web/themes/custom/alchemize_forge/components/<machine_name>/
├── <machine_name>.component.yml   # Props, slots, metadata
├── <machine_name>.twig            # Twig template
├── <machine_name>.css             # Component styles
├── README.md                      # Component docs
└── thumbnail.png                  # Preview image
```

**Questions asked:**
1. Theme name → `alchemize_forge`
2. Component name → e.g., "Hero Banner"
3. Machine name → e.g., `hero_banner`
4. Description → e.g., "A full-width hero section with background image"
5. Library dependencies → (usually empty)
6. Create CSS? → Yes/No
7. Create JS? → Yes/No
8. Add props? → Yes/No (interactive prop definition)
9. Add slots? → Yes/No (interactive slot definition)

**After generating:** Run `canvas-regenerate-components` capability script so Canvas discovers the new component.

**Important:** Bootstrap Forge already provides 12 SDC components. Check `canvas/canvas-sdc-components.md` before creating new ones — Canvas also provides similar components via `canvas_bootstrap` that are deduplicated. See `canvas/canvas-bootstrap-integration.md` for the deduplication rules.

#### `drush generate module` — Custom Module

Scaffolds a new custom module at `web/modules/custom/<name>/`.

**When to use:** When you need custom PHP logic — event subscribers, plugins, services, hooks — that doesn't belong in the theme.

#### `drush generate hook` — Hook Implementation

Adds a hook implementation to an existing module's `.module` file.

#### `drush generate plugin:block` — Custom Block Plugin

Creates a custom block plugin class. Useful when Canvas's built-in components aren't sufficient and you need a custom block.

### Non-Interactive Usage (Tested Patterns)

#### Using `--answer` flags (simple generators)

The `--answer` flag provides answers to the generator's questions **in order**. Always use `--dry-run -vvv` first to discover the question order:

```bash
# Step 1: Discover question order
ddev drush generate module --dry-run -vvv --answer="My Module" --answer="my_module"

# Step 2: Run for real with all answers
ddev drush generate module \
  --answer="My Module" \
  --answer="my_module" \
  --answer="A custom module" \
  --answer="Custom" \
  --answer="" \
  --answer="Yes" \
  --answer="Yes" \
  --answer="No"
```

**Module generator question order:** name → machine_name → description → package → dependencies → create .module? → create .install? → create README?

**⚠️ `--answer` limitations:**
- Strictly positional — answers map to questions in order
- Wrong values get accepted literally (e.g., a version string becomes a dependency name)
- Multi-value prompts (like SDC props/slots) don't terminate properly with `--answer`

#### Using stdin pipe (complex generators like SDC)

For generators with multi-value interactive prompts, pipe answers via stdin inside `ddev exec`:

```bash
ddev exec bash -c 'printf "alchemize_forge\nProject Card\nproject_card\nA card component\n\nYes\nNo\nYes\nHeading\nheading\nMain heading text\n1\nNo\nNo\n" | drush generate single-directory-component --dry-run'
```

**SDC generator question order:** theme_machine_name → component_name → machine_name → description → library_deps (repeat until empty) → CSS? → JS? → props? → [prop_title → prop_machine_name → prop_description → prop_type (1-6 numeric) → add_another?]... → slots? → [slot_title → slot_machine_name → slot_description → add_another?]...

**Prop type numeric values:** 1=String, 2=Number, 3=Boolean, 4=Array, 5=Object, 6=Always null

### What `drush generate` Does NOT Do

`drush generate` creates **code scaffolding** — PHP classes, YML files, Twig templates. It does **not**:

- Create field instances on content types — use `ddev drush field:create` (see Quick Reference above)
- Configure Views — use the Views UI + config export (see `data-model/views.md`)
- Create Canvas pages or content templates — use Canvas UI or capability scripts
- Manage configuration — use `ddev drush config:import/export`
- Install or enable modules — use `ddev drush en`

**Common confusion:** The `field` generator creates a **field type plugin** (a new PHP class defining a new kind of field), NOT a field instance on a content type. To add an existing field type (text, entity reference, link, etc.) to a content type, use `ddev drush field:create`.

**SDC generator limitation:** Only scaffolds components for **themes**, not modules. If you need an SDC in a custom module, scaffold with the theme name then manually move the files, or create the component files by hand.

**Building a complete feature?** See `features/content-type-listing-pattern.md` for the end-to-end workflow: content type + fields + View + Canvas page + detail page.

---

## Capability Scripts

**Location:** `.alchemize/drupal/capabilities/`

Custom Drush scripts for project-specific operations that go beyond standard Drupal generators. Each script has a `.drush.php` file and a `.drush.json` metadata companion.

### Running Capability Scripts

```bash
ddev drush php:script .alchemize/drupal/capabilities/<script-name>.drush.php
```

### Script Catalog

#### Canvas Page Building & Generators

| Script | Purpose | Design System | When to Use |
|--------|---------|---------------|-------------|
| `generators/canvas-build-page` | Builds ExampleHomepage (hero + product grid) | **Preset-based** — uses `hero-dark`, `hero`, `lead`, `caption`, `card-title`, `dark` presets. Primary teaching example for preset-first composition. | Reference for Canvas data model + design system presets |
| `generators/build-demo-page` | Builds Alchemize Forge Demo (6 polished sections) | **Preset-based** — demonstrates all wrapper presets (`hero-dark`, `light-section`, `content-section`, `cta-banner`), heading presets (`hero`, `section-title`, `card-title`), card presets (`elevated`, `dark`), and individual prop overrides alongside presets. | Most comprehensive preset example — study this for preset variety |
| `generators/canvas-build-projects-page` | Builds Projects page (hero + Views block) | **Preset-based** — `hero-dark`, `hero`, `lead`, `content-section` presets. Also demonstrates block component integration. | Reference for Views block + preset composition |
| `generators/build-preset-demo-page` | Builds Preset Showcase page (`/node/6`) | **Preset-focused** — programmatically generates all preset variants as living documentation. | Re-run after theme/preset changes to keep showcase current |
| `generators/build-component-showcase-page` | Builds Component Showcase & Developer Guide | **Individual props only** — shows every available prop on every component. Prop API reference, NOT a design system example. | Prop API reference only — **do not use as a template for new pages** |

> **Important for new page work:** Use the preset-based scripts (`canvas-build-page`, `build-demo-page`, `canvas-build-projects-page`) as templates. These demonstrate the correct design system approach. The Component Showcase script exists solely as a prop API reference — it shows what individual props are available, but its patterns (manual `text_color`, `display_size`, `font_weight`, `custom_class`) should **not** be copied into new page-building scripts. See `component-strategy.md` for the 8-layer design system architecture.

#### Canvas Diagnostics

| Script | Purpose | When to Use |
|--------|---------|-------------|
| `canvas-component-status` | Lists all Canvas components, enabled/disabled status, deduplication info | After theme changes, when components appear missing |
| `canvas-page-region-status` | Shows PageRegion config — which regions have component trees | When debugging global layout (header, footer, nav) |
| `canvas-code-component-sync` | Lists code components, checks internal/exposed status, config sync | After CLI code component changes |
| `canvas-regenerate-components` | Forces Canvas to rediscover all components | After adding/removing SDC components in theme |

#### Canvas Setup

| Script | Purpose | When to Use |
|--------|---------|-------------|
| `canvas-oauth-setup` | Verifies OAuth setup for Canvas CLI | When setting up `@drupal-canvas/cli` locally |

### When to Use Which Tool

**Use `drush generate` when:**
- You need standard Drupal boilerplate (module, plugin, service, form, test, SDC, YML files)
- The generated code follows standard Drupal patterns
- The output is a starting point you'll customize

**Use `drush field:create` when:**
- Adding a field to a content type — supports all field types including media references
- Example: `ddev drush field:create node project --field-name=field_image --field-label="Image" --field-type=entity_reference --target-type=media --target-bundle=image --field-widget=media_library_widget`

**Use capability scripts when:**
- Creating content types (`NodeType::create()` + body field + taxonomy vocabulary — all in one script)
- Building Canvas pages programmatically (component tree construction)
- Creating Pathauto patterns (`PathautoPattern::create()` with selection conditions)
- Complex operations involving multiple interrelated config entities
- Project-specific diagnostics (Canvas status, component audit)

**Use `drush config:set` when:**
- Changing a single config value (`config:set system.site name "My Site"`)
- Tweaking field display settings (`config:set core.entity_view_display.node.TYPE.MODE content.FIELD.label inline`)
- Toggling a boolean (`config:set canvas.component.xxx status true`)

**Use Canvas CLI (`npx canvas`) when:**
- Creating, building, validating, or uploading code components (JS/JSX)
- Full workflow: `scaffold` → edit → `validate` → `build` → `upload`
- See `canvas/canvas-cli.md` for complete reference

**Use Admin UI + config export when:**
- Creating or modifying Views (Views UI is most practical)
- Complex form/view display configuration (drag-and-drop formatter settings)
- ContentTemplates with dynamic field prop linking (Canvas UI handles Unicode expressions)
- Any config change you need to verify visually before committing

**Always follow with:** `ddev drush cex -y` after any structural changes to capture config in YAML.

See `global/architecture.md` Rule 5 for the full policy on capability scripts vs. direct config changes.

---

## Drush Field Commands (Tested)

Drush provides non-interactive commands for managing field instances directly from the command line.

### `field:create` — Add a field to a content type

```bash
# Simple text field
ddev drush field:create node article \
  --field-name=field_subtitle \
  --field-label="Subtitle" \
  --field-type=string \
  --field-widget=string_textfield \
  --is-required=0

# Media reference field (image)
ddev drush field:create node project \
  --field-name=field_hero_image \
  --field-label="Hero Image" \
  --field-type=entity_reference \
  --target-type=media \
  --target-bundle=image \
  --field-widget=media_library_widget

# Entity reference to taxonomy
ddev drush field:create node project \
  --field-name=field_category \
  --field-label="Category" \
  --field-type=entity_reference \
  --target-type=taxonomy_term \
  --target-bundle=categories \
  --field-widget=options_select

# List (select) field
ddev drush field:create node project \
  --field-name=field_status \
  --field-label="Status" \
  --field-type=list_string \
  --field-widget=options_select
```

**What `field:create` does automatically:**
- Creates `FieldStorageConfig` (if not existing)
- Creates `FieldConfig` (field instance on the bundle)
- Adds the field to the default form display with the specified widget
- Adds the field to the default view display with the default formatter

**What it does NOT do:**
- Configure allowed values for list fields — edit YAML config or use Admin UI
- Set up view mode-specific display settings — use `drush config:set` or Admin UI
- Add the body field — use `node_add_body_field()` in a capability script
- Set complex formatter settings — use Admin UI + config export

### `field:delete` — Remove a field

```bash
ddev drush field:delete node project --field-name=field_subtitle
```

### `field:info` — List fields on a bundle

```bash
ddev drush field:info node project
```

---

## Contrib Module Policy

> ⛔ **NEVER edit files in `web/modules/contrib/` or `web/themes/contrib/` directly.** Changes will be lost on the next `composer update`.

### Installing Contrib Modules

```bash
# Install
ddev composer require drupal/module_name

# Enable
ddev drush en module_name -y

# Export config
ddev drush cex -y
```

### Patching Contrib Modules

This project uses `cweagans/composer-patches` v2 for applying patches. Patches are the **only** acceptable way to modify contrib code.

#### How composer-patches v2 works

Understanding the internals prevents subtle bugs:

1. **Patch application**: The plugin runs `git -C <install_path> apply -p<depth> <patch_file>` where `<install_path>` is the package directory (e.g., `web/modules/contrib/canvas`). Default depth is 1.
2. **Path format**: With `-p1`, the `a/` prefix is stripped. So patch paths must be **relative to the module root** — use `a/src/Foo.php`, NOT `a/web/modules/contrib/module/src/Foo.php`.
3. **Lock file (`patches.lock.json`)**: Hashes patch **metadata** (description + path from composer.json) — NOT the patch file contents. This is critical; see gotchas below.
4. **Git init**: If the package directory is not a git repo, the `GitInitPatcher` creates a temporary `.git`, applies the patch, then removes `.git`.

#### Applying a patch from drupal.org

1. Find the issue on drupal.org (e.g., `drupal.org/project/MODULE/issues/ISSUE_ID`)
2. Download or copy the URL of the most recent patch from the issue comments
3. Add to `composer.json`:

```json
{
  "extra": {
    "patches": {
      "drupal/module_name": {
        "#ISSUE_ID: Brief description of fix": "https://www.drupal.org/files/issues/YYYY-MM-DD/patch-filename.patch"
      }
    }
  }
}
```

4. Apply: `rm patches.lock.json && ddev composer reinstall drupal/module_name`
5. Verify the fix
6. Commit `composer.json` and `patches.lock.json`

#### Creating a custom patch

The recommended workflow uses git to track the original module state and generate clean diffs:

```bash
# 1. Find the commit where the module was first installed (clean composer state)
git log --oneline -- web/modules/contrib/MODULE | tail -1
# Example output: f89ae0ff Add and enable Drupal MODULE module (v1.2.3)

# 2. Make your changes to the contrib files directly
#    Edit files in web/modules/contrib/MODULE/...

# 3. Commit your changes (so they're safe and diffable)
git add web/modules/contrib/MODULE/
git commit -m "Patch MODULE: description of changes"

# 4. Generate the patch with module-relative paths
#    IMPORTANT: Strip the web/modules/contrib/MODULE/ prefix so paths
#    are relative to the module root (required for -p1 depth)
git diff ORIGINAL_COMMIT HEAD -- web/modules/contrib/MODULE/ \
  | sed 's|a/web/modules/contrib/MODULE/|a/|g; s|b/web/modules/contrib/MODULE/|b/|g' \
  > patches/module-description.patch

# 5. Add to composer.json extra.patches section (if not already there)

# 6. Verify the patch applies cleanly against a fresh module install
rm patches.lock.json
ddev composer reinstall drupal/MODULE

# 7. Confirm zero diffs after reinstall
git diff HEAD -- web/modules/contrib/MODULE/
# Should produce no output

# 8. Commit patches.lock.json
git add patches.lock.json patches/module-description.patch
git commit -m "Update MODULE patch"
```

#### Updating an existing patch

> ⚠️ **CRITICAL GOTCHA**: `patches.lock.json` hashes the patch metadata (description string + file path), NOT the file contents. If you update a `.patch` file on disk without changing its path or description in `composer.json`, the lock hash stays the same and the plugin **silently skips re-applying the patch**. The module gets a clean download with NO patches applied.

```bash
# 1. Make your additional changes to the contrib files
# 2. Commit them

# 3. Regenerate the patch from the original install commit
git diff ORIGINAL_COMMIT HEAD -- web/modules/contrib/MODULE/ \
  | sed 's|a/web/modules/contrib/MODULE/|a/|g; s|b/web/modules/contrib/MODULE/|b/|g' \
  > patches/module-description.patch

# 4. ALWAYS delete the lock file when the patch contents change
rm patches.lock.json

# 5. Reinstall to verify
ddev composer reinstall drupal/MODULE

# 6. Verify zero diffs
git diff HEAD -- web/modules/contrib/MODULE/

# 7. Commit both files
git add patches.lock.json patches/module-description.patch
git commit -m "Update MODULE patch"
```

#### Removing a patch

```bash
# 1. Remove the patch entry from composer.json extra.patches
# 2. Delete patches.lock.json
rm patches.lock.json
# 3. Reinstall the module
ddev composer reinstall drupal/module_name
```

#### Contributing patches to drupal.org

When the task is to contribute a fix or feature to a drupal.org issue:

1. **Work locally** — Create the patch using the same workflow as "Creating a custom patch" above. Develop and test within your project.
2. **Add to composer patches** — Add your patch to `composer.json` `extra.patches` so the project can use it locally until the upstream issue is resolved.
3. **Attach to the issue** — Upload the `.patch` file to the drupal.org issue. Add a comment describing the change.
4. **Follow the issue** — Maintainers may request changes. Revise the patch and upload a new version; re-test locally after each revision.

The patch file is created with `git diff` from the project repo. Ensure the diff uses paths relative to the module root (strip `web/modules/contrib/MODULE/` prefix). Run the module's existing tests (`phpunit`) before submitting. See `infrastructure/modules.md` for guidance on exploring contrib module structure before making changes.

#### Generating a patch from a reversed diff

When you have clean upstream in the working tree and your modifications committed in HEAD (e.g., after removing the module, reinstalling fresh with no patch, while HEAD still tracks your patched files):

```bash
# Working tree = clean upstream, HEAD = your modifications
# git diff shows HEAD→working tree (removing your changes)
# git diff -R reverses it: working tree→HEAD (adding your changes = the patch you want)
git diff -R -- web/modules/contrib/MODULE/ \
  ':!web/modules/contrib/MODULE/ui/package-lock.json' \
  | sed 's|a/web/modules/contrib/MODULE/|a/|g; s|b/web/modules/contrib/MODULE/|b/|g' \
  > patches/module-description.patch
```

> **Note:** `git diff -R` swaps the `a/` and `b/` prefixes in diff headers (`--- b/file` / `+++ a/file` instead of the usual `--- a/file` / `+++ b/file`). This is cosmetic — `git apply -p1` strips the first path component regardless of the letter, so the patch applies fine.

> **Always exclude `package-lock.json`** (and similar lockfiles) from patches. These contain platform-specific dependency resolutions that differ between macOS and Linux (DDEV). Context lines won't match on a fresh install, causing the patch to fail. Since `npm install` regenerates them, they don't need to be in the patch. Use the `':!path/to/lockfile'` pathspec to exclude.

#### Gotchas and best practices

| Issue | Cause | Fix |
|-------|-------|-----|
| **Patch silently not applied after update** | `patches.lock.json` hash is based on metadata, not file contents | **Always `rm patches.lock.json`** before reinstall when patch contents change |
| **Patch fails with "already exists in working directory"** | Patch has `new file mode` for files that already exist (e.g., testing against a tree where the patch was already applied) | Test against a clean module install, not the current working tree |
| **Paths doubled (file not found)** | Patch uses full repo paths (`web/modules/contrib/MODULE/src/...`) instead of module-relative paths | Strip prefix with `sed` when generating: `s\|a/web/modules/contrib/MODULE/\|a/\|g` |
| **Patch applies but changes missing** | Composer used a **cached** patched package | Delete `patches.lock.json`; optionally `ddev composer clear-cache` |
| **Multiple patches for same module** | Each patch must apply independently on top of the previous | Test by deleting the module folder and running `ddev composer install` from scratch |
| **`composer install` won't reinstall a deleted module** | Composer sees the lock file is unchanged and reports "Nothing to install" even if the module directory is missing | Use `ddev composer reinstall drupal/MODULE` to force re-download and extraction |
| **Old patch applied after `git checkout .`** | `git checkout .` restores `patches.lock.json` to its committed state (with the old patch hash). The plugin reads the lock file and applies the old cached patch, even if `composer.json` was modified | **Always `rm patches.lock.json`** before any patch-related reinstall — treat it as step zero |
| **Patch fails on `package-lock.json`** | Platform-specific npm resolutions (`@rollup/rollup-darwin-arm64`, etc.) create context mismatches between committed lockfile and fresh install | Exclude lockfiles from patches with `':!path/to/package-lock.json'` pathspec |
| **Patched module's frontend build is stale** | The `post-install-cmd` script (`npm run build`) may build from unpatched source depending on composer event ordering during reinstall | After reinstall, verify bundle size matches expectations. If not, rebuild manually from the **host** (not DDEV): `cd web/modules/contrib/MODULE/ui && npm install --ignore-scripts && npm run build` |
| **Plugin works in drush but not in browser** | PHP OPcache in the web server (PHP-FPM) still serves bytecode from the previous unpatched module install | `ddev drush cr` only clears Drupal's cache, NOT OPcache. Run **`ddev restart`** to restart PHP-FPM and clear OPcache. Drush uses CLI SAPI (fresh process each run) so it always sees current files |

**Key rules:**
- `patches.lock.json` is auto-generated — **always commit it** alongside patch changes
- **Always delete `patches.lock.json`** when updating patch file contents (the #1 gotcha)
- Patches use depth-1 strip level by default — paths must be module-relative
- Patches made against different module versions will fail — always test after module updates
- Local patches go in `patches/` directory; drupal.org patches use the URL directly
- After any patch operation, verify with `git diff HEAD -- web/modules/contrib/MODULE/` — zero output means success

#### Post-patch verification checklist

After applying or updating a patch, run through this checklist:

```bash
# 1. Verify zero source diffs (exclude lockfiles)
git diff -- web/modules/contrib/MODULE/ ':!web/modules/contrib/MODULE/**/package-lock.json'
# Should produce no output

# 2. If the module has a frontend build, verify bundle size
ls -la web/modules/contrib/MODULE/ui/dist/assets/*.js
# Compare against the known-good size from a working environment

# 3. If bundle size is wrong, rebuild from host (NOT ddev exec)
cd web/modules/contrib/MODULE/ui && npm install --ignore-scripts && npm run build

# 4. Restart DDEV to clear OPcache
ddev restart

# 5. Clear Drupal cache
ddev drush cr

# 6. Verify the module's plugins are discovered
ddev drush ev "\Drupal::service('plugin.manager.field.widget')->getDefinition('WIDGET_ID');"
```

---

## Code Quality: PHPCS and PHPCBF

**Package:** `squizlabs/php_codesniffer` 3.13.5 + `drupal/coder` 8.3.31

Enforces Drupal coding standards on all custom code. Catches style issues, security patterns, and best practices. Critically, PHPCS produces **machine-parseable JSON output** that makes it ideal as a deterministic feedback loop for agents — instead of "hoping the LLM knows Drupal standards," run phpcs and let the structured output drive targeted fixes.

### Configuration

**Config file:** `phpcs.xml.dist` (project root)

```xml
<ruleset name="Custom Drupal coding standards">
  <rule ref="Drupal"/>
  <rule ref="DrupalPractice"/>
  <arg name="extensions" value="php,module,inc,install,test,profile,theme,css,info,txt,md,yml"/>
  <arg value="sp"/>
  <file>web/modules/custom/</file>
  <file>web/themes/custom/</file>
  <exclude-pattern>node_modules</exclude-pattern>
  <exclude-pattern>vendor</exclude-pattern>
  <exclude-pattern>\.alchemize</exclude-pattern>
</ruleset>
```

**What's checked:**
- **Drupal** standard — Drupal-specific coding conventions (based on PSR-12 + Drupal extras)
- **DrupalPractice** standard — Best practice recommendations (deprecated API usage, etc.)
- Only files in `web/modules/custom/` and `web/themes/custom/`
- File types: `.php`, `.module`, `.inc`, `.install`, `.test`, `.profile`, `.theme`, `.css`, `.info`, `.txt`, `.md`, `.yml`

**Installed standards:** Drupal, DrupalPractice, VariableAnalysis, SlevomatCodingStandard, PSR1, PSR2, PSR12, Squiz, PEAR, Zend, MySource

### Usage

```bash
# Check all custom code (uses phpcs.xml.dist config)
ddev exec vendor/bin/phpcs

# Check a specific file
ddev exec vendor/bin/phpcs web/modules/custom/my_module/src/MyClass.php

# Check a specific directory
ddev exec vendor/bin/phpcs web/themes/custom/my_theme/

# Show sniff codes (useful for understanding violations)
ddev exec vendor/bin/phpcs -s web/modules/custom/

# Auto-fix what can be auto-fixed
ddev exec vendor/bin/phpcbf

# Auto-fix a specific file
ddev exec vendor/bin/phpcbf web/modules/custom/my_module/src/MyClass.php
```

### JSON Reporting (For Agent/Automation Use)

PHPCS can output structured JSON that agents can parse programmatically. This is the **recommended approach for automated workflows** — much more reliable than parsing human-readable output.

```bash
# JSON to stdout
ddev exec vendor/bin/phpcs --report=json --basepath=/var/www/html web/modules/custom/

# JSON to file (suppresses screen output)
ddev exec vendor/bin/phpcs --report=json --report-file=/tmp/phpcs-report.json --basepath=/var/www/html web/modules/custom/

# JSON to file + summary to screen (multiple reports simultaneously)
ddev exec vendor/bin/phpcs --report=json --report-file=/tmp/phpcs-report.json --report=summary --basepath=/var/www/html web/modules/custom/
```

**JSON output structure (tested):**

```json
{
  "totals": { "errors": 6, "warnings": 0, "fixable": 4 },
  "files": {
    "web/modules/custom/my_module/src/MyClass.php": {
      "errors": 6, "warnings": 0,
      "messages": [
        {
          "message": "Expected 1 space after IF keyword; 0 found",
          "source": "Drupal.ControlStructures.ControlSignature.SpaceAfterKeyword",
          "severity": 5,
          "fixable": true,
          "type": "ERROR",
          "line": 11,
          "column": 3
        }
      ]
    }
  }
}
```

**Key JSON fields for agents:**
- `totals.errors` / `totals.warnings` — quick pass/fail check (`errors == 0` = clean)
- `totals.fixable` — how many can phpcbf auto-fix
- `messages[].source` — sniff code, useful for grouping related violations
- `messages[].fixable` — whether this specific issue can be auto-fixed
- `messages[].line` / `messages[].column` — exact location for targeted edits
- `--basepath` strips the absolute path prefix, making paths workspace-relative

### Diff Report (Preview Fixes)

The `--report=diff` shows exactly what phpcbf would change, as a unified diff — useful for previewing auto-fixes before applying:

```bash
ddev exec vendor/bin/phpcs --report=diff --basepath=/var/www/html web/modules/custom/my_module/
```

Output example:
```diff
--- web/modules/custom/my_module/my_module.module
+++ PHP_CodeSniffer
@@ -8,7 +8,9 @@
-// Bad inline comment
+/**
+ * Bad inline comment.
+ */
 function my_module_example() {
-  if($x == TRUE){
+  if ($x == TRUE) {
```

### Delta Linting (Changed Files Only)

PHPCS has built-in Git filters to scope checks to only changed files. This significantly speeds up iterative lint-fix cycles.

```bash
# Lint only staged files (after git add)
vendor/bin/phpcs --filter=GitStaged --report=json --basepath=$(pwd) web/modules/custom/

# Lint only modified (unstaged) tracked files
vendor/bin/phpcs --filter=GitModified --report=json --basepath=$(pwd) web/modules/custom/
```

> **IMPORTANT: Git filters require host execution, NOT `ddev exec`.** The `.git` directory is not mounted inside the DDEV container, so `--filter=GitModified` and `--filter=GitStaged` silently return empty results when run via `ddev exec`. Run from the host:
>
> ```bash
> # CORRECT (host, has .git access):
> vendor/bin/phpcs --filter=GitStaged --report=json web/modules/custom/
>
> # WRONG (DDEV container, no .git — returns empty):
> ddev exec vendor/bin/phpcs --filter=GitStaged web/modules/custom/
> ```

**Note:** `GitModified` only catches tracked files that changed. Brand new (untracked) files require `git add` first, then use `GitStaged`.

### Performance Flags

```bash
# Parallel checking (useful for larger codebases)
ddev exec vendor/bin/phpcs --parallel=4 web/modules/custom/

# Cache results between runs (skips unchanged files)
ddev exec vendor/bin/phpcs --cache web/modules/custom/

# Combine both
ddev exec vendor/bin/phpcs --parallel=4 --cache web/modules/custom/
```

### Exit Code Behavior

Understanding exit codes is critical for scripts and agents:

| Tool | Exit Code | Meaning |
|------|-----------|---------|
| **phpcs** | 0 | No errors or warnings |
| **phpcs** | 1 | Errors and/or warnings found |
| **phpcs** | 2 | Processing error (invalid standard, etc.) |
| **phpcbf** | 0 | No fixable violations found |
| **phpcbf** | 1 | Fixes were applied (NOT a failure!) |
| **phpcbf** | 2 | Processing error |

> **Agent tip:** For phpcs, prefer parsing JSON `totals` over relying on exit codes — JSON gives you `errors`, `warnings`, and `fixable` counts. For phpcbf, exit code 1 is success (fixes applied); only exit code 2 is a real failure.

### Recommended Agent Workflow (Deterministic Feedback Loop)

The most reliable approach for agents writing Drupal code:

```bash
# 1. Scaffold with drush generate (deterministic boilerplate)
ddev drush generate module --answer="My Module" ...

# 2. Agent edits the generated scaffold

# 3. Run phpcs with JSON output (deterministic feedback)
ddev exec vendor/bin/phpcs --report=json --basepath=/var/www/html web/modules/custom/my_module/

# 4. Parse JSON → fix issues prioritizing:
#    a) fixable: true issues first (phpcbf can handle these)
#    b) ERROR type before WARNING
#    c) Group by source (sniff code) for batch fixes

# 5. Auto-fix what's fixable
ddev exec vendor/bin/phpcbf web/modules/custom/my_module/

# 6. Re-run phpcs → parse JSON → manually fix remaining
ddev exec vendor/bin/phpcs --report=json --basepath=/var/www/html web/modules/custom/my_module/

# 7. Repeat until totals.errors == 0
```

**Why this works better than "just write correct code":**
- `drush generate` produces Drupal-standards-compliant boilerplate — start clean
- PHPCS catches things LLMs commonly miss (docblock format, spacing conventions, naming rules)
- JSON output gives agents precise `file:line:column` locations — no guessing
- `fixable: true` flag tells the agent "let phpcbf handle this" vs "I need to fix this manually"
- The loop converges: each cycle reduces `totals.errors`, giving a clear progress signal

### Common Violations

| Sniff | What It Catches |
|-------|-----------------|
| `Drupal.Commenting.DocComment` | Missing or malformed docblocks |
| `Drupal.Commenting.FunctionComment.WrongStyle` | `//` comment instead of `/** */` for functions |
| `Drupal.ControlStructures.ControlSignature` | Missing spaces around `if`/`else`/`while` keywords |
| `Drupal.NamingConventions.ValidFunctionName` | Function naming violations |
| `Drupal.Files.EndFileNewline` | Missing newline at end of file |
| `Generic.Strings.UnnecessaryStringConcat` | Unnecessary string concatenation (not auto-fixable) |
| `DrupalPractice.CodeAnalysis.VariableAnalysis` | Unused variables |
| `Drupal.Commenting.InlineComment.InvalidEndChar` | Inline comments not ending in `.!?:)` |

### Note on Scope

PHPCS only checks `web/modules/custom/` and `web/themes/custom/`. It does **not** check:
- Contributed modules (`web/modules/contrib/`) — maintained by their authors
- Contributed themes (`web/themes/contrib/`) — including Bootstrap Forge
- Capability scripts (`.alchemize/`) — excluded by pattern
- Drupal core (`web/core/`) — maintained by Drupal core team

---

## Workflow: Creating a New SDC Component (Theme)

End-to-end workflow for creating SDC components in the theme using `drush generate`:

```bash
# 1. Scaffold the component (interactive — answers theme, name, props, slots)
ddev drush generate sdc

# Or non-interactively via stdin pipe:
ddev exec bash -c 'printf "alchemize_forge\nHero Banner\nhero_banner\nA full-width hero section\n\nYes\nNo\nYes\nHeading\nheading\nMain heading\n1\nNo\nYes\ncontent\ncontent\nMain content area\nNo\n" | drush generate single-directory-component'

# 2. Edit the generated files in your IDE
#    - Customize .component.yml (props, slots)
#    - Write the Twig template
#    - Add CSS styles

# 3. Tell Canvas to discover the new component
ddev drush php:script .alchemize/drupal/capabilities/canvas-regenerate-components.drush.php

# 4. Clear cache
ddev drush cr

# 5. Verify Canvas sees it
ddev drush php:script .alchemize/drupal/capabilities/canvas-component-status.drush.php

# 6. Export any config changes
ddev drush cex -y
```

## Workflow: Creating a Canvas Code Component (JS/JSX)

For JS/JSX components managed by the Canvas CLI:

```bash
# 1. Scaffold
npx canvas scaffold --name my_component --dir ./canvas-components

# 2. Edit the generated files
#    - component.yml — props, slots, metadata
#    - index.jsx — React-style component
#    - index.css — Component styles

# 3. Validate
npx canvas validate --components my_component --yes

# 4. Build (local test)
npx canvas build --components my_component --yes --no-tailwind

# 5. Upload to Drupal site
npx canvas upload --components my_component --yes

# 6. Export config (captures canvas.js_component.* entity)
ddev drush cex -y
```

See `canvas/canvas-cli.md` for full CLI reference, OAuth setup, and all command flags.

## Workflow: Creating a New Custom Module

```bash
# 1. Scaffold the module (non-interactive with --answer flags)
ddev drush generate module \
  --answer="My Module" \
  --answer="my_module" \
  --answer="Description of the module" \
  --answer="Custom" \
  --answer="" \
  --answer="Yes" \
  --answer="Yes" \
  --answer="No"

# 2. Add plugins/services/hooks as needed
ddev drush generate plugin:block   # example
ddev drush generate service:event-subscriber  # example

# 3. Edit generated code — add your logic

# 4. Lint-fix loop (deterministic feedback)
ddev exec vendor/bin/phpcs --report=json --basepath=/var/www/html web/modules/custom/my_module/
#    → Parse JSON: if totals.errors > 0, continue fixing
ddev exec vendor/bin/phpcbf web/modules/custom/my_module/
#    → phpcbf exit code 1 = fixes applied (success, not failure)
ddev exec vendor/bin/phpcs --report=json --basepath=/var/www/html web/modules/custom/my_module/
#    → Repeat until totals.errors == 0

# 5. Enable the module
ddev drush en my_module -y

# 6. Export config
ddev drush cex -y

# 7. Commit
git add web/modules/custom/my_module/ config/<site>/
git commit -m "Add my_module with [description]"
```

## Workflow: Adding Fields to a Content Type

```bash
# 1. Create the field
ddev drush field:create node my_type \
  --field-name=field_example \
  --field-label="Example" \
  --field-type=string \
  --field-widget=string_textfield

# 2. (Optional) Adjust display settings
ddev drush config:set core.entity_view_display.node.my_type.default content.field_example.label inline -y

# 3. Export config
ddev drush cex -y
```

---

## Change Surface

| What | Where |
|------|-------|
| Drush generate config | Bundled with `chi-teck/drupal-code-generator` (Composer) |
| PHPCS config | `phpcs.xml.dist` (project root) |
| PHPCS standards | `vendor/drupal/coder/` + `vendor/squizlabs/php_codesniffer/` |
| Capability scripts | `.alchemize/drupal/capabilities/*.drush.php` |
| Capability metadata | `.alchemize/drupal/capabilities/*.drush.json` |

## Related Docs

- `global/architecture.md` — Rule 5: When to use capability scripts vs direct config
- `global/drush-config-workflow.md` — Config import/export workflow
- `global/development-workflow.md` — Composer, Drush commands, deployment
- `canvas/canvas-cli.md` — **Canvas CLI complete reference** (scaffold, build, validate, upload, download)
- `canvas/canvas-sdc-components.md` — Existing SDC component catalog
- `canvas/canvas-bootstrap-integration.md` — SDC deduplication rules
- `canvas/canvas-build-guide.md` — Canvas page building patterns and data model
- `data-model/content-types.md` — Content type reference and PHP API patterns
- `data-model/views.md` — Views catalog and creation workflow
- `features/content-type-listing-pattern.md` — End-to-end feature composition guide
- `theming/theming.md` — SCSS build workflow, custom CSS classes
