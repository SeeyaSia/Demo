# Environment & Setup — DDEV Local Development

## Purpose

Developer guide for setting up and working with the DDEV local development environment for Drupal projects. Covers DDEV configuration, database access, PHP settings, settings files, and common workflows.

---

## DDEV Configuration

### Project structure

DDEV configuration lives in the `.ddev/` directory:

| File | Purpose | Editable? |
|------|---------|-----------|
| `.ddev/config.yaml` | Main DDEV config (project name, PHP version, database) | Yes |
| `.ddev/config.local.yaml` | Local overrides (hostname, custom settings) | Auto-generated or manual |
| `.ddev/nginx_full/nginx-site.conf` | Nginx configuration | Rarely needed |
| `.ddev/mutagen/mutagen.yml` | File sync configuration (macOS/Windows) | Optional tuning |
| `.ddev/traefik/` | HTTPS proxy and SSL certificates | Managed by DDEV |

### Key settings

```yaml
# .ddev/config.yaml
name: my-project           # Project name (determines hostname)
type: drupal                # Project type
docroot: web                # Web root directory
php_version: "8.3"          # PHP version
database:
  type: mysql               # mysql, mariadb, or postgres
  version: "8.0"            # Database version
```

### Database defaults

DDEV provides a pre-configured database:

| Setting | Value |
|---------|-------|
| Type | MySQL 8.0 (configurable) |
| Database name | `db` |
| Username | `db` |
| Password | `db` |
| Host | `db` (internal container hostname) |
| Port | `3306` |

Database credentials are automatically injected via `settings.ddev.php`.

---

## Initial Setup

### Prerequisites

