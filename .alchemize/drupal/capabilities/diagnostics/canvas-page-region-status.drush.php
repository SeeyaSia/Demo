<?php

/**
 * @file
 * Reports Canvas PageRegion configuration status.
 *
 * Shows which theme regions have Canvas PageRegion config entities,
 * whether Canvas or Block Layout is active, and which regions have
 * component trees populated.
 *
 * Usage: ddev drush php:script .alchemize/drupal/capabilities/diagnostics/canvas-page-region-status.drush.php
 */

$entityTypeManager = \Drupal::entityTypeManager();

try {
  $storage = $entityTypeManager->getStorage('page_region');
  $regions = $storage->loadMultiple();
}
catch (\Exception $e) {
  echo "ERROR: Could not load PageRegion entities. Is the Canvas module enabled?\n";
  echo "  " . $e->getMessage() . "\n";
  echo "=== Aborted ===\n";
  return;
}

if (empty($regions)) {
  echo "No Canvas PageRegion config entities found.\n";
  echo "Block Layout is active for all themes.\n";
  return;
}

// Group by theme.
$byTheme = [];
foreach ($regions as $id => $region) {
  $theme = $region->get('theme');
  $byTheme[$theme][$id] = $region;
}

echo "=== Canvas PageRegion Status Report ===\n\n";

foreach ($byTheme as $theme => $themeRegions) {
  $enabledCount = count(array_filter($themeRegions, fn($r) => $r->get('status')));
  echo "Theme: $theme (" . count($themeRegions) . " regions, $enabledCount enabled)\n";

  if ($enabledCount > 0) {
    echo "  ⚡ Canvas PageVariant is ACTIVE (Block Layout bypassed for this theme)\n";
  }
  else {
    echo "  ℹ️  No enabled PageRegions — Block Layout is still active\n";
  }
  echo "\n";

  $populated = [];
  $empty = [];

  foreach ($themeRegions as $id => $region) {
    $regionName = $region->get('region');
    $status = $region->get('status') ? 'enabled' : 'disabled';
    $tree = $region->get('component_tree') ?? [];
    $componentCount = is_array($tree) ? count($tree) : 0;

    if ($componentCount > 0) {
      $populated[] = [
        'region' => $regionName,
        'status' => $status,
        'components' => $componentCount,
        'tree' => $tree,
      ];
    }
    else {
      $empty[] = $regionName;
    }
  }

  if (!empty($populated)) {
    echo "  Populated regions:\n";
    foreach ($populated as $info) {
      echo sprintf("    %-25s [%s] %d component(s)\n",
        $info['region'], $info['status'], $info['components']);
      foreach ($info['tree'] as $item) {
        $componentId = $item['component_id'] ?? 'unknown';
        echo sprintf("      └─ %s\n", $componentId);
      }
    }
    echo "\n";
  }

  if (!empty($empty)) {
    echo "  Empty regions (" . count($empty) . "):\n";
    echo "    " . implode(', ', $empty) . "\n\n";
  }
}

echo "=== End Report ===\n";
