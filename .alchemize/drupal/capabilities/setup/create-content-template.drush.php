<?php

/**
 * @file
 * Creates a Canvas ContentTemplate with a wrapper and exposed slot.
 *
 * Creates a ContentTemplate for a content type + view mode combination,
 * with a single root wrapper component and its content slot exposed for
 * per-entity editing. This is the correct setup pattern — an empty
 * component_tree (no wrapper) causes downstream generators to silently fail.
 *
 * Parameters (environment variables):
 *   CONTENT_TYPE — Machine name of the content type (default: "page")
 *   VIEW_MODE    — View mode (default: "full")
 *   FIELD_NAME   — Field name for exposed slot key (default: "field_canvas_body")
 *   SLOT_LABEL   — Human-readable label for exposed slot (default: "Page content")
 *
 * Usage: ddev exec "CONTENT_TYPE=page VIEW_MODE=full FIELD_NAME=field_canvas_body SLOT_LABEL='Page content' drush php:script .alchemize/drupal/capabilities/setup/create-content-template.drush.php"
 */

use Drupal\canvas\Entity\ContentTemplate;

require_once __DIR__ . '/../lib/canvas-lib.php';

$content_type = getenv('CONTENT_TYPE') ?: 'page';
$view_mode = getenv('VIEW_MODE') ?: 'full';
$field_name = getenv('FIELD_NAME') ?: 'field_canvas_body';
$slot_label = getenv('SLOT_LABEL') ?: 'Page content';

$template_id = "node.$content_type.$view_mode";

echo "=== Create Content Template ===\n\n";
echo "Template ID: $template_id\n";
echo "Field name:  $field_name\n";
echo "Slot label:  $slot_label\n\n";

// ============================================================
// Step 1: Check for existing template
// ============================================================

$template = ContentTemplate::load($template_id);
if ($template) {
  // Check if it already has exposed slots — don't overwrite a good template.
  $existing_slots = $template->getExposedSlots();
  if (!empty($existing_slots)) {
    echo "Content template $template_id already exists with " . count($existing_slots) . " exposed slot(s):\n";
    foreach ($existing_slots as $key => $slot) {
      echo "  $key: component_uuid={$slot['component_uuid']}, slot_name={$slot['slot_name']}, label={$slot['label']}\n";
    }
    echo "\n=== Done (no changes) ===\n";
    return;
  }
  echo "Content template $template_id exists but has no exposed slots — updating.\n\n";
} else {
  echo "Creating new content template $template_id\n\n";
  $template = ContentTemplate::create([
    'content_entity_type_id' => 'node',
    'content_entity_type_bundle' => $content_type,
    'content_entity_type_view_mode' => $view_mode,
    'component_tree' => [],
    'status' => TRUE,
  ]);
}

// ============================================================
// Step 2: Load wrapper component version
// ============================================================

[$theme, $components, $versions] = canvas_lib_init(['wrapper'], verbose: TRUE);

if (empty($versions)) {
  echo "ERROR: Could not load wrapper component.\n";
  echo "=== Aborted ===\n";
  return;
}

// ============================================================
// Step 3: Build component tree with wrapper
// ============================================================
// NOTE: ContentTemplate is a config entity. Its config schema requires that
// null-valued keys (parent_uuid, slot, label) are OMITTED rather than set to
// NULL, and inputs must be an array (not json_encode'd). So we build the tree
// item manually instead of using canvas_tree_item() which is designed for
// content entity fields.

$wrapper_uuid = canvas_uuid('template_wrapper');

$component_tree = [
  [
    'uuid' => $wrapper_uuid,
    'component_id' => $components['wrapper'],
    'component_version' => $versions['wrapper'],
    'inputs' => ['html_tag' => 'section'],
  ],
];

echo "\nTree structure:\n";
echo "  Wrapper (uuid: $wrapper_uuid) — root, exposed slot: content\n";

// ============================================================
// Step 4: Set tree and exposed slots
// ============================================================

$template->set('component_tree', $component_tree);
$template->set('exposed_slots', [
  $field_name => [
    'component_uuid' => $wrapper_uuid,
    'slot_name' => 'content',
    'label' => $slot_label,
  ],
]);

// ============================================================
// Step 5: Validate before saving
// ============================================================

echo "\nValidating...\n";
$violations = $template->getTypedData()->validate();
if ($violations->count() > 0) {
  echo "VALIDATION ERRORS:\n";
  foreach ($violations as $violation) {
    echo "  - " . $violation->getMessage() . " (at " . $violation->getPropertyPath() . ")\n";
  }
  echo "ERROR: Template failed validation.\n";
  echo "=== Aborted ===\n";
  return;
}

echo "Validation passed!\n";
$template->save();
echo "Content template saved successfully.\n";

// ============================================================
// Step 6: Verify
// ============================================================

$reloaded = ContentTemplate::load($template_id);
$tree = $reloaded->get('component_tree');
$slots = $reloaded->getExposedSlots();

echo "\nVerification:\n";
echo "  Components in tree: " . count($tree) . "\n";
echo "  Exposed slots: " . count($slots) . "\n";
foreach ($slots as $key => $slot) {
  echo "    $key: component_uuid={$slot['component_uuid']}, slot_name={$slot['slot_name']}, label={$slot['label']}\n";
}

if (empty($slots)) {
  echo "WARNING: No exposed slots found after save — template may need manual review.\n";
}

echo "\n=== Done ===\n";
