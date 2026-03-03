<?php

/**
 * @file
 * Tests deep component nesting: row/column grids, cards, accordions.
 *
 * Exercises multi-level slot nesting to verify:
 *   1. Row → Column → Card → (heading + paragraph + link) — 4 levels deep
 *   2. Accordion container → accordion items → paragraph — 3 levels deep
 *   3. Wrapper → Row → Column → Wrapper → Heading — 5 levels deep
 *   4. Tree validation passes with deeply nested items
 *   5. Rendering works for deeply nested structures
 *   6. Parent/slot relationships are preserved through save/load
 *
 * Usage: ddev drush php:script .alchemize/drupal/capabilities/tests/canvas-test-nested-components.drush.php
 */

require_once __DIR__ . '/../lib/canvas-lib.php';

$GLOBALS['_canvas_test_pass'] = 0;
$GLOBALS['_canvas_test_fail'] = 0;

echo "=== Canvas Test: Nested Components ===\n\n";

// ============================================================
// Load component versions
// ============================================================

[$theme, $components, $versions] = canvas_lib_init([
  'wrapper', 'heading', 'paragraph', 'button', 'row', 'column',
  'card', 'link', 'accordion', 'accordion-container',
]);

if (empty($versions)) {
  echo "=== Aborted: missing components ===\n";
  return;
}

echo "All " . count($versions) . " required components loaded.\n\n";

// ============================================================
// Create test page
// ============================================================

$page_storage = \Drupal::entityTypeManager()->getStorage('canvas_page');
$page = $page_storage->create([
  'title' => 'Test Page - Nested Components',
  'status' => 1,
  'path' => '/test-nested-components-' . time(),
]);
$page->save();
echo "Created test page ID: " . $page->id() . "\n\n";

$tree = [];

// ============================================================
// Test 1: Row → Column → Card grid (4-level nesting)
// ============================================================

echo "--- Test 1: Row/Column/Card grid (4 levels) ---\n";

// Root wrapper
$tree[] = canvas_wrapper(canvas_uuid('grid_wrapper'), [
  'html_tag' => 'section',
  'container_type' => 'container',
  'padding_y' => 'py-4',
]);

// Row inside wrapper
$tree[] = canvas_row(canvas_uuid('grid_row'), [
  'row_cols' => 'row-cols-1',
  'row_cols_md' => 'row-cols-md-3',
  'gap' => 'g-4',
], canvas_uuid('grid_wrapper'), 'content');

// 3 columns with cards
$cards_data = [
  ['key' => 'card1', 'title' => 'Card One', 'body' => 'First card body text.', 'link' => '/one'],
  ['key' => 'card2', 'title' => 'Card Two', 'body' => 'Second card body text.', 'link' => '/two'],
  ['key' => 'card3', 'title' => 'Card Three', 'body' => 'Third card body text.', 'link' => '/three'],
];

foreach ($cards_data as $c) {
  $k = $c['key'];

  $tree[] = canvas_column(canvas_uuid("{$k}_col"), [
    'col' => 'col',
  ], canvas_uuid('grid_row'), 'row');

  $tree[] = canvas_card(canvas_uuid($k), [
    'show_header' => FALSE,
    'show_image' => FALSE,
    'show_footer' => FALSE,
    'bg_color' => 'bg-light',
    'border_color' => 'border-secondary',
  ], canvas_uuid("{$k}_col"), 'column');

  $tree[] = canvas_heading(canvas_uuid("{$k}_heading"), $c['title'], 'h3', [], canvas_uuid($k), 'card_body');

  $tree[] = canvas_paragraph(canvas_uuid("{$k}_desc"), '<p>' . $c['body'] . '</p>', [], canvas_uuid($k), 'card_body');

  $tree[] = canvas_link(canvas_uuid("{$k}_link"), 'Details', $c['link'], [], canvas_uuid($k), 'card_body');
}

$grid_count = count($tree);
canvas_assert_true($grid_count === 17, "Grid section has 17 components (got $grid_count)");

// ============================================================
// Test 2: Accordion nesting (3 levels)
// ============================================================

echo "\n--- Test 2: Accordion container/items (3 levels) ---\n";

// Wrapper for accordion section
$tree[] = canvas_wrapper(canvas_uuid('acc_wrapper'), [
  'html_tag' => 'section',
  'padding_y' => 'py-4',
]);

// Accordion container
$tree[] = canvas_accordion_container(canvas_uuid('acc_container'), [
  'flush' => FALSE,
], canvas_uuid('acc_wrapper'), 'content');

// Accordion item 1 with paragraph
$tree[] = canvas_accordion(canvas_uuid('acc_item1'), 'FAQ Question 1', [
  'heading_level' => 3,
  'open_by_default' => TRUE,
], canvas_uuid('acc_container'), 'accordion_content');

$tree[] = canvas_paragraph(canvas_uuid('acc_item1_para'), '<p>This is the answer to question 1. Accordion items can contain any content.</p>', [], canvas_uuid('acc_item1'), 'accordion_body');

// Accordion item 2 with heading + paragraph
$tree[] = canvas_accordion(canvas_uuid('acc_item2'), 'FAQ Question 2', [
  'heading_level' => 3,
  'open_by_default' => FALSE,
], canvas_uuid('acc_container'), 'accordion_content');

