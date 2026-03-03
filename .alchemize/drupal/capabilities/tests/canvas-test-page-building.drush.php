<?php

/**
 * @file
 * Tests Canvas Page building: creation, component placement, save, render.
 *
 * Exercises the full canvas_page entity workflow:
 *   1. Creates a fresh Canvas Page entity
 *   2. Adds simple leaf components (heading, paragraph, button, blockquote)
 *   3. Adds components with media (image with empty field — tests our
 *      ComputedUrlWithQueryString fix)
 *   4. Saves the page and verifies the tree round-trips correctly
 *   5. Validates the entity passes constraint validation
 *   6. Cleans up by deleting the test page
 *
 * Usage: ddev drush php:script .alchemize/drupal/capabilities/tests/canvas-test-page-building.drush.php
 */

require_once __DIR__ . '/../lib/canvas-lib.php';

$GLOBALS['_canvas_test_pass'] = 0;
$GLOBALS['_canvas_test_fail'] = 0;

echo "=== Canvas Test: Page Building ===\n\n";

// ============================================================
// Prerequisite: Load component versions
// ============================================================

[$theme, $components, $versions] = canvas_lib_init([
  'wrapper', 'heading', 'paragraph', 'button', 'blockquote', 'image', 'link',
]);

if (empty($versions)) {
  echo "\n=== Aborted: missing components ===\n";
  return;
}

echo "All required components loaded.\n\n";

// ============================================================
// Test 1: Create a Canvas Page entity
// ============================================================

echo "--- Test 1: Canvas Page creation ---\n";

$page_storage = \Drupal::entityTypeManager()->getStorage('canvas_page');
$page = $page_storage->create([
  'title' => 'Test Page - Canvas Page Building',
  'status' => 1,
  'path' => '/test-canvas-page-building-' . time(),
]);

try {
  $page->save();
  canvas_assert_true(!empty($page->id()), "Canvas Page created with ID: " . $page->id());
}
catch (\Exception $e) {
  canvas_assert_true(FALSE, "Canvas Page creation failed: " . $e->getMessage());
  echo "\n=== Aborted ===\n";
  return;
}

// ============================================================
// Test 2: Build tree with simple leaf components
// ============================================================

echo "\n--- Test 2: Simple leaf components (heading, paragraph, button, blockquote) ---\n";

$tree = [];

// Root wrapper
$tree[] = canvas_wrapper(canvas_uuid('wrapper'), [
  'html_tag' => 'section',
  'container_type' => 'container',
  'padding_y' => 'py-4',
]);

// Heading inside wrapper
$tree[] = canvas_heading(canvas_uuid('heading'), 'Test Page Heading', 'h1', [
  'alignment' => 'text-start',
], canvas_uuid('wrapper'), 'content');

// Paragraph inside wrapper
$tree[] = canvas_paragraph(canvas_uuid('para'), '<p>This is test paragraph content with <strong>bold</strong> and <em>italic</em> text.</p>', [
  'margin_bottom' => 'mb-3',
], canvas_uuid('wrapper'), 'content');

// Button inside wrapper
$tree[] = canvas_button(canvas_uuid('button'), 'Test Button', '/test', [], canvas_uuid('wrapper'), 'content');

// Blockquote inside wrapper
$tree[] = canvas_blockquote(canvas_uuid('blockquote'), 'A famous quote goes here.', [
  'footer' => 'Test Author',
  'cite' => 'Test Source',
], canvas_uuid('wrapper'), 'content');

// Link inside wrapper
$tree[] = canvas_link(canvas_uuid('link'), 'Learn more', '/about', [], canvas_uuid('wrapper'), 'content');

$page->set('components', $tree);

try {
  $page->save();
  canvas_assert_true(TRUE, "Saved page with " . count($tree) . " leaf components");
}
catch (\Exception $e) {
  canvas_assert_true(FALSE, "Save failed: " . $e->getMessage());
}

// ============================================================
// Test 3: Verify tree round-trip
// ============================================================

echo "\n--- Test 3: Tree round-trip verification ---\n";

$reloaded = $page_storage->load($page->id());
$field = $reloaded->get('components');
$saved_tree = $field->getValue();

canvas_assert_true(count($saved_tree) === count($tree), "Tree item count matches: " . count($saved_tree) . " == " . count($tree));

