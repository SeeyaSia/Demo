<?php

/**
 * @file
 * Builds a "Component Showcase" basic page — a full dynamic styleguide.
 *
 * Creates a Basic Page node with per-content components injected into the
 * page template's exposed slot (field_canvas_body). The page serves as a
 * living styleguide and reference for site editors, demonstrating every
 * available component with real example content.
 *
 * Sections (Component Showcase):
 *   1. Hero / Introduction
 *   2. Color Palette (swatches + SCSS variable hint)
 *   3. Typography (headings, colors, alignment + font config hint)
 *   4. Buttons (full matrix + theme-colors hint)
 *   5. Links (standalone + stretched-link hint)
 *   6. Grid Layouts (2/3/4-col + Row/Column prop hint)
 *   7. Cards (basic, header/footer, colored + slots hint)
 *   8. Accordion (FAQ — editor + developer questions)
 *   9. Blockquotes (alignment, italic + plain-text hint)
 *  10. Images
 *  11. Wrappers & Spacing (padding, flex + composition rule)
 *  12. Quick Reference
 *
 * Sections (Developer Guide):
 *  13. Theme Architecture (file structure, inheritance)
 *  14. SCSS / CSS Build Pipeline (webpack, npm, PostCSS)
 *  15. Customizing Theme Variables (colors, typography, settings)
 *  16. Working with Components (SDC anatomy, slots, creating new)
 *  17. JavaScript & Libraries (BS5 bundle, Drupal behaviors, custom JS)
 *  18. Developer Quick Start (5 steps + common tasks table)
 *
 * Usage: ddev drush php:script .alchemize/drupal/capabilities/generators/build-component-showcase-page.drush.php
 */

use Drupal\canvas\Entity\ContentTemplate;

require_once __DIR__ . '/../lib/canvas-lib.php';

echo "=== Component Showcase / Styleguide Builder ===\n\n";

// ============================================================
// Step 1: Resolve theme and load component versions
// ============================================================

[$theme, $components, $versions] = canvas_lib_init([
  'wrapper', 'heading', 'paragraph', 'button', 'link', 'blockquote',
  'image', 'row', 'column', 'card', 'accordion', 'accordion-container',
], verbose: TRUE);

if (empty($versions)) {
  echo "=== Aborted ===\n";
  return;
}

echo "All " . count($versions) . " components loaded.\n\n";

// ============================================================
// Step 2: Load the page template and get exposed slot UUID
// ============================================================

$template = ContentTemplate::load('node.page.full');
if (!$template) {
  echo "ERROR: ContentTemplate 'node.page.full' not found!\n";
  echo "=== Aborted ===\n";
  return;
}

$exposed_slots = $template->getExposedSlots();
if (!isset($exposed_slots['field_canvas_body'])) {
  echo "ERROR: No 'field_canvas_body' exposed slot on page template!\n";
  echo "=== Aborted ===\n";
  return;
}

$slot_component_uuid = $exposed_slots['field_canvas_body']['component_uuid'];
$slot_name = $exposed_slots['field_canvas_body']['slot_name'];
echo "Slot target: component=$slot_component_uuid, slot=$slot_name\n\n";

// ============================================================
// Step 3: Create or load the page node
// ============================================================

$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$existing = $node_storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'page')
  ->condition('title', 'Component Showcase')
  ->execute();

if (!empty($existing)) {
  $node_id = reset($existing);
  $node = $node_storage->load($node_id);
  echo "Loaded existing 'Component Showcase' page (ID: $node_id)\n";
}
else {
  $node = $node_storage->create([
    'type' => 'page',
    'title' => 'Component Showcase',
    'status' => 1,
    'uid' => 1,
  ]);
  $node->save();
  $node_id = $node->id();
  echo "Created 'Component Showcase' page (ID: $node_id)\n";
}

// ============================================================
// Step 4: Build the component tree for slot content
// ============================================================

$slot_uuid = $slot_component_uuid;
$slot = $slot_name;

$tree = [];

