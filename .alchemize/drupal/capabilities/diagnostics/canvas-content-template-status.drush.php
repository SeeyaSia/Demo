<?php

/**
 * @file
 * Reports on all Canvas ContentTemplate config entities.
 *
 * Shows each template's status, component tree size, exposed slots,
 * and whether field_component_tree is configured on the target bundle.
 *
 * Usage: ddev drush php:script .alchemize/drupal/capabilities/diagnostics/canvas-content-template-status.drush.php
 */

use Drupal\canvas\Entity\ContentTemplate;

$entityTypeManager = \Drupal::entityTypeManager();

try {
  $storage = $entityTypeManager->getStorage('content_template');
  $templates = $storage->loadMultiple();
}
catch (\Exception $e) {
  echo "ERROR: Could not load ContentTemplate entities. Is the Canvas module enabled?\n";
  echo "  " . $e->getMessage() . "\n";
  echo "=== Aborted ===\n";
  return;
}

if (empty($templates)) {
  echo "No Canvas ContentTemplate config entities found.\n";
  echo "Create one via Canvas UI or programmatically.\n";
  return;
}

echo "=== Canvas ContentTemplate Status Report ===\n\n";
echo "Total templates: " . count($templates) . "\n\n";

$field_manager = \Drupal::service('entity_field.manager');

foreach ($templates as $id => $template) {
  $status = $template->status() ? 'enabled' : 'disabled';
  $entity_type = $template->get('content_entity_type_id');
  $bundle = $template->get('content_entity_type_bundle');
  $view_mode = $template->get('content_entity_type_view_mode');
  $tree = $template->get('component_tree') ?? [];
  $component_count = is_array($tree) ? count($tree) : 0;
  $exposed_slots = $template->getExposedSlots();

  echo "--- $id [$status] ---\n";
  echo "  Target: $entity_type / $bundle / $view_mode\n";
  echo "  Components in tree: $component_count\n";

  // List component IDs in tree.
  if ($component_count > 0) {
    echo "  Tree components:\n";
    foreach ($tree as $item) {
      $comp_id = $item['component_id'] ?? 'unknown';
      $parent = !empty($item['parent_uuid']) ? "parent={$item['parent_uuid']}" : 'ROOT';
      $slot = !empty($item['slot']) ? "slot={$item['slot']}" : '';
      echo "    - $comp_id ($parent $slot)\n";
    }
  }

  // Exposed slots.
  if (!empty($exposed_slots)) {
    echo "  Exposed slots (" . count($exposed_slots) . "):\n";
    foreach ($exposed_slots as $key => $slot) {
      echo "    $key: component_uuid={$slot['component_uuid']}, slot={$slot['slot_name']}, label=\"{$slot['label']}\"\n";
    }
  }
  else {
    echo "  Exposed slots: none\n";
  }

  // Check for any component_tree field on the target bundle.
  try {
    $field_defs = $field_manager->getFieldDefinitions($entity_type, $bundle);
    $ct_fields = [];
    foreach ($field_defs as $fname => $fdef) {
      if ($fdef->getType() === 'component_tree') {
        $ct_fields[] = $fname;
      }
    }
    if (!empty($ct_fields)) {
      echo "  Component tree field(s): " . implode(', ', $ct_fields) . "\n";
    }
    else {
      if (!empty($exposed_slots)) {
        echo "  Component tree field: MISSING (required for per-content editing!)\n";
      }
      else {
        echo "  Component tree field: not present (not needed — no exposed slots)\n";
      }
    }
  }
  catch (\Exception $e) {
    echo "  Component tree field: could not check (" . $e->getMessage() . ")\n";
  }

  // Validation check.
  try {
    $violations = $template->getTypedData()->validate();
    if ($violations->count() > 0) {
      echo "  Validation: FAILED (" . $violations->count() . " violations)\n";
      foreach ($violations as $v) {
        echo "    - " . $v->getPropertyPath() . ': ' . $v->getMessage() . "\n";
      }
    }
    else {
      echo "  Validation: PASSED\n";
    }
  }
  catch (\Exception $e) {
    echo "  Validation: ERROR (" . $e->getMessage() . ")\n";
  }

  echo "\n";
}

echo "=== End Report ===\n";
