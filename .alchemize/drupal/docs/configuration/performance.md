# Performance and Caching — Configuration Reference

## Purpose

Developer guide for Drupal performance configuration: page caching, CSS/JS aggregation, Fast 404, cron scheduling, and the caching module stack. Understanding these settings is critical for production readiness.

---

## Caching Module Stack

Drupal has three caching modules that work together:

| Module | Caches for | How it works |
|--------|-----------|--------------|
| **Page Cache** (`page_cache`) | Anonymous users | Stores full page responses; bypasses Drupal bootstrap entirely |
| **Dynamic Page Cache** (`dynamic_page_cache`) | Authenticated users | Caches pages with placeholders for per-user content |
| **BigPipe** (`big_pipe`) | Authenticated users | Streams page HTML; sends cached content first, then fills in personalized placeholders |

### Page Cache

Caches complete page responses for anonymous users. When enabled, anonymous requests are served from cache without executing any Drupal code.

**Configuration** (`system.performance.yml`):
```yaml
cache:
  page:
    max_age: 3600   # Seconds. 0 = disabled.
```

| `max_age` value | Behavior |
|----------------|----------|
| `0` | **Disabled.** Every anonymous request hits Drupal. |
| `3600` (1 hour) | Reasonable default for most sites |
| `86400` (1 day) | For mostly static content |
| `-1` | Cache indefinitely (until manual clear) |

**Important:** `max_age: 0` means page caching is effectively disabled even if the module is enabled. This is a common development setting that should be changed for production.

### Dynamic Page Cache

Caches page responses for authenticated users. Personalizes cached pages by using **placeholders** for user-specific content (username, account menu, contextual links).

- No configuration needed — it's either on or off (module enabled/disabled)
- Works automatically with any content that implements `#lazy_builder` pattern
- Significantly reduces processing for authenticated users

### BigPipe

Streams page responses to improve **perceived** load time:
1. Sends the cached/static HTML immediately
2. Streams personalized content as it becomes available
3. User sees the page structure instantly, with personalized bits filling in

- Works with Dynamic Page Cache
- No configuration needed — enabled by having the module active

---

## CSS and JavaScript Aggregation

Combines multiple CSS/JS files into fewer files, reducing HTTP requests.

**Configuration** (`system.performance.yml`):
```yaml
css:
  preprocess: true     # Aggregate CSS files
  gzip: true           # Gzip compressed aggregates
js:
  preprocess: true     # Aggregate JS files
  gzip: true           # Gzip compressed aggregates
```

| Setting | Development | Production |
|---------|------------|------------|
| `css.preprocess` | `false` | `true` |
| `js.preprocess` | `false` | `true` |
| `css.gzip` | `true` | `true` |
| `js.gzip` | `true` | `true` |

**Clearing aggregated files:**
```bash
ddev drush cr                    # Clear all caches (including aggregates)
```

**Note:** During development, disable aggregation for easier debugging. Enable for production.

---

## Fast 404

Returns 404 responses quickly for non-existent **static files** without bootstrapping Drupal.

**Configuration** (`system.performance.yml`):
```yaml
fast_404:
  enabled: true
  paths: '/\.(?:txt|png|gif|jpe?g|css|js|ico|swf|flv|cgi|bat|pl|dll|exe|asp)$/i'
  exclude_paths: '/\/(?:styles|imagecache)\//'
  html: '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL "@path" was not found on this server.</p></body></html>'
```

- **`paths`**: Regex matching file extensions to intercept
- **`exclude_paths`**: Paths to skip (e.g., `/styles/` for image style generation)
- Keep enabled — it prevents unnecessary Drupal bootstraps for missing static assets

---

## Automated Cron

Drupal's automated cron triggers scheduled tasks on page requests.

**Configuration** (`automated_cron.settings.yml`):
```yaml
interval: 10800    # Seconds between cron runs (10800 = 3 hours)
```

### How it works

1. On each page request, Drupal checks when cron last ran
2. If the interval has elapsed, cron runs in the background
3. Cron executes all `hook_cron()` implementations from enabled modules

