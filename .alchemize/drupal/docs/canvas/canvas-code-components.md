# Canvas Code Components

## Purpose

Documents code components (JS/JSX components) — the third component type in Canvas alongside SDCs and Blocks. Covers how code components work, their file structure, Preact/React rendering, props via JSON Schema, available packages, ESLint validation rules, and the full CLI-driven development workflow.

## System Overview

Code components are client-side rendered components written in JSX (Preact with React compatibility). They can be created in the Canvas browser-based code editor or managed externally via the `@drupal-canvas/cli` tool. Each code component is stored as a `JavaScriptComponent` config entity (`canvas.js_component.*`).

Code components complement SDC components (server-side Twig) and block components (server-side PHP) by allowing dynamic, interactive UI elements without writing Drupal module code.

## Architecture

### JavaScriptComponent config entity

Each code component is stored as a `canvas.js_component.*` config entity containing:
- **JSX source code** — The component's JavaScript/JSX source
- **CSS** — Component-specific styles
- **Props schema** — JSON Schema defining inputs (same format as SDC props)
- **Status** — `true` (exposed) or `false` (internal/draft)

### Internal vs Exposed

| Status | Meaning | Editor access |
|--------|---------|--------------|
| `status: false` | **Internal** — work in progress | Cannot be used by content editors |
| `status: true` | **Exposed** — published and available | Appears in Canvas component library |

When a code component is set to `status: true`, Canvas automatically creates a corresponding `Component` config entity (`canvas.component.js.*`) so it appears in the editor.

### Working copy (auto-save)

Code components have an auto-save mechanism. Edits in the Canvas UI create a "working copy" that overrides the published version in the editor, without modifying the actual config entity until explicitly saved/published.

## Component File Structure (for CLI)

When working with `@drupal-canvas/cli`, each code component is a directory with three files:

```
canvas-components/
├── my_hero/
│   ├── component.yml    # Metadata: name, props schema, description
│   ├── index.jsx        # Preact/React component source
│   ├── index.css        # Component styles (optional)
│   └── dist/            # Build output (auto-generated, do not edit)
├── my_card/
│   ├── component.yml
│   ├── index.jsx
│   ├── index.css
│   └── dist/
└── dist/                # Top-level Tailwind CSS build output
```

### `component.yml` (metadata)

```yaml
name: My Hero
description: A hero banner with heading and background image.
props:
  type: object
  required:
    - heading
  properties:
    heading:
      type: string
      title: Heading
      examples:
        - "Welcome to our site"
    subheading:
      type: string
      title: Subheading
      examples:
        - "Discover what we offer"
    image:
      $ref: "json-schema-definitions://canvas.module/image"
      title: Background Image
```

Props use the same JSON Schema format as SDC components. The same eligibility rules apply: every prop needs `title`, required props need `examples`.

### `index.jsx` (component source)

```jsx
const MyHero = ({ heading = "Welcome", subheading, image }) => {
  return (
    <section className="hero bg-primary text-white p-5">
      <h1 className="display-4">{heading}</h1>
      {subheading && <p className="lead">{subheading}</p>}
    </section>
  );
};

export default MyHero;
```

**Requirements:**
- Must provide a **default export** (named exports are forbidden)
- Must NOT use relative imports
- Rendered with **Preact** (React compatibility layer enabled)

### `index.css` (optional styles)

```css
.hero {
  min-height: 400px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}
```

## Available Packages

### Bundled packages (importable by name)

These are pre-included in the Canvas runtime and can be imported directly:

| Package | Import example | Purpose |
|---------|---------------|---------|
| **`drupal-canvas`** | `import { cn, Image } from 'drupal-canvas'` | Primary utility package (see below) |
| **`clsx`** | `import clsx from 'clsx'` | Conditionally construct className strings |
| **`class-variance-authority`** | `import { cva } from 'class-variance-authority'` | Type-safe component variant definitions |
| **`drupal-jsonapi-params`** | `import { DrupalJsonApiParams } from 'drupal-jsonapi-params'` | JSON:API parameter builder |
| **`swr`** | `import useSWR from 'swr'` | Data fetching and caching library |
| **`tailwind-merge`** | `import { twMerge } from 'tailwind-merge'` | Merge Tailwind classes without conflicts |
| **`preact`** | `import { h } from 'preact'` | Core rendering library (React compat enabled) |
| **`preact/hooks`** | `import { useState } from 'preact/hooks'` | Hooks API |
| **`react`** | `import React from 'react'` | React compatibility layer (maps to Preact) |
| **`react-dom`** | `import ReactDOM from 'react-dom'` | React DOM compatibility |

**Tailwind CSS 4** is available globally (no import needed) — use utility classes directly in JSX.

**`@drupal-api-client/json-api-client`** — Allowed but **deprecated**. Import `JsonApiClient` from `drupal-canvas` instead.

### The `drupal-canvas` package

The primary utility package. All exports:

