# Canvas CLI (`@drupal-canvas/cli`)

## Purpose

Complete reference for the `@drupal-canvas/cli` tool (v0.6.2) — the command-line interface agents use to create, build, validate, download, and upload Drupal Canvas code components. Covers installation, OAuth2 authentication setup, configuration, all commands with flags, and integration with DDEV.

## System Overview

The Canvas CLI manages **code components** (JS/JSX components) outside the browser-based Canvas editor. It communicates with the Drupal site's Canvas HTTP API using OAuth2 authentication provided by the `canvas_oauth` submodule.

**Workflow:** `scaffold` → develop locally → `validate` → `build` → `upload` to Drupal site. Or: `download` from site → edit locally → `upload` back.

## Prerequisites

### Drupal-side requirements
1. **`canvas_oauth` submodule** must be enabled (ships with `canvas` module)
2. **`simple_oauth` module** (>=6.0.0) must be installed: `composer require drupal/simple_oauth:^6`
3. RSA key pair must be generated and paths configured in Simple OAuth settings

### Server-side requirements
- Node.js (check `.nvmrc` if present)
- npm or npx

### Installation
```bash
npm install @drupal-canvas/cli
# Or use directly via npx:
npx canvas --help
```

Verified installed version: **0.6.2**

## OAuth2 Setup (Required for `download` and `upload`)

### Step 1: Generate RSA key pair
```bash
openssl genrsa -out private.key 2048
openssl rsa -in private.key -pubout > public.key
```
Store keys **outside** the document root. Configure paths at `/admin/config/people/simple_oauth`.

### Step 2: Enable `canvas_oauth`
```bash
ddev drush en canvas_oauth -y
ddev drush cex -y
```
This creates two OAuth2 scopes as dynamic scope config entities:

| Scope | Permission |
|-------|-----------|
| `canvas:js_component` | `administer code components` |
| `canvas:asset_library` | `administer code components` |

### Step 3: Create an OAuth consumer
1. Visit `/admin/config/services/consumer`
2. Create a new consumer (or edit existing)
3. Set a **Client ID** (e.g., `canvas_cli`) and **Client Secret**
4. Enable **Client Credentials** grant type
5. Select scopes: `canvas_js_component` and `canvas_asset_library` (entity IDs, not scope labels)
6. Configure an **authenticated user** as the action author (NOT anonymous)
7. Set access token expiration (15–60 minutes recommended)

**Typical consumer setup:** Create a consumer named `canvas_cli` with both scopes and `client_credentials` grant type.

### Step 4: Test authentication

**Important:** When using `curl` in a shell, avoid `&` characters in `-d` strings — the shell interprets them as background operators. Use `--data-urlencode` instead:

```bash
# Request a token (use --data-urlencode to avoid shell & issues)
ddev exec curl -s -X POST https://<project-name>.ddev.site/oauth/token \
  --data-urlencode "grant_type=client_credentials" \
  --data-urlencode "client_id=canvas_cli" \
  --data-urlencode "client_secret=YOUR_SECRET" \
  --data-urlencode "scope=canvas:js_component canvas:asset_library"

# Use the token
ddev exec curl -s https://<project-name>.ddev.site/canvas/api/v0/config/js_component \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

## Configuration

Settings are applied in order of precedence (highest to lowest):

1. **Command-line arguments** (e.g., `--site-url`)
2. **Environment variables** (e.g., `CANVAS_SITE_URL`)
3. **Project `.env` file** (in working directory)
4. **Global `~/.canvasrc` file** (in home directory)

### Configuration parameters

| CLI argument | Environment variable | Description |
|-------------|---------------------|-------------|
| `--site-url` | `CANVAS_SITE_URL` | Base URL of the Drupal site |
| `--client-id` | `CANVAS_CLIENT_ID` | OAuth client ID |
| `--client-secret` | `CANVAS_CLIENT_SECRET` | OAuth client secret |
| `--dir` | `CANVAS_COMPONENT_DIR` | Directory where code components are stored locally |
| `--scope` | `CANVAS_SCOPE` | Space-separated OAuth scopes (default: `"canvas:js_component canvas:asset_library"`) |

### Example `.env` file
```bash
CANVAS_SITE_URL=https://<project-name>.ddev.site
CANVAS_CLIENT_ID=canvas_cli
CANVAS_CLIENT_SECRET=canvas-cli-secret-2024
CANVAS_COMPONENT_DIR=canvas-components
```

The `.env.example` template is at `.env.example` (project root). The `.env` file is gitignored.

## Commands

### `canvas download`

Download code components from the Drupal site to local filesystem.

```bash
npx canvas download [options]
```

| Flag | Description |
|------|-------------|
| `-c, --components <names>` | Download specific component(s) by machine name (comma-separated) |
| `--all` | Download all components |
| `-y, --yes` | Skip all confirmation prompts (non-interactive / CI mode) |
| `--skip-overwrite` | Skip components that already exist locally |
| `--skip-css` | Skip global CSS download |
| `--css-only` | Download only global CSS (skip components) |
| `-d, --dir <directory>` | Component directory |
| `--client-id <id>` | OAuth client ID |
| `--client-secret <secret>` | OAuth client secret |
| `--site-url <url>` | Site URL |
| `--scope <scope>` | OAuth scope |

**Mutually exclusive:** `--components` and `--all`; `--skip-css` and `--css-only`.

**Agent usage examples:**
```bash
# Download all components non-interactively
npx canvas download --all --yes

# Download specific components, skip existing
npx canvas download --components hero,card --yes --skip-overwrite

