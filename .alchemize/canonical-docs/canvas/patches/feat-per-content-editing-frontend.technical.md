# Per-Content Editing Frontend -- Technical Specification

## Summary

This branch implements the full React/Redux frontend for per-content editing in Canvas. It introduces template context tracking, enforces visual and functional locking of template-owned components, restricts drop targets to exposed slots only, adapts the contextual panel for per-content mode, and extends routing and API integration to support the entity-scoped SPA launch.

## Branch

`local/feat/per-content-editing-frontend` based on `origin/1.x`

## Files Changed

| File | Change Type | Description |
|------|-------------|-------------|
| `packages/types/src/DrupalSettings.ts` | Modified | Adds `entityType`, `entity`, and `templateContext` to settings type |
| `ui/src/app/AppRoutes.tsx` | Modified | Auto-redirect to entity editor when entity context is present |
| `ui/src/components/ComponentInstanceForm.tsx` | Modified | Shows "part of template" message for locked components |
| `ui/src/components/panel/ContextualPanel.tsx` | Modified | Replaces PageDataForm with "Edit content" link in per-content mode |
| `ui/src/features/layout/layers/ComponentLayer.module.css` | Modified | Adds `.locked` style for template components |
| `ui/src/features/layout/layers/ComponentLayer.tsx` | Modified | Lock icon, disables drag/selection/hover/context menu for locked components |
| `ui/src/features/layout/layers/RegionLayer.tsx` | Modified | Handles region-level editability in per-content mode |
| `ui/src/features/layout/layers/SlotLayer.tsx` | Modified | Computes `isSlotExposed`, disables drops on non-exposed slots |
| `ui/src/features/layout/layoutModelSlice.ts` | Modified | Integrates `editable` flag into component model |
| `ui/src/features/layout/previewOverlay/ComponentOverlay.tsx` | Modified | Disables click/hover/drag for locked components |
| `ui/src/features/layout/previewOverlay/PreviewOverlay.module.css` | Modified | Adds `.locked` and `.exposedPerContent` CSS classes |
| `ui/src/features/layout/previewOverlay/RegionOverlay.tsx` | Modified | Handles region overlay in per-content mode |
| `ui/src/features/layout/previewOverlay/SlotOverlay.tsx` | Modified | Green dashed border for exposed slots, no interaction for non-exposed |
| `ui/src/features/ui/uiSlice.ts` | Modified | Adds `TemplateContext` interface, `setTemplateContext` reducer, selectors |
| `ui/src/main.tsx` | Modified | Reads template context from `drupalSettings` on boot |
| `ui/src/services/baseQuery.ts` | Modified | Extended `extractEntityParams()` with fallback regexes and drupalSettings |
| `ui/src/services/componentAndLayout.ts` | Modified | Dispatches `setTemplateContext` from API response, extends type |

## Detailed Changes

### `packages/types/src/DrupalSettings.ts`

Extends the `DrupalSettings` TypeScript interface:

```typescript
interface CanvasSettings {
  // ... existing settings
  entityType?: string;
  entity?: string;
  templateContext?: TemplateContext;
}

interface TemplateContext {
  contentTemplateId: string;
  hasExposedSlots: boolean;
  exposedSlots: Record<string, ExposedSlotConfig>;
}
```

These are populated by `CanvasController::entityLayout()` on the backend when the SPA is launched from an entity's Layout tab.

### `ui/src/features/ui/uiSlice.ts`

Central state management changes for per-content editing:

```typescript
interface TemplateContext {
  contentTemplateId: string;
  hasExposedSlots: boolean;
  exposedSlots: Record<string, ExposedSlotConfig>;
}

interface UiState {
  // ... existing state
  templateContext: TemplateContext | null;
}

// New reducer
setTemplateContext: (state, action: PayloadAction<TemplateContext>) => {
  state.templateContext = action.payload;
},

// New selectors
export const selectTemplateContext = (state: RootState) => state.ui.templateContext;
export const selectIsPerContentMode = (state: RootState) => state.ui.templateContext !== null;
export const selectExposedSlots = (state: RootState) =>
  state.ui.templateContext?.exposedSlots ?? {};
```

The `selectIsPerContentMode` selector is the primary way all components determine whether per-content editing restrictions should be applied.

### `ui/src/features/layout/layers/ComponentLayer.tsx`

