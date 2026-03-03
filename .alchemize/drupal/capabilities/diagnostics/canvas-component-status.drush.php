<?php

/**
 * @file
 * Lists all Canvas components and their status.
 *
 * Reports enabled/disabled SDC, Block, and JS components,
 * deduplication status, and components that fail requirements.
 *
 * Usage: ddev drush php:script .alchemize/drupal/capabilities/diagnostics/canvas-component-status.drush.php
 */

$entityTypeManager = \Drupal::entityTypeManager();

try {
  $storage = $entityTypeManager->getStorage('component');
  $components = $storage->loadMultiple();
}
catch (\Exception $e) {
  echo "ERROR: Could not load Canvas components. Is the Canvas module enabled?\n";
  echo "  " . $e->getMessage() . "\n";
  echo "=== Aborted ===\n";
  return;
}

if (empty($components)) {
  echo "No Canvas components found.\n";
  return;
}

// Group components by source type.
$grouped = [
  'sdc' => [],
  'block' => [],
  'js' => [],
  'other' => [],
];

foreach ($components as $id => $component) {
  $source = $component->get('source') ?? 'unknown';
  $group = isset($grouped[$source]) ? $source : 'other';
  $grouped[$group][$id] = $component;
}

echo "=== Canvas Component Status Report ===\n\n";

// Summary.
$enabled = array_filter($components, fn($c) => $c->status());
$disabled = array_filter($components, fn($c) => !$c->status());
echo "Total components: " . count($components) . "\n";
echo "Enabled: " . count($enabled) . "\n";
echo "Disabled: " . count($disabled) . "\n\n";

// SDC Components.
echo "--- SDC Components (" . count($grouped['sdc']) . ") ---\n";
foreach ($grouped['sdc'] as $id => $component) {
  $status = $component->status() ? '✅ enabled' : '❌ disabled';
  $label = $component->label() ?? $id;
  echo sprintf("  %-60s %s\n", $id, $status);
}
echo "\n";

// Block Components.
echo "--- Block Components (" . count($grouped['block']) . ") ---\n";
foreach ($grouped['block'] as $id => $component) {
  $status = $component->status() ? '✅ enabled' : '❌ disabled';
  echo sprintf("  %-60s %s\n", $id, $status);
}
echo "\n";

// JS (Code) Components.
echo "--- JS Code Components (" . count($grouped['js']) . ") ---\n";
if (empty($grouped['js'])) {
  echo "  (none)\n";
}
foreach ($grouped['js'] as $id => $component) {
  $status = $component->status() ? '✅ enabled' : '❌ disabled';
  echo sprintf("  %-60s %s\n", $id, $status);
}
echo "\n";

// Deduplication report: identify canvas_bootstrap components overridden by the active theme.
$theme = \Drupal::config('system.theme')->get('default');
$theme_prefix = "sdc.{$theme}.";
echo "--- Deduplication Status (theme: $theme) ---\n";
$theme_names = [];
$bootstrap_module_names = [];
foreach ($grouped['sdc'] as $id => $component) {
  if (str_starts_with($id, $theme_prefix)) {
    $name = str_replace($theme_prefix, '', $id);
    $theme_names[$name] = $component->status();
  }
  if (str_starts_with($id, 'sdc.canvas_bootstrap.')) {
    $name = str_replace('sdc.canvas_bootstrap.', '', $id);
    $bootstrap_module_names[$name] = $component->status();
  }
}

$overridden = array_intersect_key($bootstrap_module_names, $theme_names);
if (!empty($overridden)) {
  echo "  Components overridden by theme ($theme wins):\n";
  foreach ($overridden as $name => $moduleStatus) {
    $themeStatus = $theme_names[$name] ? 'enabled' : 'disabled';
    $modStatus = $moduleStatus ? '⚠️ ENABLED (should be disabled!)' : 'disabled (correct)';
    echo sprintf("    %-30s theme=%s  module=%s\n", $name, $themeStatus, $modStatus);
  }
}
else {
  echo "  No overlapping components found.\n";
}
echo "\n";

echo "=== End Report ===\n";