# Download only global CSS
npx canvas download --css-only --yes
```

### `canvas scaffold`

Create a new code component scaffold with starter files.

```bash
npx canvas scaffold [options]
```

| Flag | Description |
|------|-------------|
| `-n, --name <n>` | Machine name for the new component |
| `-d, --dir <directory>` | Directory to create component in |

**Creates:** `<name>/component.yml`, `<name>/index.jsx`, `<name>/index.css`

**Important:** Component names must use **underscores**, not hyphens (`my_hero`, not `my-hero`). The scaffold creates a directory matching the machine name.

**Agent usage:**
```bash
npx canvas scaffold --name my_hero --dir ./canvas-components
```

### `canvas build`

Build (compile) local components and Tailwind CSS assets.

```bash
npx canvas build [options]
```

| Flag | Description |
|------|-------------|
| `-c, --components <names>` | Build specific component(s) (comma-separated) |
| `--all` | Build all components |
| `-y, --yes` | Skip confirmation prompts |
| `--no-tailwind` | Skip Tailwind CSS building |
| `-d, --dir <directory>` | Component directory |

**Output:** Each component gets a `dist/` directory with compiled output. A top-level `dist/` directory is created for Tailwind CSS assets.

**Agent usage:**
```bash
# Build all components for CI
npx canvas build --all --yes

# Build specific component without Tailwind
npx canvas build --components hero --no-tailwind --yes
```

### `canvas validate`

Validate local components using ESLint with `@drupal-canvas/eslint-config` rules.

```bash
npx canvas validate [options]
```

| Flag | Description |
|------|-------------|
| `-c, --components <names>` | Validate specific component(s) (comma-separated) |
| `--all` | Validate all components |
| `-y, --yes` | Skip confirmation prompts |
| `--fix` | Apply automatic fixes for linting issues |
| `-d, --dir <directory>` | Component directory |

**Key validation rules enforced:**
- Must provide a default export (named exports forbidden)
- No relative imports (use `@/components/name`, `@/lib/name`, bundled packages, or full URLs)
- Component directory structure: flat, one folder per component with `index.jsx`, `component.yml`, optional `index.css`
- Prop machine names in `component.yml` must be camelCase versions of prop titles

**Agent usage:**
```bash
# Validate all components with auto-fix
npx canvas validate --all --yes --fix

# Validate specific component
npx canvas validate --components hero --yes
```

### `canvas upload`

Build, validate, and upload local components to the Drupal site. Automatically runs `build` and `validate` before uploading.

```bash
npx canvas upload [options]
```

| Flag | Description |
|------|-------------|
| `-c, --components <names>` | Upload specific component(s) (comma-separated) |
| `--all` | Upload all components |
| `-y, --yes` | Skip confirmation prompts |
| `--no-tailwind` | Skip Tailwind CSS build and upload |
| `--skip-css` | Skip global CSS upload |
| `--css-only` | Upload only global CSS (skip components) |
| `-d, --dir <directory>` | Component directory |
| `--client-id <id>` | OAuth client ID |
| `--client-secret <secret>` | OAuth client secret |
| `--site-url <url>` | Site URL |
| `--scope <scope>` | OAuth scope |

**Agent usage:**
```bash
# Upload all components non-interactively
npx canvas upload --all --yes

# Upload specific components without Tailwind
npx canvas upload --components hero,card --yes --no-tailwind

# Upload only global CSS
npx canvas upload --css-only --yes
```

## Typical Agent Workflow

```bash
# 1. Set up configuration (one-time)
#    .env file already exists at project root with credentials

# 2. Download existing components from site
npx canvas download --all --yes

# 3. Create a new component (use underscores in names)
npx canvas scaffold --name my_component --dir ./canvas-components

# 4. Edit component files (index.jsx, component.yml, index.css)

# 5. Validate
npx canvas validate --components my_component --yes

# 6. Build locally (test compilation)
npx canvas build --components my_component --yes

# 7. Upload to site (runs build + validate automatically before uploading)
npx canvas upload --components my_component --yes

# 8. Export config to capture the new component config entity
ddev drush cex -y
```

## Change Surface

- `.env` — CLI configuration (gitignored; `.env.example` is the committed template)
- `canvas-components/` — Local code component source files (`CANVAS_COMPONENT_DIR`)
- `canvas-components/*/dist/` — Build output (gitignored, do not edit)
- `config/<site>/canvas.js_component.*` — Code component config entities (after upload + config export)
- `config/<site>/canvas.asset_library.*` — Asset library config entities (after global CSS upload + config export)

## Failure Modes

- **OAuth not configured**: `download` and `upload` will fail with 401/403. Ensure `canvas_oauth` is enabled and consumer is configured.
- **Wrong scopes**: If consumer doesn't have `canvas_js_component` scope, code component operations fail. Scope entity IDs use underscores (`canvas_js_component`), not colons.
- **Token expired**: The CLI handles token acquisition automatically, but `curl` tests need a fresh token. Default expiry is configurable per consumer.
- **Missing Node.js**: CLI commands require Node.js runtime.
- **Build failures**: Invalid JSX, missing default export, or relative imports will cause `build`/`validate` to fail.
- **Component name with hyphens**: `scaffold --name my-hero` creates a directory `my-hero` but `component.yml` uses `my_hero` as `machineName`. Rename the directory to match: use underscores in names.

## Constraints

- The CLI manages only **code components** (JS/JSX). It does not manage SDC components, block components, Canvas Pages, or PageRegions.
- The CLI requires network access to the Drupal site for `download` and `upload` commands. `scaffold`, `build`, and `validate` work offline.
- `build` needs site URL access for Tailwind CSS generation (fetches theme config). Use `--no-tailwind` for fully offline builds.
