# ComputedUrlWithQueryString Empty Field Handling -- Technical Specification

## Summary

This branch fixes a crash that occurs when Canvas components are mapped to optional URL fields that are empty. It changes the `is_required` declaration in `ComputedUrlWithQueryString` from `TRUE` to `FALSE`, adds a NULL guard returning an empty `GeneratedUrl`, and adds an early NULL return in the structured data `Evaluator` for empty unlimited-cardinality fields when `is_required` is false.

## Branch

`local/fix/computed-url-optional-field` based on `origin/1.x`

## Files Changed

| File | Change Type | Description |
|------|-------------|-------------|
| `src/Plugin/DataType/ComputedUrlWithQueryString.php` | Modified | Changed `is_required` to FALSE, added NULL guard (+8/-1) |
| `src/PropExpressions/StructuredData/Evaluator.php` | Modified | Added early NULL return for empty unlimited-cardinality fields (+6/-1) |

## Detailed Changes

### `src/Plugin/DataType/ComputedUrlWithQueryString.php`

**Change 1: `is_required` flag**

Before:
```php
$properties['computed_url'] = DataDefinition::create('string')
  ->setLabel('Computed URL')
  ->setComputed(TRUE)
  ->setRequired(TRUE);
```

After:
```php
$properties['computed_url'] = DataDefinition::create('string')
  ->setLabel('Computed URL')
  ->setComputed(TRUE)
  ->setRequired(FALSE);
```

**Change 2: NULL guard in `getValue()`**

A check is added at the top of the `getValue()` method (or the relevant computation method) to detect when the parent field item has no URI value:

```php
// If the underlying field is empty, return an empty GeneratedUrl
// rather than attempting to compute a URL from NULL.
if (empty($this->getParent()->get('uri')->getValue())) {
  return new GeneratedUrl();
}
```

This ensures that code receiving the computed value always gets a valid `GeneratedUrl` object, even when the field is empty. The empty `GeneratedUrl` has an empty string for its URL and no cache metadata beyond defaults.

### `src/PropExpressions/StructuredData/Evaluator.php`

An early return is added in the field evaluation path, in the section that handles unlimited-cardinality (multi-value) fields:

Before:
```php
// Evaluate the field items...
$items = $field->getValue();
// ... proceed to process items
```

After:
```php
// For optional unlimited-cardinality fields, return NULL early if empty.
if ($field->isEmpty() && !$field_definition->isRequired()) {
  return NULL;
}
$items = $field->getValue();
// ... proceed to process items
```

This prevents the evaluator from attempting to access item-level data (e.g., `$items[0]`) on an empty field list, which would trigger an `OutOfBoundsException` or similar error.

**Call flow:**

```
Component render
  -> PropExpressionEvaluator::evaluate()
    -> StructuredData\Evaluator::evaluateExpression()
      -> Field is empty + is_required=FALSE
      -> Returns NULL early
  -> Component receives NULL for prop
  -> Renders empty/default value
```

## Testing

### Manual Verification

1. Create a content type with an optional Link field.
2. Create a Canvas template mapping that Link field to a Button component's `href` prop.
3. Create two nodes:
   - **Node A:** Link field populated with `https://example.com`.
   - **Node B:** Link field left empty.
4. View Node A: verify the Button renders with the correct URL.
5. View Node B: verify the component renders without error (Button either hidden or rendered with empty href).

### Automated Testing

```bash
# Run data type tests
phpunit --filter=ComputedUrlWithQueryStringTest

# Run evaluator tests
phpunit --filter=EvaluatorTest

# Run full prop expression test suite
phpunit --group=prop_expressions
```

### Edge Cases to Verify

- Link field with cardinality = 1 (single value), empty: should return NULL/empty GeneratedUrl.
- Link field with cardinality = unlimited, zero items: should return NULL from evaluator.
- Link field with cardinality = unlimited, one item populated: should return the computed URL normally.
- Non-Link computed URL fields (if any exist): verify they still work with `is_required: FALSE`.

## Dependencies

None. This change is self-contained.