Major changes to component rendering in per-content mode:

```tsx
const ComponentLayer: React.FC<ComponentLayerProps> = ({ component }) => {
  const isPerContentMode = useAppSelector(selectIsPerContentMode);
  const isLocked = isPerContentMode && component.editable === false;

  return (
    <div
      className={cn(styles.component, { [styles.locked]: isLocked })}
      // Disable all interactive handlers for locked components.
      draggable={!isLocked}
      onClick={isLocked ? undefined : handleClick}
      onMouseEnter={isLocked ? undefined : handleMouseEnter}
      onContextMenu={isLocked ? undefined : handleContextMenu}
    >
      {isLocked && <LockIcon className={styles.lockIcon} />}
      {/* ... existing component content */}
    </div>
  );
};
```

When `isLocked` is true:
- The component cannot be dragged (no drag handle rendered).
- Click events do not trigger selection.
- Hover effects are suppressed.
- The context menu is not shown.
- A lock icon is rendered in the component's corner.

### `ui/src/features/layout/layers/ComponentLayer.module.css`

New `.locked` class:

```css
.locked {
  opacity: 0.6;
  cursor: not-allowed;
  border: 1px dashed #9e9e9e;
  pointer-events: none;
  user-select: none;
}

.locked .lockIcon {
  position: absolute;
  top: 4px;
  right: 4px;
  width: 14px;
  height: 14px;
  color: #757575;
}
```

Note: `pointer-events: none` on the layer means the component is completely non-interactive in the layer panel. The overlay (below) handles its own interaction blocking separately.

### `ui/src/features/layout/previewOverlay/ComponentOverlay.tsx`

Disables overlay interactions for locked components:

```tsx
const ComponentOverlay: React.FC<Props> = ({ component, rect }) => {
  const isPerContentMode = useAppSelector(selectIsPerContentMode);
  const isLocked = isPerContentMode && component.editable === false;

  const handleClick = (e: React.MouseEvent) => {
    if (isLocked) {
      e.stopPropagation();
      return;
    }
    // ... existing click logic
  };

  const handleDragStart = (e: React.DragEvent) => {
    if (isLocked) {
      e.preventDefault();
      return;
    }
    // ... existing drag logic
  };

  return (
    <div
      className={cn(styles.overlay, { [styles.locked]: isLocked })}
      onClick={handleClick}
      onDragStart={handleDragStart}
      onMouseEnter={isLocked ? undefined : handleMouseEnter}
    >
      {/* ... */}
    </div>
  );
};
```

### `ui/src/features/layout/previewOverlay/PreviewOverlay.module.css`

New CSS classes for per-content mode:

```css
.locked {
  opacity: 0.6;
  cursor: not-allowed;
  border: 1px dashed #9e9e9e;
}

.exposedPerContent {
  border: 2px dashed #4caf50;
  border-radius: 4px;
  /* No background -- only the border indicates the slot is exposed. */
}
```

The `.exposedPerContent` class differs from the `.exposed` class (used in template editing mode) by not having a background fill or floating label. In per-content mode, the green border is the only indicator.

### `ui/src/features/layout/layers/SlotLayer.tsx`

Computes whether a slot is exposed and controls drop behavior:

```tsx
const SlotLayer: React.FC<SlotLayerProps> = ({ slot, componentId }) => {
  const isPerContentMode = useAppSelector(selectIsPerContentMode);
  const exposedSlots = useAppSelector(selectExposedSlots);

  const isSlotExposed = useMemo(() => {
    if (!isPerContentMode) return false;
    return Object.values(exposedSlots).some(
      (config) => config.componentId === componentId && config.slotId === slot.id
    );
  }, [isPerContentMode, exposedSlots, componentId, slot.id]);

  // In per-content mode, only exposed slots accept drops.
  const slotDisableDrop = isPerContentMode && !isSlotExposed;

  return (
    <div className={cn(styles.slot, { [styles.disabled]: slotDisableDrop })}>
      {/* ... slot content */}
    </div>
  );
};
```

### `ui/src/features/layout/previewOverlay/SlotOverlay.tsx`

Applies per-content visual treatment to slot overlays:

```tsx
const SlotOverlay: React.FC<Props> = ({ slot, componentId, rect }) => {
  const isPerContentMode = useAppSelector(selectIsPerContentMode);
  const exposedSlots = useAppSelector(selectExposedSlots);

  const isSlotExposed = useMemo(() => {
    return Object.values(exposedSlots).some(
      (config) => config.componentId === componentId && config.slotId === slot.id
    );
  }, [exposedSlots, componentId, slot.id]);

  if (isPerContentMode && !isSlotExposed) {
    // Non-exposed slots are not rendered in per-content mode overlay.
    return null;
  }

  return (
    <div className={cn(styles.slotOverlay, {
      [styles.exposedPerContent]: isPerContentMode && isSlotExposed,
    })}>
      {/* ... drop zone and slot content */}
    </div>
  );
};
```

### `ui/src/features/layout/layers/RegionLayer.tsx`

Handles region-level editability. Global regions (header, footer) are always non-editable in per-content mode:

```tsx
const RegionLayer: React.FC<RegionLayerProps> = ({ region }) => {
  const isPerContentMode = useAppSelector(selectIsPerContentMode);

  // In per-content mode, region-level interactions are disabled.
  // Region components are part of the template/global layout.
  const isRegionLocked = isPerContentMode;

  return (
    <div className={cn(styles.region, { [styles.locked]: isRegionLocked })}>
      {/* ... region content */}
    </div>
  );
};
```

### `ui/src/features/layout/previewOverlay/RegionOverlay.tsx`

Mirrors `RegionLayer` changes in the preview overlay. Region overlays suppress interaction in per-content mode.

### `ui/src/components/ComponentInstanceForm.tsx`

Shows an informational message for locked components instead of editable form fields:

```tsx
const ComponentInstanceForm: React.FC<Props> = ({ component }) => {
  const isPerContentMode = useAppSelector(selectIsPerContentMode);
  const isLocked = isPerContentMode && component.editable === false;

  if (isLocked) {
    return (
      <div className={styles.lockedMessage}>
        <LockIcon />
        <p>This component is part of the template and cannot be edited here.</p>
        <p>To modify this component, edit the template directly.</p>
      </div>
    );
  }

  // ... existing form rendering
};
```

### `ui/src/components/panel/ContextualPanel.tsx`

Replaces the page data form in per-content mode:

```tsx
const ContextualPanel: React.FC = () => {
  const isPerContentMode = useAppSelector(selectIsPerContentMode);
  const { entityType, entity } = useAppSelector(selectDrupalSettings);

  if (isPerContentMode) {
    return (
      <div className={styles.perContentPanel}>
        <h3>Content Layout</h3>
        <p>You are editing the layout for this content.</p>
        <p>To edit the content fields (title, body, etc.), use the entity edit form:</p>
        <a
          href={`/entity/${entityType}/${entity}/edit`}
          target="_blank"
          rel="noopener noreferrer"
          className={styles.editContentLink}
        >
          Edit content
        </a>
      </div>
    );
  }

  // ... existing PageDataForm rendering
};
```

### `ui/src/app/AppRoutes.tsx`

Auto-redirects to the entity editor when entity context is present:

```tsx
const AppRoutes: React.FC = () => {
  const drupalSettings = useAppSelector(selectDrupalSettings);

  // If launched from an entity's Layout tab, go directly to the editor.
  if (drupalSettings?.entityType && drupalSettings?.entity) {
    return (
      <Routes>
        <Route path="*" element={<EntityEditor />} />
      </Routes>
    );
  }

  // ... existing route definitions (template list, template editor, etc.)
};
```

### `ui/src/main.tsx`

Reads template context from `drupalSettings` on app initialization:

```typescript
const drupalSettings = (window as any).drupalSettings?.canvas;

if (drupalSettings?.templateContext) {
  store.dispatch(setTemplateContext(drupalSettings.templateContext));
}
```

This ensures the template context is available in the Redux store before any component renders, avoiding a flash of incorrect UI state.

### `ui/src/services/baseQuery.ts`

Extended `extractEntityParams()` to handle the SPA being mounted outside the standard `/canvas/` base path:

