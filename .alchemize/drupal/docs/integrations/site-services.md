# Site Services — Metatag, Search, Webform, Media, Redirects, Menus

## Purpose

Developer guide for configuring Drupal integration modules: Metatag (SEO), Search API (search), Webform (forms), Media (assets), Rabbit Hole (entity display control), Redirect (URL management), Simple OAuth (API auth), and Menus (navigation). Each section covers what the module does, how to configure it, and how it integrates with content types and Canvas.

---

## Metatag (SEO)

**Module:** `metatag`

**Purpose:** Manages SEO meta tags (title, description, canonical URLs, Open Graph) for all entity types.

### How Metatag works

Metatag uses a **defaults hierarchy**:
1. **Global defaults** — Apply to all pages
2. **Entity type defaults** — Override global for nodes, users, taxonomy terms
3. **Per-entity overrides** — Authors can customize tags on individual entities

### Default tag patterns

| Scope | Title pattern | Description | Canonical URL |
|-------|-------------|-------------|---------------|
| Global | `[current-page:title] \| [site:name]` | — | `[current-page:url]` |
| Node | `[node:title] \| [site:name]` | `[node:summary]` | `[node:url]` |
| Taxonomy term | `[term:name] \| [site:name]` | — | `[term:url]` |
| User | `[user:display-name] \| [site:name]` | — | `[user:url]` |
| Front page | Customizable | Customizable | `[site:url]` |
| 403/404 | Customizable | — | — |

### Configuring Metatag

**Admin UI:** `/admin/config/search/metatag`

**Programmatically:**
```php
$config = \Drupal::configFactory()->getEditable('metatag.metatag_defaults.node');
$config->set('tags.title', '[node:title] | [site:name]');
$config->set('tags.description', '[node:summary]');
$config->set('tags.canonical_url', '[node:url]');
$config->save();
```

### Token system

Metatag uses Drupal's token system for dynamic values:
- `[node:title]` — Node title
- `[node:summary]` — Body field summary
- `[node:url]` — Node canonical URL
- `[site:name]` — Site name from `system.site.yml`
- `[current-page:title]` — Current page title
- `[current-page:url]` — Current page URL

### Submodules

| Submodule | Purpose | Enable when... |
|-----------|---------|---------------|
| `metatag_open_graph` | Open Graph tags (Facebook, LinkedIn) | You need social sharing previews |
| `metatag_twitter_cards` | Twitter card tags | You need Twitter sharing previews |
| `metatag_verification` | Site verification meta tags | You need Google/Bing verification |

### Configuration files

- `config/<site>/metatag.settings.yml` — Global settings
- `config/<site>/metatag.metatag_defaults.*.yml` — Default tag patterns per scope

---

## Search API

**Module:** `search_api` with `search_api_db` backend

**Purpose:** Provides content indexing and search. Uses the database backend for indexing (no external search engine needed).

### Architecture

Search API has three main config entities:
1. **Server** — Defines the backend (database, Solr, Elasticsearch)
2. **Index** — Defines what to index (entity types, fields, processors)
3. **Search display** — How results appear (usually via Views)

### Setting up Search API

**Step 1: Create a server**

```php
use Drupal\search_api\Entity\Server;

Server::create([
  'id' => 'default_server',
  'name' => 'Default Server',
  'backend' => 'search_api_db',
  'backend_config' => [
    'database' => 'default:default',
    'min_chars' => 3,
    'autocomplete' => ['suggest_suffix' => TRUE],
  ],
])->save();
```

**Step 2: Create an index**

```php
use Drupal\search_api\Entity\Index;

Index::create([
  'id' => 'content',
  'name' => 'Content',
  'server' => 'default_server',
  'datasource_settings' => [
    'entity:node' => [
      'bundles' => ['default' => TRUE],
    ],
  ],
  'field_settings' => [
    'title' => ['type' => 'text', 'datasource_id' => 'entity:node', 'property_path' => 'title'],
    'body' => ['type' => 'text', 'datasource_id' => 'entity:node', 'property_path' => 'body'],
    'status' => ['type' => 'boolean', 'datasource_id' => 'entity:node', 'property_path' => 'status'],
  ],
])->save();
```

**Step 3: Index content**

```bash
ddev drush search-api:index          # Index all pending items
ddev drush search-api:status         # Check indexing status
ddev drush search-api:reset-tracker  # Reset tracking (re-index everything)
```

### Search API + Views

Search API indexes are used as Views base tables. Create a View with base table set to a Search API index to build search pages:

1. Create View at `/admin/structure/views/add`
2. Select "Index: [index name]" as the View type
3. Add search keywords exposed filter
4. Configure result display (entity view mode or fields)

### Cron indexing settings

```yaml
# search_api.settings.yml
default_cron_limit: 50         # Items indexed per cron run
cron_worker_runtime: 15        # Max seconds for cron indexing
```

### Configuration files

