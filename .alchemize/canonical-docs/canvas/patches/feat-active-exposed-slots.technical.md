# Add Disabled Flag and Active-Slot Filtering for Exposed Slots -- Technical Specification

## Summary

This branch adds a `disabled` boolean to the exposed slot configuration schema and introduces a `getActiveExposedSlots()` method on `ContentTemplate` that filters out disabled slots. The `build()` method is updated to use only active slots during rendering. This is a foundational piece for the broader exposed slots infrastructure, enabling downstream features to reliably work with only the slots that template authors intend to be active.

## Branch

`local/feat/active-exposed-slots` based on `origin/1.x`

## Files Changed

| File | Change Type | Description |
|------|-------------|-------------|
| `config/schema/canvas.schema.yml` | Modified | Adds `disabled` boolean property to exposed slot schema |
| `src/Entity/ContentTemplate.php` | Modified | Adds `getActiveExposedSlots()` method, updates `build()` to use it |

## Detailed Changes

### `config/schema/canvas.schema.yml`

The exposed slot mapping schema is extended with a new property:

```yaml
disabled:
  type: boolean
  label: 'Whether this exposed slot is disabled'
```

This property is added to the existing exposed slot schema mapping. It is optional -- when absent, the slot is treated as active (`disabled: FALSE`). This ensures backward compatibility with existing template configurations that were created before this flag existed.

### `src/Entity/ContentTemplate.php`

**New method: `getActiveExposedSlots()`**

```php
public function getActiveExposedSlots(): array
```

- Retrieves the full list of exposed slots from the template configuration.
- Filters out any slot where `disabled` is `TRUE`.
- Returns the remaining active slots as an associative array, preserving the original slot keys.
- Slots without a `disabled` key are included (treated as active by default).

**Modified method: `build()`**

- The `build()` method previously accessed the raw exposed slots array directly.
- It now calls `$this->getActiveExposedSlots()` to get only active slots.
- This ensures that disabled slots are excluded from the rendering pipeline without requiring every downstream consumer to implement its own filtering logic.

## Testing

### Manual Verification

1. Create a content template with two or more exposed slots.
2. Set one slot's `disabled` property to `TRUE` in the configuration (via config edit or drush).
3. Call `$template->getActiveExposedSlots()` and verify only the non-disabled slots are returned.
4. Render the template and verify the disabled slot is not included in the output.
5. Remove the `disabled` key from a slot's configuration and verify it defaults to active.

### Automated Tests

- **Unit test** for `getActiveExposedSlots()`:
  - Template with no exposed slots returns empty array.
  - Template with all slots active returns all slots.
  - Template with one disabled slot filters it out.
  - Template with slots missing the `disabled` key treats them as active.
- **Kernel test** for config schema validation: verify that a template with `disabled: true` passes schema validation.
- **Functional test** for `build()`: verify that a disabled slot's content is not rendered.

## Dependencies

None. This branch has no dependencies on other feature branches. It is itself a dependency for `feat/merged-component-tree` and `feat/auto-provision-slot-fields`.