### Common cron tasks

| Task | Module | Purpose |
|------|--------|---------|
| Search indexing | Search API / Search | Index new/updated content |
| Temp file cleanup | System | Delete expired temporary files |
| Session cleanup | System | Remove expired sessions |
| Content moderation | Content Moderation | Process scheduled transitions |
| Log rotation | Database Logging | Trim old log entries |

### Cron interval recommendations

| Interval | Use case |
|----------|----------|
| `0` | **Disabled.** Use external cron (recommended for production). |
| `3600` (1 hour) | Active sites with search indexing needs |
| `10800` (3 hours) | Reasonable default |
| `21600` (6 hours) | Low-traffic sites |
| `86400` (1 day) | Minimal maintenance needs |

### External cron (production)

For production, disable automated cron (`interval: 0`) and use a system cron job:

```bash
# Add to crontab
*/15 * * * * cd /path/to/project && ddev drush cron
```

External cron is more reliable — automated cron depends on site traffic to trigger.

### Manual cron

```bash
ddev drush cron              # Run cron immediately
ddev drush cron --verbose    # Run with detailed output
```

---

## Development vs Production Settings

| Setting | Development | Production |
|---------|------------|------------|
| `cache.page.max_age` | `0` | `3600` or higher |
| `css.preprocess` | `false` | `true` |
| `js.preprocess` | `false` | `true` |
| `fast_404.enabled` | `true` | `true` |
| Automated cron interval | `10800` | `0` (use external cron) |
| Twig debug | `true` | `false` |
| Twig cache | `false` | `true` |

### Toggling dev/prod settings

```bash
# Development: Disable caching and aggregation
ddev drush config:set system.performance cache.page.max_age 0
ddev drush config:set system.performance css.preprocess 0
ddev drush config:set system.performance js.preprocess 0
ddev drush cr

# Production: Enable caching and aggregation
ddev drush config:set system.performance cache.page.max_age 3600
ddev drush config:set system.performance css.preprocess 1
ddev drush config:set system.performance js.preprocess 1
ddev drush cr
```

**Note:** Twig debug/cache settings are in `services.yml` or `development.services.yml`, not in config sync. See `theming/theming.md` for Twig debug settings.

---

## Cache Clearing

```bash
ddev drush cr                        # Clear ALL caches (nuclear option)
ddev drush cache:rebuild             # Same as cr (alias)
ddev drush cache:tag invalidate TAG  # Invalidate specific cache tags
```

**When to clear cache:**
- After config changes: `ddev drush cr`
- After new Canvas pages/Views: `ddev drush cr` (for component discovery)
- After theme changes: `ddev drush cr`
- After module install/uninstall: Automatic (but `cr` if something seems stale)

---

## Configuration Files

| File | Contents |
|------|----------|
| `config/<site>/system.performance.yml` | Page cache, CSS/JS aggregation, Fast 404 |
| `config/<site>/automated_cron.settings.yml` | Cron interval |

---

## Gotchas

- **`max_age: 0` ≠ caching disabled.** The Page Cache module is still loaded — it just stores nothing. For true no-caching in development, consider using `settings.local.php` overrides.
- **Aggregation breaks during development.** If CSS/JS aggregation is on and you change a file, you must clear cache to regenerate aggregates. Disable during development.
- **Automated cron depends on traffic.** If no one visits the site, cron doesn't run. Use external cron for production.
- **BigPipe requires JavaScript.** If a user has JS disabled, BigPipe falls back to normal rendering. No harm, but no streaming benefit.
- **Cache tags are powerful.** Modules that implement proper cache tags enable fine-grained invalidation instead of full cache clears.

---

## Related Documentation

| Document | Relevance |
|---|---|
| `configuration/site-settings.md` | System settings (related config files) |
| `theming/theming.md` | Twig debug/cache settings |
| `infrastructure/environment-setup.md` | DDEV environment setup |
| `global/drush-config-workflow.md` | Config export after changes |
