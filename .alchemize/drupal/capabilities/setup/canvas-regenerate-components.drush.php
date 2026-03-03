<?php

/**
 * @file
 * Regenerates Canvas component config entities.
 *
 * Forces Canvas to rediscover all SDC, Block, and JS components,
 * re-run requirements checks, and update component config entities.
 * Use after theme changes, module install/uninstall, or when components
 * appear duplicated or missing.
 *
 * Usage: ddev drush php:script .alchemize/drupal/capabilities/setup/canvas-regenerate-components.drush.php
 */

$entityTypeManager = \Drupal::entityTypeManager();
$storage = $entityTypeManager->getStorage('component');

// Count before.
$before = $storage->loadMultiple();
$beforeEnabled = count(array_filter($before, fn($c) => $c->status()));
$beforeDisabled = count(array_filter($before, fn($c) => !$c->status()));

echo "=== Canvas Component Regeneration ===\n\n";
echo "Before:\n";
echo "  Total: " . count($before) . " (enabled: $beforeEnabled, disabled: $beforeDisabled)\n\n";

// Regenerate.
echo "Regenerating components...\n";
try {
  $manager = \Drupal::service('Drupal\canvas\ComponentSource\ComponentSourceManager');
  $manager->generateComponents();
  echo "Done.\n\n";
}
catch (\Exception $e) {
  echo "ERROR: Component regeneration failed: " . $e->getMessage() . "\n";
  echo "=== Aborted ===\n";
  return;
}

// Rebuild cache.
echo "Rebuilding cache...\n";
try {
  drupal_flush_all_caches();
  echo "Done.\n\n";
}
catch (\Exception $e) {
  echo "WARNING: Cache rebuild failed: " . $e->getMessage() . "\n";
  echo "Try running: ddev drush cr\n\n";
}

// Count after.
$after = $storage->loadMultiple();
$afterEnabled = count(array_filter($after, fn($c) => $c->status()));
$afterDisabled = count(array_filter($after, fn($c) => !$c->status()));

echo "After:\n";
echo "  Total: " . count($after) . " (enabled: $afterEnabled, disabled: $afterDisabled)\n\n";

// Diff.
$beforeIds = array_keys($before);
$afterIds = array_keys($after);
$added = array_diff($afterIds, $beforeIds);
$removed = array_diff($beforeIds, $afterIds);

if (!empty($added)) {
  echo "New components discovered:\n";
  foreach ($added as $id) {
    echo "  + $id\n";
  }
  echo "\n";
}

if (!empty($removed)) {
  echo "Components removed:\n";
  foreach ($removed as $id) {
    echo "  - $id\n";
  }
  echo "\n";
}

if (empty($added) && empty($removed)) {
  echo "No components added or removed.\n\n";
}

// Check for status changes.
$statusChanges = [];
foreach ($after as $id => $component) {
  if (isset($before[$id]) && $before[$id]->status() !== $component->status()) {
    $oldStatus = $before[$id]->status() ? 'enabled' : 'disabled';
    $newStatus = $component->status() ? 'enabled' : 'disabled';
    $statusChanges[] = "$id: $oldStatus → $newStatus";
  }
}

if (!empty($statusChanges)) {
  echo "Status changes:\n";
  foreach ($statusChanges as $change) {
    echo "  ~ $change\n";
  }
  echo "\n";
}

echo "⚠️  Remember to export config: ddev drush cex -y\n";
echo "=== End ===\n";