| Export | Type | Purpose |
|--------|------|---------|
| **`FormattedText`** | Component | Safely render trusted HTML via `dangerouslySetInnerHTML` |
| **`Image`** | Component | Optimized image component (replaces deprecated `next-image-standalone`) |
| **`cn`** | Function | Tailwind CSS class combining utility (uses clsx + tailwind-merge) |
| **`JsonApiClient`** | Class | Pre-configured JSON:API client for the current Drupal site |
| **`getPageData`** | Function | Fetch page data from Drupal |
| **`getSiteData`** | Function | Fetch site-level data from Drupal |
| **`getNodePath`** | Function | Get a node's path from JSON:API data |
| **`sortMenu`** | Function | Sort JSON:API menu items |
| **`sortLinksetMenu`** | Function | Sort linkset menu items (renamed from drupal-utils `sortMenu`) |

```jsx
// Example: common imports
import { cn, FormattedText, Image, JsonApiClient } from 'drupal-canvas';
```

### External packages via esm.sh

Import any npm package at runtime using `esm.sh`:

```jsx
import { motion } from 'https://esm.sh/motion@12.23.26/react?external=react,react-dom';
```

**Important:** Always append `?external=react,react-dom` when importing packages that depend on React to prevent loading duplicate React instances.

### Sibling component imports

Import other code components using the `@/components/` alias:

```jsx
import MyButton from '@/components/my_button';
```

## ESLint Validation Rules

Code components are validated by `@drupal-canvas/eslint-config`. The `validate` command in the CLI enforces these rules:

### Required rules (will fail validation)

1. **Default export required** — Every component must have a `default` export
2. **No relative imports** — Cannot use `./` or `../` imports
3. **Import restrictions** — Only these import patterns are allowed:
   - `@/components/<component_name>` — sibling components
   - Bundled packages (`clsx`, `swr`, `drupal-canvas`, `class-variance-authority`, `tailwind-merge`, `drupal-jsonapi-params`, `preact`, `react`, `react-dom`)
   - Full URLs (e.g., `https://esm.sh/...`)
   - **Deprecated** (auto-fixable): `@/lib/drupal-utils`, `@/lib/utils`, `@/lib/jsonapi-utils`, `@/lib/FormattedText`, `next-image-standalone` — all moved to `drupal-canvas` package
4. **Flat directory structure** — Components must be in a single directory, no nesting
5. **Prop name casing** — Prop machine names in `component.yml` must be camelCase versions of prop titles

### Running validation

```bash
# Validate all components
npx canvas validate --all --yes

# Validate with auto-fix
npx canvas validate --all --yes --fix
```

## CLI Development Workflow

See `canvas-cli.md` for complete CLI reference. Summary workflow:

```bash
# 1. Create scaffold (use underscores in names)
npx canvas scaffold --name my_hero --dir ./canvas-components

# 2. Edit index.jsx, component.yml, index.css

# 3. Validate
npx canvas validate --components my_hero --yes

# 4. Build (compile + Tailwind)
npx canvas build --components my_hero --yes

# 5. Upload to Drupal
npx canvas upload --components my_hero --yes

# 6. Export Drupal config to version-control the new component
ddev drush cex -y
```

## Config Storage

Code component config entities are stored at:
- `config/<site>/canvas.js_component.<machine_name>.yml` — The JavaScriptComponent entity
- `config/<site>/canvas.component.js.<machine_name>.yml` — The corresponding Component entity (auto-created when exposed)

## Change Surface

- `canvas-components/*/index.jsx` — Component source code
- `canvas-components/*/component.yml` — Component metadata and props
- `canvas-components/*/index.css` — Component styles
- `canvas-components/*/dist/` — Build output (gitignored, auto-generated)
- `config/<site>/canvas.js_component.*` — Config entities (after upload + export)
- `config/<site>/canvas.component.js.*` — Component config entities (auto-generated when exposed)

## Failure Modes

- **Missing default export**: Build and validate will fail. ESLint rule enforces this.
- **Relative imports**: Build will fail. Use `@/components/` or full URLs.
- **Unsupported prop type**: If the JSON Schema prop can't be mapped to a Drupal field type, the component can't be exposed.
- **OAuth not configured**: Upload/download requires `canvas_oauth`. See `canvas-cli.md`.
- **Config not exported after upload**: If you upload via CLI but don't `ddev drush cex -y`, the config entity won't be in version control and will be lost on next `cim`.

## Constraints

- Code components render **client-side only** (Preact). Server-side rendering (SSR) via Node.js API is experimental.
- Code components cannot access Drupal's server-side render pipeline (no Twig, no preprocess hooks).
- The Canvas browser editor and the CLI are two separate workflows. Changes in one don't auto-sync to the other — use `download`/`upload` to synchronize.
- Code components share the same JSON Schema prop format as SDCs, but define props in `component.yml` rather than `*.component.yml`.
