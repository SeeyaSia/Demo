<?php

/**
 * @file
 * Tests per-content editing: slot injection, merged tree, rendering.
 *
 * Exercises the per-content editing workflow where each article node
 * can have its own components inside the template's exposed slot:
 *   1. Verifies the article template has an exposed slot configured
 *   2. Verifies the article content type has field_component_tree
 *   3. Creates a test article node
 *   4. Adds components to the article's field_component_tree (slot content)
 *   5. Tests getMergedComponentTree() — template + slot content
 *   6. Tests that slot components appear in the merged tree with correct parent
 *   7. Tests rendering the merged tree
 *   8. Tests the SlotTreeExtractor for template UUID identification
 *   9. Tests injectSubTreeItemList for correct slot injection
 *   10. Cleans up test article
 *
 * Usage: ddev drush php:script .alchemize/drupal/capabilities/tests/canvas-test-per-content-editing.drush.php
 */

use Drupal\canvas\Entity\ContentTemplate;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList;

require_once __DIR__ . '/../lib/canvas-lib.php';

$GLOBALS['_canvas_test_pass'] = 0;
$GLOBALS['_canvas_test_fail'] = 0;

echo "=== Canvas Test: Per-Content Editing ===\n\n";

// ============================================================
// Prerequisites
// ============================================================

[$theme, $components, $versions] = canvas_lib_init([
  'wrapper', 'heading', 'paragraph', 'row', 'column',
]);

if (empty($versions)) {
  echo "=== Aborted: missing components ===\n";
  return;
}

// ============================================================
// Test 1: Article template has exposed slot
// ============================================================

echo "--- Test 1: Template exposed slot setup ---\n";

$template = ContentTemplate::load('node.article.full');
canvas_assert_true($template !== NULL, "Article template exists");
if (!$template) {
  echo "=== Aborted: no article template ===\n";
  return;
}

$exposed_slots = $template->getExposedSlots();
canvas_assert_true(isset($exposed_slots['article_content']), "Exposed slot 'article_content' exists");

$slot_config = $exposed_slots['article_content'] ?? NULL;
if (!$slot_config) {
  echo "=== Aborted: no article_content exposed slot ===\n";
  return;
}

$slot_component_uuid = $slot_config['component_uuid'];
$slot_name = $slot_config['slot_name'];
echo "  Slot target: component=$slot_component_uuid, slot=$slot_name\n";

// ============================================================
// Test 2: Article content type has Canvas field (slot key = field name)
// ============================================================

echo "\n--- Test 2: Canvas field on article (per-slot naming) ---\n";

$slot_key = array_key_first($exposed_slots);
$field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'article');

// The slot key IS the field machine name in the new architecture.
// Also check for legacy field_component_tree as fallback.
$canvas_field_name = isset($field_definitions[$slot_key]) ? $slot_key : 'field_component_tree';
canvas_assert_true(isset($field_definitions[$canvas_field_name]), "Canvas field '$canvas_field_name' exists on article");

if (isset($field_definitions[$canvas_field_name])) {
  $field_def = $field_definitions[$canvas_field_name];
  canvas_assert_true($field_def->getType() === 'component_tree', "Field type is 'component_tree'");
}
echo "  Using Canvas field: $canvas_field_name\n";

// ============================================================
// Test 3: Create test article node
// ============================================================

echo "\n--- Test 3: Create test article node ---\n";

$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$test_node = $node_storage->create([
  'type' => 'article',
  'title' => 'Test Article - Per-Content Editing - ' . time(),
  'status' => 1,
  'uid' => 1,
]);

try {
  $test_node->save();
  canvas_assert_true(!empty($test_node->id()), "Article node created with ID: " . $test_node->id());
}
catch (\Exception $e) {
  canvas_assert_true(FALSE, "Article creation failed: " . $e->getMessage());
  echo "=== Aborted ===\n";
  return;
}

// ============================================================
// Test 4: Add components to field_component_tree (slot content)
// ============================================================

echo "\n--- Test 4: Add slot components to article ---\n";