// ===========================================================================
// SECTION 1: Hero / Introduction
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('intro_wrapper'), [
  'html_tag' => 'section',
  'container_type' => 'container',
  'padding_y' => 'py-5',
  'flex_enabled' => TRUE,
  'flex_direction' => 'flex-column',
  'flex_gap' => 'gap-3',
  'align_items' => 'align-items-center',
  'custom_class' => 'text-center bg-light',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('intro_heading'), 'Alchemize Design System Styleguide', 'h1', [
  'alignment' => 'text-center',
], canvas_uuid('intro_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('intro_desc'), '<p class="lead">A comprehensive living reference for site editors and developers. This page showcases every available component, color, typographic scale, grid layout, and spacing utility in our design system.</p>', [], canvas_uuid('intro_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('intro_desc2'), '<p>Built with Canvas components from the <strong>' . $theme . '</strong> theme. Use this page as a guide when building pages with the Canvas visual editor.</p>', [], canvas_uuid('intro_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('intro_desc3'), '<p><strong>Editors:</strong> Browse the component showcase above to see what\'s available. <strong>Developers:</strong> Look for the <em class="text-muted">Developer</em> hints in each section, and scroll down to the theme architecture, build pipeline, and customization guides.</p>', [], canvas_uuid('intro_wrapper'), 'content');

$tree[] = canvas_button(canvas_uuid('intro_btn'), 'Jump to Components', '/component-showcase', [
  'size' => 'lg',
], canvas_uuid('intro_wrapper'), 'content');

// ===========================================================================
// SECTION 2: Color Palette
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('color_wrapper'), [
  'html_tag' => 'section',
  'container_type' => 'container',
  'padding_y' => 'py-5',
  'flex_enabled' => TRUE,
  'flex_direction' => 'flex-column',
  'flex_gap' => 'gap-3',
  'custom_class' => 'border-bottom',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('color_title'), 'Color Palette', 'h2', [
  'text_color' => 'text-primary',
], canvas_uuid('color_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('color_intro'), '<p>These are the theme\'s Bootstrap color variables. Each color is available as background (<code>bg-*</code>), text (<code>text-*</code>), and border (<code>border-*</code>) utility classes throughout the component system.</p>', [], canvas_uuid('color_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('color_dev_hint'), '<p class="small text-muted"><strong>Developers:</strong> Colors are defined in <code>scss/_variables.scss</code>. Change <code>$accent-shade</code> (→ $primary) and <code>$primary-shade</code> (→ $secondary), then run <code>ddev theme-build</code>. Every swatch on this page will update. See the <em>Customizing Theme Variables</em> section below for full details.</p>', [], canvas_uuid('color_wrapper'), 'content');

// Color swatches in a 4-column grid
$colors = [
  ['name' => 'primary',   'hex' => '#0079C0', 'label' => 'Primary (Blue)',    'text' => 'text-white'],
  ['name' => 'secondary', 'hex' => 'rgb(255,78,46)', 'label' => 'Secondary (Orange-Red)', 'text' => 'text-white'],
  ['name' => 'success',   'hex' => '#28a745', 'label' => 'Success (Green)',   'text' => 'text-white'],
  ['name' => 'danger',    'hex' => '#dc3545', 'label' => 'Danger (Red)',      'text' => 'text-white'],
  ['name' => 'warning',   'hex' => '#ffc107', 'label' => 'Warning (Yellow)',  'text' => 'text-dark'],
  ['name' => 'info',      'hex' => '#17a2b8', 'label' => 'Info (Cyan)',       'text' => 'text-white'],
  ['name' => 'light',     'hex' => '#f8f9fa', 'label' => 'Light (Gray-100)',  'text' => 'text-dark'],
  ['name' => 'dark',      'hex' => '#343a40', 'label' => 'Dark (Gray-800)',   'text' => 'text-white'],
];

$tree[] = canvas_row(canvas_uuid('color_row'), [
  'row_cols' => 'row-cols-2',
  'row_cols_md' => 'row-cols-md-4',
  'gap' => 'g-3',
], canvas_uuid('color_wrapper'), 'content');

foreach ($colors as $i => $c) {
  $tree[] = canvas_column(canvas_uuid("color_col_{$i}"), [], canvas_uuid('color_row'), 'row');

  $tree[] = canvas_card(canvas_uuid("color_card_{$i}"), [
    'bg_color' => "bg-{$c['name']}",
    'card_rounding' => 'rounded-3',
    'position' => 'static',
  ], canvas_uuid("color_col_{$i}"), 'column');

  $tree[] = canvas_heading(canvas_uuid("color_h_{$i}"), $c['label'], 'h5', [
    'text_color' => $c['text'],
  ], canvas_uuid("color_card_{$i}"), 'card_body');

  $tree[] = canvas_paragraph(canvas_uuid("color_p_{$i}"), '<p><code class="' . $c['text'] . '">' . $c['hex'] . '</code></p><p><code class="' . $c['text'] . '">bg-' . $c['name'] . ' / text-' . $c['name'] . '</code></p>', [], canvas_uuid("color_card_{$i}"), 'card_body');
}

// ===========================================================================
// SECTION 3: Typography
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('typo_wrapper'), [
  'html_tag' => 'section',
  'container_type' => 'container',
  'padding_y' => 'py-5',
  'flex_enabled' => TRUE,
  'flex_direction' => 'flex-column',
  'flex_gap' => 'gap-3',
  'custom_class' => 'border-bottom',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('typo_title'), 'Typography', 'h2', [
  'text_color' => 'text-primary',
], canvas_uuid('typo_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('typo_intro'), '<p>The typographic scale uses a system font stack for optimal performance. Headings range from <strong>h1</strong> (2.5rem / 40px) down to <strong>h6</strong> (1rem / 16px). Responsive font sizes are enabled.</p>', [], canvas_uuid('typo_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('typo_dev_hint'), '<p class="small text-muted"><strong>Developers:</strong> Fonts and sizes are configured in <code>scss/_typography.scss</code>. To use a custom font, import it and set <code>$font-family-sans-serif</code>. Heading sizes use <code>$h1-font-size</code> through <code>$h6-font-size</code> as multipliers of <code>$font-size-base</code> (1rem). Rebuild CSS after changes.</p>', [], canvas_uuid('typo_wrapper'), 'content');

// All heading levels h1-h6
$heading_sizes = [
  ['level' => 'h1', 'size' => '2.5rem (40px)'],
  ['level' => 'h2', 'size' => '2rem (32px)'],
  ['level' => 'h3', 'size' => '1.75rem (28px)'],
  ['level' => 'h4', 'size' => '1.5rem (24px)'],
  ['level' => 'h5', 'size' => '1.25rem (20px)'],
  ['level' => 'h6', 'size' => '1rem (16px)'],
];

foreach ($heading_sizes as $i => $hs) {
  $tree[] = canvas_heading(canvas_uuid("heading_{$hs['level']}"), "Heading Level {$hs['level']} — {$hs['size']}", $hs['level'], [], canvas_uuid('typo_wrapper'), 'content');
}

// Body text examples
$tree[] = canvas_heading(canvas_uuid('typo_body_label'), 'Body Text', 'h4', [
  'text_color' => 'text-muted',
], canvas_uuid('typo_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('typo_body'), '<p>Body text uses the <strong>Paragraph</strong> component. It renders rich HTML content and supports:</p><ul><li><strong>Bold text</strong> for emphasis</li><li><em>Italic text</em> for titles and subtle emphasis</li><li>Ordered and unordered lists</li><li><a href="/component-showcase">Inline links</a> to other pages</li><li><code>Inline code</code> for technical references</li></ul><p>Keep paragraphs concise. Break long content into multiple paragraph components for better visual rhythm.</p>', [], canvas_uuid('typo_wrapper'), 'content');

// Text color showcase
$tree[] = canvas_heading(canvas_uuid('typo_color_label'), 'Text Colors', 'h4', [
  'text_color' => 'text-muted',
], canvas_uuid('typo_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('typo_color_intro'), '<p>Headings support the following text color classes via the <code>text_color</code> prop:</p>', [], canvas_uuid('typo_wrapper'), 'content');

$text_colors = [
  'text-primary', 'text-secondary', 'text-success', 'text-danger',
  'text-warning', 'text-info', 'text-dark', 'text-muted',
];

foreach ($text_colors as $i => $tc) {
  $tree[] = canvas_heading(canvas_uuid("tc_{$i}"), ucwords(str_replace(['-', 'text '], [' ', ''], $tc)) . " ($tc)", 'h5', [
    'text_color' => $tc,
  ], canvas_uuid('typo_wrapper'), 'content');
}

// Heading alignment
$tree[] = canvas_heading(canvas_uuid('typo_align_label'), 'Text Alignment', 'h4', [
  'text_color' => 'text-muted',
], canvas_uuid('typo_wrapper'), 'content');

$tree[] = canvas_heading(canvas_uuid('align_start'), 'Left aligned (text-start)', 'h5', [
  'alignment' => 'text-start',
], canvas_uuid('typo_wrapper'), 'content');

$tree[] = canvas_heading(canvas_uuid('align_center'), 'Center aligned (text-center)', 'h5', [
  'alignment' => 'text-center',
], canvas_uuid('typo_wrapper'), 'content');

$tree[] = canvas_heading(canvas_uuid('align_end'), 'Right aligned (text-end)', 'h5', [
  'alignment' => 'text-end',
], canvas_uuid('typo_wrapper'), 'content');

// ===========================================================================
// SECTION 4: Buttons — Full Matrix
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('btn_wrapper'), [
  'html_tag' => 'section',
  'container_type' => 'container',
  'padding_y' => 'py-5',
  'flex_enabled' => TRUE,
  'flex_direction' => 'flex-column',
  'flex_gap' => 'gap-3',
  'custom_class' => 'border-bottom',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('btn_title'), 'Buttons', 'h2', [
  'text_color' => 'text-primary',
], canvas_uuid('btn_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('btn_intro'), '<p>Buttons are for primary actions. Each button has a <code>variant</code> (color), optional <code>outline</code> style, and <code>size</code> (default, sm, lg). Below is the complete button matrix.</p>', [], canvas_uuid('btn_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('btn_dev_hint'), '<p class="small text-muted"><strong>Developers:</strong> Button colors inherit from the Bootstrap <code>$theme-colors</code> map. Changing <code>$primary</code> in <code>scss/_variables.scss</code> automatically updates all Primary buttons, outlines, and hover states. The Button component (<code>components/button/</code>) renders as an <code>&lt;a class="btn btn-{variant}"&gt;</code> — no spacing props, so use a parent Wrapper with flex for button groups.</p>', [], canvas_uuid('btn_wrapper'), 'content');

// --- Solid buttons: all 8 variants ---
$tree[] = canvas_heading(canvas_uuid('btn_solid_label'), 'Solid Buttons', 'h4', [], canvas_uuid('btn_wrapper'), 'content');

$btn_variants = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];

$tree[] = canvas_row(canvas_uuid('btn_solid_row'), [
  'row_cols' => 'row-cols-2',
  'row_cols_md' => 'row-cols-md-4',
  'gap' => 'g-3',
], canvas_uuid('btn_wrapper'), 'content');

foreach ($btn_variants as $i => $bv) {
  $tree[] = canvas_column(canvas_uuid("btn_s_col_{$i}"), [], canvas_uuid('btn_solid_row'), 'row');

  $tree[] = canvas_button(canvas_uuid("btn_s_{$i}"), ucfirst($bv), '/component-showcase', [
    'variant' => $bv,
    'outline' => FALSE,
  ], canvas_uuid("btn_s_col_{$i}"), 'column');
}

// --- Outline buttons: all 8 variants ---
$tree[] = canvas_heading(canvas_uuid('btn_outline_label'), 'Outline Buttons', 'h4', [], canvas_uuid('btn_wrapper'), 'content');

$tree[] = canvas_row(canvas_uuid('btn_outline_row'), [
  'row_cols' => 'row-cols-2',
  'row_cols_md' => 'row-cols-md-4',
  'gap' => 'g-3',
], canvas_uuid('btn_wrapper'), 'content');

foreach ($btn_variants as $i => $bv) {
  $tree[] = canvas_column(canvas_uuid("btn_o_col_{$i}"), [], canvas_uuid('btn_outline_row'), 'row');

  $tree[] = canvas_button(canvas_uuid("btn_o_{$i}"), ucfirst($bv), '/component-showcase', [
    'variant' => $bv,
    'outline' => TRUE,
  ], canvas_uuid("btn_o_col_{$i}"), 'column');
}

// --- Button sizes ---
$tree[] = canvas_heading(canvas_uuid('btn_size_label'), 'Button Sizes', 'h4', [], canvas_uuid('btn_wrapper'), 'content');

$tree[] = canvas_row(canvas_uuid('btn_size_row'), [
  'row_cols' => 'row-cols-1',
  'row_cols_md' => 'row-cols-md-3',
  'gap' => 'g-3',
], canvas_uuid('btn_wrapper'), 'content');

$btn_sizes = [
  ['size' => 'sm', 'label' => 'Small (sm)'],
  ['size' => 'default', 'label' => 'Default'],
  ['size' => 'lg', 'label' => 'Large (lg)'],
];

foreach ($btn_sizes as $i => $bs) {
  $tree[] = canvas_column(canvas_uuid("btn_sz_col_{$i}"), [], canvas_uuid('btn_size_row'), 'row');

  $tree[] = canvas_button(canvas_uuid("btn_sz_{$i}"), $bs['label'], '/component-showcase', [
    'size' => $bs['size'],
  ], canvas_uuid("btn_sz_col_{$i}"), 'column');
}

// ===========================================================================
// SECTION 5: Links
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('link_wrapper'), [
  'html_tag' => 'section',
  'container_type' => 'container',
  'padding_y' => 'py-5',
  'flex_enabled' => TRUE,
  'flex_direction' => 'flex-column',
  'flex_gap' => 'gap-3',
  'custom_class' => 'border-bottom',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('link_title'), 'Links', 'h2', [
  'text_color' => 'text-primary',
], canvas_uuid('link_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('link_intro'), '<p>The <strong>Link</strong> component provides standalone navigation links. Links can open in a new tab, be styled as buttons, or act as stretched links (making an entire parent card clickable).</p>', [], canvas_uuid('link_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('link_dev_hint'), '<p class="small text-muted"><strong>Developers:</strong> Link color comes from <code>$link-color</code> (defaults to <code>$primary</code>) defined in <code>scss/_variables.scss</code>. The <code>stretched_link</code> prop adds Bootstrap\'s <code>.stretched-link</code> class, making the closest <code>position: relative</code> ancestor fully clickable — perfect for card CTAs.</p>', [], canvas_uuid('link_wrapper'), 'content');

$tree[] = canvas_link(canvas_uuid('link_basic'), 'Standard link — Learn more about our platform', '/component-showcase', [], canvas_uuid('link_wrapper'), 'content');

$tree[] = canvas_link(canvas_uuid('link_new_tab'), 'Link opening in new tab (target: _blank)', '/component-showcase', [
  'target' => 'new',
], canvas_uuid('link_wrapper'), 'content');

// ===========================================================================
// SECTION 6: Grid Layouts — 2-col, 3-col, 4-col
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('grid_wrapper'), [
  'html_tag' => 'section',
  'container_type' => 'container',
  'padding_y' => 'py-5',
  'flex_enabled' => TRUE,
  'flex_direction' => 'flex-column',
  'flex_gap' => 'gap-4',
  'custom_class' => 'border-bottom',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('grid_title'), 'Grid Layouts', 'h2', [
  'text_color' => 'text-primary',
], canvas_uuid('grid_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('grid_intro'), '<p>Use <strong>Row</strong> and <strong>Column</strong> components to create responsive grid layouts. The <code>row_cols_md</code> prop controls how many columns appear at medium+ screen widths. On mobile, all layouts stack to a single column.</p>', [], canvas_uuid('grid_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('grid_dev_hint'), '<p class="small text-muted"><strong>Developers:</strong> The grid is Bootstrap 5\'s standard <code>.row</code> / <code>.col</code> system. Row component renders <code>&lt;div class="row row-cols-{n}"&gt;</code> and Column renders <code>&lt;div class="col"&gt;</code>. Row accepts <code>row_cols</code>, <code>row_cols_md</code>, <code>row_cols_lg</code> for responsive breakpoints, and <code>gap</code> (e.g., <code>g-4</code>) for gutters. Column supports <code>col</code> prop for specific widths like <code>col-6</code> or <code>col-md-4</code>.</p>', [], canvas_uuid('grid_wrapper'), 'content');

// --- 2-column grid ---
$tree[] = canvas_heading(canvas_uuid('grid2_label'), 'Two-Column Layout', 'h4', [], canvas_uuid('grid_wrapper'), 'content');

$tree[] = canvas_row(canvas_uuid('grid2_row'), [
  'row_cols' => 'row-cols-1',
  'row_cols_md' => 'row-cols-md-2',
  'gap' => 'g-4',
], canvas_uuid('grid_wrapper'), 'content');

$grid2_cols = [
  ['name' => 'g2c1', 'title' => 'Left Column', 'body' => 'Two-column layouts work well for side-by-side comparisons, feature highlights paired with descriptions, or any content that benefits from equal halves.'],
  ['name' => 'g2c2', 'title' => 'Right Column', 'body' => 'Columns automatically stack on mobile for a responsive experience. No additional configuration is needed — the grid handles breakpoints for you.'],
];

foreach ($grid2_cols as $gc) {
  $tree[] = canvas_column(canvas_uuid($gc['name']), [], canvas_uuid('grid2_row'), 'row');

  $tree[] = canvas_wrapper(canvas_uuid($gc['name'] . '_wrap'), [
    'html_tag' => 'div',
    'padding_all' => 'p-3',
    'custom_class' => 'bg-light rounded-3 h-100',
  ], canvas_uuid($gc['name']), 'column');

  $tree[] = canvas_heading(canvas_uuid($gc['name'] . '_h'), $gc['title'], 'h5', [
    'text_color' => 'text-dark',
  ], canvas_uuid($gc['name'] . '_wrap'), 'content');

  $tree[] = canvas_paragraph(canvas_uuid($gc['name'] . '_p'), '<p>' . $gc['body'] . '</p>', [], canvas_uuid($gc['name'] . '_wrap'), 'content');
}

// --- 3-column grid ---
$tree[] = canvas_heading(canvas_uuid('grid3_label'), 'Three-Column Layout', 'h4', [], canvas_uuid('grid_wrapper'), 'content');

$tree[] = canvas_row(canvas_uuid('grid3_row'), [
  'row_cols' => 'row-cols-1',
  'row_cols_md' => 'row-cols-md-3',
  'gap' => 'g-4',
], canvas_uuid('grid_wrapper'), 'content');

$grid3_cols = [
  ['name' => 'g3c1', 'title' => 'Step 1: Plan', 'body' => 'Define the page structure. Decide which sections and components you need before building.'],
  ['name' => 'g3c2', 'title' => 'Step 2: Build', 'body' => 'Add components to the Canvas editor. Use wrappers for sections, rows for grids, and cards for features.'],
  ['name' => 'g3c3', 'title' => 'Step 3: Publish', 'body' => 'Preview your page on mobile and desktop, verify the layout, then publish for your audience.'],
];

foreach ($grid3_cols as $gc) {
  $tree[] = canvas_column(canvas_uuid($gc['name']), [], canvas_uuid('grid3_row'), 'row');

  $tree[] = canvas_wrapper(canvas_uuid($gc['name'] . '_wrap'), [
    'html_tag' => 'div',
    'padding_all' => 'p-3',
    'custom_class' => 'bg-light rounded-3 h-100',
  ], canvas_uuid($gc['name']), 'column');

  $tree[] = canvas_heading(canvas_uuid($gc['name'] . '_h'), $gc['title'], 'h5', [
    'text_color' => 'text-dark',
  ], canvas_uuid($gc['name'] . '_wrap'), 'content');

  $tree[] = canvas_paragraph(canvas_uuid($gc['name'] . '_p'), '<p>' . $gc['body'] . '</p>', [], canvas_uuid($gc['name'] . '_wrap'), 'content');
}

// --- 4-column grid ---
$tree[] = canvas_heading(canvas_uuid('grid4_label'), 'Four-Column Layout', 'h4', [], canvas_uuid('grid_wrapper'), 'content');

$tree[] = canvas_row(canvas_uuid('grid4_row'), [
  'row_cols' => 'row-cols-2',
  'row_cols_md' => 'row-cols-md-4',
  'gap' => 'g-4',
], canvas_uuid('grid_wrapper'), 'content');

$grid4_cols = [
  ['name' => 'g4c1', 'icon' => '01', 'title' => 'Design', 'body' => 'Create wireframes and plan your component hierarchy.'],
  ['name' => 'g4c2', 'icon' => '02', 'title' => 'Develop', 'body' => 'Build SDC components with Twig templates and YAML props.'],
  ['name' => 'g4c3', 'icon' => '03', 'title' => 'Compose', 'body' => 'Assemble pages in Canvas using the visual editor.'],
  ['name' => 'g4c4', 'icon' => '04', 'title' => 'Deploy', 'body' => 'Export configurations and deploy across environments.'],
];

foreach ($grid4_cols as $gc) {
  $tree[] = canvas_column(canvas_uuid($gc['name']), [], canvas_uuid('grid4_row'), 'row');

  $tree[] = canvas_wrapper(canvas_uuid($gc['name'] . '_wrap'), [
    'html_tag' => 'div',
    'padding_all' => 'p-3',
    'custom_class' => 'text-center bg-light rounded-3 h-100',
  ], canvas_uuid($gc['name']), 'column');

  $tree[] = canvas_heading(canvas_uuid($gc['name'] . '_num'), $gc['icon'], 'h2', [
    'text_color' => 'text-primary',
    'alignment' => 'text-center',
  ], canvas_uuid($gc['name'] . '_wrap'), 'content');

  $tree[] = canvas_heading(canvas_uuid($gc['name'] . '_h'), $gc['title'], 'h5', [
    'alignment' => 'text-center',
  ], canvas_uuid($gc['name'] . '_wrap'), 'content');

  $tree[] = canvas_paragraph(canvas_uuid($gc['name'] . '_p'), '<p>' . $gc['body'] . '</p>', [], canvas_uuid($gc['name'] . '_wrap'), 'content');
}

// ===========================================================================
// SECTION 7: Cards — Basic, Header/Footer, Colored
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('cards_wrapper'), [
  'html_tag' => 'section',
  'container_type' => 'container',
  'padding_y' => 'py-5',
  'flex_enabled' => TRUE,
  'flex_direction' => 'flex-column',
  'flex_gap' => 'gap-4',
  'custom_class' => 'border-bottom',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('cards_title'), 'Cards', 'h2', [
  'text_color' => 'text-primary',
], canvas_uuid('cards_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('cards_intro'), '<p>Cards group related content with optional <strong>header</strong>, <strong>image</strong>, <strong>body</strong>, and <strong>footer</strong> slots. They support background colors, border colors, and rounded corners. Cards work great in grid layouts for feature lists, team profiles, or product catalogs.</p>', [], canvas_uuid('cards_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('cards_dev_hint'), '<p class="small text-muted"><strong>Developers:</strong> The Card component (<code>components/card/</code>) is the most complex SDC — it has 4 named slots (<code>card_header</code>, <code>card_image</code>, <code>card_body</code>, <code>card_footer</code>) controlled by <code>show_header</code>, <code>show_image</code>, <code>show_footer</code> boolean props. It supports <code>bg_color</code> (11 options), <code>border_color</code> (9 options), <code>card_rounding</code>, <code>orientation</code> (horizontal), and <code>position</code> (static/relative for stretched-link cards).</p>', [], canvas_uuid('cards_wrapper'), 'content');

// --- Basic cards (body only) ---
$tree[] = canvas_heading(canvas_uuid('cards_basic_label'), 'Basic Cards (Body Only)', 'h4', [], canvas_uuid('cards_wrapper'), 'content');

$tree[] = canvas_row(canvas_uuid('cards_basic_row'), [
  'row_cols' => 'row-cols-1',
  'row_cols_md' => 'row-cols-md-3',
  'gap' => 'g-4',
], canvas_uuid('cards_wrapper'), 'content');

$basic_cards = [
  ['name' => 'bc1', 'title' => 'Alchemize Dev', 'desc' => 'CLI-integrated AI development platform. Write, test, and deploy with structured AI assistance.', 'border' => 'border-primary'],
  ['name' => 'bc2', 'title' => 'Alchemize Studio', 'desc' => 'Visual media production engine powered by AI. Create images, videos, and graphics at scale.', 'border' => 'border-info'],
  ['name' => 'bc3', 'title' => 'Alchemize Beats', 'desc' => 'AI-driven audio and music production. Generate beats, stems, and soundscapes for your projects.', 'border' => 'border-success'],
];

foreach ($basic_cards as $cd) {
  $tree[] = canvas_column(canvas_uuid($cd['name'] . '_col'), [], canvas_uuid('cards_basic_row'), 'row');

  $tree[] = canvas_card(canvas_uuid($cd['name']), [
    'bg_color' => 'bg-white',
    'border_color' => $cd['border'],
    'card_rounding' => 'rounded-3',
    'position' => 'relative',
  ], canvas_uuid($cd['name'] . '_col'), 'column');

  $tree[] = canvas_heading(canvas_uuid($cd['name'] . '_h'), $cd['title'], 'h3', [], canvas_uuid($cd['name']), 'card_body');

  $tree[] = canvas_paragraph(canvas_uuid($cd['name'] . '_p'), '<p>' . $cd['desc'] . '</p>', [
    'margin_bottom' => 'mb-3',
  ], canvas_uuid($cd['name']), 'card_body');

  $tree[] = canvas_link(canvas_uuid($cd['name'] . '_link'), 'Learn more →', '/component-showcase', [
    'stretched_link' => TRUE,
  ], canvas_uuid($cd['name']), 'card_body');
}

// --- Cards with header and footer ---
$tree[] = canvas_heading(canvas_uuid('cards_hf_label'), 'Cards with Header & Footer', 'h4', [], canvas_uuid('cards_wrapper'), 'content');

$tree[] = canvas_row(canvas_uuid('cards_hf_row'), [
  'row_cols' => 'row-cols-1',
  'row_cols_md' => 'row-cols-md-3',
  'gap' => 'g-4',
], canvas_uuid('cards_wrapper'), 'content');

$hf_cards = [
  ['name' => 'hfc1', 'header' => 'Featured', 'title' => 'Pro Plan', 'desc' => 'Full access to all AI tools, priority support, and unlimited API calls.', 'footer' => '$49/month', 'bg' => 'bg-white', 'border' => 'border-primary'],
  ['name' => 'hfc2', 'header' => 'Popular', 'title' => 'Team Plan', 'desc' => 'Collaborative workspace for up to 10 team members with shared resources.', 'footer' => '$99/month', 'bg' => 'bg-white', 'border' => 'border-secondary'],
  ['name' => 'hfc3', 'header' => 'Enterprise', 'title' => 'Custom Plan', 'desc' => 'Dedicated infrastructure, SLA guarantees, and custom integrations.', 'footer' => 'Contact us', 'bg' => 'bg-white', 'border' => 'border-dark'],
];

foreach ($hf_cards as $cd) {
  $tree[] = canvas_column(canvas_uuid($cd['name'] . '_col'), [], canvas_uuid('cards_hf_row'), 'row');

  $tree[] = canvas_card(canvas_uuid($cd['name']), [
    'show_header' => TRUE,
    'show_footer' => TRUE,
    'bg_color' => $cd['bg'],
    'border_color' => $cd['border'],
    'card_rounding' => 'rounded-3',
    'position' => 'static',
  ], canvas_uuid($cd['name'] . '_col'), 'column');

  // Header slot content
  $tree[] = canvas_heading(canvas_uuid($cd['name'] . '_hdr'), $cd['header'], 'h6', [
    'text_color' => 'text-muted',
  ], canvas_uuid($cd['name']), 'card_header');

  // Body slot content
  $tree[] = canvas_heading(canvas_uuid($cd['name'] . '_h'), $cd['title'], 'h3', [], canvas_uuid($cd['name']), 'card_body');

  $tree[] = canvas_paragraph(canvas_uuid($cd['name'] . '_p'), '<p>' . $cd['desc'] . '</p>', [], canvas_uuid($cd['name']), 'card_body');

  // Footer slot content
  $tree[] = canvas_paragraph(canvas_uuid($cd['name'] . '_ftr'), '<p class="fw-bold mb-0">' . $cd['footer'] . '</p>', [], canvas_uuid($cd['name']), 'card_footer');
}

// --- Colored background cards ---
$tree[] = canvas_heading(canvas_uuid('cards_bg_label'), 'Colored Background Cards', 'h4', [], canvas_uuid('cards_wrapper'), 'content');

$tree[] = canvas_row(canvas_uuid('cards_bg_row'), [
  'row_cols' => 'row-cols-2',
  'row_cols_md' => 'row-cols-md-4',
  'gap' => 'g-3',
], canvas_uuid('cards_wrapper'), 'content');

$bg_cards = [
  ['name' => 'bgc1', 'bg' => 'bg-primary', 'text_color' => 'text-white', 'label' => 'Primary'],
  ['name' => 'bgc2', 'bg' => 'bg-secondary', 'text_color' => 'text-white', 'label' => 'Secondary'],
  ['name' => 'bgc3', 'bg' => 'bg-success', 'text_color' => 'text-white', 'label' => 'Success'],
  ['name' => 'bgc4', 'bg' => 'bg-dark', 'text_color' => 'text-white', 'label' => 'Dark'],
];

foreach ($bg_cards as $cd) {
  $tree[] = canvas_column(canvas_uuid($cd['name'] . '_col'), [], canvas_uuid('cards_bg_row'), 'row');

  $tree[] = canvas_card(canvas_uuid($cd['name']), [
    'bg_color' => $cd['bg'],
    'card_rounding' => 'rounded-3',
    'position' => 'static',
  ], canvas_uuid($cd['name'] . '_col'), 'column');

  $tree[] = canvas_heading(canvas_uuid($cd['name'] . '_h'), $cd['label'], 'h5', [
    'text_color' => $cd['text_color'],
  ], canvas_uuid($cd['name']), 'card_body');

  $tree[] = canvas_paragraph(canvas_uuid($cd['name'] . '_p'), '<p class="' . $cd['text_color'] . '">Card with <code class="' . $cd['text_color'] . '">' . $cd['bg'] . '</code> background.</p>', [], canvas_uuid($cd['name']), 'card_body');
}

// ===========================================================================
// SECTION 8: Accordion (FAQ)
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('faq_wrapper'), [
  'html_tag' => 'section',
  'container_type' => 'container',
  'padding_y' => 'py-5',
  'flex_enabled' => TRUE,
  'flex_direction' => 'flex-column',
  'flex_gap' => 'gap-3',
  'custom_class' => 'border-bottom',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('faq_title'), 'Accordion (FAQ)', 'h2', [
  'text_color' => 'text-primary',
], canvas_uuid('faq_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('faq_intro'), '<p>Accordions are ideal for FAQs, collapsible sections, and progressive disclosure. The <strong>Accordion Container</strong> wraps individual <strong>Accordion</strong> items. Each item has a title bar and expandable body that can contain any components. Set <code>open_by_default</code> to expand an item initially.</p>', [], canvas_uuid('faq_wrapper'), 'content');

$tree[] = canvas_accordion_container(canvas_uuid('acc_container'), [], canvas_uuid('faq_wrapper'), 'content');

$faq_items = [
  [
    'name' => 'faq1',
    'title' => 'What is Canvas?',
    'body' => '<p>Canvas is a visual page building system for Drupal. It lets you compose pages from reusable components — headings, paragraphs, cards, grids — without writing code. Components are defined as SDC (Single Directory Components) in your theme.</p>',
    'open' => TRUE,
  ],
  [
    'name' => 'faq2',
    'title' => 'How do Content Templates work?',
    'body' => '<p>Content Templates define a reusable layout for a content type (e.g., Article, Basic Page). They include template-locked components (like a heading bound to the title field) and <strong>exposed slots</strong> where editors can add per-content components.</p>',
    'open' => FALSE,
  ],
  [
    'name' => 'faq3',
    'title' => 'What are Exposed Slots?',
    'body' => '<p>Exposed slots are designated areas within a Content Template where editors can insert their own components. The template defines the page structure; the exposed slot is where unique per-page content goes. Think of it as a "content zone" within a fixed layout.</p>',
    'open' => FALSE,
  ],
  [
    'name' => 'faq4',
    'title' => 'How do I create a new page?',
    'body' => '<p>Navigate to <strong>Content → Add content → Basic page</strong>. Give your page a title, then open the Canvas editor to add components into the exposed slot. You can add headings, paragraphs, cards, grids — anything shown on this showcase page.</p>',
    'open' => FALSE,
  ],
  [
    'name' => 'faq5',
    'title' => 'Can I nest components?',
    'body' => '<p>Yes! Components can be nested up to 5+ levels deep. For example: <strong>Wrapper → Row → Column → Card → Heading + Paragraph</strong>. Use wrappers for sections, rows and columns for grids, and cards for grouped content.</p>',
    'open' => FALSE,
  ],
  [
    'name' => 'faq6',
    'title' => 'How do I change the brand colors?',
    'body' => '<p>Open <code>scss/_variables.scss</code> in your theme. Change <code>$accent-shade</code> (mapped to Bootstrap\'s <code>$primary</code>) and <code>$primary-shade</code> (mapped to <code>$secondary</code>). Then rebuild CSS with <code>ddev theme-build</code> and clear Drupal cache with <code>ddev drush cr</code>. Every button, background, and text color on the site will update automatically.</p>',
    'open' => FALSE,
  ],
  [
    'name' => 'faq7',
    'title' => 'How do I add a new SDC component?',
    'body' => '<p>Create a new directory under <code>components/</code> with two files: a <code>.component.yml</code> (defining props, slots, and metadata) and a <code>.twig</code> template (the HTML output). Clear cache with <code>ddev drush cr</code> and the component appears in Canvas. Study <code>components/heading/</code> for a simple example or <code>components/wrapper/</code> for a complex one.</p>',
    'open' => FALSE,
  ],
  [
    'name' => 'faq8',
    'title' => 'Where do I add custom CSS or JS?',
    'body' => '<p><strong>CSS:</strong> Add custom styles to <code>scss/style.scss</code> after the import line. Your rules will be compiled to <code>css/style.css</code> and loaded after Bootstrap.<br><strong>JS:</strong> Add custom Drupal behaviors in <code>js/custom.js</code>. Use the <code>Drupal.behaviors</code> pattern so your code runs on page load and after AJAX content inserts.</p>',
    'open' => FALSE,
  ],
  [
    'name' => 'faq9',
    'title' => 'How do I rebuild the CSS after SCSS changes?',
    'body' => '<p>Run <code>ddev theme-build</code> for a production build, or <code>ddev theme-build --watch</code> for auto-rebuilding during development. Use <code>ddev theme-build --clean</code> for a fresh compile. Remember to also run <code>ddev drush cr</code> if Drupal has cached the old CSS aggregation.</p>',
    'open' => FALSE,
  ],
];

foreach ($faq_items as $faq) {
  $tree[] = canvas_accordion(canvas_uuid($faq['name']), $faq['title'], [
    'open_by_default' => $faq['open'],
  ], canvas_uuid('acc_container'), 'accordion_content');

  $tree[] = canvas_paragraph(canvas_uuid($faq['name'] . '_body'), $faq['body'], [], canvas_uuid($faq['name']), 'accordion_body');
}

// ===========================================================================
// SECTION 9: Blockquotes
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('bq_wrapper'), [
  'html_tag' => 'section',
  'container_type' => 'container',
  'padding_y' => 'py-5',
  'flex_enabled' => TRUE,
  'flex_direction' => 'flex-column',
  'flex_gap' => 'gap-3',
  'custom_class' => 'border-bottom',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('bq_title'), 'Blockquotes', 'h2', [
  'text_color' => 'text-primary',
], canvas_uuid('bq_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('bq_intro'), '<p>Blockquotes display quoted text with optional footer attribution and citation. They support alignment, italic style, opacity, and text color customization.</p>', [], canvas_uuid('bq_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('bq_dev_hint'), '<p class="small text-muted"><strong>Developers — Important:</strong> The blockquote Twig template wraps text in <code>&lt;p&gt;</code> tags automatically. Always pass <strong>plain text</strong> to the <code>text</code> prop — never HTML with <code>&lt;p&gt;</code> tags, or it will double-wrap. This differs from the Paragraph component, which expects HTML with <code>&lt;p&gt;</code> tags.</p>', [], canvas_uuid('bq_wrapper'), 'content');

// Default blockquote — text is PLAIN TEXT (template wraps in <p> automatically)
$tree[] = canvas_blockquote(canvas_uuid('bq_default'), 'The best way to predict the future is to create it. AI-driven tools like Alchemize empower builders to do exactly that.', [
  'footer' => 'Alchemize Team',
  'cite' => 'Internal Design Principles',
  'alignment' => 'text-start',
], canvas_uuid('bq_wrapper'), 'content');

// Center-aligned blockquote
$tree[] = canvas_blockquote(canvas_uuid('bq_center'), 'Good design is as little design as possible. Less, but better — because it concentrates on the essential aspects.', [
  'footer' => 'Dieter Rams',
  'cite' => 'Ten Principles for Good Design',
  'alignment' => 'text-center',
  'italic' => TRUE,
], canvas_uuid('bq_wrapper'), 'content');

// Right-aligned blockquote with color
$tree[] = canvas_blockquote(canvas_uuid('bq_right'), 'Simplicity is the ultimate sophistication.', [
  'footer' => 'Leonardo da Vinci',
  'alignment' => 'text-end',
  'text_color' => 'text-secondary',
], canvas_uuid('bq_wrapper'), 'content');

// ===========================================================================
// SECTION 10: Images
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('img_wrapper'), [
  'html_tag' => 'section',
  'container_type' => 'container',
  'padding_y' => 'py-5',
  'flex_enabled' => TRUE,
  'flex_direction' => 'flex-column',
  'flex_gap' => 'gap-3',
  'custom_class' => 'border-bottom',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('img_title'), 'Images', 'h2', [
  'text_color' => 'text-primary',
], canvas_uuid('img_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('img_intro'), '<p>The Image component renders media with configurable aspect ratio (<code>size</code>) and border radius. Images can be placed standalone, inside cards, or anywhere in the component tree. Available aspect ratios: 1:1, 2:1, 3:1, 4:1, 4:3, 16:9, 21:9.</p>', [], canvas_uuid('img_wrapper'), 'content');

$tree[] = canvas_image(canvas_uuid('img_example'), [
  'src' => '/core/misc/druplicon.png',
  'alt' => 'Drupal logo — example image component',
  'width' => '200',
  'height' => '200',
], [
  'size' => '1:1',
  'radius' => 'small',
], canvas_uuid('img_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('img_caption'), '<p><em>Above: The Drupal logo rendered via the Image component with 1:1 aspect ratio and small border radius.</em></p>', [], canvas_uuid('img_wrapper'), 'content');

// ===========================================================================
// SECTION 11: Wrappers & Spacing
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('space_wrapper'), [
  'html_tag' => 'section',
  'container_type' => 'container',
  'padding_y' => 'py-5',
  'flex_enabled' => TRUE,
  'flex_direction' => 'flex-column',
  'flex_gap' => 'gap-4',
  'custom_class' => 'border-bottom',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('space_title'), 'Wrappers & Spacing', 'h2', [
  'text_color' => 'text-primary',
], canvas_uuid('space_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('space_intro'), '<p>The <strong>Wrapper</strong> component is the most versatile building block. It creates HTML sections or divs with configurable container types, spacing (margin/padding from 0-5), flex utilities, width/height classes, and custom CSS classes. Wrappers also support flex layouts with direction, gap, justify-content, and align-items.</p>', [], canvas_uuid('space_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('space_dev_hint'), '<p class="small text-muted"><strong>Developers — Composition Rule:</strong> Every page section should use a Wrapper with <code>flex_enabled: TRUE</code>, <code>flex_direction: flex-column</code>, and <code>flex_gap: gap-3</code>. This provides consistent vertical spacing between all children without needing <code>margin_bottom</code> on each child. The page template already provides a container + row wrapping, so section wrappers with <code>container_type</code> create nested containers — which is the standard Bootstrap pattern for sections with different backgrounds.</p>', [], canvas_uuid('space_wrapper'), 'content');

// Padding demonstration
$tree[] = canvas_heading(canvas_uuid('space_pad_label'), 'Padding Scale (p-0 through p-5)', 'h4', [], canvas_uuid('space_wrapper'), 'content');

$tree[] = canvas_row(canvas_uuid('space_pad_row'), [
  'row_cols' => 'row-cols-3',
  'row_cols_md' => 'row-cols-md-6',
  'gap' => 'g-2',
], canvas_uuid('space_wrapper'), 'content');

for ($i = 0; $i <= 5; $i++) {
  $tree[] = canvas_column(canvas_uuid("pad_col_{$i}"), [], canvas_uuid('space_pad_row'), 'row');

  $tree[] = canvas_wrapper(canvas_uuid("pad_demo_{$i}"), [
    'html_tag' => 'div',
    'padding_all' => "p-{$i}",
    'custom_class' => 'bg-primary bg-opacity-10 border border-primary rounded text-center',
  ], canvas_uuid("pad_col_{$i}"), 'column');

  $tree[] = canvas_paragraph(canvas_uuid("pad_label_{$i}"), "<p class=\"fw-bold mb-0\">p-{$i}</p>", [], canvas_uuid("pad_demo_{$i}"), 'content');
}

// Dark wrapper example
$tree[] = canvas_heading(canvas_uuid('wrap_dark_label'), 'Dark Wrapper Example', 'h4', [], canvas_uuid('space_wrapper'), 'content');

$tree[] = canvas_wrapper(canvas_uuid('wrap_demo_dark'), [
  'html_tag' => 'div',
  'padding_all' => 'p-4',
  'flex_enabled' => TRUE,
  'flex_direction' => 'flex-column',
  'flex_gap' => 'gap-2',
  'custom_class' => 'bg-dark text-white rounded-3',
], canvas_uuid('space_wrapper'), 'content');

$tree[] = canvas_heading(canvas_uuid('wrap_demo_dark_h'), 'This is a dark wrapper', 'h4', [
  'text_color' => 'text-white',
], canvas_uuid('wrap_demo_dark'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('wrap_demo_dark_p'), '<p>This content sits inside a Wrapper with <code class="text-white">bg-dark text-white rounded-3</code> custom classes and <code class="text-white">p-4</code> padding. Wrappers are how you create visually distinct sections on a page.</p>', [], canvas_uuid('wrap_demo_dark'), 'content');

// Primary wrapper example
$tree[] = canvas_heading(canvas_uuid('wrap_primary_label'), 'Primary Color Wrapper', 'h4', [], canvas_uuid('space_wrapper'), 'content');

$tree[] = canvas_wrapper(canvas_uuid('wrap_demo_primary'), [
  'html_tag' => 'div',
  'padding_all' => 'p-4',
  'flex_enabled' => TRUE,
  'flex_direction' => 'flex-column',
  'flex_gap' => 'gap-2',
  'custom_class' => 'bg-primary text-white rounded-3',
], canvas_uuid('space_wrapper'), 'content');

$tree[] = canvas_heading(canvas_uuid('wrap_demo_primary_h'), 'Primary branded section', 'h4', [
  'text_color' => 'text-white',
], canvas_uuid('wrap_demo_primary'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('wrap_demo_primary_p'), '<p>Use <code class="text-white">bg-primary</code> to create sections that match the brand identity. Combine with <code class="text-white">text-white</code> for proper contrast.</p>', [], canvas_uuid('wrap_demo_primary'), 'content');

// Flex layout demonstration
$tree[] = canvas_heading(canvas_uuid('flex_label'), 'Flex Layout (Wrapper with flex_enabled)', 'h4', [], canvas_uuid('space_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('flex_intro'), '<p>Enable <code>flex_enabled</code> on a wrapper to use flexbox utilities. Control direction (<code>flex-row</code> / <code>flex-column</code>), gap, justify-content, and align-items.</p>', [], canvas_uuid('space_wrapper'), 'content');

$tree[] = canvas_wrapper(canvas_uuid('flex_demo'), [
  'html_tag' => 'div',
  'flex_enabled' => TRUE,
  'flex_direction' => 'flex-row',
  'flex_gap' => 'gap-3',
  'justify_content' => 'justify-content-center',
  'align_items' => 'align-items-center',
  'padding_all' => 'p-3',
  'custom_class' => 'bg-light rounded-3',
], canvas_uuid('space_wrapper'), 'content');

$tree[] = canvas_button(canvas_uuid('flex_btn1'), 'Flex Item 1', '/component-showcase', [
  'variant' => 'primary',
], canvas_uuid('flex_demo'), 'content');

$tree[] = canvas_button(canvas_uuid('flex_btn2'), 'Flex Item 2', '/component-showcase', [
  'variant' => 'secondary',
], canvas_uuid('flex_demo'), 'content');

$tree[] = canvas_button(canvas_uuid('flex_btn3'), 'Flex Item 3', '/component-showcase', [
  'variant' => 'info',
], canvas_uuid('flex_demo'), 'content');

// ===========================================================================
// SECTION 12: Quick Reference
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('ref_wrapper'), [
  'html_tag' => 'section',
  'container_type' => 'container',
  'padding_y' => 'py-5',
  'flex_enabled' => TRUE,
  'flex_direction' => 'flex-column',
  'flex_gap' => 'gap-4',
  'custom_class' => 'bg-light',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('ref_title'), 'Quick Reference', 'h2', [
  'text_color' => 'text-primary',
  'alignment' => 'text-center',
], canvas_uuid('ref_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('ref_table'), '<table class="table table-striped table-bordered"><thead><tr><th>Component</th><th>Purpose</th><th>Key Props</th><th>Slots</th></tr></thead><tbody><tr><td><strong>Wrapper</strong></td><td>Section container</td><td>html_tag, container_type, padding/margin (0-5), flex, custom_class</td><td>content</td></tr><tr><td><strong>Heading</strong></td><td>Titles & headings</td><td>text, level (h1-h6), alignment, text_color</td><td>—</td></tr><tr><td><strong>Paragraph</strong></td><td>Rich text body</td><td>text (HTML), margin/padding utilities</td><td>—</td></tr><tr><td><strong>Button</strong></td><td>Call-to-action</td><td>text, variant (8), size (sm/default/lg), outline, url</td><td>—</td></tr><tr><td><strong>Link</strong></td><td>Navigation</td><td>text, url, target, stretched_link, as_button</td><td>—</td></tr><tr><td><strong>Blockquote</strong></td><td>Quoted text</td><td>text (plain), footer, cite, alignment, italic, opacity, text_color</td><td>—</td></tr><tr><td><strong>Image</strong></td><td>Media display</td><td>media (src/alt/width/height), size (aspect ratio), radius</td><td>—</td></tr><tr><td><strong>Row</strong></td><td>Grid container</td><td>row_cols, row_cols_md, row_cols_lg, gap</td><td>row</td></tr><tr><td><strong>Column</strong></td><td>Grid column</td><td>col (sizing class)</td><td>column</td></tr><tr><td><strong>Card</strong></td><td>Grouped content</td><td>bg_color (11), border_color (9), show_header/image/footer, orientation</td><td>card_header, card_image, card_body, card_footer</td></tr><tr><td><strong>Accordion Container</strong></td><td>Collapsible wrapper</td><td>flush</td><td>accordion_content</td></tr><tr><td><strong>Accordion</strong></td><td>Collapsible item</td><td>title, heading_level, open_by_default</td><td>accordion_body</td></tr></tbody></table>', [], canvas_uuid('ref_wrapper'), 'content');

// Theme variables reference
$tree[] = canvas_heading(canvas_uuid('ref_vars_label'), 'Theme Variables', 'h3', [
  'alignment' => 'text-center',
], canvas_uuid('ref_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('ref_vars_table'), '<table class="table table-bordered table-sm"><thead><tr><th>Variable</th><th>Value</th><th>Bootstrap Alias</th></tr></thead><tbody><tr><td>$accent-shade</td><td>#0079C0 (Blue)</td><td>$primary</td></tr><tr><td>$primary-shade</td><td>rgb(255, 78, 46) (Orange-Red)</td><td>$secondary</td></tr><tr><td>$green</td><td>#28a745</td><td>$success</td></tr><tr><td>$cyan</td><td>#17a2b8</td><td>$info</td></tr><tr><td>$yellow</td><td>#ffc107</td><td>$warning</td></tr><tr><td>$red</td><td>#dc3545</td><td>$danger</td></tr><tr><td>$gray-100</td><td>#f8f9fa</td><td>$light</td></tr><tr><td>$gray-800</td><td>#343a40</td><td>$dark</td></tr></tbody></table>', [], canvas_uuid('ref_wrapper'), 'content');

// Typography reference
$tree[] = canvas_heading(canvas_uuid('ref_typo_label'), 'Typography Scale', 'h3', [
  'alignment' => 'text-center',
], canvas_uuid('ref_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('ref_typo_table'), '<table class="table table-bordered table-sm"><thead><tr><th>Element</th><th>Size</th><th>Pixels (at 16px base)</th></tr></thead><tbody><tr><td>h1</td><td>2.5rem</td><td>40px</td></tr><tr><td>h2</td><td>2rem</td><td>32px</td></tr><tr><td>h3</td><td>1.75rem</td><td>28px</td></tr><tr><td>h4</td><td>1.5rem</td><td>24px</td></tr><tr><td>h5</td><td>1.25rem</td><td>20px</td></tr><tr><td>h6</td><td>1rem</td><td>16px</td></tr><tr><td>Body</td><td>1rem</td><td>16px</td></tr><tr><td>Small</td><td>0.875rem</td><td>14px</td></tr></tbody></table>', [], canvas_uuid('ref_wrapper'), 'content');

// Footer note (moved to end, after developer sections)

// ===========================================================================
// SECTION 13: Theme Architecture & File Structure (Developer)
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('arch_wrapper'), [
  'html_tag' => 'section',
  'container_type' => 'container',
  'padding_y' => 'py-5',
  'flex_enabled' => TRUE,
  'flex_direction' => 'flex-column',
  'flex_gap' => 'gap-3',
  'custom_class' => 'border-bottom',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('arch_title'), 'Theme Architecture', 'h2', [
  'text_color' => 'text-primary',
], canvas_uuid('arch_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('arch_intro'), '<p class="lead">This section is for <strong>themers and developers</strong>. It explains the file structure, build tools, customization points, and development workflow for the <code>' . $theme . '</code> theme.</p>', [], canvas_uuid('arch_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('arch_overview'), '<p>The theme is a <strong>Bootstrap Barrio sub-theme</strong> built with Bootstrap 5, SCSS, Webpack, and Drupal\'s Single Directory Components (SDC). It overrides the base theme\'s global styling library entirely and compiles its own CSS from SCSS source files.</p>', [], canvas_uuid('arch_wrapper'), 'content');

// File tree
$tree[] = canvas_heading(canvas_uuid('arch_files_label'), 'File Structure', 'h3', [], canvas_uuid('arch_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('arch_filetree'), '<pre class="bg-dark text-white p-4 rounded-3"><code>' . $theme . '/
├── ' . $theme . '.info.yml        # Theme metadata, base theme, regions, libraries
├── ' . $theme . '.libraries.yml   # JS and CSS asset definitions
├── ' . $theme . '.theme           # PHP hooks (theme settings form)
├── config/
│   ├── install/                    # Default settings (sidebar, navbar, etc.)
│   ├── optional/                   # Block placement configs
│   └── schema/                     # Settings schema
├── components/                     # SDC components (Twig + YAML)
│   ├── accordion/                  # Each has .twig + .component.yml
│   ├── accordion-container/
│   ├── blockquote/
│   ├── button/
│   ├── card/
│   ├── column/
│   ├── heading/
│   ├── image/
│   ├── link/
│   ├── paragraph/
│   ├── row/
│   └── wrapper/
├── scss/                           # SCSS source (edit these)
│   ├── _variables.scss             # ★ Theme color &amp; spacing variables
│   ├── _typography.scss            # ★ Font families &amp; sizes
│   ├── _mixins.scss                # Custom SCSS mixins
│   ├── _default.scss               # Bootstrap functions + variable imports
│   ├── _import.scss                # Full Bootstrap 5 component imports
│   ├── bootstrap.scss              # Compiled → css/bootstrap.css
│   └── style.scss                  # Compiled → css/style.css
├── css/                            # Compiled output (do NOT edit)
│   ├── bootstrap.css               # Full Bootstrap 5 CSS
│   └── style.css                   # Theme-specific overrides
├── js/
│   ├── bootstrap.bundle.min.js     # Bootstrap 5.3 + Popper (do NOT edit)
│   ├── barrio.js                   # Base theme scroll/dropdown behavior
│   └── custom.js                   # ★ Your custom Drupal behaviors
├── templates/
│   └── layout/
│       └── page.html.twig          # Page template (regions, container)
├── package.json                    # npm dependencies &amp; build scripts
├── webpack.config.mjs              # Webpack SCSS → CSS pipeline
├── postcss.config.js               # PostCSS plugins (autoprefixer, pxtorem)
└── pxtorem.config.js               # px → rem conversion rules</code></pre>', [], canvas_uuid('arch_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('arch_files_note'), '<p>Files marked with <strong>★</strong> are the primary customization points. The <code>scss/</code> directory is where you make changes — the <code>css/</code> directory is generated output.</p>', [], canvas_uuid('arch_wrapper'), 'content');

// Inheritance
$tree[] = canvas_heading(canvas_uuid('arch_inherit_label'), 'Theme Inheritance', 'h3', [], canvas_uuid('arch_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('arch_inherit'), '<p>The inheritance chain is: <code>' . $theme . '</code> → <code>bootstrap_barrio</code> → <code>stable9</code>. Our theme overrides the base theme\'s global-styling library entirely:</p>', [], canvas_uuid('arch_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('arch_inherit_code'), '<pre class="bg-dark text-white p-3 rounded-3"><code># ' . $theme . '.info.yml
base theme: bootstrap_barrio

libraries:
  - ' . $theme . '/global-styling
libraries-override:
  bootstrap_barrio/global-styling: false</code></pre>', [], canvas_uuid('arch_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('arch_inherit_note'), '<p>This means we control <strong>all CSS and JS</strong> from our sub-theme. The base theme provides template inheritance, regions, and theme settings — but all assets come from our compiled SCSS and bundled JS.</p>', [], canvas_uuid('arch_wrapper'), 'content');

// ===========================================================================
// SECTION 14: SCSS / CSS Build Pipeline (Developer)
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('build_wrapper'), [
  'html_tag' => 'section',
  'container_type' => 'container',
  'padding_y' => 'py-5',
  'flex_enabled' => TRUE,
  'flex_direction' => 'flex-column',
  'flex_gap' => 'gap-3',
  'custom_class' => 'border-bottom',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('build_title'), 'SCSS / CSS Build Pipeline', 'h2', [
  'text_color' => 'text-primary',
], canvas_uuid('build_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('build_intro'), '<p>The theme uses <strong>Webpack</strong> to compile SCSS to CSS. The build pipeline processes all non-partial SCSS files (those not starting with <code>_</code>) through Sass, PostCSS (autoprefixer + px→rem), and outputs to the <code>css/</code> directory.</p>', [], canvas_uuid('build_wrapper'), 'content');

// Setup commands
$tree[] = canvas_heading(canvas_uuid('build_setup_label'), 'Initial Setup', 'h3', [], canvas_uuid('build_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('build_setup'), '<pre class="bg-dark text-white p-4 rounded-3"><code># Full build (npm install + copy Bootstrap JS + compile SCSS → CSS)
ddev theme-build

# The build script handles everything: npm install, Bootstrap JS copy,
# and webpack compilation. Run from anywhere in the project.</code></pre>', [], canvas_uuid('build_wrapper'), 'content');

// Build commands
$tree[] = canvas_heading(canvas_uuid('build_cmds_label'), 'Build Commands', 'h3', [], canvas_uuid('build_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('build_cmds'), '<table class="table table-bordered"><thead><tr><th>Command</th><th>Description</th><th>Use When</th></tr></thead><tbody><tr><td><code>ddev theme-build</code></td><td>Production build (minified)</td><td>Before deployment or committing CSS</td></tr><tr><td><code>ddev theme-build --dev</code></td><td>Development build (source maps)</td><td>During development for debugging</td></tr><tr><td><code>ddev theme-build --watch</code></td><td>Watch mode (auto-rebuild on change)</td><td>Active SCSS development</td></tr><tr><td><code>ddev theme-build --clean</code></td><td>Clean and rebuild</td><td>Fresh compile or troubleshooting</td></tr><tr><td><code>ddev theme-build --ci</code></td><td>Skip npm install if possible</td><td>CI/CD environments</td></tr></tbody></table>', [], canvas_uuid('build_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('build_cmds_ddev'), '<p><strong>How it works:</strong> <code>ddev theme-build</code> runs <code>scripts/build.sh</code> inside DDEV. The script handles npm install, copies the Bootstrap JS bundle from <code>node_modules/</code> to <code>js/</code>, and runs webpack. You can also run npm scripts directly:</p>', [], canvas_uuid('build_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('build_cmds_examples'), '<pre class="bg-dark text-white p-4 rounded-3"><code># Direct npm commands (alternative to ddev theme-build)
ddev exec "cd web/themes/custom/' . $theme . ' &amp;&amp; npm run build"
ddev exec "cd web/themes/custom/' . $theme . ' &amp;&amp; npm run watch"

# The prebuild hook automatically copies Bootstrap JS
# before every npm run build / build:dev</code></pre>', [], canvas_uuid('build_wrapper'), 'content');

// How the pipeline works
$tree[] = canvas_heading(canvas_uuid('build_pipeline_label'), 'How the Pipeline Works', 'h3', [], canvas_uuid('build_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('build_pipeline_flow'), '<p>The build flow for each SCSS entry point:</p><ol><li><strong>Webpack</strong> finds non-partial SCSS files: <code>bootstrap.scss</code>, <code>style.scss</code></li><li><strong>sass-loader</strong> compiles SCSS → CSS (resolves imports, variables, mixins)</li><li><strong>postcss-loader</strong> applies transforms:<ul><li><code>autoprefixer</code> — adds vendor prefixes for browser compatibility</li><li><code>postcss-pxtorem</code> — converts px values to rem (font-size, margin, padding, gap, border)</li><li><code>postcss-use-logical</code> — converts to logical properties (bootstrap.scss only)</li></ul></li><li><strong>css-loader</strong> + <strong>MiniCssExtractPlugin</strong> → outputs to <code>css/</code> directory</li></ol>', [], canvas_uuid('build_wrapper'), 'content');

// Two CSS files explained
$tree[] = canvas_heading(canvas_uuid('build_files_label'), 'Output Files', 'h3', [], canvas_uuid('build_wrapper'), 'content');

$tree[] = canvas_row(canvas_uuid('build_files_row'), [
  'row_cols' => 'row-cols-1',
  'row_cols_md' => 'row-cols-md-2',
  'gap' => 'g-4',
], canvas_uuid('build_wrapper'), 'content');

$tree[] = canvas_column(canvas_uuid('build_file_bs_col'), [], canvas_uuid('build_files_row'), 'row');
$tree[] = canvas_card(canvas_uuid('build_file_bs_card'), [
  'bg_color' => 'bg-white',
  'border_color' => 'border-primary',
  'card_rounding' => 'rounded-3',
  'position' => 'static',
], canvas_uuid('build_file_bs_col'), 'column');
$tree[] = canvas_heading(canvas_uuid('build_file_bs_h'), 'css/bootstrap.css', 'h5', [
  'text_color' => 'text-primary',
], canvas_uuid('build_file_bs_card'), 'card_body');
$tree[] = canvas_paragraph(canvas_uuid('build_file_bs_p'), '<p>Compiled from <code>scss/bootstrap.scss</code> → <code>_import.scss</code>. Contains the <strong>full Bootstrap 5 framework</strong> with your variable overrides baked in. This is the complete utility, grid, and component CSS.</p><p><strong>Source chain:</strong><br><code>bootstrap.scss</code> → <code>_import.scss</code> → <code>_variables.scss</code> + <code>_typography.scss</code> + Bootstrap modules</p>', [], canvas_uuid('build_file_bs_card'), 'card_body');

$tree[] = canvas_column(canvas_uuid('build_file_st_col'), [], canvas_uuid('build_files_row'), 'row');
$tree[] = canvas_card(canvas_uuid('build_file_st_card'), [
  'bg_color' => 'bg-white',
  'border_color' => 'border-secondary',
  'card_rounding' => 'rounded-3',
  'position' => 'static',
], canvas_uuid('build_file_st_col'), 'column');
$tree[] = canvas_heading(canvas_uuid('build_file_st_h'), 'css/style.css', 'h5', [
  'text_color' => 'text-secondary',
], canvas_uuid('build_file_st_card'), 'card_body');
$tree[] = canvas_paragraph(canvas_uuid('build_file_st_p'), '<p>Compiled from <code>scss/style.scss</code> → <code>_default.scss</code>. Contains <strong>theme-specific overrides</strong> — body background, link colors, nav colors, card-group styles. Keep custom styles here.</p><p><strong>Source chain:</strong><br><code>style.scss</code> → <code>_default.scss</code> → <code>_variables.scss</code> + <code>_typography.scss</code> + Bootstrap core (no components)</p>', [], canvas_uuid('build_file_st_card'), 'card_body');

// ===========================================================================
// SECTION 15: Customizing Theme Variables (Developer)
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('vars_wrapper'), [
  'html_tag' => 'section',
  'container_type' => 'container',
  'padding_y' => 'py-5',
  'flex_enabled' => TRUE,
  'flex_direction' => 'flex-column',
  'flex_gap' => 'gap-3',
  'custom_class' => 'border-bottom',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('vars_title'), 'Customizing Theme Variables', 'h2', [
  'text_color' => 'text-primary',
], canvas_uuid('vars_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('vars_intro'), '<p>The most impactful customizations happen in two SCSS files: <code>_variables.scss</code> for colors and settings, and <code>_typography.scss</code> for fonts and sizes. These variables are loaded <strong>before</strong> Bootstrap\'s defaults, so your values take precedence.</p>', [], canvas_uuid('vars_wrapper'), 'content');

// Color variables
$tree[] = canvas_heading(canvas_uuid('vars_colors_label'), 'Color Variables (_variables.scss)', 'h3', [], canvas_uuid('vars_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('vars_colors_explain'), '<p>The theme uses a two-level color system: <strong>brand colors</strong> (your actual color values) are mapped to <strong>Bootstrap semantic colors</strong> (primary, secondary, etc.).</p>', [], canvas_uuid('vars_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('vars_colors_code'), '<pre class="bg-dark text-white p-4 rounded-3"><code>/* _variables.scss — Color Configuration */

// ① Define your brand colors
$primary-shade: rgb(255, 78, 46);   // Brand orange-red
$accent-shade:  #0079C0;             // Brand blue

// Auto-generate tints and shades
$primary-light: tint-color($primary-shade, 37%);
$primary-dark:  shade-color($primary-shade, 12%);
$accent-light:  tint-color($accent-shade, 37%);
$accent-dark:   shade-color($accent-shade, 12%);

// ② Map to Bootstrap semantic colors
$primary:   $accent-shade;    // Blue  → bg-primary, btn-primary, text-primary
$secondary: $primary-shade;   // Red   → bg-secondary, btn-secondary, text-secondary
$success:   #28a745;          // Green → bg-success, btn-success
$danger:    #dc3545;          // Red   → bg-danger, btn-danger
$warning:   #ffc107;          // Amber → bg-warning, btn-warning
$info:      #17a2b8;          // Cyan  → bg-info, btn-info
$light:     #f8f9fa;          // Gray  → bg-light
$dark:      #343a40;          // Dark  → bg-dark

// ③ Body settings
$body-bg:    $white;
$body-color: $gray-800;

// ④ Link styles
$link-decoration: none;</code></pre>', [], canvas_uuid('vars_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('vars_colors_tip'), '<p><strong>To change the brand:</strong> Update <code>$primary-shade</code> and <code>$accent-shade</code>, then rebuild. Every button, background, text color, and border color across the entire site will update automatically because they reference these variables through the Bootstrap semantic layer.</p>', [], canvas_uuid('vars_wrapper'), 'content');

// Typography variables
$tree[] = canvas_heading(canvas_uuid('vars_typo_label'), 'Typography (_typography.scss)', 'h3', [], canvas_uuid('vars_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('vars_typo_code'), '<pre class="bg-dark text-white p-4 rounded-3"><code>/* _typography.scss — Font Configuration */

// System font stack (no external font loading = fast)
$font-family-sans-serif: -apple-system, BlinkMacSystemFont,
  "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans",
  sans-serif;

$font-family-monospace: SFMono-Regular, Menlo, Monaco,
  Consolas, "Liberation Mono", "Courier New", monospace;

// Base size (all rem values are relative to this)
$font-size-base: 1rem;     // 16px

// Heading scale
$h1-font-size: $font-size-base * 2.5;   // 40px
$h2-font-size: $font-size-base * 2;     // 32px
$h3-font-size: $font-size-base * 1.75;  // 28px
$h4-font-size: $font-size-base * 1.5;   // 24px
$h5-font-size: $font-size-base * 1.25;  // 20px
$h6-font-size: $font-size-base;         // 16px

// Line heights
$line-height-base: 1.5;
$line-height-sm:   1.25;
$line-height-lg:   2;</code></pre>', [], canvas_uuid('vars_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('vars_typo_tip'), '<p><strong>To use a custom font:</strong> Import the font (via Google Fonts URL or local @font-face), then set <code>$font-family-sans-serif</code> to your font stack. Rebuild and every heading, body text, and component will use the new font.</p>', [], canvas_uuid('vars_wrapper'), 'content');

// Other settings
$tree[] = canvas_heading(canvas_uuid('vars_other_label'), 'Other Settings', 'h3', [], canvas_uuid('vars_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('vars_other_code'), '<pre class="bg-dark text-white p-4 rounded-3"><code>/* _variables.scss — Other Bootstrap Settings */

$enable-responsive-font-sizes: true;    // RFS scales fonts on small screens
$enable-shadows: false;                  // Box shadows on components
$enable-gradients: false;                // Gradient backgrounds
$enable-rtl: true;                       // Right-to-left support
$enable-css-logical-properties: true;    // Use logical (start/end) properties
$enable-caret: true;                     // Dropdown carets

// Custom aspect ratios (available via ratio-* utilities)
$aspect-ratios: (
  "1x1":  100%,
  "2x1":  calc(1 / 2 * 100%),
  "4x3":  calc(3 / 4 * 100%),
  "16x9": calc(9 / 16 * 100%),
  "21x9": calc(9 / 21 * 100%),
);</code></pre>', [], canvas_uuid('vars_wrapper'), 'content');

// PostCSS pxtorem
$tree[] = canvas_heading(canvas_uuid('vars_pxtorem_label'), 'Automatic px → rem Conversion', 'h3', [], canvas_uuid('vars_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('vars_pxtorem'), '<p>PostCSS automatically converts pixel values to rem for the following properties: <code>font</code>, <code>font-size</code>, <code>line-height</code>, <code>letter-spacing</code>, <code>margin</code>, <code>padding</code>, <code>gap</code>, and <code>border</code>. Pixels smaller than 2px are left unchanged. The root value is <strong>16px = 1rem</strong>.</p>', [], canvas_uuid('vars_wrapper'), 'content');

// ===========================================================================
// SECTION 16: Working with Components (Developer)
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('sdc_wrapper'), [
  'html_tag' => 'section',
  'container_type' => 'container',
  'padding_y' => 'py-5',
  'flex_enabled' => TRUE,
  'flex_direction' => 'flex-column',
  'flex_gap' => 'gap-3',
  'custom_class' => 'border-bottom',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('sdc_title'), 'Working with Components', 'h2', [
  'text_color' => 'text-primary',
], canvas_uuid('sdc_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('sdc_intro'), '<p>Components use Drupal\'s <strong>Single Directory Components (SDC)</strong> system. Each component lives in a directory under <code>components/</code> with a Twig template and a YAML definition file.</p>', [], canvas_uuid('sdc_wrapper'), 'content');

// Component anatomy
$tree[] = canvas_heading(canvas_uuid('sdc_anatomy_label'), 'Component Anatomy', 'h3', [], canvas_uuid('sdc_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('sdc_anatomy'), '<pre class="bg-dark text-white p-4 rounded-3"><code>components/heading/
├── heading.twig            # Twig template (renders HTML)
└── heading.component.yml   # Schema (props, slots, metadata)

# The .component.yml defines:
#   - name: Human-readable label
#   - props: Typed properties (string, boolean, enum, etc.)
#   - slots: Named content slots for child components</code></pre>', [], canvas_uuid('sdc_wrapper'), 'content');

// Example component file
$tree[] = canvas_heading(canvas_uuid('sdc_example_label'), 'Example: Heading Component', 'h3', [], canvas_uuid('sdc_wrapper'), 'content');

$tree[] = canvas_row(canvas_uuid('sdc_example_row'), [
  'row_cols' => 'row-cols-1',
  'row_cols_md' => 'row-cols-md-2',
  'gap' => 'g-4',
], canvas_uuid('sdc_wrapper'), 'content');

$tree[] = canvas_column(canvas_uuid('sdc_twig_col'), [], canvas_uuid('sdc_example_row'), 'row');
$tree[] = canvas_card(canvas_uuid('sdc_twig_card'), [
  'bg_color' => 'bg-white',
  'border_color' => 'border-info',
  'card_rounding' => 'rounded-3',
  'show_header' => TRUE,
  'position' => 'static',
], canvas_uuid('sdc_twig_col'), 'column');
$tree[] = canvas_heading(canvas_uuid('sdc_twig_hdr'), 'heading.twig', 'h6', [
  'text_color' => 'text-info',
], canvas_uuid('sdc_twig_card'), 'card_header');
$tree[] = canvas_paragraph(canvas_uuid('sdc_twig_body'), '<pre class="mb-0"><code>&lt;{{ level }} class="{{ text_color }} {{ alignment }}"&gt;
  {{ text }}
&lt;/{{ level }}&gt;</code></pre>', [], canvas_uuid('sdc_twig_card'), 'card_body');

$tree[] = canvas_column(canvas_uuid('sdc_yml_col'), [], canvas_uuid('sdc_example_row'), 'row');
$tree[] = canvas_card(canvas_uuid('sdc_yml_card'), [
  'bg_color' => 'bg-white',
  'border_color' => 'border-success',
  'card_rounding' => 'rounded-3',
  'show_header' => TRUE,
  'position' => 'static',
], canvas_uuid('sdc_yml_col'), 'column');
$tree[] = canvas_heading(canvas_uuid('sdc_yml_hdr'), 'heading.component.yml', 'h6', [
  'text_color' => 'text-success',
], canvas_uuid('sdc_yml_card'), 'card_header');
$tree[] = canvas_paragraph(canvas_uuid('sdc_yml_body'), '<pre class="mb-0"><code>name: Heading
props:
  type: object
  properties:
    text:
      type: string
      title: Text
    level:
      type: string
      title: Level
      enum: [h1, h2, h3, h4, h5, h6]
    text_color:
      type: string
      title: Color
    alignment:
      type: string
      title: Alignment</code></pre>', [], canvas_uuid('sdc_yml_card'), 'card_body');

// Slot pattern
$tree[] = canvas_heading(canvas_uuid('sdc_slots_label'), 'Component Slots', 'h3', [], canvas_uuid('sdc_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('sdc_slots_explain'), '<p>Slots are named content areas where child components can be placed. They are defined in the <code>.component.yml</code> and used with <code>{%% block slot_name %%}</code> in Twig. Canvas uses slots to build nested component trees.</p>', [], canvas_uuid('sdc_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('sdc_slots_table'), '<table class="table table-bordered table-sm"><thead><tr><th>Component</th><th>Slot Name(s)</th><th>Purpose</th></tr></thead><tbody><tr><td>Wrapper</td><td><code>content</code></td><td>Main content area for child components</td></tr><tr><td>Row</td><td><code>row</code></td><td>Holds Column children</td></tr><tr><td>Column</td><td><code>column</code></td><td>Holds any child components</td></tr><tr><td>Card</td><td><code>card_header</code>, <code>card_image</code>, <code>card_body</code>, <code>card_footer</code></td><td>Structured content regions</td></tr><tr><td>Accordion Container</td><td><code>accordion_content</code></td><td>Holds Accordion items</td></tr><tr><td>Accordion</td><td><code>accordion_body</code></td><td>Collapsible content area</td></tr><tr><td>Heading, Paragraph, Button, Link, Blockquote, Image</td><td><em>none</em></td><td>Leaf components (no children)</td></tr></tbody></table>', [], canvas_uuid('sdc_wrapper'), 'content');

// Creating new components
$tree[] = canvas_heading(canvas_uuid('sdc_new_label'), 'Creating a New Component', 'h3', [], canvas_uuid('sdc_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('sdc_new_steps'), '<ol><li>Create a new directory under <code>components/</code> with your component name</li><li>Add a <code>.component.yml</code> file defining props and slots</li><li>Add a <code>.twig</code> template file for the HTML output</li><li>Clear Drupal cache: <code>ddev drush cr</code></li><li>The component will automatically appear in Canvas editor</li></ol>', [], canvas_uuid('sdc_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('sdc_new_tip'), '<p><strong>Tip:</strong> Study existing components like <code>wrapper</code> (complex with many props) and <code>heading</code> (simple) as templates for new ones. Follow Bootstrap 5 utility class naming conventions for props.</p>', [], canvas_uuid('sdc_wrapper'), 'content');

// ===========================================================================
// SECTION 17: JavaScript & Libraries (Developer)
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('js_wrapper'), [
  'html_tag' => 'section',
  'container_type' => 'container',
  'padding_y' => 'py-5',
  'flex_enabled' => TRUE,
  'flex_direction' => 'flex-column',
  'flex_gap' => 'gap-3',
  'custom_class' => 'border-bottom',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('js_title'), 'JavaScript & Libraries', 'h2', [
  'text_color' => 'text-primary',
], canvas_uuid('js_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('js_intro'), '<p>The theme\'s JavaScript setup is defined in <code>' . $theme . '.libraries.yml</code>. All JS and CSS is loaded via this single library definition.</p>', [], canvas_uuid('js_wrapper'), 'content');

// libraries.yml explained
$tree[] = canvas_heading(canvas_uuid('js_lib_label'), 'Library Definition', 'h3', [], canvas_uuid('js_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('js_lib_code'), '<pre class="bg-dark text-white p-4 rounded-3"><code># ' . $theme . '.libraries.yml

global-styling:
  version: VERSION
  js:
    js/bootstrap.bundle.min.js: { minified: true, weight: -50 }
    js/barrio.js: {}
    js/custom.js: {}
  css:
    component:
      css/bootstrap.css: {}
      css/style.css: {}
  dependencies:
    - core/jquery       # Required by barrio.js
    - core/drupal       # Drupal.behaviors API</code></pre>', [], canvas_uuid('js_wrapper'), 'content');

// JS files explained
$tree[] = canvas_heading(canvas_uuid('js_files_label'), 'JavaScript Files', 'h3', [], canvas_uuid('js_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('js_files_table'), '<table class="table table-bordered"><thead><tr><th>File</th><th>Source</th><th>Purpose</th><th>Edit?</th></tr></thead><tbody><tr><td><code>bootstrap.bundle.min.js</code></td><td>Bootstrap 5.3 + Popper v2</td><td>Accordion, collapse, dropdown, modal, tooltip</td><td>No — replace from node_modules if upgrading</td></tr><tr><td><code>barrio.js</code></td><td>Bootstrap Barrio base theme</td><td>Scroll detection (adds <code>.scrolled</code> class to body), dropdown toggle</td><td>Rarely — extend in custom.js instead</td></tr><tr><td><code>custom.js</code></td><td>Your custom code</td><td>Empty placeholder for project-specific Drupal behaviors</td><td><strong>Yes — add your code here</strong></td></tr></tbody></table>', [], canvas_uuid('js_wrapper'), 'content');

// Custom JS example
$tree[] = canvas_heading(canvas_uuid('js_custom_label'), 'Adding Custom JavaScript', 'h3', [], canvas_uuid('js_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('js_custom_code'), '<pre class="bg-dark text-white p-4 rounded-3"><code>// js/custom.js — Drupal behavior pattern

(function($, Drupal) {
  \'use strict\';

  Drupal.behaviors.alchemize_forge = {
    attach: function(context, settings) {

      // Your custom code here
      // \'context\' is the newly added DOM element
      // Use $(\'selector\', context) to scope jQuery queries

    }
  };

})(jQuery, Drupal);</code></pre>', [], canvas_uuid('js_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('js_custom_note'), '<p><strong>Important:</strong> Always use the Drupal behavior pattern. The <code>attach</code> function runs on page load and whenever new content is added to the DOM (e.g., via AJAX). Use <code>context</code> to scope selectors and avoid double-binding.</p>', [], canvas_uuid('js_wrapper'), 'content');

// Upgrading Bootstrap JS
$tree[] = canvas_heading(canvas_uuid('js_upgrade_label'), 'Upgrading Bootstrap JS', 'h3', [], canvas_uuid('js_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('js_upgrade'), '<pre class="bg-dark text-white p-4 rounded-3"><code># To upgrade Bootstrap JS to a new version:
# 1. Update bootstrap version in package.json
# 2. Run the full build pipeline:
ddev theme-build &amp;&amp; ddev drush cr
# The build script automatically copies the updated
# bootstrap.bundle.min.js from node_modules/ to js/</code></pre>', [], canvas_uuid('js_wrapper'), 'content');

// ===========================================================================
// SECTION 18: Developer Quick Start
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('quickstart_wrapper'), [
  'html_tag' => 'section',
  'container_type' => 'container',
  'padding_y' => 'py-5',
  'flex_enabled' => TRUE,
  'flex_direction' => 'flex-column',
  'flex_gap' => 'gap-4',
  'custom_class' => 'bg-light',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('quickstart_title'), 'Developer Quick Start', 'h2', [
  'text_color' => 'text-primary',
  'alignment' => 'text-center',
], canvas_uuid('quickstart_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('quickstart_intro'), '<p class="lead text-center">Get up and running with theme development in 5 steps.</p>', [], canvas_uuid('quickstart_wrapper'), 'content');

// 5 steps in cards
$quickstart_steps = [
  [
    'name' => 'qs1',
    'num' => '1',
    'title' => 'Build Theme',
    'body' => '<pre class="mb-0 small"><code>ddev theme-build</code></pre><p class="mb-0 small text-muted">Installs deps, copies Bootstrap JS, compiles SCSS → CSS.</p>',
  ],
  [
    'name' => 'qs2',
    'num' => '2',
    'title' => 'Start Watch Mode',
    'body' => '<pre class="mb-0 small"><code>ddev theme-build --watch</code></pre>',
  ],
  [
    'name' => 'qs3',
    'num' => '3',
    'title' => 'Edit SCSS Variables',
    'body' => '<p class="mb-0 small">Open <code>scss/_variables.scss</code> and change <code>$primary-shade</code> or <code>$accent-shade</code>. Save — watch mode rebuilds automatically.</p>',
  ],
  [
    'name' => 'qs4',
    'num' => '4',
    'title' => 'Clear Drupal Cache',
    'body' => '<pre class="mb-0 small"><code>ddev drush cr</code></pre>',
  ],
  [
    'name' => 'qs5',
    'num' => '5',
    'title' => 'Verify Changes',
    'body' => '<p class="mb-0 small">Refresh this showcase page. Colors, typography, and component styles will reflect your changes immediately.</p>',
  ],
];

$tree[] = canvas_row(canvas_uuid('quickstart_row'), [
  'row_cols' => 'row-cols-1',
  'row_cols_md' => 'row-cols-md-5',
  'gap' => 'g-3',
], canvas_uuid('quickstart_wrapper'), 'content');

foreach ($quickstart_steps as $qs) {
  $tree[] = canvas_column(canvas_uuid($qs['name'] . '_col'), [], canvas_uuid('quickstart_row'), 'row');

  $tree[] = canvas_card(canvas_uuid($qs['name'] . '_card'), [
    'bg_color' => 'bg-white',
    'border_color' => 'border-primary',
    'card_rounding' => 'rounded-3',
    'position' => 'static',
  ], canvas_uuid($qs['name'] . '_col'), 'column');

  $tree[] = canvas_heading(canvas_uuid($qs['name'] . '_num'), $qs['num'], 'h2', [
    'text_color' => 'text-primary',
    'alignment' => 'text-center',
  ], canvas_uuid($qs['name'] . '_card'), 'card_body');

  $tree[] = canvas_heading(canvas_uuid($qs['name'] . '_h'), $qs['title'], 'h5', [
    'alignment' => 'text-center',
  ], canvas_uuid($qs['name'] . '_card'), 'card_body');

  $tree[] = canvas_paragraph(canvas_uuid($qs['name'] . '_p'), $qs['body'], [], canvas_uuid($qs['name'] . '_card'), 'card_body');
}

// Common tasks reference
$tree[] = canvas_heading(canvas_uuid('qs_tasks_label'), 'Common Developer Tasks', 'h3', [
  'alignment' => 'text-center',
], canvas_uuid('quickstart_wrapper'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('qs_tasks_table'), '<table class="table table-bordered table-sm bg-white"><thead><tr><th>Task</th><th>Command / Action</th></tr></thead><tbody><tr><td>Change brand colors</td><td>Edit <code>scss/_variables.scss</code> → <code>$primary-shade</code>, <code>$accent-shade</code></td></tr><tr><td>Change fonts</td><td>Edit <code>scss/_typography.scss</code> → <code>$font-family-sans-serif</code></td></tr><tr><td>Add custom styles</td><td>Edit <code>scss/style.scss</code> (add rules after the imports)</td></tr><tr><td>Add custom JS</td><td>Edit <code>js/custom.js</code> inside the Drupal.behaviors block</td></tr><tr><td>Create a new component</td><td>Add directory under <code>components/</code> with <code>.twig</code> + <code>.component.yml</code></td></tr><tr><td>Override a template</td><td>Copy from base theme to <code>templates/</code>, modify, <code>ddev drush cr</code></td></tr><tr><td>Build theme (full)</td><td><code>ddev theme-build</code></td></tr><tr><td>Build theme (dev)</td><td><code>ddev theme-build --dev</code></td></tr><tr><td>Watch mode</td><td><code>ddev theme-build --watch</code></td></tr><tr><td>Clean rebuild</td><td><code>ddev theme-build --clean</code></td></tr><tr><td>Upgrade Bootstrap</td><td>Update version in <code>package.json</code>, then <code>ddev theme-build</code></td></tr><tr><td>Clear all caches</td><td><code>ddev drush cr</code></td></tr><tr><td>Regenerate this page</td><td><code>ddev drush php:script .alchemize/drupal/capabilities/generators/build-component-showcase-page.drush.php</code></td></tr></tbody></table>', [], canvas_uuid('quickstart_wrapper'), 'content');

// Final footer note (at end of the page)
$tree[] = canvas_paragraph(canvas_uuid('ref_note'), '<p class="text-center"><em>This styleguide was generated programmatically by the <code>build-component-showcase-page.drush.php</code> capability script — ' . count($tree) . '+ components in the tree. Theme: <strong>' . $theme . '</strong>.</em></p>', [], canvas_uuid('quickstart_wrapper'), 'content');

// ============================================================
// Step 5: Save the tree to the node
// ============================================================

echo "Built tree with " . count($tree) . " components.\n";

$node->set('field_canvas_body', $tree);
try {
  $node->save();
  echo "Saved Component Showcase page (node/$node_id)\n";
}
catch (\Exception $e) {
  echo "ERROR: Save failed: " . $e->getMessage() . "\n";
  echo "=== Aborted ===\n";
  return;
}

// ============================================================
// Step 6: Verify
// ============================================================

$reloaded = $node_storage->load($node_id);
$saved_tree = $reloaded->get('field_canvas_body')->getValue();
echo "Verified: " . count($saved_tree) . " components saved to field_canvas_body\n";

// Render check
try {
  $merged = $template->getMergedComponentTree($reloaded);
  $renderable = $merged->toRenderable($template, isPreview: TRUE);
  $root_key = \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList::ROOT_UUID;
  if (isset($renderable[$root_key])) {
    $html = (string) \Drupal::service('renderer')->renderInIsolation($renderable[$root_key]);
    echo "Rendered: " . strlen($html) . " chars of HTML\n";
  }
}
catch (\Exception $e) {
  echo "WARNING: Render check failed: " . $e->getMessage() . "\n";
}

echo "\n=== Done ===\n";
echo "View at: /node/$node_id\n";
