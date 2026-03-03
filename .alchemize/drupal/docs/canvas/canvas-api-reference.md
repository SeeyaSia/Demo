# Canvas API Reference

## Purpose

Documents the Canvas HTTP API — both the internal API used by the Canvas editor UI and the external API endpoints used by the `@drupal-canvas/cli` and third-party applications. Covers endpoint inventory, authentication methods, the OpenAPI spec location, and OAuth2 scopes.

## System Overview

Canvas provides a custom HTTP API (NOT JSON:API) for managing its config entities. The API has two tiers:

1. **Internal API** — Used by the Canvas editor's JavaScript UI. Authenticated via Drupal session cookies. Not intended for external use.
2. **External API** — Endpoints marked with `canvas_external_api: true` route option. Authenticated via OAuth2 when the `canvas_oauth` submodule is enabled. Used by `@drupal-canvas/cli`.

Canvas deliberately does not use Drupal's JSON:API module because:
- No pagination needed
- No entity relationship surfacing needed
- Component data is enriched with additional metadata beyond the config entity
- Avoids requiring JSON:API as a dependency

## OpenAPI Specification

The complete API spec lives at: `web/modules/contrib/canvas/openapi.yml` (OpenAPI 3.1.0)

View interactively at: https://editor-next.swagger.io/ (paste the YAML content)

## Authentication

### Session authentication (internal)
All API routes accept Drupal session cookies. This is how the Canvas editor UI communicates with the backend.

### OAuth2 authentication (external)
When `canvas_oauth` is enabled, external API routes also accept OAuth2 Bearer tokens.

**Token request:**
```bash
curl -X POST https://<project-name>.ddev.site/oauth/token \
  -d "grant_type=client_credentials&client_id=CLI_CLIENT_ID&client_secret=CLI_CLIENT_SECRET&scope=canvas:js_component canvas:asset_library"
```

**Using the token:**
```bash
curl https://<project-name>.ddev.site/canvas/api/v0/config/js_component \
  -H "Authorization: Bearer ACCESS_TOKEN"
```

### OAuth2 scopes

| Scope | Permission | Covers |
|-------|-----------|--------|
| `canvas:js_component` | `administer code components` | All code component endpoints |
| `canvas:asset_library` | `administer code components` | All asset library endpoints |

Both scopes are created as dynamic scope config entities when `canvas_oauth` is installed, configured for the Client Credentials grant type.

See `canvas-cli.md` for full OAuth setup instructions.

## External API Endpoints (used by CLI)

These endpoints have `canvas_external_api: true` and support OAuth2 authentication:

### Code Components (`js_component`)

| Method | Endpoint | Description |
|--------|---------|-------------|
| `GET` | `/canvas/api/v0/config/js_component` | List all code components |
| `POST` | `/canvas/api/v0/config/js_component` | Create a new code component |
| `GET` | `/canvas/api/v0/config/js_component/{id}` | Get a specific code component |
| `PATCH` | `/canvas/api/v0/config/js_component/{id}` | Update a code component |
| `DELETE` | `/canvas/api/v0/config/js_component/{id}` | Delete a code component |

### Asset Libraries

| Method | Endpoint | Description |
|--------|---------|-------------|
| `GET` | `/canvas/api/v0/config/asset_library` | List all asset libraries |
| `POST` | `/canvas/api/v0/config/asset_library` | Create a new asset library |
| `GET` | `/canvas/api/v0/config/asset_library/{id}` | Get a specific asset library |
| `PATCH` | `/canvas/api/v0/config/asset_library/{id}` | Update an asset library |
| `DELETE` | `/canvas/api/v0/config/asset_library/{id}` | Delete an asset library |

## Internal API Endpoints (editor UI only)

These are session-authenticated and used by the Canvas editor frontend:

### Auto-saves

| Method | Endpoint | Description |
|--------|---------|-------------|
| `GET` | `/canvas/api/v0/auto-saves/pending` | All current auto-save entries |
| `GET` | `/canvas/api/v0/auto-saves/css/js_component/{id}` | Draft CSS for a code component |
| `GET` | `/canvas/api/v0/auto-saves/css/asset_library/{id}` | Draft CSS for an asset library |
| `GET` | `/canvas/api/v0/auto-saves/js/js_component/{id}` | Draft JS for a code component |
| `GET` | `/canvas/api/v0/auto-saves/js/asset_library/{id}` | Draft JS for an asset library |

### Components

| Method | Endpoint | Description |
|--------|---------|-------------|
| `GET` | `/canvas/api/v0/config/component` | List all component config entities |

### Content Templates

| Method | Endpoint | Description |
|--------|---------|-------------|
| `GET` | `/canvas/api/v0/config/content_template` | List all content templates |
| `GET` | `/canvas/api/v0/config/content_template/{id}` | Get a specific content template |

### Folders

| Method | Endpoint | Description |
|--------|---------|-------------|
| `GET` | `/canvas/api/v0/config/folder` | List all folders |
| `GET` | `/canvas/api/v0/config/folder/{id}` | Get a specific folder |

### Patterns

| Method | Endpoint | Description |
|--------|---------|-------------|
| `GET` | `/canvas/api/v0/config/pattern` | List all patterns |
| `GET` | `/canvas/api/v0/config/pattern/{id}` | Get a specific pattern |

### Config auto-save

| Method | Endpoint | Description |
|--------|---------|-------------|
| Various | `/canvas/api/v0/config/auto-save/{entityTypeId}/{id}` | Auto-save operations for config entities |

## UI Routes

| Route | Description |
|-------|-------------|
| `/admin/appearance/component` | Available components list |
| `/admin/appearance/component/status` | Unavailable components with reasons |
| `/canvas` | Canvas Pages editor (Content > Pages) |

## Change Surface

- `web/modules/contrib/canvas/openapi.yml` — OpenAPI spec (authoritative API documentation)
- `web/modules/contrib/canvas/canvas.routing.yml` — Route definitions (look for `canvas.api.config.*` routes)
- `web/modules/contrib/canvas/modules/canvas_oauth/` — OAuth2 authentication for external API

## Constraints

- The API is versioned at `v0` — it is **not yet considered stable** and may change between Canvas releases
- External API currently covers only code components and asset libraries. PageRegions, ContentTemplates, and Patterns are internal-only
- OAuth2 requires `simple_oauth` module (>=6.0.0) as a dependency of `canvas_oauth`
- The API does not provide pagination — response size is bounded by the number of config entities

## Failure Modes

- **401 Unauthorized**: Missing or expired OAuth token. Request a new token.
- **403 Forbidden**: Token lacks required scope, or the configured OAuth consumer user lacks permissions.
- **404 Not Found**: `canvas_oauth` not enabled, or incorrect endpoint path.
- **JSON:API read-only conflict**: If JSON:API read-only mode is enabled, Canvas API write operations may be blocked. Check status report.
