# Modules — Drupal Module Architecture and Contrib Reference

## Purpose

Developer guide for understanding Drupal's module system: core modules, contributed modules, custom modules, and how they interact. Covers module categories, what each major module does, and how to manage the module stack.

---

## Module Architecture

### Module types

| Type | Location | Managed by | Examples |
|------|----------|-----------|---------|
| **Core** | `web/core/modules/` | Drupal core | node, user, views, media, taxonomy |
| **Contributed** | `web/modules/contrib/` | Composer | canvas, pathauto, metatag, webform |
| **Custom** | `web/modules/custom/` | Developer | Site-specific modules |

### Enabling and managing modules

```bash
# Enable a module
ddev drush en module_name -y

# Disable a module (uninstall)
ddev drush pm:uninstall module_name -y

# List enabled modules
ddev drush pm:list --status=enabled --type=module

# Check module info
ddev drush pm:info module_name

# Export config after module changes
ddev drush cex -y
```

### Module dependencies

Modules declare dependencies in their `.info.yml` file. When enabling a module, Drupal automatically enables its dependencies. When uninstalling, you must uninstall dependent modules first.

The enabled module list is stored in `config/<site>/core.extension.yml`.

---

## Core Module Categories

### Content and Data

| Module | Purpose | Notes |
|--------|---------|-------|
| `node` | Content types and node entities | Foundation of content management |
| `taxonomy` | Vocabularies and taxonomy terms | Categorization and tagging |
| `comment` | Comment entities on content | Threaded discussion |
| `media` | Reusable media entities | Image, video, document, audio, remote video |
| `media_library` | Visual media browser widget | Grid view, upload, search |
| `block_content` | Custom content block entities | Reusable blocks for regions |
| `file` | File upload and management | Underlying file system |
| `image` | Image processing and styles | Scale, crop, format conversion |

### Content Editing

| Module | Purpose | Notes |
|--------|---------|-------|
| `ckeditor5` | WYSIWYG rich text editor | CKEditor 5 integration |
| `editor` | Text editor framework | Base for CKEditor 5 |
| `filter` | Text format filtering | HTML allowed tags, security |
| `text` | Formatted text field types | text_long, text_with_summary |
| `field_ui` | Field management UI | Add/configure fields via admin |
| `options` | List field widgets | Select, checkboxes, radio buttons |
| `link` | Link field type | URL + title fields |
| `telephone` | Phone number field type | Basic phone field |

### Page Building and Display

| Module | Purpose | Notes |
|--------|---------|-------|
| `views` | Query builder and listing engine | Foundation for all listings |
| `views_ui` | Views admin interface | `/admin/structure/views` |
| `block` | Block placement system | Traditional block regions |
| `layout_builder` | Visual layout builder | Alternative to Canvas for entity display |
| `layout_discovery` | Layout plugin discovery | Required by layout_builder |

### Workflow and Moderation

| Module | Purpose | Notes |
|--------|---------|-------|
| `content_moderation` | Draft → Published → Archived workflow | Editorial states |
| `workflows` | Workflow engine | Used by content_moderation |

### User and Access

| Module | Purpose | Notes |
|--------|---------|-------|
| `user` | User entities and authentication | Roles, permissions, login |

### Performance

| Module | Purpose | Notes |
|--------|---------|-------|
| `page_cache` | Anonymous page caching | Full page cache |
| `dynamic_page_cache` | Authenticated page caching | Per-user placeholder caching |
| `big_pipe` | Response streaming | Progressive page loading |

### Admin Interface

| Module | Purpose | Notes |
|--------|---------|-------|
| `navigation` | Drupal 11 admin navigation | Modern admin sidebar |
| `toolbar` | Legacy admin toolbar | Top bar navigation |
| `shortcut` | Quick access shortcuts | Admin shortcut links |
| `contextual` | Contextual edit links | Quick edit buttons |
| `help` | Help system | Module help pages |

### System and Utilities

| Module | Purpose | Notes |
|--------|---------|-------|
| `automated_cron` | Scheduled task execution | See `configuration/performance.md` |
| `dblog` | Database logging | System event logs |
| `update` | Update notifications | Core + contrib update checking |
| `config` | Configuration management | Config import/export |
| `migrate` | Content migration framework | Data import from other systems |
| `serialization` | Data serialization | JSON/XML support |
| `path` | URL aliases | Manual path aliases |
| `path_alias` | Path alias entities | Underlying alias storage |

---

## Contributed Module Stack

### Canvas Ecosystem

| Package | Modules provided | Purpose |
|---------|-----------------|---------|
| `drupal/canvas` | `canvas`, `canvas_dev_mode`, `canvas_oauth` | Visual page builder system |
| `drupal/canvas_bootstrap` | `canvas_bootstrap` | Bootstrap component integration |

Canvas is the primary page building system. See `canvas/` docs for details.

### SEO and URLs

| Package | Module | Purpose |
|---------|--------|---------|
| `drupal/metatag` | `metatag` | SEO meta tags |
| `drupal/pathauto` | `pathauto` | Automatic URL aliases |
| `drupal/redirect` | `redirect` | URL redirect management |
| `drupal/token` | `token` | Token browser and API |

### Search

| Package | Modules | Purpose |
|---------|---------|---------|
| `drupal/search_api` | `search_api`, `search_api_db` | Flexible search framework with database backend |

### Forms

| Package | Modules | Purpose |
|---------|---------|---------|
| `drupal/webform` | `webform`, `webform_ui` | Form builder and submissions |

