# Add disabled flag and active-slot filtering for exposed slots

**Project:** Canvas (Experience Builder)
**Component:** Content templates / Config schema
**Category:** Feature request
**Priority:** Normal
**Canvas Version:** 1.x-dev
**Existing Issue:** Related to [META #3541000](https://www.drupal.org/project/experience_builder/issues/3541000) which explicitly defers exposed slots to post-1.0. This is a foundational building block for the exposed slots infrastructure.

## Problem/Motivation

The exposed slots feature allows content template authors to designate certain slots within a template's component tree as editable by content editors. However, there is currently no mechanism to temporarily disable an exposed slot without removing it entirely from the template configuration.

Template authors need the ability to:

1. **Disable a slot** without losing its configuration (e.g., during development, A/B testing, or when a slot's target field is not yet ready).
2. **Filter slots** at runtime so that only active (non-disabled) slots are used during template rendering, field provisioning, and the Canvas editor UI.

Without a `disabled` flag, the only way to remove a slot from active use is to delete it from the configuration entirely, losing the slot's label, field mapping, and other metadata.

Additionally, `ContentTemplate::build()` currently uses all exposed slots indiscriminately. There is no method to retrieve only the active subset, which is needed by downstream features (merged component trees, auto-provisioned fields, and the Canvas fields API).

## Proposed Resolution

1. **Extend the config schema** in `config/schema/canvas.schema.yml` to add a `disabled` boolean property to the exposed slot schema. Defaults to `FALSE` (slots are active by default).

2. **Add `getActiveExposedSlots()` method** to `src/Entity/ContentTemplate.php` that filters the template's exposed slots, returning only those where `disabled` is `FALSE` (or not set).

3. **Update `build()`** in `ContentTemplate.php` to call `getActiveExposedSlots()` instead of using the raw exposed slots array, ensuring that disabled slots are excluded from rendering.

## Remaining Tasks

- [ ] Code review
- [ ] Test coverage
- [ ] Commit

## User Interface Changes

None directly. The `disabled` flag is a data-layer addition. UI for toggling this flag will be provided by a separate feature (the ExposeSlotDialog in the Canvas React app).

## API Changes

- New public method `ContentTemplate::getActiveExposedSlots()` returns an array of exposed slot configurations that are not disabled.
- The `disabled` key is available in exposed slot configuration arrays.

## Data Model Changes

- **Config schema change:** The exposed slot mapping in `canvas.schema.yml` gains a new optional `disabled` property of type `boolean`. Existing configurations without this property are treated as `disabled: FALSE` (active) for backward compatibility.

## Release Notes Snippet

Exposed slots in content templates now support a `disabled` flag, allowing template authors to deactivate slots without deleting their configuration.
