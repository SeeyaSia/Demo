# Canvas Fields API Endpoint -- Technical Specification

## Summary

This branch adds a new GET API endpoint that returns all `component_tree` fields for a given entity type and bundle. The backend controller queries the Entity Field Manager and filters for `component_tree` field types. The frontend adds an RTK Query endpoint and React hook (`useGetCanvasFieldsQuery`) to consume this data. This is used by the ExposeSlotDialog to let template editors select which canvas field an exposed slot should map to.

## Branch

`local/feat/canvas-fields-api` based on `origin/1.x`

## Files Changed

| File | Change Type | Description |
|------|-------------|-------------|
| `canvas.routing.yml` | Modified | Adds route for `canvas.api.v0.ui.content_template.canvas_fields` |
| `src/Controller/ApiUiContentTemplateControllers.php` | Modified | Adds `canvasFields()` controller method |
| `ui/src/services/componentAndLayout.ts` | Modified | Adds `getCanvasFields` RTK Query endpoint and `useGetCanvasFieldsQuery` hook |

**Total:** 3 files, +69/-0 lines

## Detailed Changes

### `canvas.routing.yml`

New route definition:

```yaml
canvas.api.v0.ui.content_template.canvas_fields:
  path: '/canvas/api/v0/ui/content_template/canvas-fields/{entity_type_id}/{bundle}'
  defaults:
    _controller: '\Drupal\canvas\Controller\ApiUiContentTemplateControllers::canvasFields'
  requirements:
    _permission: 'administer canvas'
  methods: [GET]
```

The route follows the existing Canvas API URL pattern (`/canvas/api/v0/ui/...`) and uses the same permission requirement as other content template UI API routes.

### `src/Controller/ApiUiContentTemplateControllers.php`

**New method: `canvasFields(string $entity_type_id, string $bundle): JsonResponse`**

Implementation:

1. Injects `EntityFieldManagerInterface` via the controller's service container (if not already injected, adds it to the constructor).
2. Calls `$this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle)` to get all field definitions for the bundle.
3. Filters the field definitions to include only those where `$definition->getType() === 'component_tree'`.
4. For each matching field, constructs an object with:
   - `name`: the field machine name (e.g., `field_hero_content`)
   - `label`: the human-readable label (e.g., "Hero Content"), cast to string to handle `TranslatableMarkup`.
5. Returns a `JsonResponse` with the array of field objects.

**Edge cases:**
- If the entity type or bundle does not exist, Drupal's routing system will return a 404 before the controller is reached (the parameters are validated by the route).
- If no `component_tree` fields exist on the bundle, returns an empty JSON array `[]`.

### `ui/src/services/componentAndLayout.ts`

**New RTK Query endpoint: `getCanvasFields`**

```typescript
getCanvasFields: builder.query<
  Array<{ name: string; label: string }>,
  { entityTypeId: string; bundle: string }
>({
  query: ({ entityTypeId, bundle }) => ({
    url: `/canvas/api/v0/ui/content_template/canvas-fields/${entityTypeId}/${bundle}`,
    method: 'GET',
  }),
})
```

This endpoint is added to the existing RTK Query API slice in `componentAndLayout.ts`. It follows the same patterns used by other query endpoints in this file.

**Exported hook:**

```typescript
export const { useGetCanvasFieldsQuery } = componentAndLayoutApi;
```

The hook is exported alongside existing query hooks. React components can use it as:

```typescript
const { data: canvasFields, isLoading } = useGetCanvasFieldsQuery({
  entityTypeId: 'node',
  bundle: 'article',
});
```

The result `canvasFields` is an array of `{name, label}` objects suitable for rendering in a select dropdown or list.

## Testing

### Manual Verification

1. Ensure a content type (e.g., `article`) has at least one `component_tree` field.
2. Make a GET request to `/canvas/api/v0/ui/content_template/canvas-fields/node/article` as an authenticated admin user.
3. Verify the response is a JSON array containing the field's name and label.
4. Test with a bundle that has no `component_tree` fields and verify an empty array is returned.
5. Test with an invalid entity type/bundle and verify a 404 response.
6. In the Canvas React app, verify that the `useGetCanvasFieldsQuery` hook returns the expected data when used in a component.

### Automated Tests

- **Functional test for the API endpoint:**
  - Authenticated request returns correct `component_tree` fields.
  - Unauthenticated request returns 403.
  - Bundle with no canvas fields returns empty array.
  - Multiple canvas fields on a single bundle are all returned.
- **Frontend unit test for RTK Query endpoint:**
  - Mock the API response and verify the hook returns the expected data structure.
  - Verify loading and error states.

## Dependencies

- `feat/auto-provision-slot-fields` -- the auto-provisioned fields must exist (either as persisted `FieldConfig` or as runtime `BundleFieldDefinition`) for this endpoint to discover them. Without this dependency, the endpoint would only find manually created `component_tree` fields.