// Slot content: components that live inside the exposed slot.
// parent_uuid for root-level slot items = the exposed slot's component UUID.
// slot = the exposed slot's slot_name.
$slot_items = [
  // Heading inside the exposed slot (root level of slot content)
  canvas_heading(canvas_uuid('slot_heading'), 'Article Body Heading', 'h2', [], $slot_component_uuid, $slot_name),

  // Paragraph inside the exposed slot
  canvas_paragraph(canvas_uuid('slot_para'), '<p>This article has custom per-content body text inside the exposed slot.</p>', [
    'margin_bottom' => 'mb-3',
  ], $slot_component_uuid, $slot_name),

  // Nested: Row > 2 Columns > Paragraphs (inside the exposed slot)
  canvas_row(canvas_uuid('slot_row'), [
    'row_cols' => 'row-cols-1',
    'row_cols_md' => 'row-cols-md-2',
    'gap' => 'g-3',
  ], $slot_component_uuid, $slot_name),

  canvas_column(canvas_uuid('slot_col1'), [
    'col' => 'col',
  ], canvas_uuid('slot_row'), 'row'),

  canvas_paragraph(canvas_uuid('slot_para2'), '<p>Left column content for this specific article.</p>', [], canvas_uuid('slot_col1'), 'column'),

  canvas_column(canvas_uuid('slot_col2'), [
    'col' => 'col',
  ], canvas_uuid('slot_row'), 'row'),

  canvas_paragraph(canvas_uuid('slot_para3'), '<p>Right column content for this specific article.</p>', [], canvas_uuid('slot_col2'), 'column'),
];

$test_node->set($canvas_field_name, $slot_items);
try {
  $test_node->save();
  canvas_assert_true(TRUE, "Saved article with " . count($slot_items) . " slot components");
}
catch (\Exception $e) {
  canvas_assert_true(FALSE, "Save with slot components failed: " . $e->getMessage());
}

// ============================================================
// Test 5: Verify slot items round-trip
// ============================================================

echo "\n--- Test 5: Slot items round-trip ---\n";

$reloaded = $node_storage->load($test_node->id());
$field_value = $reloaded->get($canvas_field_name)->getValue();
canvas_assert_true(count($field_value) === count($slot_items), "Slot item count preserved: " . count($field_value));

// Check root-level slot items have correct parent
$root_slot_items = array_filter($field_value, fn($item) =>
  ($item['parent_uuid'] ?? NULL) === $slot_component_uuid
);
canvas_assert_true(count($root_slot_items) === 3, "3 root-level slot items point to template wrapper (got " . count($root_slot_items) . ")");

// Check nested items
$nested_items = array_filter($field_value, fn($item) =>
  ($item['parent_uuid'] ?? NULL) !== $slot_component_uuid && !empty($item['parent_uuid'])
);
canvas_assert_true(count($nested_items) === 4, "4 nested items within slot tree (got " . count($nested_items) . ")");

// ============================================================
// Test 6: getMergedComponentTree
// ============================================================

echo "\n--- Test 6: Merged component tree ---\n";

try {
  $merged_tree = $template->getMergedComponentTree($reloaded);
  $merged_items = $merged_tree->getValue();
  canvas_assert_true(!empty($merged_items), "Merged tree is non-empty");

  // Merged tree should contain both template items AND slot items
  $template_tree = $template->get('component_tree');
  $expected_total = count($template_tree) + count($slot_items);
  canvas_assert_true(
    count($merged_items) === $expected_total,
    "Merged tree has template + slot items: " . count($merged_items) . " == $expected_total"
  );

  // Check that slot items appear in the merged tree
  $merged_uuids = array_column($merged_items, 'uuid');
  canvas_assert_true(in_array(canvas_uuid('slot_heading'), $merged_uuids), "Slot heading UUID found in merged tree");
  canvas_assert_true(in_array(canvas_uuid('slot_para'), $merged_uuids), "Slot paragraph UUID found in merged tree");
  canvas_assert_true(in_array(canvas_uuid('slot_row'), $merged_uuids), "Slot row UUID found in merged tree");

  // Template items should also be present
  foreach ($template_tree as $t_item) {
    canvas_assert_true(in_array($t_item['uuid'], $merged_uuids), "Template item " . $t_item['component_id'] . " found in merged tree");
  }
}
catch (\Exception $e) {
  canvas_assert_true(FALSE, "getMergedComponentTree failed: " . $e->getMessage());
}

