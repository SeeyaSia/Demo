<?php

/**
 * @file
 * Adds a Canvas component_tree field to a content type.
 *
 * Creates field storage, field instance, form display (canvas widget),
 * view display default (canvas renderer), and view display teaser (hidden).
 * All operations are idempotent — checks for existing config before creating.
 *
 * Parameters (environment variables):
 *   CONTENT_TYPE  — Machine name of the content type (default: "page")
 *   FIELD_NAME    — Machine name of the field (default: "field_canvas_body")
 *   FIELD_LABEL   — Human-readable label (default: "Canvas Body")
 *
 * Usage: ddev exec "CONTENT_TYPE=page FIELD_NAME=field_canvas_body FIELD_LABEL='Canvas Body' drush php:script .alchemize/drupal/capabilities/setup/add-canvas-field.drush.php"
 */

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

$content_type = getenv('CONTENT_TYPE') ?: 'page';
$field_name = getenv('FIELD_NAME') ?: 'field_canvas_body';
$field_label = getenv('FIELD_LABEL') ?: 'Canvas Body';

echo "=== Add Canvas Field ===\n\n";
echo "Content type: $content_type\n";
echo "Field name:   $field_name\n";
echo "Field label:  $field_label\n\n";

// ============================================================
// Step 1: Force plugin rediscovery
// ============================================================
// Ensures Canvas widgets/formatters/field types are known after module install.

\Drupal::service('plugin.manager.field.widget')->clearCachedDefinitions();
\Drupal::service('plugin.manager.field.formatter')->clearCachedDefinitions();
\Drupal::service('plugin.manager.field.field_type')->clearCachedDefinitions();

echo "Plugin caches cleared.\n\n";

// ============================================================
// Step 2: Create field storage (shared across bundles)
// ============================================================

if (!FieldStorageConfig::loadByName('node', $field_name)) {
  FieldStorageConfig::create([
    'field_name' => $field_name,
    'entity_type' => 'node',
    'type' => 'component_tree',
    'module' => 'canvas',
    'cardinality' => 1,
    'translatable' => TRUE,
  ])->save();
  echo "CREATED field storage node.$field_name\n";
} else {
  echo "Field storage node.$field_name already exists\n";
}

// ============================================================
// Step 3: Create field instance on bundle
// ============================================================

if (!FieldConfig::loadByName('node', $content_type, $field_name)) {
  FieldConfig::create([
    'field_name' => $field_name,
    'entity_type' => 'node',
    'bundle' => $content_type,
    'label' => $field_label,
    'required' => FALSE,
    'translatable' => FALSE,
  ])->save();
  echo "CREATED field instance node.$content_type.$field_name\n";
} else {
  echo "Field instance node.$content_type.$field_name already exists\n";
}

// ============================================================
// Step 4: Configure form display — add canvas widget
// ============================================================

$formDisplay = \Drupal::entityTypeManager()
  ->getStorage('entity_form_display')
  ->load("node.$content_type.default");
if ($formDisplay) {
  $formDisplay->setComponent($field_name, [
    'type' => 'canvas_component_tree_widget',
    'weight' => 121,
    'region' => 'content',
    'settings' => [],
  ])->save();
  echo "Form display configured\n";
} else {
  echo "ERROR: Form display node.$content_type.default not found\n";
  echo "=== Aborted ===\n";
  return;
}

// ============================================================
// Step 5: Configure view display (default) — canvas renderer
// ============================================================

$viewDisplay = \Drupal::entityTypeManager()
  ->getStorage('entity_view_display')
  ->load("node.$content_type.default");
if ($viewDisplay) {
  $viewDisplay->setComponent($field_name, [
    'type' => 'canvas_naive_render_sdc_tree',
    'label' => 'above',
    'weight' => 102,
    'region' => 'content',
    'settings' => [],
  ])->save();
  echo "View display (default) configured\n";
} else {
  echo "ERROR: View display node.$content_type.default not found\n";
  echo "=== Aborted ===\n";
  return;
}

// ============================================================
// Step 6: Configure view display (teaser) — hide canvas field
// ============================================================

$teaserDisplay = \Drupal::entityTypeManager()
  ->getStorage('entity_view_display')
  ->load("node.$content_type.teaser");
if ($teaserDisplay) {
  $teaserDisplay->removeComponent($field_name)->save();
  echo "View display (teaser) configured — field hidden\n";
} else {
  echo "No teaser display found — skipping\n";
}

echo "\n=== Done ===\n";