```typescript
function extractEntityParams(url: string): EntityParams | null {
  // Standard canvas path patterns.
  const canvasMatch = url.match(/\/canvas\/(?:editor|template)\/(\w+)\/(\d+)/);
  if (canvasMatch) {
    return { entityType: canvasMatch[1], entityId: canvasMatch[2] };
  }

  // Generic fallback for /editor/ and /template/ paths (per-content layout).
  const genericMatch = url.match(/\/(?:editor|template)\/(\w+)\/(\d+)/);
  if (genericMatch) {
    return { entityType: genericMatch[1], entityId: genericMatch[2] };
  }

  // Final fallback: read from drupalSettings.
  const settings = (window as any).drupalSettings?.canvas;
  if (settings?.entityType && settings?.entity) {
    return { entityType: settings.entityType, entityId: settings.entity };
  }

  return null;
}
```

The fallback chain is important because when the SPA is mounted at `/canvas/layout/{entity_type}/{entity}`, the URL structure differs from the standard `/canvas/editor/...` pattern.

### `ui/src/services/componentAndLayout.ts`

Two changes:

1. **`getPageLayout` response handling**: Dispatches `setTemplateContext` when the response indicates per-content mode:

```typescript
async onQueryStarted(arg, { dispatch, queryFulfilled }) {
  const { data } = await queryFulfilled;
  if (data.contentTemplateId && data.exposedSlots) {
    dispatch(setTemplateContext({
      contentTemplateId: data.contentTemplateId,
      hasExposedSlots: true,
      exposedSlots: data.exposedSlots,
    }));
  }
},
```

2. **Type extension**: `LayoutApiResponse` now includes optional `contentTemplateId`:

```typescript
interface LayoutApiResponse {
  layout: ComponentTree;
  contentTemplateId?: string;
  exposedSlots?: Record<string, ExposedSlotConfig>;
}
```

### `ui/src/features/layout/layoutModelSlice.ts`

Integrates the `editable` flag into the component model so it is available throughout the component tree:

```typescript
interface ComponentNode {
  // ... existing fields
  editable?: boolean;
}
```

The `editable` flag is set during tree deserialization from the API response and is used by all layer and overlay components to determine interaction behavior.

## Testing

### Manual Verification

1. Create a template with exposed slots and assign it to a content type.
2. Create a node of that content type.
3. Navigate to the node's Layout tab.
4. **Locked components**: Verify template-owned components show lock icon, gray dashed border, 0.6 opacity, and `not-allowed` cursor. Confirm they cannot be selected, dragged, or right-clicked.
5. **Exposed slots**: Verify only exposed slots show green dashed borders and accept component drops. Non-exposed slots should not show drop zones.
6. **Component editing**: Add a component to an exposed slot. Select it and verify the ComponentInstanceForm shows editable fields. Then click a template component and verify the "part of template" message.
7. **Contextual panel**: Verify the panel shows an "Edit content" link instead of page data fields.
8. **Save and reload**: Save the layout, reload the page, and verify per-content components persist and template components remain locked.

### Automated Testing

```bash
# Run component tests
npm test -- --testPathPattern=ComponentLayer
npm test -- --testPathPattern=ComponentOverlay
npm test -- --testPathPattern=SlotLayer
npm test -- --testPathPattern=SlotOverlay
npm test -- --testPathPattern=ComponentInstanceForm
npm test -- --testPathPattern=ContextualPanel

# Run slice tests
npm test -- --testPathPattern=uiSlice
npm test -- --testPathPattern=layoutModelSlice

# Run service tests
npm test -- --testPathPattern=baseQuery
npm test -- --testPathPattern=componentAndLayout

# Run route tests
npm test -- --testPathPattern=AppRoutes

# Full frontend test suite
npm test
```

### Edge Cases to Verify

- SPA launched from `/canvas/layout/node/1` (not standard `/canvas/` path): verify `extractEntityParams()` correctly resolves entity parameters.
- Template context missing from `drupalSettings` but present in API response: verify `setTemplateContext` dispatch on response.
- Component with `editable: undefined` (field not present): verify it defaults to editable behavior (backwards compatibility with non-per-content mode).
- Empty exposed slot (no per-content components yet): verify it renders as a valid drop zone.
- Deeply nested component inside an exposed slot: verify it inherits `editable: true` from the API annotation.

## Dependencies

- **`feat/expose-slot-dialog-ui`**: Provides the exposed slot state management (`editingExposedSlots`) and CSS classes (`.exposed`) that this branch builds upon with per-content variants.
- **`feat/per-content-editing-backend`**: Provides the API response shape with `editable` annotations, `contentTemplateId`, and `exposedSlots` that this frontend consumes.