- `config/<site>/search_api.settings.yml` — Global settings
- `config/<site>/search_api.server.*.yml` — Server configurations
- `config/<site>/search_api.index.*.yml` — Index configurations

---

## Core Search

**Module:** `search` (Drupal core)

An alternative to Search API. Core search provides basic full-text search using the database.

| Feature | Core Search | Search API |
|---------|------------|------------|
| **Setup** | Zero config | Requires server + index config |
| **Backend** | Database only | Database, Solr, Elasticsearch, etc. |
| **Customization** | Limited | Highly configurable |
| **Performance** | Adequate for small sites | Better for large sites |
| **Views integration** | Limited | Full Views integration |

Core search provides a search page at `/search/node` and indexes content automatically via cron.

---

## Webform

**Module:** `webform`

**Purpose:** Form builder for contact forms, surveys, and data collection.

### Creating a webform

Webforms are configuration entities with elements (fields), handlers (actions), and access control.

**Admin UI:** `/admin/structure/webform/add`

**Programmatically:**
```php
use Drupal\webform\Entity\Webform;

Webform::create([
  'id' => 'contact',
  'title' => 'Contact',
  'elements' => "
name:
  '#type': textfield
  '#title': 'Your Name'
  '#required': true
email:
  '#type': email
  '#title': 'Your Email'
  '#required': true
message:
  '#type': textarea
  '#title': 'Message'
  '#required': true
actions:
  '#type': webform_actions
  '#title': 'Submit button(s)'
  '#submit__label': 'Send message'
  ",
])->save();
```

### Email handlers

Webforms send emails via handlers. Common handler configuration:

| Setting | Submitter confirmation | Admin notification |
|---------|----------------------|-------------------|
| **To** | `[current-user:mail]` | Site email |
| **From** | Site email | Submitter email |
| **Subject** | `[webform_submission:values:subject:raw]` | Same |
| **Body** | `[webform_submission:values:message:value]` | Same |

### Webform access control

See `configuration/users-and-roles.md` — each webform has per-form access settings for create, view, update, delete operations.

### Canvas integration

Webform blocks can be placed in Canvas Pages:
- Webform module exposes forms as Drupal blocks
- Block components appear in Canvas as `block.webform_block`
- Place in Canvas page tree like any other block component

### Configuration files

- `config/<site>/webform.webform.*.yml` — Webform definitions
- `config/<site>/webform.settings.yml` — Global webform settings

---

## Rabbit Hole (Entity Display Control)

**Module:** `rabbit_hole`

**Purpose:** Controls what happens when users visit entity pages. Useful for hiding entity pages for content types that shouldn't have standalone pages.

### Actions

| Action | Behavior | Use case |
|--------|----------|----------|
| `display_page` | Normal page display (default) | Standard content |
| `page_not_found` | Return 404 | Content types without standalone pages |
| `access_denied` | Return 403 | Restricted content |
| `page_redirect` | Redirect to URL | Redirect to listing or parent |

### Configuration levels

1. **Global default** — `rabbit_hole.behavior_settings.default.yml`
2. **Bundle default** — Per content type override
3. **Per-entity override** — Individual entity setting (if `allow_override` is enabled)

### Example: Hide a content type's pages

```php
$config = \Drupal::configFactory()->getEditable('rabbit_hole.behavior_settings.node_type_my_type');
$config->set('action', 'page_not_found');
$config->set('allow_override', 0);
$config->save();
```

### Configuration files

- `config/<site>/rabbit_hole.behavior_settings.*.yml` — Behavior settings

---

## Redirect (URL Management)

**Module:** `redirect`

**Purpose:** Manages URL redirects (301/302). Automatically creates redirects when URL aliases change.

### Key settings

| Setting | Default | Purpose |
|---------|---------|---------|
| `auto_redirect` | `true` | Auto-create redirects when aliases change |
| `default_status_code` | `301` | Permanent redirect by default |
| `passthrough_querystring` | `true` | Preserve query strings in redirects |

### Pathauto integration

When Pathauto changes a URL alias (e.g., a node title changes), Redirect automatically creates a 301 redirect from the old URL to the new URL. This prevents broken links and preserves SEO value.

### Creating redirects programmatically

```php
use Drupal\redirect\Entity\Redirect;

Redirect::create([
  'redirect_source' => ['path' => 'old-path'],
  'redirect_redirect' => ['uri' => 'internal:/new-path'],
  'status_code' => 301,
])->save();
```

### Configuration files

- `config/<site>/redirect.settings.yml` — Module settings

---

## Simple OAuth (API Authentication)

**Module:** `simple_oauth`

**Purpose:** OAuth 2.0 authentication server. Used for API access and Canvas CLI integration.

### Setup requirements

1. **RSA key pair** — Required for token signing/verification
2. **OAuth consumer** — Client configuration (client ID, secret, scopes)

### RSA key generation

