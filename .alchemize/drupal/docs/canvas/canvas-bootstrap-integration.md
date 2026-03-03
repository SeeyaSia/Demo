# Canvas Bootstrap Integration

## Purpose

Documents how the three-way integration between `canvas_bootstrap` module, `alchemize_forge` theme, and `bootstrap_barrio` base theme works. Covers theme-aware SDC deduplication, the form UI grouping system, the hooks involved, and why `canvas_bootstrap` must remain enabled despite its components being auto-disabled.

## System Overview

The Canvas Bootstrap integration solves a key problem: both the `canvas_bootstrap` module and the `alchemize_forge` theme provide SDC components with **identical machine names** (accordion, button, card, etc.). Without deduplication, editors would see duplicates in the Canvas component library.

The solution has two parts:
1. **`ThemeAwareSingleDirectoryComponentDiscovery`** — A custom discovery class that auto-disables module SDCs when the active theme provides components with the same machine name.
2. **`CanvasBootstrapFormHooks`** — Form alter hooks that organize component prop forms into tabbed UI groups defined in `component.yml` metadata.

## Theme-Aware SDC Deduplication

### How it works

The `canvas_bootstrap` module replaces Canvas's default SDC discovery with `ThemeAwareSingleDirectoryComponentDiscovery`:

```
hook_canvas_component_source_alter()
  → Replaces SDC discovery class
    → ThemeAwareSingleDirectoryComponentDiscovery::checkRequirements()
      → If a module SDC has the same machine name as an active theme SDC
        → Throws ComponentDoesNotMeetRequirementsException
        → Module SDC is auto-disabled
```

**Source:** `web/modules/contrib/canvas_bootstrap/src/ComponentSource/ThemeAwareSingleDirectoryComponentDiscovery.php`

### Discovery logic

1. Get the active theme (Bootstrap Forge) and its base theme chain (Bootstrap Barrio)
2. Discover all SDC plugins from themes and modules
3. Group SDCs by machine name
4. For each group with both theme and module SDCs:
   - Prefer the **most-specific theme** component (child theme > parent theme)
   - Mark module components with the same name as "overridden"
5. When Canvas checks requirements for an overridden module SDC → it fails with "Overridden by an active theme component with the same machine name"

### Current deduplication results

| Machine name | alchemize_forge (active) | canvas_bootstrap (disabled) |
|-------------|-------------------------|---------------------------|
| accordion | `sdc.alchemize_forge.accordion` ✅ | `sdc.canvas_bootstrap.accordion` ❌ |
| accordion-container | `sdc.alchemize_forge.accordion-container` ✅ | `sdc.canvas_bootstrap.accordion-container` ❌ |
| blockquote | `sdc.alchemize_forge.blockquote` ✅ | `sdc.canvas_bootstrap.blockquote` ❌ |
| button | `sdc.alchemize_forge.button` ✅ | `sdc.canvas_bootstrap.button` ❌ |
| card | `sdc.alchemize_forge.card` ✅ | `sdc.canvas_bootstrap.card` ❌ |
| column | `sdc.alchemize_forge.column` ✅ | `sdc.canvas_bootstrap.column` ❌ |
| heading | `sdc.alchemize_forge.heading` ✅ | `sdc.canvas_bootstrap.heading` ❌ |
| image | `sdc.alchemize_forge.image` ✅ | `sdc.canvas_bootstrap.image` ❌ |
| link | `sdc.alchemize_forge.link` ✅ | `sdc.canvas_bootstrap.link` ❌ |
| paragraph | `sdc.alchemize_forge.paragraph` ✅ | `sdc.canvas_bootstrap.paragraph` ❌ |
| row | `sdc.alchemize_forge.row` ✅ | `sdc.canvas_bootstrap.row` ❌ |
| wrapper | `sdc.alchemize_forge.wrapper` ✅ | `sdc.canvas_bootstrap.wrapper` ❌ |

Bootstrap Barrio's components (`badge`, `container`, `div`, `figure`, `modal`, `teaser`, `toasts`) do NOT collide — they have unique names not provided by `canvas_bootstrap`, so they remain active.

## Form UI Groups (`canvas_bootstrap.ui_groups`)

### What it does

Component prop forms in the Canvas editor can have many props, making them unwieldy. The `canvas_bootstrap` module reads a `canvas_bootstrap.ui_groups` key from component metadata and reorganizes the flat prop form into **vertical tabs** with **fieldset subgroups**.

### Hook chain

```
hook_form_component_instance_form_alter()
  → Read component ID from form tree
  → Load component.yml metadata
  → Extract canvas_bootstrap.ui_groups key
  → Reorganize form into vertical_tabs + fieldsets
```

**Source:** `web/modules/contrib/canvas_bootstrap/src/Hook/CanvasBootstrapFormHooks.php`

### YAML syntax