### Entity Management

| Package | Module | Purpose |
|---------|--------|---------|
| `drupal/rabbit_hole` | `rabbit_hole` | Entity display control (404, redirect) |
| `drupal/better_exposed_filters` | `better_exposed_filters` | Improved Views filter widgets |

### Authentication

| Package | Modules | Purpose |
|---------|---------|---------|
| `drupal/simple_oauth` | `simple_oauth`, `consumers` | OAuth 2.0 server |

### Development

| Package | Module | Purpose |
|---------|--------|---------|
| `drupal/twig_tweak` | `twig_tweak` | Twig helper functions and filters |

---

## Module Management with Composer

### Installing a new module

```bash
# 1. Require the package
ddev composer require drupal/module_name

# 2. Enable the module
ddev drush en module_name -y

# 3. Export config
ddev drush cex -y

# 4. Commit
git add composer.json composer.lock config/
git commit -m "Add module_name module"
```

### Updating modules

```bash
# Update a specific module
ddev composer update drupal/module_name --with-dependencies

# Check for available updates
ddev drush pm:security

# Apply database updates after code update
ddev drush updb -y

# Export config (updates may change config)
ddev drush cex -y
```

### Removing a module

```bash
# 1. Uninstall the module (removes config)
ddev drush pm:uninstall module_name -y

# 2. Remove the package
ddev composer remove drupal/module_name

# 3. Export config
ddev drush cex -y
```

### Patching contrib modules

See `infrastructure/developer-tools.md` for the complete patching workflow using `cweagans/composer-patches`.

### Exploring contrib when preparing patches

When contributing a patch to a contrib module (e.g., for a drupal.org issue), you need to understand the module's structure before making changes. Use these entry points:

| What to understand | Where to look |
|--------------------|---------------|
| **Routes and URLs** | `MODULE.routing.yml` — routes, parameters, access requirements |
| **Access control** | Route `_access` or `_access_check` keys; `src/Access/` classes |
| **Entities** | `src/Entity/` — config and content entities, their schema and handlers |
| **Services and dependencies** | `MODULE.services.yml` — service definitions, injected dependencies |
| **Controllers** | `src/Controller/` — HTTP handlers, parameter conversion |
| **Validation** | `src/Plugin/Validation/Constraint/` — constraint validators |
| **Config schema** | `config/schema/` — typed config and validation rules |
| **Existing behavior** | `tests/` — kernel and unit tests document expected behavior |
| **Design decisions** | `docs/`, `docs/adr/` — ADRs and config-management docs |

Start with the routing file to find the code path for the feature you're changing. Trace access checks, controllers, and services. The module's own docs and tests are the best reference for intended behavior. Run `ddev exec vendor/bin/phpunit -- web/modules/contrib/MODULE/` to execute the module's tests before and after your changes.

---

## Custom Modules

Custom modules live in `web/modules/custom/`. Use Drush to scaffold:

```bash
ddev drush generate module         # Generate a new module
ddev drush generate controller     # Add a controller
ddev drush generate service        # Add a service
```

Custom modules should follow Drupal coding standards. See `infrastructure/developer-tools.md` for PHPCS configuration.

---

## Module Interactions

### Common module relationships

| Integration | Modules involved | How they interact |
|------------|-----------------|-------------------|
| **Page building** | Canvas + Canvas Bootstrap + Bootstrap Forge theme | Canvas uses SDC components from theme |
| **URL management** | Pathauto + Token + Redirect | Pathauto generates aliases, Redirect preserves old URLs |
| **SEO** | Metatag + Token + Pathauto | Metatag uses tokens including URL aliases |
| **Content workflow** | Content Moderation + Workflows + Views | Views can filter by moderation state |
| **Search** | Search API + Search API DB + Views | Views provides search result display |
| **API access** | Simple OAuth + Consumers + Canvas OAuth | Canvas CLI authenticates via OAuth |
| **Media** | Media + Media Library + Image | Media Library uses Image module for processing |

### Potential conflicts

- **Layout Builder + Canvas**: Both are page building systems. Use one per content type, not both.
- **Core Search + Search API**: Both provide search. Typically only one is actively used.
- **Navigation + Toolbar**: Both provide admin navigation. Drupal 11 prefers Navigation.

---

## Configuration Files

| File | Contents |
|------|----------|
| `config/<site>/core.extension.yml` | Complete list of enabled modules and themes |
| `composer.json` | Required Composer packages (with version constraints) |
| `composer.lock` | Locked dependency versions |

---

## Gotchas

- **Never install modules manually.** Always use Composer. Manual installs break dependency tracking.
- **Module uninstall removes config.** Uninstalling a module deletes its configuration. Export config before uninstalling if you want to preserve settings.
- **Submodules are separate.** A Composer package may provide multiple modules (e.g., `search_api` provides `search_api_db`). Enable them separately.
- **Module updates may require `drush updb`.** Database schema changes need to be applied after code updates.
- **`core.extension.yml` is the authority.** If a module appears in this file, it's enabled. If not, it's installed but not enabled.

---

## Related Documentation

| Document | Relevance |
|---|---|
| `infrastructure/developer-tools.md` | Drush commands, contrib patching, PHPCS |
| `infrastructure/environment-setup.md` | DDEV setup, Composer configuration |
| `global/development-workflow.md` | Composer workflow, deployment |
| `global/drush-config-workflow.md` | Config export after module changes |
| `integrations/site-services.md` | Detailed config for Metatag, Search API, Webform, etc. |
