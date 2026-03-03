<?php

/**
 * @file
 * Runs all Canvas test scripts and reports overall results.
 *
 * Orchestrates the full Canvas test suite by executing each test as a
 * separate drush php:script process to avoid function name collisions.
 *
 * Tests:
 *   1. canvas-test-page-building — page entity, leaf components, rendering
 *   2. canvas-test-nested-components — deep nesting, row/column/card/accordion
 *   3. canvas-test-content-template — template lifecycle, exposed slots, validation
 *   4. canvas-test-per-content-editing — slot injection, merged tree, rendering
 *   5. canvas-build-page — existing CLI page builder (regression)
 *
 * Usage: ddev drush php:script .alchemize/drupal/capabilities/tests/canvas-test-run-all.drush.php
 */

echo "======================================================\n";
echo "       Canvas Feature Test Suite                       \n";
echo "======================================================\n\n";

$tests_path = dirname(__FILE__);
$capabilities_path = dirname($tests_path);

// Tests map: script name => [subfolder, description]
$tests = [
  'canvas-test-page-building'      => ['tests',      'Page Building (leaf components, rendering)'],
  'canvas-test-nested-components'   => ['tests',      'Nested Components (row/column/card/accordion)'],
  'canvas-test-content-template'    => ['tests',      'Content Templates (exposed slots, validation)'],
  'canvas-test-per-content-editing' => ['tests',      'Per-Content Editing (slot injection, merged tree)'],
  'canvas-build-page'              => ['generators',  'CLI Page Builder (regression)'],
];

$results = [];
$overall_pass = TRUE;

foreach ($tests as $script => $test_info) {
  [$subfolder, $description] = $test_info;
  $file = "$capabilities_path/$subfolder/$script.drush.php";
  $relative = ".alchemize/drupal/capabilities/$subfolder/$script.drush.php";

  if (!file_exists($file)) {
    echo "SKIP: $script.drush.php not found\n\n";
    $results[$script] = 'SKIP';
    continue;
  }

  echo "------------------------------------------------------\n";
  echo " Running: $description\n";
  echo " Script:  $script.drush.php\n";
  echo "------------------------------------------------------\n\n";

  // Run as separate drush process for function isolation.
  // Use absolute path since drush cwd may differ.
  $cmd = "drush php:script '$file' 2>&1";
  $output = shell_exec($cmd);

  if ($output === NULL) {
    echo "ERROR: Failed to execute script as subprocess.\n";
    echo "Run individually instead:\n";
    echo "  ddev drush php:script $relative\n";
    $results[$script] = 'ERROR';
    $overall_pass = FALSE;
    continue;
  }

  echo $output;

  // Parse results
  if (str_contains($output, 'STATUS: PASS')) {
    $results[$script] = 'PASS';
  }
  elseif (str_contains($output, 'STATUS: FAIL')) {
    $results[$script] = 'FAIL';
    $overall_pass = FALSE;
  }
  elseif (str_contains($output, '=== Done ===')) {
    $results[$script] = 'PASS';
  }
  elseif (str_contains($output, 'Aborted')) {
    $results[$script] = 'ABORT';
    $overall_pass = FALSE;
  }
  else {
    $results[$script] = 'UNKNOWN';
    $overall_pass = FALSE;
  }

  echo "\n";
}

// ============================================================
// Summary
// ============================================================

echo "======================================================\n";
echo "               Test Suite Summary                      \n";
echo "======================================================\n";

foreach ($results as $script => $status) {
  $icon = match ($status) {
    'PASS' => 'PASS',
    'FAIL' => 'FAIL',
    'SKIP' => 'SKIP',
    'ABORT' => 'ABRT',
    'ERROR' => 'ERR!',
    default => '????',
  };
  echo sprintf("  [%s]  %s\n", $icon, $script);
}

echo "------------------------------------------------------\n";
$overall = $overall_pass ? 'ALL TESTS PASSED' : 'SOME TESTS FAILED';
echo "  $overall\n";
echo "======================================================\n";
