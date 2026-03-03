# Allow Optional Fields to Match Required Component Props -- Technical Specification

## Summary

This branch removes two overly restrictive `isRequired()` guard clauses from `JsonSchemaFieldInstanceMatcher` that prevented optional Drupal field instances from appearing as suggestions when mapping to required component props. The Canvas rendering pipeline already handles NULL/empty values gracefully, making these guards unnecessary. The net result is a 7-line addition and 8-line removal, yielding a slightly smaller and more permissive matcher.

## Branch

`local/fix/optional-field-mapping` based on `origin/1.x`

## Files Changed

| File | Change Type | Description |
|------|-------------|-------------|
| `src/ShapeMatcher/JsonSchemaFieldInstanceMatcher.php` | Modified | Removed two `isRequired()` guard clauses (+7/-8) |

## Detailed Changes

### `src/ShapeMatcher/JsonSchemaFieldInstanceMatcher.php`

**Guard 1 (~line 522):**

Before:
```php
// Skip optional fields for required props
if ($prop_is_required && !$field_definition->isRequired()) {
  continue;
}
```

After: The entire conditional block is removed. Optional fields are now evaluated by the same matching logic as required fields.

**Guard 2 (~line 847):**

Before:
```php
if ($prop_is_required && !$field_definition->isRequired()) {
  return [];
}
```

After: The early return is removed. The method proceeds to evaluate the field regardless of its required status.

**Rationale:**

Both guards assumed that mapping an optional (potentially NULL) field to a required prop would cause rendering failures. In practice, the rendering pipeline handles this case:

1. `StructuredData\Evaluator` returns NULL for empty fields.
2. Component render functions treat NULL props as empty strings or skip rendering.
3. Twig templates use default filters (`{{ content|default('') }}`).

The original issue #3541361 recognized this for `type: object` props (images/videos) and removed the guard for that specific case. This change generalizes the fix to all prop types.

**Impact on match results:**

- Before: A content type with 5 required fields and 3 optional fields would suggest at most 5 fields for a required prop.
- After: All 8 fields are evaluated, and any that structurally match the prop schema are suggested.

## Testing

### Manual Verification

1. Create a content type `article` with:
   - Required field: `title` (string)
   - Optional field: `body` (text_long, not required)
   - Optional field: `field_subtitle` (string, not required)
2. Create a Canvas template for `article`.
3. Add a component with a required `content` prop (string type).
4. Open the field mapping suggestions for the `content` prop.
5. Verify that `body` and `field_subtitle` appear in the suggestion list alongside `title`.
6. Map `body` to `content`, save, and verify the template renders correctly:
   - With `body` populated: renders body content.
   - With `body` empty: renders empty string or nothing (no error).

### Automated Testing

- Run the existing shape matcher test suite to ensure no regressions:
  ```
  phpunit --filter=JsonSchemaFieldInstanceMatcherTest
  ```
- Verify that the test for #3541361 (object-type optional matching) still passes.
- Consider adding a new test case that maps an optional text field to a required string prop and asserts the match is found.

## Dependencies

None. This is a standalone change to the shape matching logic.
