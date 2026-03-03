<?php

/**
 * @file
 * Builds the ExampleHomepage Canvas Page programmatically.
 *
 * Creates a Canvas Page with a hero section and 4-card product grid
 * using the active theme's SDC components and the design system's
 * preset classes. This is a tested, working reference implementation
 * for the canvas-build-example.md guide.
 *
 * DESIGN SYSTEM PATTERNS demonstrated:
 *   - Wrapper presets (hero-dark, content-section) instead of manual utility classes
 *   - Heading presets (hero, card-title) instead of manual display_size/font_weight
 *   - Paragraph presets (lead) instead of manual is_lead
 *   - Card presets (dark) instead of manual bg_color/border_color
 *   - Dark section presets set inherited text color — no text_color needed on children
 *   - Individual prop overrides only when presets don't cover the need
 *
 * See component-strategy.md for the 8-layer design system architecture.
 *
 * Usage: ddev drush php:script .alchemize/drupal/capabilities/generators/canvas-build-page.drush.php
 *
 * What it does:
 *   1. Creates (or loads) the "ExampleHomepage" Canvas Page entity
 *   2. Loads component version hashes from the component config entities
 *   3. Builds a 27-component tree (hero + product grid)
 *   4. Saves the tree to the page's `components` field
 *   5. Prints the resulting tree hierarchy for verification
 *
 * The page is then viewable at /example-homepage (or /page/{id}).
 */

require_once __DIR__ . '/../lib/canvas-lib.php';

echo "=== Canvas Page Builder ===\n\n";

// ============================================================
// Step 1: Create or load the Canvas Page entity
// ============================================================

$page_storage = \Drupal::entityTypeManager()->getStorage('canvas_page');

// Check if ExampleHomepage already exists.
$existing = $page_storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('title', 'ExampleHomepage')
  ->execute();

try {
  if (!empty($existing)) {
    $page_id = reset($existing);
    $page = $page_storage->load($page_id);
    echo "Loaded existing ExampleHomepage (ID: $page_id)\n";
  }
  else {
    $page = $page_storage->create([
      'title' => 'ExampleHomepage',
      'status' => 1,
      'path' => '/example-homepage',
    ]);
    $page->save();
    $page_id = $page->id();
    echo "Created new ExampleHomepage (ID: $page_id)\n";
  }
}
catch (\Exception $e) {
  echo "ERROR: Failed to create/load Canvas Page: " . $e->getMessage() . "\n";
  echo "=== Aborted ===\n";
  return;
}

// ============================================================
// Step 2: Load component version hashes
// ============================================================

[$theme, $components, $versions] = canvas_lib_init([
  'wrapper', 'heading', 'paragraph', 'button', 'row', 'column', 'card', 'link',
], verbose: TRUE);

if (empty($versions)) {
  echo "=== Aborted ===\n";
  return;
}

// ============================================================
// Step 3: Build the component tree
// ============================================================

$tree = [];

// --------------------------------------------------
// SECTION 1: HERO (centered text stack)
// --------------------------------------------------
// Wrapper → Heading + Paragraph + Button + Paragraph
// No Row/Column needed — single-column centered layout.
//
// DESIGN SYSTEM: Uses wrapper preset: hero-dark
//   → .preset-section-hero-dark sets: bg-brand-900, white text, centered,
//     flex-column, gap, aligned. No manual flex/padding/class needed.
//   → Child text inherits white color from the section — no text_color overrides.
//   → Heading preset: hero → .role-heading-hero (3.5rem, bold)
//   → Paragraph preset: lead → .role-text-lead (1.25rem, light weight)

$tree[] = canvas_wrapper(canvas_uuid('hero_wrapper'), [
  'preset' => 'hero-dark',
]);

$tree[] = canvas_heading(canvas_uuid('hero_heading'), 'Building Intelligent Systems Across Code, Media, and Infrastructure.', 'h1', [
  'preset' => 'hero',
], canvas_uuid('hero_wrapper'), 'content');

// Paragraph text MUST include HTML tags — the prop has contentMediaType: text/html
$tree[] = canvas_paragraph(canvas_uuid('hero_description'), '<p>The Alchemize Suite is a powerful set of AI-driven tools for orchestrating code, generating media, and transforming workflows.</p>', [
  'preset'        => 'lead',
  'margin_bottom' => 'mb-4',  // Individual prop override — Bootstrap utility wins
], canvas_uuid('hero_wrapper'), 'content');

$tree[] = canvas_button(canvas_uuid('hero_button'), 'Explore Alchemize Suite →', '/explore', [
  'size' => 'lg',
], canvas_uuid('hero_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('hero_tagline'), '<p>Empowering builders with structured AI to create more intelligently.</p>', [
  'preset'     => 'caption',     // .role-text-caption — small, muted
  'margin_top' => 'mt-4',        // Individual prop override
], canvas_uuid('hero_wrapper'), 'content');