$tree[] = canvas_heading(canvas_uuid('acc_item2_heading'), 'Detailed Answer', 'h4', [], canvas_uuid('acc_item2'), 'accordion_body');

$tree[] = canvas_paragraph(canvas_uuid('acc_item2_para'), '<p>This answer has both a heading and paragraph inside the accordion body.</p>', [], canvas_uuid('acc_item2'), 'accordion_body');

$acc_items = count($tree) - $grid_count;
canvas_assert_true($acc_items === 7, "Accordion section has 7 components (got $acc_items)");

// ============================================================
// Test 3: Deep nesting (5 levels: wrapper → row → column → wrapper → heading)
// ============================================================

echo "\n--- Test 3: Deep nesting (5 levels) ---\n";

$tree[] = canvas_wrapper(canvas_uuid('deep_wrapper'), [
  'html_tag' => 'div',
  'custom_class' => 'deep-level-1',
]);

$tree[] = canvas_row(canvas_uuid('deep_row'), [
  'row_cols' => 'row-cols-1',
], canvas_uuid('deep_wrapper'), 'content');

$tree[] = canvas_column(canvas_uuid('deep_col'), [
  'col' => 'col-12',
], canvas_uuid('deep_row'), 'row');

$tree[] = canvas_wrapper(canvas_uuid('deep_inner_wrapper'), [
  'html_tag' => 'div',
  'custom_class' => 'deep-level-4',
  'padding_all' => 'p-3',
], canvas_uuid('deep_col'), 'column');

$tree[] = canvas_heading(canvas_uuid('deep_heading'), 'Five levels deep!', 'h5', [], canvas_uuid('deep_inner_wrapper'), 'content');

$total = count($tree);
canvas_assert_true($total === 29, "Total tree has 29 components (got $total)");

// ============================================================
// Test 4: Save and verify
// ============================================================

echo "\n--- Test 4: Save and verify deep tree ---\n";

$page->set('components', $tree);
try {
  $page->save();
  canvas_assert_true(TRUE, "Saved page with $total nested components");
}
catch (\Exception $e) {
  canvas_assert_true(FALSE, "Save failed: " . $e->getMessage());
}

// Reload and verify
$reloaded = $page_storage->load($page->id());
$saved_tree = $reloaded->get('components')->getValue();
canvas_assert_true(count($saved_tree) === $total, "Reloaded tree count matches: " . count($saved_tree));

// Verify deep nesting chain: wrapper → row → col → wrapper → heading
$deep_heading_item = NULL;
foreach ($saved_tree as $item) {
  if ($item['uuid'] === canvas_uuid('deep_heading')) {
    $deep_heading_item = $item;
    break;
  }
}
canvas_assert_true($deep_heading_item !== NULL, "Deep heading found in reloaded tree");
canvas_assert_true($deep_heading_item['parent_uuid'] === canvas_uuid('deep_inner_wrapper'), "Deep heading parent is inner wrapper");
canvas_assert_true($deep_heading_item['slot'] === 'content', "Deep heading slot is 'content'");

// Walk up the parent chain to verify all 5 levels
$parents = [];
$current_uuid = canvas_uuid('deep_heading');
$item_map = [];
foreach ($saved_tree as $item) {
  $item_map[$item['uuid']] = $item;
}
while ($current_uuid !== NULL) {
  $item = $item_map[$current_uuid] ?? NULL;
  if (!$item) break;
  $parents[] = $item['component_id'];
  $current_uuid = $item['parent_uuid'] ?? NULL;
}
canvas_assert_true(count($parents) === 5, "Deep nesting chain is 5 levels (got " . count($parents) . "): " . implode(' → ', array_reverse($parents)));

// ============================================================
// Test 5: Validation on deeply nested tree
// ============================================================

echo "\n--- Test 5: Validation on deeply nested tree ---\n";

$violations = $reloaded->getTypedData()->validate();
if ($violations->count() > 0) {
  $msgs = [];
  foreach ($violations as $violation) {
    $msgs[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
  }
  canvas_assert_true(FALSE, "Validation failed: " . implode('; ', $msgs));
}
else {
  canvas_assert_true(TRUE, "Entity validation passes with 0 violations");
}

// ============================================================
// Test 6: Render deeply nested tree
// ============================================================

echo "\n--- Test 6: Render deeply nested tree ---\n";

try {
  $view_builder = \Drupal::entityTypeManager()->getViewBuilder('canvas_page');
  $build = $view_builder->view($reloaded);
  $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
  canvas_assert_true(!empty($html), "Renders to non-empty HTML (" . strlen($html) . " chars)");
  canvas_assert_true(str_contains($html, 'Card One'), "HTML contains card heading 'Card One'");
  canvas_assert_true(str_contains($html, 'FAQ Question 1'), "HTML contains accordion title");
  canvas_assert_true(str_contains($html, 'Five levels deep'), "HTML contains deeply nested heading");
}
catch (\Exception $e) {
  canvas_assert_true(FALSE, "Rendering failed: " . $e->getMessage());
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

$pass = $GLOBALS['_canvas_test_pass'];
$fail = $GLOBALS['_canvas_test_fail'];
echo "\n=== Results: $pass passed, $fail failed ===\n";
echo "STATUS: " . ($fail > 0 ? 'FAIL' : 'PASS') . "\n";