Add to any SDC's `component.yml`:

```yaml
canvas_bootstrap:
  ui_groups:
    GroupTabLabel:              # Becomes a vertical tab
      weight: 10               # Tab ordering
      groups:                  # Subgroups within the tab
        SubgroupLabel:         # Becomes a fieldset
          weight: 0
          fields:              # Props to move into this fieldset
            - prop_name_1
            - prop_name_2
        AnotherSubgroup:
          weight: 10
          fields:
            - prop_name_3
    AnotherTab:
      weight: 20
      groups:
        OnlyGroup:
          weight: 0
          fields:
            - prop_name_4
```

### Real example: Card component

The card component (`web/themes/contrib/alchemize_forge/components/card/card.component.yml`) organizes its 40+ props into 4 tabs:

| Tab (weight) | Subgroup | Props |
|-------------|----------|-------|
| **Card** (10) | Structure | `image_rounding`, `image_wrapper_class`, `reverse_order`, `show_header/image/footer`, `body_orientation*` |
| | Style | `position`, `card_rounding`, `border_color`, `bg_color`, `card_class` |
| **Header** (30) | Header | `header_class` |
| **Body** (40) | Body | `body_class` |
| | Flex | All `body_justify_content_*` and `body_align_items_*` responsive props |
| **Footer** (50) | Footer | `footer_class` |

Without UI groups, all 40+ card props would display in one flat list. With groups, they're organized into logical, collapsible sections.

### Group key generation

Group keys are auto-generated from labels:
- Tab key: `canvas_bootstrap_group_` + lowercase alphanumeric label (e.g., `canvas_bootstrap_group_card`)
- Subgroup key: `canvas_bootstrap_group_` + tab_subgroup (e.g., `canvas_bootstrap_group_card_structure`)

## Why `canvas_bootstrap` Must Stay Enabled

Even though all 12 of its SDC components are auto-disabled, the module provides two essential services:

1. **`ThemeAwareSingleDirectoryComponentDiscovery`** — Without this, both `canvas_bootstrap` AND `alchemize_forge` components would appear, causing duplicates
2. **Form UI grouping hooks** — Without this, component forms lose their organized tab structure

**Do NOT uninstall `canvas_bootstrap`.** The module's components being disabled is the **intended behavior** — the module's value is in its PHP code, not its SDC components.

## Concrete Examples

### Discovery class registration
```php
// web/modules/contrib/canvas_bootstrap/src/Hook/CanvasBootstrapFormHooks.php
#[Hook('canvas_component_source_alter')]
public function canvasComponentSourceAlter(array &$definitions): void {
    if (isset($definitions['sdc'])) {
        $definitions['sdc']['discovery'] = ThemeAwareSingleDirectoryComponentDiscovery::class;
    }
}
```

### Component metadata path resolution
The form hooks resolve component metadata from both theme and module paths:
```
{theme_path}/components/{name}/{name}.component.yml
{module_path}/components/{name}/{name}.component.yml
```

## Change Surface

- `web/modules/contrib/canvas_bootstrap/src/ComponentSource/ThemeAwareSingleDirectoryComponentDiscovery.php` — Deduplication logic
- `web/modules/contrib/canvas_bootstrap/src/Hook/CanvasBootstrapFormHooks.php` — Form alter hooks
- `web/themes/contrib/alchemize_forge/components/*/*.component.yml` — `canvas_bootstrap.ui_groups` key in component metadata
- `config/<site>/canvas.component.sdc.canvas_bootstrap.*` — Disabled component config entities

## Failure Modes

- **Duplicate components**: If `canvas_bootstrap` is uninstalled, its SDC components would reappear (as module-only SDCs) without the deduplication filter. And if `alchemize_forge` components exist too, both would show.
- **`canvas_bootstrap` uninstalled**: Deduplication and form groups stop working. Components appear duplicated, prop forms lose organization.
- **Theme switch**: If `alchemize_forge` is no longer the default theme, the deduplication changes. A theme without matching component names won't trigger deduplication, so `canvas_bootstrap` components would become active.
- **Component regeneration needed**: After theme changes, run the capability script: `ddev drush php:script .alchemize/drupal/capabilities/canvas-regenerate-components.drush.php` (or manually: `ddev drush php-eval "\Drupal::service('Drupal\canvas\ComponentSource\ComponentSourceManager')->generateComponents();"` followed by `ddev drush cr`).

## Notes for Future Changes

- **Adding UI groups to a component**: Add the `canvas_bootstrap.ui_groups` key to the component's `component.yml`. Cache rebuild is needed.
- **Custom components**: If creating new SDC components in Bootstrap Forge, consider adding `canvas_bootstrap.ui_groups` for any component with more than ~5 props.
- **Theme switching**: If ever switching away from Bootstrap Forge, the deduplication logic will automatically adjust — it reads the active theme dynamically.
