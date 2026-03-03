<?php

/**
 * @file
 * Reports Canvas code component status and sync state.
 *
 * Lists all JavaScriptComponent config entities with their status
 * (internal vs exposed), verifies corresponding Component config entities
 * exist, and checks config export sync state.
 *
 * Usage: ddev drush php:script .alchemize/drupal/capabilities/diagnostics/canvas-code-component-sync.drush.php
 */

$entityTypeManager = \Drupal::entityTypeManager();

echo "=== Canvas Code Component Sync Report ===\n\n";

// Load all JavaScriptComponent config entities.
try {
  $jsComponentStorage = $entityTypeManager->getStorage('js_component');
  $jsComponents = $jsComponentStorage->loadMultiple();
}
catch (\Exception $e) {
  echo "ERROR: Could not load JS components. Is the Canvas module enabled?\n";
  echo "  " . $e->getMessage() . "\n";
  echo "=== Aborted ===\n";
  return;
}

if (empty($jsComponents)) {
  echo "No code components found.\n";
  echo "Create one with: npx canvas scaffold --name my_component --dir ./canvas-components\n";
  echo "Or in the Canvas UI at /canvas\n\n";
  echo "=== End Report ===\n";
  return;
}

echo "Code components (" . count($jsComponents) . "):\n\n";

// Load corresponding Component config entities for comparison.
$componentStorage = $entityTypeManager->getStorage('component');

foreach ($jsComponents as $id => $jsComponent) {
  $label = $jsComponent->label() ?? $id;
  $status = $jsComponent->status();
  $statusLabel = $status ? '🟢 exposed (available to editors)' : '🟡 internal (draft)';

  echo "  $label ($id)\n";
  echo "    Status: $statusLabel\n";

  // Check for corresponding Component config entity.
  $componentId = 'js.' . $id;
  $component = $componentStorage->load($componentId);
  if ($component) {
    $compStatus = $component->status() ? 'enabled' : 'disabled';
    echo "    Component entity: ✅ exists ($componentId, $compStatus)\n";
  }
  elseif ($status) {
    echo "    Component entity: ⚠️ MISSING — exposed JS component should have a Component entity\n";
  }
  else {
    echo "    Component entity: ℹ️  none (expected for internal components)\n";
  }

  echo "\n";
}

// Check config export sync state.
echo "--- Config Export Sync ---\n";
$configSyncDir = \Drupal\Core\Site\Settings::get('config_sync_directory', '../config/alchemizetechwebsite');

// Check for JS component config files.
$jsConfigFiles = glob($configSyncDir . '/canvas.js_component.*.yml');
$dbCount = count($jsComponents);
$fileCount = is_array($jsConfigFiles) ? count($jsConfigFiles) : 0;

echo "  JS components in database: $dbCount\n";
echo "  JS component config files: $fileCount\n";

if ($dbCount !== $fileCount) {
  echo "  ⚠️  Mismatch! Run 'ddev drush cex -y' to export, or 'ddev drush cim -y' to import.\n";
}
else {
  echo "  ✅ Counts match.\n";
}

echo "\nℹ️  After uploading via CLI, always run: ddev drush cex -y\n";
echo "=== End Report ===\n";
