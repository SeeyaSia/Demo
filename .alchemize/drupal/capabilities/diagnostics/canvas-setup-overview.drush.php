<?php

/**
 * @file
 * Canvas setup diagnostic overview.
 *
 * Reports on the complete Canvas environment: theme, components,
 * PageRegions, ContentTemplates, OAuth, and key configuration.
 * Useful as a first-run diagnostic when debugging Canvas issues.
 *
 * Usage: ddev drush php:script .alchemize/drupal/capabilities/diagnostics/canvas-setup-overview.drush.php
 */

echo "=== Canvas Setup Overview ===\n\n";

// ============================================================
// 1. Theme info
// ============================================================

echo "--- Theme Configuration ---\n";
$default_theme = \Drupal::config('system.theme')->get('default');
$admin_theme = \Drupal::config('system.theme')->get('admin');
echo "  Default theme: $default_theme\n";
echo "  Admin theme:   $admin_theme\n";

// Check if Canvas settings reference this theme.
$canvas_settings = \Drupal::config('canvas.settings');
if ($canvas_settings) {
  $all = $canvas_settings->getRawData();
  if (!empty($all)) {
    echo "  Canvas settings:\n";
    foreach ($all as $key => $value) {
      if ($key === '_core') continue;
      $display = is_scalar($value) ? $value : json_encode($value);
      echo "    $key: $display\n";
    }
  }
}
echo "\n";

// ============================================================
// 2. Components summary
// ============================================================

