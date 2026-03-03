<?php

/**
 * @file
 * Creates Canvas PageRegion entities for a theme.
 *
 * Creates PageRegion entities with empty component trees for the specified
 * theme and regions. Required for CanvasPageVariant to activate.
 * All operations are idempotent — checks for existing entities before creating.
 *
 * Parameters (environment variables):
 *   THEME   — Theme machine name (default: "alchemize_forge")
 *   REGIONS — Comma-separated region names (default: "none,breadcrumb,highlighted")
 *
 * Usage: ddev exec "THEME=alchemize_forge REGIONS=none,breadcrumb,highlighted drush php:script .alchemize/drupal/capabilities/setup/create-page-regions.drush.php"
 */

use Drupal\canvas\Entity\PageRegion;

$theme = getenv('THEME') ?: 'alchemize_forge';
$regions_str = getenv('REGIONS') ?: 'none,breadcrumb,highlighted';
$regions = array_map('trim', explode(',', $regions_str));

echo "=== Create Page Regions ===\n\n";
echo "Theme:   $theme\n";
echo "Regions: " . implode(', ', $regions) . "\n\n";

$created = 0;
$skipped = 0;

foreach ($regions as $region) {
  if (empty($region)) {
    continue;
  }

  $id = "$theme.$region";

  if (PageRegion::load($id)) {
    echo "PageRegion $id already exists\n";
    $skipped++;
    continue;
  }

  PageRegion::create([
    'theme' => $theme,
    'region' => $region,
    'component_tree' => [],
  ])->save();
  echo "CREATED PageRegion $id\n";
  $created++;
}

echo "\nSummary: $created created, $skipped already existed\n";
echo "\n=== Done ===\n";
