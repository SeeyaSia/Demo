<?php

/**
 * @file
 * Updates the Article ContentTemplate with an exposed slot for per-content editing.
 *
 * Loads the existing node.article.full ContentTemplate and restructures it:
 * - Adds a Wrapper component as an exposed slot container
 * - Keeps Heading and Image as root-level template-locked components
 * - Removes the static Paragraph (body content belongs in per-entity slot)
 * - Configures the Wrapper's `content` slot as the exposed slot
 *
 * Usage: ddev drush php:script .alchemize/drupal/capabilities/generators/update-article-content-template.drush.php
 */

use Drupal\canvas\Entity\ContentTemplate;

require_once __DIR__ . '/../lib/canvas-lib.php';

echo "=== Update Article ContentTemplate with Exposed Slot ===\n\n";

// ============================================================
// Step 1: Load the existing ContentTemplate
// ============================================================

$template = ContentTemplate::load('node.article.full');
if (!$template) {
  echo "ERROR: ContentTemplate 'node.article.full' not found!\n";
  echo "=== Aborted ===\n";
  return;
}

if (!$template->status()) {
  echo "ERROR: ContentTemplate 'node.article.full' is disabled!\n";
  echo "=== Aborted ===\n";
  return;
}

echo "Loaded ContentTemplate: " . $template->id() . "\n";
echo "Current components: " . count($template->get('component_tree')) . "\n\n";

// ============================================================
// Step 2: Load component version hashes
// ============================================================

[$theme, $components, $versions] = canvas_lib_init([
  'wrapper', 'heading', 'image',
], verbose: TRUE);

if (empty($versions)) {
  echo "=== Aborted ===\n";
  return;
}

// ============================================================
// Step 3: Build the new component tree
// ============================================================
// Structure:
//   Root level (no parent):
//     - Heading (template-locked, linked to title) — existing UUID preserved
//     - Image (template-locked, linked to field_image) — existing UUID preserved
//     - Wrapper (new, template-locked, its content slot is the exposed slot)
//
// The Wrapper's `content` slot is EMPTY in the template (required by
// ValidExposedSlotConstraintValidator). Per-entity content is injected
// at runtime via injectSubTreeItemList().
//
// NOTE: Heading and Image use dynamic binding expressions (sourceType: dynamic)
// which require raw tree_item construction — they don't map to static
// component builder functions.

$wrapper_uuid = canvas_uuid('wrapper');

// Preserve existing component UUIDs for heading and image.
$heading_uuid = '3329d777-e4b8-463e-ae9a-c1f510d268e9';
$image_uuid = 'c1d4756e-7b87-47e7-b641-040f3de389ff';
// Paragraph is being removed (body content belongs in per-entity slot).

$component_tree = [
  // Heading — root level, template-locked, dynamic title binding.
  canvas_tree_item($heading_uuid, $components['heading'], $versions['heading'], [
    'text' => [
      'sourceType' => 'dynamic',
      'expression' => "\u{2139}\u{FE0E}\u{241C}entity:node:article\u{241D}title\u{241E}\u{241F}value",
    ],
    'level' => 'h1',
    'alignment' => 'text-start',
  ]),
  // Image — root level, template-locked, dynamic field_image binding.
  canvas_tree_item($image_uuid, $components['image'], $versions['image'], [
    'media' => [
      'sourceType' => 'dynamic',
      'expression' => "\u{2139}\u{FE0E}\u{241C}entity:node:article\u{241D}field_image\u{241E}\u{241F}{src\u{21A0}src_with_alternate_widths,alt\u{21A0}alt,width\u{21A0}width,height\u{21A0}height}",
    ],
    'size' => '16:9',
    'radius' => 'small',
    'margin_bottom' => 'mb-0',
  ]),
  // Wrapper — root level, template-locked, its `content` slot is the exposed slot.
  canvas_tree_item($wrapper_uuid, $components['wrapper'], $versions['wrapper'], [
    'html_tag' => 'section',
  ]),
];

echo "\nNew tree structure:\n";
echo "  Heading (uuid: $heading_uuid) — root, template-locked\n";
echo "  Image (uuid: $image_uuid) — root, template-locked\n";
echo "  Wrapper (uuid: $wrapper_uuid) — root, exposed slot: content\n";

// ============================================================
// Step 4: Update the ContentTemplate
// ============================================================

$template->set('component_tree', $component_tree);
$template->set('exposed_slots', [
  'article_content' => [
    'component_uuid' => $wrapper_uuid,
    'slot_name' => 'content',
    'label' => 'Article content',
  ],
]);

echo "\nValidating...\n";
$violations = $template->getTypedData()->validate();
if ($violations->count() > 0) {
  echo "VALIDATION ERRORS:\n";
  foreach ($violations as $violation) {
    echo "  - " . $violation->getMessage() . " (at " . $violation->getPropertyPath() . ")\n";
  }
  echo "=== Aborted (not saved) ===\n";
  return;
}

echo "Validation passed!\n";
$template->save();
echo "ContentTemplate saved successfully.\n";

// ============================================================
// Step 5: Verify
// ============================================================

$reloaded = ContentTemplate::load('node.article.full');
$tree = $reloaded->get('component_tree');
$slots = $reloaded->getExposedSlots();

echo "\nVerification:\n";
echo "  Components in tree: " . count($tree) . "\n";
echo "  Exposed slots: " . count($slots) . "\n";
foreach ($slots as $key => $slot) {
  echo "    $key: component_uuid={$slot['component_uuid']}, slot_name={$slot['slot_name']}, label={$slot['label']}\n";
}

echo "\n=== Done ===\n";