// --------------------------------------------------
// SECTION 2: PRODUCT GRID (4-column card layout)
// --------------------------------------------------
// Wrapper → Row → 4×(Column → Card → Heading + Paragraph + Link)
//
// DESIGN SYSTEM: Uses wrapper preset: hero-dark for the dark section,
// and card preset: dark for the dark cards. The preset handles bg + text
// color + shadow + radius. We only set individual props for things
// presets don't cover (e.g., position: relative for stretched-link).

$tree[] = canvas_wrapper(canvas_uuid('grid_wrapper'), [
  'preset' => 'hero-dark',
]);

// Row with responsive column counts:
// - 1 column on small screens (row-cols-1)
// - 2 columns on medium (row-cols-md-2)
// - 4 columns on large (row-cols-lg-4)
$tree[] = canvas_row(canvas_uuid('grid_row'), [
  'row_cols'    => 'row-cols-1',
  'row_cols_md' => 'row-cols-md-2',
  'row_cols_lg' => 'row-cols-lg-4',
  'gap'         => 'g-4',
], canvas_uuid('grid_wrapper'), 'content');

// Card data — each card follows the same pattern.
$cards = [
  ['key' => 'card1', 'title' => 'Alchemize Dev', 'desc' => 'CLI-Integrated AI Development Platform.', 'url' => '/products/dev'],
  ['key' => 'card2', 'title' => 'Alchemize Beats', 'desc' => 'Audio/Music Media Production Engine.', 'url' => '/products/beats'],
  ['key' => 'card3', 'title' => 'Alchemize Studio', 'desc' => 'Visual Media AI Production Engine.', 'url' => '/products/studio'],
  ['key' => 'card4', 'title' => 'Alchemize Chat', 'desc' => 'Conversational AI Interface.', 'url' => '/products/chat'],
];

foreach ($cards as $c) {
  $k = $c['key'];

  // Column inside Row's "row" slot
  $tree[] = canvas_column(canvas_uuid("{$k}_col"), [], canvas_uuid('grid_row'), 'row');

  // Card inside Column's "column" slot.
  // DESIGN SYSTEM: preset: dark → .preset-card-dark sets bg-brand-900,
  // white text, shadow, border-radius. Only position: relative is manual
  // (required for stretched_link to work).
  $tree[] = canvas_card(canvas_uuid($k), [
    'preset'   => 'dark',
    'position' => 'relative',   // Required for stretched_link
  ], canvas_uuid("{$k}_col"), 'column');

  // Card body children: Heading + Paragraph + Link
  // No text_color needed — .preset-card-dark sets color: $neutral-0
  $tree[] = canvas_heading(canvas_uuid("{$k}_heading"), $c['title'], 'h3', [
    'preset' => 'card-title',
  ], canvas_uuid($k), 'card_body');

  $tree[] = canvas_paragraph(canvas_uuid("{$k}_desc"), '<p>' . $c['desc'] . '</p>', [], canvas_uuid($k), 'card_body');

  // stretched_link makes the entire card clickable via this link
  $tree[] = canvas_link(canvas_uuid("{$k}_link"), 'Learn More →', $c['url'], [
    'stretched_link' => TRUE,
    'link_classes'   => 'text-info',
  ], canvas_uuid($k), 'card_body');
}

// ============================================================
// Step 4: Save the tree to the Canvas Page
// ============================================================

$page->set('components', $tree);
try {
  $page->save();
  echo "\nSaved " . count($tree) . " components to ExampleHomepage\n";
  echo "View at: /page/$page_id (or /example-homepage)\n\n";
}
catch (\Exception $e) {
  echo "\nERROR: Failed to save component tree: " . $e->getMessage() . "\n";
  echo "=== Aborted ===\n";
  return;
}

// ============================================================
// Step 5: Print the tree hierarchy for verification
// ============================================================

// Build lookup map.
$items_map = [];
foreach ($tree as $item) {
  $items_map[$item['uuid']] = $item;
}

function print_tree(array $items, ?string $parent = NULL, int $indent = 0): void {
  foreach ($items as $uuid => $item) {
    if ($item['parent_uuid'] === $parent) {
      $prefix = str_repeat('  ', $indent);
      $component = preg_replace('/^sdc\.[^.]+\./', '', $item['component_id']);
      $slot_info = $item['slot'] ? " [slot: {$item['slot']}]" : ' [ROOT]';
      $inputs = json_decode($item['inputs'], TRUE) ?? [];
      $label = '';
      if (!empty($inputs['text'])) {
        $text = strip_tags($inputs['text']);
        $label = ' → "' . (strlen($text) > 40 ? substr($text, 0, 40) . '...' : $text) . '"';
      }
      echo $prefix . '├─ ' . strtoupper($component) . $slot_info . $label . "\n";
      print_tree($items, $uuid, $indent + 1);
    }
  }
}

echo "--- Component Tree ---\n";
print_tree($items_map);

echo "\n=== Done ===\n";