// ============================================================
// Test 7: Render merged tree
// ============================================================

echo "\n--- Test 7: Render merged tree ---\n";

try {
  $renderable = $merged_tree->toRenderable($template, isPreview: TRUE);
  $root_key = ComponentTreeItemList::ROOT_UUID;

  if (isset($renderable[$root_key])) {
    $html = (string) \Drupal::service('renderer')->renderInIsolation($renderable[$root_key]);
    canvas_assert_true(!empty($html), "Merged tree renders to HTML (" . strlen($html) . " chars)");
    canvas_assert_true(str_contains($html, 'Article Body Heading'), "HTML contains slot heading text");
    canvas_assert_true(str_contains($html, 'per-content body text'), "HTML contains slot paragraph text");
    canvas_assert_true(str_contains($html, 'Left column content'), "HTML contains nested slot column content");
  }
  else {
    canvas_assert_true(FALSE, "Renderable missing ROOT_UUID key");
  }
}
catch (\Exception $e) {
  canvas_assert_true(FALSE, "Merged tree rendering failed: " . $e->getMessage());
}

// ============================================================
// Test 8: SlotTreeExtractor
// ============================================================

echo "\n--- Test 8: SlotTreeExtractor ---\n";

try {
  $slot_extractor = \Drupal::service(\Drupal\canvas\Storage\SlotTreeExtractor::class);
  $template_uuid_map = $slot_extractor->getTemplateUuidMap($template);

  canvas_assert_true(!empty($template_uuid_map), "Template UUID map is non-empty");
  canvas_assert_true(count($template_uuid_map) === count($template_tree), "UUID map has " . count($template_uuid_map) . " entries (matches template tree)");

  // Slot items should NOT be in the template UUID map
  canvas_assert_true(!isset($template_uuid_map[canvas_uuid('slot_heading')]), "Slot heading NOT in template UUID map");
  canvas_assert_true(!isset($template_uuid_map[canvas_uuid('slot_para')]), "Slot paragraph NOT in template UUID map");
}
catch (\Exception $e) {
  canvas_assert_true(FALSE, "SlotTreeExtractor failed: " . $e->getMessage());
}

// ============================================================
// Test 9: Empty slot content — article with no slot field data
// ============================================================

echo "\n--- Test 9: Empty slot content ---\n";

$empty_node = $node_storage->create([
  'type' => 'article',
  'title' => 'Test Article Empty Slots - ' . time(),
  'status' => 1,
  'uid' => 1,
]);
$empty_node->save();

try {
  $merged_empty = $template->getMergedComponentTree($empty_node);
  $merged_empty_items = $merged_empty->getValue();
  canvas_assert_true(
    count($merged_empty_items) === count($template_tree),
    "Merged tree with empty slot = template tree only (" . count($merged_empty_items) . " items)"
  );

  // Render should work with empty slot
  $renderable = $merged_empty->toRenderable($template, isPreview: TRUE);
  $root_key = ComponentTreeItemList::ROOT_UUID;
  if (isset($renderable[$root_key])) {
    $html = (string) \Drupal::service('renderer')->renderInIsolation($renderable[$root_key]);
    canvas_assert_true(!empty($html), "Empty-slot merged tree renders (" . strlen($html) . " chars)");
  }
}
catch (\Exception $e) {
  canvas_assert_true(FALSE, "Empty slot merge/render failed: " . $e->getMessage());
}

// Cleanup empty node
$empty_node->delete();

// ============================================================
// Cleanup
// ============================================================

echo "\n--- Cleanup ---\n";
try {
  $test_node->delete();
  canvas_assert_true(TRUE, "Test article deleted");
}
catch (\Exception $e) {
  echo "  WARNING: Cleanup failed: " . $e->getMessage() . "\n";
}

// Clean auto-saves that might have been created
\Drupal::database()->delete('key_value')
  ->condition('collection', 'canvas.auto_save')
  ->execute();

$pass = $GLOBALS['_canvas_test_pass'];
$fail = $GLOBALS['_canvas_test_fail'];
echo "\n=== Results: $pass passed, $fail failed ===\n";
echo "STATUS: " . ($fail > 0 ? 'FAIL' : 'PASS') . "\n";
