<?php

/**
 * @file
 * Builds the Projects Canvas Page with hero + Views block listing.
 *
 * DESIGN SYSTEM PATTERNS demonstrated:
 *   - Wrapper preset: hero-dark for the hero section
 *   - Wrapper preset: content-section for the listing section
 *   - Heading preset: hero for the page title
 *   - Paragraph preset: lead for the intro text
 *   - Dark section presets set inherited text color — no text_color overrides
 *   - Block component usage (Views block with label/label_display inputs)
 *
 * See component-strategy.md for the 8-layer design system architecture.
 *
 * Usage: ddev drush php:script .alchemize/drupal/capabilities/generators/canvas-build-projects-page.drush.php
 */

require_once __DIR__ . '/../lib/canvas-lib.php';

echo "=== Canvas Projects Page Builder ===\n\n";

// Step 1: Create or load the page.
$page_storage = \Drupal::entityTypeManager()->getStorage('canvas_page');
$existing = $page_storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('title', 'Projects')
  ->execute();

if (!empty($existing)) {
  $page_id = reset($existing);
  $page = $page_storage->load($page_id);
  echo "Loaded existing Projects page (ID: $page_id)\n";
}
else {
  $page = $page_storage->create([
    'title' => 'Projects',
    'status' => 1,
    'path' => '/our-projects',
  ]);
  $page->save();
  $page_id = $page->id();
  echo "Created Projects page (ID: $page_id)\n";
}

// Step 2: Load component versions.
[$theme, $components, $versions] = canvas_lib_init([
  'wrapper', 'heading', 'paragraph',
], verbose: TRUE);

if (empty($versions)) {
  echo "=== Aborted ===\n";
  return;
}

// Also load the Views block component (not a theme SDC, loaded separately).
$comp_storage = \Drupal::entityTypeManager()->getStorage('component');
$views_block_id = 'block.views_block.projects-block_1';
$views_block_entity = $comp_storage->load($views_block_id);
if (!$views_block_entity) {
  echo "ERROR: Component $views_block_id not found! Is the View created with a Block display?\n";
  return;
}
$views_block_version = $views_block_entity->toArray()['active_version'];
echo "  views_block => $views_block_version\n";

// Step 3: Build tree.
$tree = [];

// Section 1: Hero intro.
// DESIGN SYSTEM: preset: hero-dark → .preset-section-hero-dark
//   → bg-brand-900, white text, centered, flex-column, gap, aligned.
//   → Child text inherits white — no text_color overrides needed.
$tree[] = canvas_wrapper(canvas_uuid('hero_wrapper'), [
  'preset' => 'hero-dark',
]);

// preset: hero → .role-heading-hero (3.5rem, bold)
$tree[] = canvas_heading(canvas_uuid('hero_heading'), 'Our Projects', 'h1', [
  'preset' => 'hero',
], canvas_uuid('hero_wrapper'), 'content');

// preset: lead → .role-text-lead (1.25rem, light weight)
// No text_color needed — inherits white from hero-dark section.
$tree[] = canvas_paragraph(canvas_uuid('hero_desc'), '<p>Explore our portfolio of AI-powered tools and platforms.</p>', [
  'preset' => 'lead',
], canvas_uuid('hero_wrapper'), 'content');

// Section 2: Views listing block.
// DESIGN SYSTEM: preset: content-section → neutral section with padding + gap.
$tree[] = canvas_wrapper(canvas_uuid('listing_wrapper'), [
  'preset' => 'content-section',
]);

// Block components REQUIRE 'label' and 'label_display' in inputs.
// They may also accept block-specific settings (e.g., 'items_per_page' for Views blocks).
// label_display '0' = hidden, 'visible' = shown above the block.
$tree[] = canvas_tree_item(
  canvas_uuid('views_block'),
  $views_block_id,
  $views_block_version,
  [
    'label' => 'Projects listing',
    'label_display' => '0',
  ],
  canvas_uuid('listing_wrapper'),
  'content'
);

// Step 4: Save.
$page->set('components', $tree);
$page->save();

echo "\nSaved " . count($tree) . " components to Projects Canvas page\n";
echo "View at: /page/$page_id (or /our-projects)\n";
echo "\n=== Done ===\n";
