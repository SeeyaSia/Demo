# Drush and Configuration Management Workflow

## Purpose
This document describes the standard development workflow for managing Drupal configuration using Drush commands via DDEV. This workflow ensures configuration changes are properly synchronized between the database and version-controlled configuration files.

## Overview
This is a **single-site** Drupal 11 installation. Configuration files are stored in `config/alchemizetechwebsite/`. All Drush commands must be executed through DDEV.

## Configuration Storage

### Config Sync Directory

- **Config directory**: `config/alchemizetechwebsite/` (configured in `web/sites/default/settings.php` as `../config/alchemizetechwebsite`)

This directory contains YAML files representing the site's configuration. These files are version-controlled and should be committed to the repository.

## Standard Development Workflow

### Before Starting Work

**Always import configuration before beginning new work** to ensure your local database matches the latest committed configuration:

```bash
ddev drush cim -y
```

**What to expect:**
- If there are no differences: `[notice] There are no changes to import.`
- If there are differences: The command will list what will be imported and apply the changes.

**Important:** If there are differences, review them carefully. They may indicate:
- Uncommitted configuration changes from previous work
- Configuration drift between environments
- Missing configuration files

### During Development

Make your configuration changes through the Drupal UI or via Drush commands. Common operations include:

#### Installing a Module
```bash
# 1. Install via Composer (downloads the code)
composer require drupal/module_name

# 2. Enable the module
ddev drush en module_name -y

# 3. Configure as needed (via UI or Drush)

# 4. Export config to capture the change
ddev drush cex -y

# 5. Review exported config files for accuracy
git diff config/alchemizetechwebsite/

# 6. Commit all changes
git add composer.json composer.lock config/alchemizetechwebsite/ web/modules/contrib/module_name/
git commit -m "Install and enable module_name"
```

**Note:** For themes, use `ddev drush then theme_name -y` instead of `ddev drush en`.

#### Uninstalling a Module
```bash
# 1. Import config first
ddev drush cim -y

# 2. Uninstall the module
ddev drush pm:uninstall module_name -y

# 3. Export config to capture the change
ddev drush cex -y

# 4. Verify the site works

# 5. Review exported config files
git diff config/alchemizetechwebsite/

# 6. Commit the configuration changes
git add config/alchemizetechwebsite/
git commit -m "Uninstall module_name module"
```

#### Changing Configuration Values
```bash
# 1. Import config first
ddev drush cim -y

# 2. Make the change (via UI or Drush)
ddev drush config:set config.name key value -y

# 3. Export config
ddev drush cex -y

# 4. Review and commit
```

### After Making Changes

**Always export configuration after making changes** to capture them in version-controlled files:

```bash
ddev drush cex -y
```

**What to expect:**
- Configuration files will be written to `config/alchemizetechwebsite/`
- The command will list what was exported

**Critical steps after export:**
1. **Verify site functionality** - Test that the site works correctly with the new configuration
2. **Review exported files** - Use `git diff` to review what changed:
   ```bash
   git diff config/alchemizetechwebsite/
   ```
3. **Check for accuracy** - Ensure the exported configuration matches your intent
4. **Commit the changes**:
   ```bash
   git add config/alchemizetechwebsite/
   git commit -m "Description of configuration changes"
   git push
   ```

### Role responsibilities (config export)

| Role | Responsibility |
|------|----------------|
| **drupal_developer** | **MUST** run `ddev drush cex -y` at the **very end, before the final commit** on a task. Then add and commit all new or modified files under the config sync directory. Skipping this leads to partial config and broken deploys. |
| **drupal_code_reviewer** | **MUST** run `ddev drush cex -y` before completing the review to ensure no configuration was missed. If the export produces new or changed files, add and commit them (or require the author to do so) so the branch has a complete config export. |

## Essential Drush Commands

### Configuration Management

#### Check Configuration Status
Check if there are differences between the database and sync directory:

```bash
ddev drush config:status
```

#### Import Configuration
Import configuration from sync directory to database:

```bash
ddev drush cim -y
# or
ddev drush config:import -y
```

#### Export Configuration
Export configuration from database to sync directory:

```bash
ddev drush cex -y
# or
ddev drush config:export -y
```

#### Get Configuration Value
Retrieve a configuration value:

```bash
ddev drush config:get config.name
ddev drush config:get config.name key
```

#### Set Configuration Value
Set a configuration value:

```bash
ddev drush config:set config.name key value -y
```

### Module Management

#### List Modules
```bash
# List all enabled non-core modules
ddev drush pm:list --status=enabled --type=module --no-core

# Filter by name
ddev drush pm:list --filter=module_name
```

#### Install Module
```bash
ddev drush en module_name -y
# or
ddev drush pm:install module_name -y
```

**Note:** This enables an already-downloaded module. Use `composer require` first to download the code.

#### Uninstall Module
```bash
ddev drush pm:uninstall module_name -y
```

**Important:** Always export configuration after uninstalling a module to capture the change.