```bash
# Generate keys in the DDEV project
mkdir -p .ddev/oauth-keys
openssl genrsa -out .ddev/oauth-keys/private.key 2048
openssl rsa -in .ddev/oauth-keys/private.key -pubout -out .ddev/oauth-keys/public.key
```

### Key configuration

```yaml
# simple_oauth.settings.yml
public_key: /var/www/html/.ddev/oauth-keys/public.key
private_key: /var/www/html/.ddev/oauth-keys/private.key
```

**Note:** Paths are container paths (inside DDEV), not host paths.

### Canvas CLI integration

Canvas CLI uses Simple OAuth for API authentication. The Canvas OAuth submodule (`canvas_oauth`) provides the consumer configuration. See `canvas/canvas-cli.md` for setup details.

### Configuration files

- `config/<site>/simple_oauth.settings.yml` — OAuth settings (key paths, scopes)

---

## Menus

**Module:** `system` (core)

**Purpose:** Navigation menus for the site.

### Standard menus

| Menu | Machine name | Purpose | Typical placement |
|------|-------------|---------|-------------------|
| **Main navigation** | `main` | Primary site navigation | Header region |
| **Footer** | `footer` | Footer navigation | Footer region |
| **Account** | `account` | User login/logout links | Header region |
| **Administration** | `admin` | Admin navigation | Admin toolbar |
| **Tools** | `tools` | Developer/utility links | Sidebar |

### Menu blocks in Canvas

Menus are exposed as block components in Canvas:
- `block.system_menu_block.main` — Main navigation
- `block.system_menu_block.footer` — Footer navigation
- `block.system_menu_block.account` — Account menu

Place menu blocks in Canvas PageRegions for site-wide navigation or in Canvas Pages for page-specific navigation.

### Menu settings for block display

| Setting | Purpose |
|---------|---------|
| `level` | Starting level (1 = top level) |
| `depth` | How many levels deep to display (0 = unlimited) |
| `expand_all_items` | Whether to expand all menu items or only active trail |

### Creating menu links programmatically

```php
use Drupal\menu_link_content\Entity\MenuLinkContent;

MenuLinkContent::create([
  'title' => 'About Us',
  'link' => ['uri' => 'internal:/about'],
  'menu_name' => 'main',
  'weight' => 0,
])->save();
```

### Configuration files

- `config/<site>/system.menu.*.yml` — Menu definitions

---

## Update Notifications

**Module:** `update` (core)

**Purpose:** Checks for available Drupal core and module updates, sends email notifications.

### Key settings (`update.settings.yml`)

| Setting | Purpose | Default |
|---------|---------|---------|
| `check.interval_days` | How often to check for updates | `1` |
| `notification.threshold` | Which updates to notify about | `all` (or `security`) |
| `notification.emails` | Email recipients | Admin email |

### Drush alternative

```bash
ddev drush pm:security          # Check for security updates only
ddev drush pm:list --status=enabled --type=module  # List enabled modules with versions
```

---

## Configuration Files Summary

| Module | Config pattern |
|--------|---------------|
| Metatag | `config/<site>/metatag.*.yml` |
| Search API | `config/<site>/search_api.*.yml` |
| Webform | `config/<site>/webform.*.yml` |
| Media | `config/<site>/media.type.*.yml` |
| Rabbit Hole | `config/<site>/rabbit_hole.*.yml` |
| Redirect | `config/<site>/redirect.settings.yml` |
| Simple OAuth | `config/<site>/simple_oauth.settings.yml` |
| Menus | `config/<site>/system.menu.*.yml` |
| Update | `config/<site>/update.settings.yml` |

---

## Gotchas

- **Metatag tokens must be valid.** Invalid tokens (e.g., `[node:nonexistent_field]`) render as empty strings. Use the token browser at `/admin/help/token`.
- **Search API requires explicit setup.** Installing the module doesn't enable search — you must create a server and index.
- **Webform email handlers depend on site email.** If `system.site.mail` is misconfigured, form notifications fail silently.
- **Redirect loops.** Misconfigured redirects can create loops. The Redirect module has loop detection but test carefully.
- **Rabbit Hole hides entity pages.** If you set a content type to 404 via Rabbit Hole, content editors can still edit via `/node/NID/edit` but the view page will 404.
- **OAuth keys must exist.** Canvas CLI and any OAuth consumers fail if RSA keys are missing or paths are wrong.
- **Media types are from core.** The standard media types (image, video, document, audio, remote_video) ship with Drupal. Don't recreate them.

---

## Related Documentation

| Document | Relevance |
|---|---|
| `data-model/content-types.md` | Content types referenced by Search API, Metatag |
| `data-model/entity-types.md` | Media types, entity reference patterns |
| `canvas/canvas-page-regions.md` | Menu blocks in Canvas regions |
| `canvas/canvas-cli.md` | Canvas CLI OAuth setup |
| `configuration/users-and-roles.md` | Webform access, module permissions |
| `configuration/site-settings.md` | Site email (used by Webform, notifications) |
| `global/drush-config-workflow.md` | Config export after changes |
