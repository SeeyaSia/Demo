# Expose Slot Dialog UI -- Technical Specification

## Summary

This branch adds the complete React-based UI for managing exposed slots during Canvas template editing. It introduces an `ExposeSlotDialog` modal for configuring slot exposure, adds visual indicators and interaction guards for exposed slots in the editor, extends Redux state management to track exposed slots during editing, and integrates exposed slot data with the template save/load API.

## Branch

`local/feat/expose-slot-dialog-ui` based on `origin/1.x`

## Files Changed

| File | Change Type | Description |
|------|-------------|-------------|
| `ui/src/components/list/TemplateList.tsx` | Modified | Adds exposed slot count badge on template list items |
| `ui/src/features/editorFrame/EditorFrame.tsx` | Modified | Prevents deletion of components owning/containing exposed slots |
| `ui/src/features/layout/layers/SlotLayer.tsx` | Modified | Adds expose/remove dropdown, disables drops, shows "(exposed)" label |
| `ui/src/features/layout/layoutModelSlice.ts` | Modified | Adds `deleteNodeAndCleanupExposedSlots` thunk |
| `ui/src/features/layout/preview/ComponentContextMenu.tsx` | Modified | Blocks copy/cut/paste/delete on components with exposed slots |
| `ui/src/features/layout/preview/ExposeSlotDialog.tsx` | New | Modal dialog for configuring exposed slot properties |
| `ui/src/features/layout/preview/Preview.tsx` | Modified | Adds `editingExposedSlots` to POST body and useEffect deps |
| `ui/src/features/layout/previewOverlay/PreviewOverlay.module.css` | Modified | Adds green dashed border style for exposed slots |
| `ui/src/features/layout/previewOverlay/SlotOverlay.tsx` | Modified | Applies exposed slot styling and context menu actions |
| `ui/src/features/ui/uiSlice.ts` | Modified | Adds `editingExposedSlots` state, reducers, and selector |
| `ui/src/services/componentAndLayout.ts` | Modified | Sends `exposed_slots` on save, dispatches on load |

## Detailed Changes

### `ui/src/features/layout/preview/ExposeSlotDialog.tsx` (New)

The central new component -- a modal dialog presented when a template author chooses to expose a slot. The dialog contains:

- **Machine name** field: Auto-generated from the slot's component ID and slot name (e.g., `hero_section__main_content`). Editable by the author, validated to ensure uniqueness across all exposed slots in the template.
- **Human label** field: A display name for the exposed slot (e.g., "Hero Content Area"), shown to content editors during per-content editing.
- **Canvas field dropdown**: Populated via `useGetCanvasFieldsQuery()`, lists all Canvas-type fields on the entity type. The selected field determines where per-content component data is stored.

```tsx
interface ExposeSlotDialogProps {
  open: boolean;
  onClose: () => void;
  slotId: string;
  componentId: string;
  existingConfig?: ExposedSlotConfig;
}

const ExposeSlotDialog: React.FC<ExposeSlotDialogProps> = ({
  open, onClose, slotId, componentId, existingConfig
}) => {
  const { data: canvasFields } = useGetCanvasFieldsQuery();
  const [machineName, setMachineName] = useState('');
  const [label, setLabel] = useState('');
  const [fieldName, setFieldName] = useState('');

  // Auto-generate machine name from component and slot IDs
  useEffect(() => {
    if (!existingConfig) {
      const generated = `${componentId}__${slotId}`.replace(/[^a-z0-9_]/gi, '_').toLowerCase();
      setMachineName(generated);
    }
  }, [componentId, slotId, existingConfig]);

  const handleSave = () => {
    dispatch(addExposedSlot({
      machineName,
      label,
      fieldName,
      componentId,
      slotId,
    }));
    onClose();
  };
  // ... render dialog with form fields
};
```

### `ui/src/features/ui/uiSlice.ts`

Adds exposed slot tracking to the UI state:

```typescript
interface ExposedSlotConfig {
  machineName: string;
  label: string;
  fieldName: string;
  componentId: string;
  slotId: string;
}

interface UiState {
  // ... existing state
  editingExposedSlots: Record<string, ExposedSlotConfig>; // keyed by machineName
}
```

New reducers:

- **`addExposedSlot`**: Adds or updates an exposed slot configuration in the `editingExposedSlots` map.
- **`removeExposedSlot`**: Removes an exposed slot by machine name.
- **`setEditingExposedSlots`**: Bulk-sets all exposed slots (used when loading a template).

New selector:

- **`selectEditingExposedSlots`**: Returns the full `editingExposedSlots` map.

### `ui/src/features/layout/layers/SlotLayer.tsx`

Significant enhancements to slot rendering during template editing:

1. **Dropdown menu**: Each slot gets a small dropdown (kebab/three-dot) menu with:
   - "Expose this slot" -- opens the `ExposeSlotDialog`.
   - "Remove exposed slot" -- dispatches `removeExposedSlot` after confirmation.
   The menu item shown depends on whether the slot is already exposed.

2. **Drop disabling**: When a slot is exposed, `slotDisableDrop` is set to `true` in template editing mode. Content is added to exposed slots only during per-content editing, not during template authoring.

3. **Visual label**: Exposed slots show an "(exposed)" text label next to the slot name to provide clear visual feedback.

```tsx
const isExposed = useMemo(() => {
  return Object.values(editingExposedSlots).some(
    (config) => config.componentId === componentId && config.slotId === slotId
  );
}, [editingExposedSlots, componentId, slotId]);
```

### `ui/src/features/layout/previewOverlay/SlotOverlay.tsx`