### Theme Management

#### Enable Theme
```bash
ddev drush then theme_name -y
```

#### Set Default Theme
```bash
ddev drush config:set system.theme default theme_name -y
```

### Cache Management

#### Rebuild Cache
Clear and rebuild all caches:

```bash
ddev drush cr
# or
ddev drush cache:rebuild
```

**When to use:**
- After configuration changes
- After module installation/uninstallation
- When experiencing caching issues
- After code changes that affect cached data

### Database Updates

#### Run Database Updates
Apply pending database updates (schema changes, etc.):

```bash
ddev drush updb -y
# or
ddev drush updatedb -y
```

### Site Status

#### Check Site Status
Get comprehensive site information:

```bash
ddev drush status
```

## Canvas-Specific Commands

### Regenerate Canvas Components
Force Canvas to rediscover and regenerate component config entities (fixes deduplication issues):

```bash
ddev drush php-eval "\Drupal::service('Drupal\canvas\ComponentSource\ComponentSourceManager')->generateComponents();"
ddev drush cr
```

### Check Canvas Component Status
List all Canvas components and their enabled/disabled status:

```bash
ddev drush php-eval "
\$components = \Drupal::entityTypeManager()->getStorage('component')->loadMultiple();
foreach (\$components as \$id => \$component) {
  echo \$id . ' => ' . (\$component->status() ? 'enabled' : 'DISABLED') . ' | ' . \$component->label() . PHP_EOL;
}
"
```

### List Canvas Pages
```bash
ddev drush php-eval "
\$pages = \Drupal::entityTypeManager()->getStorage('canvas_page')->loadMultiple();
foreach (\$pages as \$page) {
  echo 'ID: ' . \$page->id() . ' | ' . \$page->label() . ' | ' . (\$page->isPublished() ? 'Published' : 'Unpublished') . PHP_EOL;
}
"
```

## Common Workflow Examples

### Example 1: Installing a New Module

```bash
# 1. Download via Composer
composer require drupal/module_name

# 2. Enable the module
ddev drush en module_name -y

# 3. Export the configuration change
ddev drush cex -y

# 4. Rebuild cache
ddev drush cr

# 5. Verify site works

# 6. Review exported changes
git diff config/alchemizetechwebsite/

# 7. Commit the changes
git add composer.json composer.lock config/alchemizetechwebsite/ web/modules/contrib/module_name/
git commit -m "Install and enable module_name"
git push
```

### Example 2: Changing Site Configuration

```bash
# 1. Import latest config
ddev drush cim -y

# 2. Make changes (via UI or Drush)
ddev drush config:set system.site name "AlchemizeTech" -y

# 3. Export the change
ddev drush cex -y

# 4. Review and commit
git diff config/alchemizetechwebsite/system.site.yml
git add config/alchemizetechwebsite/system.site.yml
git commit -m "Update site name"
git push
```

### Example 3: Creating a New Content Type

```bash
# 1. Import latest config
ddev drush cim -y

# 2. Create the content type via UI (/admin/structure/types/add)
# 3. Add fields, configure form and view displays

# 4. Export configuration
ddev drush cex -y

# 5. Review all the new config files
git diff config/alchemizetechwebsite/

# 6. Commit
git add config/alchemizetechwebsite/
git commit -m "Add [content_type] content type with fields"
git push
```

## Canvas Programmatic API Gotchas

Hard-won lessons for writing capability scripts and working with Canvas entities in PHP.

### Entity types and their APIs differ

| Entity | Type | `get('component_tree')` returns | `getComponentTree()` returns | `setComponentTree(array)` |
|--------|------|--------------------------------|------------------------------|---------------------------|
| `ContentTemplate` | Config entity | Raw `array` | `ComponentTreeItemList` | ✅ via `ComponentTreeConfigEntityBase` |
| `canvas_page` (Page) | Content entity | `FieldItemListInterface` | `ComponentTreeItemList` | ✅ via `Page::setComponentTree()` |

**Key rule:** When you need a plain PHP array of tree items (to count, filter, append, etc.), use `$entity->get('component_tree')` for config entities. For content entities, use `$entity->get('field_name')->getValue()`. Never pass a `ComponentTreeItemList` back into `setComponentTree()` or `set('component_tree', ...)` — it expects a raw array.

### Component tree item structure

Every item in a component tree is an associative array with these keys:

```php
[
  'uuid'              => string,    // v4 UUID
  'component_id'      => string,    // e.g. 'sdc.bootstrap_forge.heading'
  'component_version' => ?string,   // version hash or NULL
  'parent_uuid'       => ?string,   // NULL = root-level
  'slot'              => ?string,   // slot name on parent (e.g. 'content', 'row', 'card_body')
  'weight'            => int,       // ordering within slot
  'inputs'            => string,    // JSON-encoded prop values
  'label'             => ?string,   // optional human label
]
```

`inputs` is a **JSON string**, not an array. Always `json_encode()` when building and `json_decode()` when reading.