echo "--- Components Summary ---\n";
try {
  $comp_storage = \Drupal::entityTypeManager()->getStorage('component');
  $components = $comp_storage->loadMultiple();

  $by_source = [];
  $by_prefix = [];
  foreach ($components as $id => $comp) {
    $source = $comp->get('source') ?? 'unknown';
    $by_source[$source] = ($by_source[$source] ?? 0) + 1;

    // Group SDC by theme/module prefix.
    if ($source === 'sdc' && preg_match('/^sdc\.([^.]+)\./', $id, $m)) {
      $prefix = $m[1];
      $by_prefix[$prefix] = ($by_prefix[$prefix] ?? 0) + 1;
    }
  }

  $enabled = count(array_filter($components, fn($c) => $c->status()));
  $total = count($components);
  echo "  Total: $total ($enabled enabled, " . ($total - $enabled) . " disabled)\n";

  echo "  By source:\n";
  foreach ($by_source as $source => $count) {
    echo "    $source: $count\n";
  }

  if (!empty($by_prefix)) {
    echo "  SDC by provider:\n";
    foreach ($by_prefix as $prefix => $count) {
      $marker = ($prefix === $default_theme) ? ' (active theme)' : '';
      echo "    $prefix: $count$marker\n";
    }
  }
}
catch (\Exception $e) {
  echo "  ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// ============================================================
// 3. PageRegions
// ============================================================

echo "--- PageRegions ---\n";
try {
  $pr_storage = \Drupal::entityTypeManager()->getStorage('page_region');
  $regions = $pr_storage->loadMultiple();

  if (empty($regions)) {
    echo "  No PageRegion config entities found.\n";
    echo "  Block Layout is active for all themes.\n";
  }
  else {
    $enabled_regions = array_filter($regions, fn($r) => $r->get('status'));
    echo "  Total: " . count($regions) . " (" . count($enabled_regions) . " enabled)\n";

    if (count($enabled_regions) > 0) {
      echo "  Canvas PageVariant is ACTIVE (at least 1 enabled PageRegion)\n";
    }

    foreach ($regions as $id => $region) {
      $status = $region->get('status') ? 'enabled' : 'disabled';
      $tree = $region->get('component_tree') ?? [];
      $count = is_array($tree) ? count($tree) : 0;
      echo sprintf("    %-50s [%s] %d components\n", $id, $status, $count);
    }
  }
}
catch (\Exception $e) {
  echo "  ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// ============================================================
// 4. ContentTemplates
// ============================================================

echo "--- ContentTemplates ---\n";
try {
  $ct_storage = \Drupal::entityTypeManager()->getStorage('content_template');
  $templates = $ct_storage->loadMultiple();

  if (empty($templates)) {
    echo "  No ContentTemplate config entities found.\n";
  }
  else {
    echo "  Total: " . count($templates) . "\n";
    foreach ($templates as $id => $template) {
      $status = $template->status() ? 'enabled' : 'disabled';
      $tree = $template->get('component_tree') ?? [];
      $slots = $template->getExposedSlots();
      $tree_count = is_array($tree) ? count($tree) : 0;
      echo sprintf("    %-40s [%s] %d components, %d exposed slots\n",
        $id, $status, $tree_count, count($slots));
    }
  }
}
catch (\Exception $e) {
  echo "  ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// ============================================================
// 5. Canvas Pages
// ============================================================

echo "--- Canvas Pages ---\n";
try {
  $page_storage = \Drupal::entityTypeManager()->getStorage('canvas_page');
  $page_ids = $page_storage->getQuery()
    ->accessCheck(FALSE)
    ->execute();

  if (empty($page_ids)) {
    echo "  No Canvas Pages found.\n";
  }
  else {
    echo "  Total: " . count($page_ids) . "\n";
    $pages = $page_storage->loadMultiple($page_ids);
    foreach ($pages as $page) {
      $title = $page->label();
      $status = $page->get('status')->value ? 'published' : 'unpublished';
      $path = $page->get('path')->value ?? '(no path)';
      $components = $page->get('components')->getValue();
      $count = count($components);
      echo sprintf("    ID %-5s %-30s [%s] path=%s (%d components)\n",
        $page->id(), $title, $status, $path, $count);
    }
  }
}
catch (\Exception $e) {
  echo "  ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// ============================================================
// 6. OAuth status (Simple OAuth)
// ============================================================

echo "--- OAuth (Simple OAuth) ---\n";
$oauth_config = \Drupal::config('simple_oauth.settings');
if ($oauth_config) {
  $public_key = $oauth_config->get('public_key');
  $private_key = $oauth_config->get('private_key');
  echo "  Public key:  " . ($public_key ?? 'NOT SET') . "\n";
  echo "  Private key: " . ($private_key ?? 'NOT SET') . "\n";

  if ($public_key && file_exists($public_key)) {
    echo "  Public key file: EXISTS\n";
  }
  elseif ($public_key) {
    echo "  Public key file: MISSING!\n";
  }

  if ($private_key && file_exists($private_key)) {
    echo "  Private key file: EXISTS\n";
  }
  elseif ($private_key) {
    echo "  Private key file: MISSING!\n";
  }
}
else {
  echo "  Simple OAuth not configured.\n";
}

// Check for Canvas consumer.
try {
  $consumer_storage = \Drupal::entityTypeManager()->getStorage('consumer');
  $consumers = $consumer_storage->loadMultiple();
  $canvas_consumers = array_filter($consumers, function ($c) {
    $label = $c->label() ?? '';
    return str_contains(strtolower($label), 'canvas');
  });

  if (!empty($canvas_consumers)) {
    echo "  Canvas OAuth consumers:\n";
    foreach ($canvas_consumers as $consumer) {
      $scopes = [];
      if ($consumer->hasField('scopes') && !$consumer->get('scopes')->isEmpty()) {
        foreach ($consumer->get('scopes') as $scope) {
          $scopes[] = $scope->target_id ?? $scope->value ?? '?';
        }
      }
      echo "    - " . $consumer->label() . " (ID: " . $consumer->id() . ")\n";
      if (!empty($scopes)) {
        echo "      Scopes: " . implode(', ', $scopes) . "\n";
      }
    }
  }
  else {
    echo "  No Canvas-related OAuth consumers found.\n";
  }
}
catch (\Exception $e) {
  // Consumer entity type might not exist.
  echo "  Could not check consumers: " . $e->getMessage() . "\n";
}
echo "\n";

echo "=== End Overview ===\n";