Applies visual treatment to exposed slots in the preview overlay:

- Exposed slots receive a green dashed border via the `.exposed` CSS class.
- Right-click context menu includes "Expose this slot" / "Remove exposed slot" actions, mirroring the SlotLayer dropdown.

### `ui/src/features/layout/previewOverlay/PreviewOverlay.module.css`

New CSS class for exposed slot styling:

```css
.exposed {
  border: 2px dashed #4caf50;
  border-radius: 4px;
  position: relative;
}

.exposed::after {
  content: 'Exposed';
  position: absolute;
  top: -10px;
  right: 4px;
  font-size: 10px;
  color: #4caf50;
  background: white;
  padding: 0 4px;
}
```

### `ui/src/features/editorFrame/EditorFrame.tsx`

Adds a guard that prevents deletion of components that own or contain exposed slots while in template editing mode:

```typescript
const canDeleteComponent = (componentId: string): boolean => {
  // Check if this component directly has an exposed slot.
  const hasDirectExposedSlot = Object.values(editingExposedSlots).some(
    (config) => config.componentId === componentId
  );
  if (hasDirectExposedSlot) return false;

  // Check if any descendant has an exposed slot.
  const descendants = getDescendantIds(componentId, layoutModel);
  return !descendants.some((descId) =>
    Object.values(editingExposedSlots).some(
      (config) => config.componentId === descId
    )
  );
};
```

When deletion is blocked, a toast notification informs the author that they must first remove the exposed slot configuration.

### `ui/src/features/layout/preview/ComponentContextMenu.tsx`

Extends the existing component context menu to block destructive operations on components that contain exposed slots:

- Copy, cut, paste, and delete options are disabled (grayed out with tooltip explanation) when the target component or any of its descendants have exposed slots.
- This prevents authors from accidentally breaking exposed slot configurations through clipboard operations.

### `ui/src/features/layout/layoutModelSlice.ts`

New async thunk `deleteNodeAndCleanupExposedSlots`:

```typescript
export const deleteNodeAndCleanupExposedSlots = createAsyncThunk(
  'layoutModel/deleteNodeAndCleanupExposedSlots',
  async (componentId: string, { dispatch, getState }) => {
    const state = getState() as RootState;
    const exposedSlots = selectEditingExposedSlots(state);

    // Find and remove any exposed slots owned by this component or descendants.
    const descendants = getDescendantIds(componentId, state.layoutModel);
    const allIds = [componentId, ...descendants];

    for (const [machineName, config] of Object.entries(exposedSlots)) {
      if (allIds.includes(config.componentId)) {
        dispatch(removeExposedSlot(machineName));
      }
    }

    // Now proceed with normal node deletion.
    dispatch(deleteNode(componentId));
  }
);
```

This ensures that when a component is deleted (in scenarios where deletion is allowed, e.g., the protection guard was intentionally bypassed or the feature is being removed), the exposed slot references are cleaned up first.

### `ui/src/services/componentAndLayout.ts`

Two changes:

1. **Template save**: The `postLayout` mutation now includes `exposed_slots` from the Redux store in the request body:

```typescript
body: JSON.stringify({
  layout: serializedLayout,
  exposed_slots: editingExposedSlots,
  // ... other fields
}),
```

2. **Template load**: When a template is fetched, the response's exposed slots are dispatched to the store:

```typescript
const result = await baseQuery(/* ... */);
if (result.data?.exposed_slots) {
  dispatch(setEditingExposedSlots(result.data.exposed_slots));
}
```

### `ui/src/components/list/TemplateList.tsx`

Adds a small badge next to each template name in the template list showing the count of exposed slots, if any:

```tsx
{template.exposedSlotCount > 0 && (
  <Badge count={template.exposedSlotCount} label="exposed slots" />
)}
```

This gives template authors a quick overview of which templates have per-content editing capabilities.

### `ui/src/features/layout/preview/Preview.tsx`

Two additions:

1. Includes `editingExposedSlots` in the POST body sent to the preview iframe for rendering.
2. Adds `editingExposedSlots` to the `useEffect` dependency array so the preview re-renders when exposed slot configuration changes.

## Testing

### Manual Verification

1. Open a Canvas template in the editor.
2. Right-click on any slot in the preview, or use the slot dropdown menu.
3. Select "Expose this slot" -- the ExposeSlotDialog should open.
4. Verify the machine name is auto-generated, the label field is empty, and the canvas field dropdown is populated.
5. Fill in the dialog and save. Verify the slot shows a green dashed border and "(exposed)" label.
6. Try to delete the component owning the exposed slot -- it should be blocked with a notification.
7. Try copy/cut on the component -- context menu items should be disabled.
8. Right-click the exposed slot and select "Remove exposed slot" -- the green border and label should disappear.
9. Save the template and reload -- the exposed slot configuration should persist.
10. Check the template list -- a badge should show the count of exposed slots.

### Automated Testing

```bash
# Run React component tests
npm test -- --testPathPattern=ExposeSlotDialog
npm test -- --testPathPattern=SlotLayer
npm test -- --testPathPattern=SlotOverlay

# Run Redux slice tests
npm test -- --testPathPattern=uiSlice
npm test -- --testPathPattern=layoutModelSlice

# Run integration tests
npm test -- --testPathPattern=componentAndLayout
```

## Dependencies

- **`feat/canvas-fields-api`**: Required for the `useGetCanvasFieldsQuery` hook that populates the canvas field dropdown in the ExposeSlotDialog.
- **`feat/active-exposed-slots`**: Required for the backend `exposed_slots` data model that this UI manages. The template save/load API must understand the `exposed_slots` field.
