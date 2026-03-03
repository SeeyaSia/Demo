# DCANV-2: Allow Linking Optional Entity Fields to Required Component Props

## Ticket Analysis

- **Core request:** When editing content type templates in Canvas (XB), the prop linker dropdown currently only shows entity fields marked as "required" in Drupal when the component prop is also required in its JSON Schema. This prevents linking common optional fields (like `body`) to required component props (like a heading's `text`). The fix: expand `DynamicPropSource` with a static fallback value so that when an optional field is empty, the component still renders with a sensible default.
- **Affected areas:** Canvas contrib module (`drupal/canvas` v1.1.0) — 4 source files + 1 test file
- **Community alignment:** This directly implements what Wim Leers rated as "easy" in drupal.org #3563309 (comment #17): "expanding what DynamicPropSource can contain." Also aligns with lauriii's comment #16 about static fallback values.

## Capability Script Catalog

No capability scripts are needed for this ticket. All changes are direct code modifications to the `drupal/canvas` contrib module (v1.1.0). The capability scripts in `.alchemize/drupal/capabilities/` are for structural/config changes and diagnostics — this ticket modifies PHP source code and kernel tests only.

## Contrib Module Target

- **Module:** `drupal/canvas`
- **Version:** `1.1.0` (from `composer.lock`)
- **Location:** `web/modules/contrib/canvas/`

---

## Implementation Plan

### Step 1: Add fallback support to `DynamicPropSource.php`

- **Type:** Code Change (contrib edit)
- **File:** `web/modules/contrib/canvas/src/PropSource/DynamicPropSource.php`
- **Details:**

This is the core change. Expand `DynamicPropSource` to optionally hold a `StaticPropSource` fallback that is used when the dynamic expression evaluates to `NULL`.

#### 1A. Add `use` statement for `NestedArray`

Add after the existing `use` statements (after line 17):

```php
use Drupal\Component\Utility\NestedArray;
```

#### 1B. Add `$fallback` parameter to constructor (line 41-52)

**Current:**
```php
public function __construct(
    public readonly EntityFieldBasedPropExpressionInterface $expression,
    private readonly ?AdapterInterface $adapter = NULL,
) {
```

**Change to:**
```php
public function __construct(
    public readonly EntityFieldBasedPropExpressionInterface $expression,
    private readonly ?AdapterInterface $adapter = NULL,
    private readonly ?StaticPropSource $fallback = NULL,
) {
```

The existing validation logic for `$adapter` remains unchanged.

#### 1C. Update `withAdapter()` to preserve fallback (line 54-64)

In the `return new static(...)` call, add `fallback: $this->fallback`:

**Current (line 60-63):**
```php
return new static(
    expression: $this->expression,
    adapter: $adapter_instance,
);
```

**Change to:**
```php
return new static(
    expression: $this->expression,
    adapter: $adapter_instance,
    fallback: $this->fallback,
);
```

#### 1D. Add `withFallback()` method

Add after `withAdapter()` method (after line 64):

```php
public function withFallback(StaticPropSource $fallback): static {
    return new static(
        expression: $this->expression,
        adapter: $this->adapter,
        fallback: $fallback,
    );
}
```

#### 1E. Update `toArray()` to serialize fallback (line 71-80)

After the adapter serialization block (after line 78), add:

```php
if ($this->fallback) {
    $array_representation['fallback'] = $this->fallback->toArray();
}
```

#### 1F. Update `parse()` to deserialize fallback (line 85-103)

**Current (line 94):**
```php
$instance = new DynamicPropSource(StructuredDataPropExpression::fromString($sdc_prop_source['expression']));
```

**Change to:**
```php
$fallback = isset($sdc_prop_source['fallback'])
    ? StaticPropSource::parse($sdc_prop_source['fallback'])
    : NULL;
$instance = new DynamicPropSource(
    StructuredDataPropExpression::fromString($sdc_prop_source['expression']),
    adapter: NULL,
    fallback: $fallback,
);
```

The rest of `parse()` (adapter handling at lines 97-102) remains unchanged — the `return $instance->withAdapter(...)` at line 102 will correctly preserve the fallback because of the change in Step 1C.

#### 1G. Update `evaluate()` to use fallback when dynamic value is NULL (line 108-127)

After the raw result is computed (after line 112), before the adapter check, add fallback logic. Also add fallback after adapter returns null.

**Replace the entire `evaluate()` method body (lines 108-127):**

```php
public function evaluate(?FieldableEntityInterface $host_entity, bool $is_required): EvaluationResult {
    if ($host_entity === NULL) {
        throw new MissingHostEntityException();
    }
    $raw_result = Evaluator::evaluate($host_entity, $this->expression, $is_required);

    // Only adapt non-empty results.
    if ($this->adapter && $raw_result->value !== NULL) {
        $sole_input_name = array_keys($this->adapter->getInputs())[0];
        $this->adapter->addInput($sole_input_name, $raw_result->value);
        $adapted_result = new EvaluationResult($this->adapter->adapt(), $raw_result);
        return $adapted_result;
    }

    // If the dynamic value is NULL and a fallback exists, use it.
    // Preserve cacheability from the dynamic source (the entity field's
    // cache tags/contexts matter even when empty).
    // @see https://www.drupal.org/project/canvas/issues/3563309
    if ($raw_result->value === NULL && $this->fallback !== NULL) {
        $fallback_result = $this->fallback->evaluate(NULL, is_required: FALSE);
        return new EvaluationResult($fallback_result->value, $raw_result);
    }

    return $raw_result;
}
```

The key insight: `new EvaluationResult($fallback_result->value, $raw_result)` passes the fallback's resolved value but preserves the dynamic source's cacheability metadata (via `$raw_result` as the cacheability source). This is important because the entity field's cache tags/contexts must be bubbled even when the field is empty.

#### 1H. Update `calculateDependencies()` to include fallback dependencies (line 136-155)

After the adapter dependency calculation block (after line 152), before `return $deps;`, add:

```php
if ($this->fallback) {
    $deps = NestedArray::mergeDeep($deps, $this->fallback->calculateDependencies($host_entity));
}
```

- **Expected outcome:** `DynamicPropSource` can now optionally carry a `StaticPropSource` fallback. It serializes/deserializes correctly. When the linked field is `NULL`, the fallback value is used instead. Dependencies include fallback dependencies.
- **Depends on:** None

---

### Step 2: Remove required-field-only filters in `JsonSchemaFieldInstanceMatcher.php`

- **Type:** Code Change (contrib edit)
- **File:** `web/modules/contrib/canvas/src/ShapeMatcher/JsonSchemaFieldInstanceMatcher.php`
- **Details:**

#### 2A. Remove field-level required filter in `matchEntityPropsForScalar()` (line 522-524)

**Remove these 3 lines entirely:**
```php
if ($is_required_in_json_schema && !$field_definition->isRequired()) {
    continue;
}
```

This is at line 522-524, inside the `foreach ($field_definitions ...)` loop. After removal, optional Drupal fields will appear as suggestions when linking to required component props.

#### 2B. Remove property-level required filter in `dataDefinitionMatchesPrimitiveType()` (lines 845-849)

**Remove these 5 lines entirely (including the preceding comment):**
```php
// If required in component's JSON schema, it must be required in Drupal's
// Typed Data too.
if ($is_required_in_json_schema && !$data_definition->isRequired()) {
    return FALSE;
}
```

This is at lines 845-849. After removal, optional typed data properties will also be matched for required component props.

- **Expected outcome:** The prop linker dropdown in Canvas UI will now show optional entity fields as available choices for required component props. The same set of fields currently shown for optional props will also appear for required props.
- **Depends on:** None (can be done in parallel with Step 1)

---

### Step 3: Wire up fallback in `GeneratedFieldExplicitInputUxComponentSourceBase.php`

- **Type:** Code Change (contrib edit)
- **File:** `web/modules/contrib/canvas/src/Plugin/Canvas/ComponentSource/GeneratedFieldExplicitInputUxComponentSourceBase.php`
- **Details:**

#### 3A. Attach fallback in `clientModelToInput()` (around line 1213-1221)

**Current (lines 1213-1221):**
```php
$source = PropSource::parse($prop_source);
if ($source instanceof DynamicPropSource) {
    if ($host_entity === NULL) {
        throw new \InvalidArgumentException('A host entity is required to set dynamic prop sources.');
    }
    $source->expression->validateSupport($host_entity);
    $props[$prop] = $this->collapse($source, $prop);
    continue;
}
```

**Change to:**
```php
$source = PropSource::parse($prop_source);
if ($source instanceof DynamicPropSource) {
    if ($host_entity === NULL) {
        throw new \InvalidArgumentException('A host entity is required to set dynamic prop sources.');
    }
    $source->expression->validateSupport($host_entity);
    // If this is a required prop, attach the default static value as a
    // fallback so the component always renders even when the linked
    // field is empty.
    // @see https://www.drupal.org/project/canvas/issues/3563309
    if ($is_required_prop && array_key_exists($prop, $this->configuration['prop_field_definitions'])) {
        $fallback = $this->getDefaultStaticPropSource($prop, FALSE);
        $source = $source->withFallback($fallback);
    }
    $props[$prop] = $this->collapse($source, $prop);
    continue;
}
```

The `$is_required_prop` variable already exists in scope at this location (it is determined earlier in the `clientModelToInput()` method). The `array_key_exists` guard ensures we only attempt to get the default for props that actually have field definitions.

#### 3B. Simplify NULL check in `hydrateComponent()` (line 383)

**Current (line 383):**
```php
if (!$is_required && $resolved_value->value === NULL) {
```

**Change to:**
```php
if ($resolved_value->value === NULL) {
```

This single-character change (`!$is_required && ` removed) means: any prop (required or optional) that resolves to `NULL` gets omitted from the SDC render, preventing the "Oops, something went wrong!" error for required props. For required props with a fallback, this branch should never be reached (since `DynamicPropSource::evaluate()` will have returned the fallback value), but this is defense-in-depth.

- **Expected outcome:** When a `DynamicPropSource` is saved for a required prop, the default static value is automatically stored as a fallback. At render time, if a required prop's value resolves to `NULL` (edge case), the component gracefully omits the prop instead of erroring.
- **Depends on:** Step 1 (needs `withFallback()` method)

---

### Step 4: Update test expectations in `PropShapeToFieldInstanceTest.php`

- **Type:** Code Change (contrib edit)
- **File:** `web/modules/contrib/canvas/tests/src/Kernel/PropShapeToFieldInstanceTest.php`
- **Details:**

After removing the two required-field filters in Step 2, the `REQUIRED, *` test case entries in `provider()` will now match additional field instances (the same optional fields that currently appear in the corresponding `optional, *` entries).

#### Approach:

1. **Run the test first** to collect the actual new matches:
   ```bash
   ddev exec vendor/bin/phpunit web/modules/contrib/canvas/tests/src/Kernel/PropShapeToFieldInstanceTest.php
   ```

2. **Analyze the failure output** — it will show exactly which instances are now matched for each `REQUIRED, *` key (via the `assertSame([], $matches_instances_extraneous, ...)` assertion).

3. **Update each `REQUIRED, *` entry's `instances` array** to include the newly-matched optional field instances. The specific entries that will grow include:
   - `'REQUIRED, type=string'` (line 583) — will add all optional string-type fields from `'optional, type=string'` (line 1300)
   - `'REQUIRED, type=object&$ref=.../image'` (line 538) — will add optional image fields
   - `'REQUIRED, type=object&$ref=.../video'` (line 569) — will add optional video reference fields (like `media_optional_vacation_videos`)
   - All other `'REQUIRED, *'` entries — each grows to include the same optional field instances as the corresponding `'optional, *'` entry

4. **Also verify** `'REQUIRED, type=integer'` entries (integer/number types) which may also grow with optional numeric fields.

5. **Do NOT change** `adapter_matches_field_type` or `adapter_matches_instance` arrays — only the `instances` arrays are affected by the filter removal.

The exact new instances per test case must be determined empirically by running the test. The developer should:
- Run the test
- Copy the extraneous instances from the failure message
- Merge them into the expected `instances` arrays in sorted order
- Re-run until the test passes

- **Expected outcome:** `PropShapeToFieldInstanceTest` passes with the updated expectations that include optional fields in REQUIRED prop shape matches.
- **Depends on:** Step 2

---

### Step 5: Run coding standards on modified files

- **Type:** Verification
- **Details:**

```bash
ddev exec vendor/bin/phpcbf web/modules/contrib/canvas/src/PropSource/DynamicPropSource.php
ddev exec vendor/bin/phpcbf web/modules/contrib/canvas/src/ShapeMatcher/JsonSchemaFieldInstanceMatcher.php
ddev exec vendor/bin/phpcbf web/modules/contrib/canvas/src/Plugin/Canvas/ComponentSource/GeneratedFieldExplicitInputUxComponentSourceBase.php
ddev exec vendor/bin/phpcbf web/modules/contrib/canvas/tests/src/Kernel/PropShapeToFieldInstanceTest.php
```

Then verify:
```bash
ddev exec vendor/bin/phpcs web/modules/contrib/canvas/src/PropSource/DynamicPropSource.php
ddev exec vendor/bin/phpcs web/modules/contrib/canvas/src/ShapeMatcher/JsonSchemaFieldInstanceMatcher.php
ddev exec vendor/bin/phpcs web/modules/contrib/canvas/src/Plugin/Canvas/ComponentSource/GeneratedFieldExplicitInputUxComponentSourceBase.php
ddev exec vendor/bin/phpcs web/modules/contrib/canvas/tests/src/Kernel/PropShapeToFieldInstanceTest.php
```

- **Expected outcome:** Zero PHPCS errors on all modified files.
- **Depends on:** Steps 1-4

---

### Step 6: Run the existing Canvas kernel tests

- **Type:** Verification
- **Details:**

Run the specific test first:
```bash
ddev exec vendor/bin/phpunit web/modules/contrib/canvas/tests/src/Kernel/PropShapeToFieldInstanceTest.php
```

Then run the broader test suite to check for regressions:
```bash
ddev exec vendor/bin/phpunit web/modules/contrib/canvas/tests/
```

Also run the Canvas capability test suite:
```bash
ddev drush php:script .alchemize/drupal/capabilities/tests/canvas-test-run-all.drush.php
```

- **Expected outcome:** All tests pass. No regressions.
- **Depends on:** Steps 1-5

---

### Step 7: Manual verification

- **Type:** Manual Testing
- **Details:**

1. **Prop linker dropdown** — In Canvas UI, edit a content type template, place a heading component, verify optional fields (like `body`) now appear in the prop linker dropdown for required props.

2. **Rendering — empty field** — Create content where the linked optional field is empty. Verify the component renders with the fallback value (from SDC `examples[0]`) instead of erroring.

3. **Rendering — populated field** — Create content where the linked optional field has a value. Verify the component renders with the actual field value (not the fallback).

4. **Regression — static props** — Verify static-only prop sources and existing dynamic links to required fields still work correctly.

5. **Regression — existing templates** — Verify existing content templates continue to render without issues.

- **Expected outcome:** All scenarios pass as described.
- **Depends on:** Steps 1-6

---

### Step 8: Commit and push

- **Type:** Git
- **Details:**

```bash
git add -f web/modules/contrib/canvas/src/PropSource/DynamicPropSource.php
git add -f web/modules/contrib/canvas/src/ShapeMatcher/JsonSchemaFieldInstanceMatcher.php
git add -f web/modules/contrib/canvas/src/Plugin/Canvas/ComponentSource/GeneratedFieldExplicitInputUxComponentSourceBase.php
git add -f web/modules/contrib/canvas/tests/src/Kernel/PropShapeToFieldInstanceTest.php
git commit -m "[DCANV-2] Allow linking optional entity fields to required component props with fallback"
git push
```

Note: `git add -f` is required because contrib directories are typically `.gitignore`d by Composer.

- **Expected outcome:** Changes committed and pushed to the `alchemize/dcanv-2-allow-linking-optional-entity-fields-to-required-c` branch.
- **Depends on:** Steps 1-7

---

## Code Changes Summary

| File | Change Type | Description |
|------|------------|-------------|
| `web/modules/contrib/canvas/src/PropSource/DynamicPropSource.php` | Modify | Add `$fallback` constructor param, `withFallback()` method, fallback serialization/deserialization in `toArray()`/`parse()`, fallback evaluation in `evaluate()`, fallback dependency calculation in `calculateDependencies()` |
| `web/modules/contrib/canvas/src/ShapeMatcher/JsonSchemaFieldInstanceMatcher.php` | Modify | Remove 3-line required-field filter at line 522 in `matchEntityPropsForScalar()`, remove 5-line required filter at lines 845-849 in `dataDefinitionMatchesPrimitiveType()` |
| `web/modules/contrib/canvas/src/Plugin/Canvas/ComponentSource/GeneratedFieldExplicitInputUxComponentSourceBase.php` | Modify | Attach fallback to `DynamicPropSource` for required props in `clientModelToInput()`, simplify NULL check in `hydrateComponent()` to apply to all props |
| `web/modules/contrib/canvas/tests/src/Kernel/PropShapeToFieldInstanceTest.php` | Modify | Update `REQUIRED, *` test case `instances` arrays to include newly-matched optional field instances |

## New Scripts Needed

None. All changes are direct code modifications.

## Risks & Dependencies

| Risk | Mitigation |
|------|------------|
| SDC Twig templates that don't use `\|default()` may render unexpected fallback values | The fallback comes from the SDC's own `examples[0]` — the component author defined it, so it should be valid for that component |
| Fallback value stored per-instance increases storage slightly | The fallback array is small (one static prop source per required prop), and only stored for required props linked to dynamic sources |
| Future Canvas updates may rename `DynamicPropSource` to `EntityFieldPropSource` (mentioned by Wim) | Rename is mechanical — the fallback pattern transfers regardless of class name |
| Removing the required filter may surface many more suggestions in the linker dropdown | This is the same set of fields shown for optional props today — the UX is already designed for this volume. The `PropSourceSuggester` already filters irrelevant fields |
| `StaticPropSource::evaluate()` is called with `NULL` host entity for fallback | `StaticPropSource::evaluate()` does not use the host entity parameter (it evaluates against its own `fieldItemList`), so passing `NULL` is safe |
| The `hydrateComponent()` change (removing `!$is_required &&`) may cause required props to be silently omitted | This is defense-in-depth only. With fallback wired in, required props linked to optional fields should always resolve to the fallback value. This change only prevents a crash if an edge case is missed. |

## Verification Criteria

1. `PropShapeToFieldInstanceTest` passes with updated expectations
2. The full Canvas kernel test suite passes with no regressions
3. The Canvas capability test suite (`canvas-test-run-all.drush.php`) passes
4. PHPCS reports zero errors on all 4 modified files
5. Optional fields appear in the prop linker dropdown when linking to required component props
6. A required prop linked to an empty optional field renders with the fallback value
7. A required prop linked to a populated optional field renders with the actual field value
8. Existing content templates and static-only prop sources continue to work correctly
