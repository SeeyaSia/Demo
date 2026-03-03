# Site Settings — System Configuration Reference

## Purpose

Developer guide for configuring Drupal site-level settings: site information, front page, error pages, file system, and other system settings. These settings control fundamental site behavior and are managed through `system.site.yml` and `system.file.yml`.

---

## Site Information (`system.site.yml`)

The primary site configuration file controls the site's identity and key page assignments.

### Configuration keys

| Key | Purpose | Default | Notes |
|-----|---------|---------|-------|
| `name` | Site name (browser title, emails, templates) | `''` | Displayed in page titles, system emails, and metatags |
| `mail` | System email address | `''` | Used as "From" for all system emails, Webform handlers, notifications |
| `slogan` | Site tagline | `''` | Available in Twig templates via `{{ site_slogan }}` |
| `page.front` | Front page path | `/node` | Path that serves as the homepage |
| `page.403` | Access denied page | `''` (Drupal default) | Custom path for 403 errors |
| `page.404` | Not found page | `''` (Drupal default) | Custom path for 404 errors |
| `default_langcode` | Default language | `en` | Site language code |
| `admin_compact_mode` | Compact admin UI | `false` | Reduces whitespace in admin pages |
| `weight_select_max` | Max weight in selects | `100` | Used in menus, blocks, ordered entities |

### Setting the front page

The front page can be any valid Drupal path:

| Approach | `page.front` value | Notes |
|----------|-------------------|-------|
| **Default (frontpage View)** | `/node` | Shows promoted content via `views.view.frontpage` |
| **Canvas Page** | `/my-home` | Set a Canvas page's path, then configure as front |
| **Specific node** | `/node/1` | Direct node route (not recommended — fragile) |
| **Custom route** | `/home` | From a custom module or Pathauto alias |

**Changing the front page:**
1. Navigate to `/admin/config/system/site-information`
2. Set the "Default front page" field
3. Export config: `ddev drush cex -y`

Or via capability script:

```php
$config = \Drupal::configFactory()->getEditable('system.site');
$config->set('page.front', '/my-home');
$config->save();
```

### Custom error pages

Create custom 403/404 pages by:
1. Create a node or Canvas page with the error content
2. Set the path in site configuration
3. Export config

```yaml
# system.site.yml
page:
  403: '/access-denied'
  404: '/page-not-found'
  front: /home
```

### Site email

The site email (`mail`) is used as the default sender for:
- User registration/password reset emails
- Content moderation notifications
- Webform email handlers (default "From" address)
- Any module that sends system emails

**Important:** Configure a valid, deliverable email address for production. Many email providers reject mail from invalid sender addresses.

---

## File System Settings (`system.file.yml`)

Controls file upload storage and temporary file management.

### Configuration keys

| Key | Purpose | Default | Security notes |
|-----|---------|---------|---------------|
| `default_scheme` | File storage scheme | `public` | `public://` = web-accessible, `private://` = access-controlled |
| `allow_insecure_uploads` | Allow dangerous file extensions | `false` | **Keep false.** Blocks .php, .js, .exe, etc. |
| `temporary_maximum_age` | Temp file cleanup interval | `21600` (6 hours) | Cron deletes temp files older than this |

### Public vs private file schemes

| Scheme | Storage path | Web-accessible | Use case |
|--------|-------------|----------------|----------|
| `public://` | `web/sites/default/files/` | ✅ Yes | Images, documents, media — default |
| `private://` | Outside web root | ❌ No — access-controlled | Sensitive files, gated downloads |

**Note:** Private file system requires additional server configuration (setting `file_private_path` in `settings.php`). Public is the default and appropriate for most content.

### Insecure uploads protection

When `allow_insecure_uploads` is `false` (default), Drupal blocks uploads of files with dangerous extensions:
- `.phar`, `.php`, `.pl`, `.py`, `.cgi`, `.asp`, `.js`, `.htaccess`, `.phtml`
- Files may be renamed (e.g., `file.php` → `file.php.txt`) or rejected entirely

**Never set this to `true` in production.** This is a critical security control.

---

## Other System Settings

### Performance (`system.performance.yml`)

See `configuration/performance.md` for details. Key settings:
- Cache max-age
- CSS/JS aggregation
- Page cache settings

### Date/time (`system.date.yml`)

| Key | Purpose |
|-----|---------|
| `timezone.default` | Default site timezone |
| `timezone.user.configurable` | Whether users can set their own timezone |
| `country.default` | Default country code |
| `first_day` | First day of the week (0 = Sunday) |

### Maintenance mode (`system.maintenance.yml`)

| Key | Purpose |
|-----|---------|
| `message` | Message shown during maintenance mode |
| `langcode` | Language of the maintenance message |

Enable/disable via Drush:
```bash
ddev drush state:set system.maintenance_mode 1    # Enable
ddev drush state:set system.maintenance_mode 0    # Disable
ddev drush cr                                      # Clear cache after
```

**Note:** Maintenance mode is stored in state (database), not config. It won't appear in config export.

---

## Modifying Settings

### Via admin UI

1. Navigate to `/admin/config/system/site-information`
2. Make changes
3. Export: `ddev drush cex -y`

### Via capability script

```php
// Site info
$site_config = \Drupal::configFactory()->getEditable('system.site');
$site_config->set('name', 'My Site Name');
$site_config->set('mail', 'admin@example.com');
$site_config->set('page.front', '/home');
$site_config->save();

// File system
$file_config = \Drupal::configFactory()->getEditable('system.file');
$file_config->set('default_scheme', 'public');
$file_config->save();
```

### Via Drush

```bash
# Read a config value
ddev drush config:get system.site name

# Set a config value
ddev drush config:set system.site name "My Site Name"

# Export after changes
ddev drush cex -y
```

---

## Configuration Files

| File | Contents |
|------|----------|
| `config/<site>/system.site.yml` | Site name, email, front page, error pages |
| `config/<site>/system.file.yml` | File storage scheme, upload security, temp file age |
| `config/<site>/system.date.yml` | Timezone, country, date format defaults |
| `config/<site>/system.performance.yml` | Cache, aggregation settings |
| `config/<site>/system.maintenance.yml` | Maintenance mode message |

---

## Gotchas

- **Front page `/node`**: The default `/node` path depends on the `frontpage` View being enabled. If it's disabled, the front page will 404.
- **Maintenance mode is state, not config.** It won't export with `drush cex` — it's stored in the `key_value` database table.
- **Site email affects all outbound mail.** Changing it affects Webform, user emails, and all system notifications.
- **File scheme change doesn't move existing files.** Changing `default_scheme` only affects future uploads. Existing files stay where they are.

---

## Related Documentation

| Document | Relevance |
|---|---|
| `configuration/performance.md` | Cache and aggregation settings |
| `configuration/users-and-roles.md` | User account settings, registration |
| `integrations/site-services.md` | Webform (uses site email), Metatag (uses site name) |
| `global/drush-config-workflow.md` | Config export/import workflow |