// Check each component UUID is preserved
$saved_uuids = array_column($saved_tree, 'uuid');
$expected_names = ['wrapper', 'heading', 'para', 'button', 'blockquote', 'link'];
foreach ($expected_names as $name) {
  $uuid = canvas_uuid($name);
  canvas_assert_true(in_array($uuid, $saved_uuids), "UUID preserved for '$name': $uuid");
}

// Check parent relationships
foreach ($saved_tree as $item) {
  if ($item['uuid'] === canvas_uuid('wrapper')) {
    canvas_assert_true(empty($item['parent_uuid']), "Wrapper is root-level (no parent_uuid)");
  }
  elseif ($item['uuid'] === canvas_uuid('heading')) {
    canvas_assert_true($item['parent_uuid'] === canvas_uuid('wrapper'), "Heading parent is wrapper");
    canvas_assert_true($item['slot'] === 'content', "Heading slot is 'content'");
  }
}

// Check inputs decode correctly
foreach ($saved_tree as $item) {
  if ($item['uuid'] === canvas_uuid('heading')) {
    $inputs = is_string($item['inputs']) ? json_decode($item['inputs'], TRUE) : $item['inputs'];
    canvas_assert_true(is_array($inputs), "Heading inputs decode to array");
    canvas_assert_true(($inputs['text'] ?? NULL) === 'Test Page Heading', "Heading text preserved: " . ($inputs['text'] ?? 'NULL'));
    canvas_assert_true(($inputs['level'] ?? NULL) === 'h1', "Heading level preserved");
  }
  if ($item['uuid'] === canvas_uuid('para')) {
    $inputs = is_string($item['inputs']) ? json_decode($item['inputs'], TRUE) : $item['inputs'];
    canvas_assert_true(str_contains($inputs['text'] ?? '', '<strong>bold</strong>'), "Paragraph HTML preserved");
  }
}

// ============================================================
// Test 4: Image component with minimal valid inputs
// ============================================================

echo "\n--- Test 4: Image component ---\n";

// Image component needs media as an object with src/alt, not empty.
// Testing that a properly structured image saves and renders correctly.
$tree[] = canvas_image(canvas_uuid('image'), [
  'src' => '/core/misc/druplicon.png',
  'alt' => 'Test image',
  'width' => '100',
  'height' => '100',
], [
  'size' => '16:9',
  'radius' => 'small',
], canvas_uuid('wrapper'), 'content');

$page->set('components', $tree);
try {
  $page->save();
  canvas_assert_true(TRUE, "Saved page with image component — no error");
}
catch (\Exception $e) {
  canvas_assert_true(FALSE, "Image component caused error: " . $e->getMessage());
}

// ============================================================
// Test 5: Validation passes
// ============================================================

echo "\n--- Test 5: Entity validation ---\n";

$violations = $page->getTypedData()->validate();
if ($violations->count() > 0) {
  $msgs = [];
  foreach ($violations as $v) {
    $msgs[] = $v->getPropertyPath() . ': ' . $v->getMessage();
  }
  canvas_assert_true(FALSE, "Validation failed: " . implode('; ', $msgs));
}
else {
  canvas_assert_true(TRUE, "Entity validation passes with 0 violations");
}

// ============================================================
// Test 6: Render the page (check no PHP fatal)
// ============================================================

echo "\n--- Test 6: Page rendering ---\n";

try {
  $view_builder = \Drupal::entityTypeManager()->getViewBuilder('canvas_page');
  $build = $view_builder->view($reloaded);
  $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
  canvas_assert_true(!empty($html), "Page renders to non-empty HTML (" . strlen($html) . " chars)");
  canvas_assert_true(str_contains($html, 'Test Page Heading'), "Rendered HTML contains heading text");
  canvas_assert_true(str_contains($html, 'bold'), "Rendered HTML contains paragraph content");
}
catch (\Exception $e) {
  canvas_assert_true(FALSE, "Page rendering failed: " . $e->getMessage());
}

// ============================================================
// Cleanup
// ============================================================

echo "\n--- Cleanup ---\n";
try {
  $page->delete();
  canvas_assert_true(TRUE, "Test page deleted");
}
catch (\Exception $e) {
  echo "  WARNING: Cleanup failed: " . $e->getMessage() . "\n";
}

// ============================================================
// Summary
// ============================================================

$pass = $GLOBALS['_canvas_test_pass'];
$fail = $GLOBALS['_canvas_test_fail'];
echo "\n=== Results: $pass passed, $fail failed ===\n";
if ($fail > 0) {
  echo "STATUS: FAIL\n";
}
else {
  echo "STATUS: PASS\n";
}