1. **DDEV** installed ([ddev.com](https://ddev.com))
2. **Docker** or **Colima** running
3. **Composer** (DDEV includes it inside the container)
4. **Git**

### First-time setup

```bash
# 1. Clone the repository
git clone <repo-url> && cd <project>

# 2. Start DDEV containers
ddev start

# 3. Install Composer dependencies
ddev composer install

# 4. Install Drupal (fresh install)
ddev drush site:install standard --account-name=admin --account-pass=admin -y

# 5. OR import existing config
ddev drush config:import -y

# 6. Clear cache
ddev drush cr
```

### Accessing the site

```bash
# Get the site URL
ddev describe

# Open in browser
ddev launch

# Open admin
ddev launch /admin
```

**URLs:**
- **Site**: `https://<project-name>.ddev.site`
- **Mailpit** (captured emails): `https://<project-name>.ddev.site:8025`
- **phpMyAdmin** (if enabled): `https://<project-name>.ddev.site:8036`

---

## Settings Files

### Settings hierarchy

Drupal loads settings files in order, with later files overriding earlier ones:

1. `web/sites/default/settings.php` — Base settings
2. `web/sites/default/settings.ddev.php` — DDEV overrides (auto-generated)
3. `web/sites/default/settings.local.php` — Developer-specific overrides (optional, gitignored)

### settings.php

The main settings file. Contains:
- Config sync directory path: `../config/<site>`
- Trusted host patterns (for production)
- Database configuration (populated by DDEV or hosting environment)
- Hash salt

### settings.ddev.php (auto-generated)

**Do not edit manually** — DDEV overwrites this file.

DDEV automatically configures:
- **Database credentials**: `db`/`db`/`db` on host `db:3306`
- **Hash salt**: Auto-generated unique value
- **Trusted host patterns**: `['.*']` (wildcard — accepts all hosts in DDEV)
- **Permissions hardening**: Disabled (`skip_permissions_hardening: TRUE`) for easier development
- **Error logging**: Verbose (`system.logging.error_level: verbose`)
- **Mail capture**: Routes all email through Mailpit on port 1025

### settings.local.php (optional)

For developer-specific overrides not committed to git:

```php
<?php
// Disable caching for development
$settings['cache']['bins']['render'] = 'cache.backend.null';
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
$settings['cache']['bins']['page'] = 'cache.backend.null';

// Enable Twig debug
$settings['twig_debug'] = TRUE;
$settings['twig_auto_reload'] = TRUE;
$settings['twig_cache'] = FALSE;
```

---

## Common DDEV Commands

### Project management

```bash
ddev start              # Start containers
ddev stop               # Stop containers
ddev restart            # Restart containers
ddev describe           # Show project info (URLs, ports, database)
ddev poweroff           # Stop all DDEV projects
```

### Drupal commands

```bash
ddev drush <command>    # Run Drush commands
ddev composer <command> # Run Composer commands
```

### Container access

```bash
ddev ssh                # SSH into web container
ddev mysql              # Access MySQL CLI
ddev logs               # View container logs
ddev logs -s db         # View database logs specifically
```

### Database operations

```bash
ddev drush sql:dump > backup.sql     # Export database
ddev import-db --file=backup.sql     # Import database
ddev snapshot                         # Create DDEV snapshot
ddev snapshot restore --latest        # Restore latest snapshot
```

---

## Multisite Routing

For projects with multiple hostnames (e.g., workspace branches), `web/sites/sites.php` maps hostnames to site directories:

```php
// Map dynamic DDEV hostnames to default site
$sites['my-project-branch-1.ddev.site'] = 'default';
$sites['my-project-branch-2.ddev.site'] = 'default';
```

This is typically auto-configured for DDEV setups with dynamic hostname patterns.

---

## DDEV Infrastructure

### Web server (Nginx)

DDEV uses Nginx by default. Custom configuration can be placed in `.ddev/nginx_full/`.

### File synchronization (Mutagen)

On macOS and Windows, DDEV can use Mutagen for faster file sync:

- **Config**: `.ddev/mutagen/mutagen.yml`
- **Sync mode**: Two-way resolved
- **Typically excluded**: `.git`, `/web/sites/default/files`, database snapshots, IDE files

Mutagen significantly improves performance compared to native Docker bind mounts.

### HTTPS (Traefik)

DDEV uses Traefik as a reverse proxy:
- All sites accessible via HTTPS
- Auto-generated SSL certificates for `*.ddev.site`
- No manual SSL configuration needed

### Profiling (xhprof)

Available for performance analysis:
- **Location**: `.ddev/xhprof/`
- **Enable**: `ddev xhprof on`
- **Disable**: `ddev xhprof off`
- **View results**: `https://<project-name>.ddev.site/xhprof/`

### Mailpit (email testing)

All outbound email is captured by Mailpit:
- **URL**: Port 8025 on the DDEV hostname
- **Purpose**: Inspect emails sent by Drupal (password resets, form submissions, notifications)
- No emails leave the development environment

---

## Configuration Files

| File | Contents |
|------|----------|
| `.ddev/config.yaml` | DDEV project configuration |
| `.ddev/config.local.yaml` | Local DDEV overrides |
| `web/sites/default/settings.php` | Drupal settings |
| `web/sites/default/settings.ddev.php` | DDEV-specific settings (auto-generated) |
| `web/sites/default/settings.local.php` | Developer-specific overrides (optional) |
| `web/sites/sites.php` | Multisite hostname routing |

---

## Gotchas

- **Don't edit `settings.ddev.php`.** DDEV regenerates it. Use `settings.local.php` for custom overrides.
- **Trusted hosts in DDEV are `['.*']`.** This is fine for development but must be restricted in production.
- **`skip_permissions_hardening` is DDEV-only.** In production, permissions hardening must be enabled for security.
- **Mutagen sync delays.** File changes may take a moment to sync. If changes don't appear, run `ddev mutagen sync`.
- **Database snapshots vs SQL dumps.** `ddev snapshot` is faster but DDEV-specific. `drush sql:dump` produces portable SQL files.
- **DDEV requires Docker.** Docker Desktop, Colima, or OrbStack must be running before `ddev start`.

---

## Related Documentation

| Document | Relevance |
|---|---|
| `global/development-workflow.md` | Composer workflow, deployment |
| `global/drush-config-workflow.md` | Drush commands, config import/export |
| `infrastructure/developer-tools.md` | Drush, Composer, PHPCS tools |
| `configuration/performance.md` | Cache settings for development vs production |