### Component prop names and values

Always check the actual `.component.yml` schema before guessing prop names. Common mistakes:

- **Heading**: props are `text` and `level` (not `heading_text`, not `heading_level`)
- **Button**: `size` enum is `["default","sm","lg"]` (not `"md"`)
- **Blockquote**: props are `footer` and `cite` (not `citation`)
- **Accordion item**: `open_by_default` (not `open`), `heading_level` is an integer like `3` (not a string like `'h3'`)
- **Image media**: Must be a proper object `{'src': '...', 'alt': '...', 'width': int, 'height': int}`, not an empty array `[]`. An empty array triggers `AssertionError` in `StaticPropSource::isMinimalRepresentation()`.

Schema files live at: `web/themes/contrib/bootstrap_forge/components/<name>/<name>.component.yml`

### AutoSaveManager API

`AutoSaveManager::getAutoSaveEntity()` returns an `AutoSaveEntity` **wrapper**, not the entity itself:

```php
$auto_saved = $auto_save_manager->getAutoSaveEntity($entity);
$auto_saved->isEmpty();     // bool — check first
$auto_saved->entity;        // the actual entity (or NULL)
$auto_saved->entity->get('component_tree');  // access fields through ->entity
```

`saveEntity()` compares a hash of the entity's current data against the stored version. If they match, it **silently deletes** the auto-save entry instead of saving. To test auto-save, you must modify the entity so the hash differs.

Delete method is `$auto_save_manager->delete($entity)` (not `deleteEntity()`).

### Drush php:script variable scope

Variables defined at the top level of a `drush php:script` file are **not** in PHP's global scope. `global $var` inside a function will NOT see them. Use `$GLOBALS['key']` instead:

```php
// At script top level:
$GLOBALS['_my_counter'] = 0;

function my_function() {
  // WRONG: global $counter; — this is NULL
  // RIGHT:
  $GLOBALS['_my_counter']++;
}
```

### ContentTemplate exposed slots

- `$template->getExposedSlots()` returns an associative array keyed by slot machine name
- Each slot has: `component_uuid`, `slot_name`, `label`
- The `component_uuid` must reference a component in the template's own tree
- The template's tree must keep exposed slots **empty** — the `ValidExposedSlotConstraint` validator rejects any tree item whose `parent_uuid`+`slot` targets an exposed slot
- `stripExposedSlotContent()` in `ApiLayoutController` provides defense-in-depth server-side

### Running scripts from subprocesses

When `drush php:script` runs from within another drush process (e.g., orchestrator script), the working directory is the Drupal root (`web/`), not the project root. Use absolute paths via `dirname(__FILE__)`:

```php
$base = dirname(__FILE__);
$cmd = "drush php:script '$base/other-script.drush.php' 2>&1";
```

## Troubleshooting

### Configuration Import Fails
If `cim` fails:
1. Check for configuration conflicts
2. Review error messages carefully
3. Check if modules are enabled/disabled correctly
4. Verify database connection: `ddev drush status`

### Configuration Export Shows Unexpected Changes
If `cex` shows unexpected changes:
1. Check if you have uncommitted config changes: `git status config/alchemizetechwebsite/`
2. Verify you imported config before making changes
3. Review what changed: `git diff config/alchemizetechwebsite/`
4. Check if changes were made directly in the database

### Site Not Responding After Config Changes
1. Rebuild cache: `ddev drush cr`
2. Check site status: `ddev drush status`
3. Run database updates: `ddev drush updb -y`
4. Check error logs: `ddev logs`

### Drush Commands Fail Outside DDEV
Drush requires the database, which runs inside the DDEV container. Always prefix commands with `ddev`:

```bash
# Correct
ddev drush cr

# Incorrect (will fail - no database connection)
vendor/bin/drush cr
```

## Best Practices

1. **Always import before starting work** - Ensures you're working from the latest committed state
2. **Always export after making changes** - Captures your work in version control
3. **Always test after changes** - Verify the site works before committing
4. **Always review diffs** - Check what changed before committing
5. **Commit config changes separately** - Keep config commits focused and reviewable
6. **Use descriptive commit messages** - Explain what changed and why
7. **Rebuild cache when needed** - After config changes, module changes, or when troubleshooting
8. **Use `ddev drush` always** - Never run Drush directly outside the DDEV container

## Related Documentation

- Drupal Configuration Management: https://www.drupal.org/docs/configuration-management
- Drush Documentation: https://www.drush.org/
- DDEV Documentation: https://ddev.readthedocs.io/

## Notes

- All commands have been tested and validated in this workspace
- Configuration files are stored as YAML in `config/alchemizetechwebsite/`
- Some Drush commands use aliases (e.g., `cim` for `config:import`, `cex` for `config:export`, `cr` for `cache:rebuild`)
- Canvas-specific operations (component regeneration, page listing) require `php-eval` commands since Canvas doesn't provide dedicated Drush commands
