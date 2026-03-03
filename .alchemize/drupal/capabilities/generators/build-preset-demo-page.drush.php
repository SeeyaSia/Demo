<?php

/**
 * @file
 * Builds the "Preset Showcase" page — demonstrates component presets.
 *
 * Shows every preset for Heading, Paragraph, Card, and Wrapper side-by-side
 * so editors can see what each preset looks like and pick the right one.
 *
 * Presets map to semantic SCSS classes defined in the design system:
 *   - Wrapper presets → .preset-section-* classes (_layout-presets.scss)
 *   - Card presets    → .preset-card-* classes (_layout-presets.scss)
 *   - Heading presets → .role-heading-* / .role-label classes (_typography-roles.scss)
 *   - Paragraph presets → .role-text-* classes (_typography-roles.scss)
 *
 * Dark/primary section presets set inherited text color, so child components
 * don't need explicit text_color overrides. See component-strategy.md.
 *
 * Sections:
 *   1. Hero (using wrapper hero-dark preset)
 *   2. Wrapper Presets — each preset shown as a section
 *   3. Heading Presets — all heading presets in one section
 *   4. Paragraph Presets — all paragraph presets in one section
 *   5. Card Presets — all card presets in a grid
 *   6. Presets in Action — a realistic page section built entirely with presets
 *
 * Usage: ddev drush php:script .alchemize/drupal/capabilities/generators/build-preset-demo-page.drush.php
 */

use Drupal\canvas\Entity\ContentTemplate;

require_once __DIR__ . '/../lib/canvas-lib.php';

echo "=== Preset Showcase Page Builder ===\n\n";

// ============================================================
// Step 1: Init
// ============================================================

[$theme, $components, $versions] = canvas_lib_init([
  'wrapper', 'heading', 'paragraph', 'button', 'link', 'blockquote',
  'row', 'column', 'card', 'accordion', 'accordion-container',
], verbose: TRUE);

if (empty($versions)) {
  echo "=== Aborted ===\n";
  return;
}

// ============================================================
// Step 2: Get exposed slot
// ============================================================

$template = ContentTemplate::load('node.page.full');
if (!$template) {
  echo "ERROR: ContentTemplate 'node.page.full' not found!\n";
  return;
}

$exposed_slots = $template->getExposedSlots();
$slot_uuid = $exposed_slots['field_canvas_body']['component_uuid'];
$slot = $exposed_slots['field_canvas_body']['slot_name'];
echo "Slot target: component=$slot_uuid, slot=$slot\n\n";

// ============================================================
// Step 3: Create or load node
// ============================================================

$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$existing = $node_storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'page')
  ->condition('title', 'Preset Showcase')
  ->execute();

if (!empty($existing)) {
  $node_id = reset($existing);
  $node = $node_storage->load($node_id);
  echo "Loaded existing 'Preset Showcase' page (ID: $node_id)\n";
}
else {
  $node = $node_storage->create([
    'type' => 'page',
    'title' => 'Preset Showcase',
    'status' => 1,
    'uid' => 1,
  ]);
  $node->save();
  $node_id = $node->id();
  echo "Created 'Preset Showcase' page (ID: $node_id)\n";
}

// ============================================================
// Step 4: Build tree
// ============================================================

$tree = [];

// ===========================================================================
// SECTION 1: Hero — using wrapper preset: hero-dark
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('hero'), [
  'preset' => 'hero-dark',
], $slot_uuid, $slot);

// NOTE: No text_color needed — the hero-dark wrapper preset sets
// color: $neutral-0 (white) via .preset-section-hero-dark in SCSS.
// Text color inherits from the section context. Only override when
// you need something DIFFERENT from the section's inherited color.
$tree[] = canvas_heading(canvas_uuid('hero_h'), 'Component Presets', 'h1', [
  'preset' => 'hero',
], canvas_uuid('hero'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('hero_p'), '<p>One dropdown, instant style. Presets apply curated combinations of shadow, typography, color, and spacing — so editors don\'t need to configure 10 props manually.</p>', [
  'preset' => 'lead',
], canvas_uuid('hero'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('hero_sub'), '<p>Each section below demonstrates a different preset. Scroll down to see them all.</p>', [
  'preset' => 'caption',
], canvas_uuid('hero'), 'content');

// ===========================================================================
// SECTION 2: Wrapper Presets — show each wrapper preset as a live section
// ===========================================================================

// Intro section (manual)
$tree[] = canvas_wrapper(canvas_uuid('wp_intro'), [
  'preset' => 'content-section',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('wp_intro_h'), 'Wrapper Presets', 'h2', [
  'preset' => 'section-title',
], canvas_uuid('wp_intro'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('wp_intro_p'), '<p>The <strong>Wrapper</strong> component has 6 presets. Each one below is a live wrapper with that preset applied. The wrapper auto-sets the HTML tag to <code>&lt;section&gt;</code> and adds a <code>container</code>.</p>', [], canvas_uuid('wp_intro'), 'content');

// Each wrapper preset demo.
// NOTE: Dark/primary presets (hero-dark, hero-primary, cta-banner) set
// color: $neutral-0 in their SCSS class, so text inherits white automatically.
// No text_color override needed for content inside these presets.
// Light sections inherit the default body color — also no override needed.
// The 'desc_color' below is only used when we want MUTED or LIGHT text
// as a deliberate design choice (e.g., descriptions in lighter weight).
$wrapper_presets = [
  [
    'key' => 'wp_hd',
    'preset' => 'hero-dark',
    'name' => 'hero-dark',
    'desc' => 'Dark background, centered text, vertical flex with gap. Perfect for hero banners and dark callouts.',
    'text_color' => '',       // Inherits white from .preset-section-hero-dark
    'desc_color' => '',       // Inherits white from section
  ],
  [
    'key' => 'wp_hp',
    'preset' => 'hero-primary',
    'name' => 'hero-primary',
    'desc' => 'Primary brand color background, centered text, vertical flex. Great for CTA sections and brand moments.',
    'text_color' => '',       // Inherits white from .preset-section-hero-primary
    'desc_color' => '',       // Inherits white from section
  ],
  [
    'key' => 'wp_ls',
    'preset' => 'light-section',
    'name' => 'light-section',
    'desc' => 'Light gray background with generous padding. The workhorse for alternating content sections.',
    'text_color' => '',
    'desc_color' => 'text-muted',  // Deliberate: lighter description text
  ],
  [
    'key' => 'wp_cs',
    'preset' => 'content-section',
    'name' => 'content-section',
    'desc' => 'Clean white background, padded, contained. Standard content section for body text and layouts.',
    'text_color' => '',
    'desc_color' => 'text-muted',  // Deliberate: lighter description text
  ],
  [
    'key' => 'wp_cta',
    'preset' => 'cta-banner',
    'name' => 'cta-banner',
    'desc' => 'Primary background with rounded corners and large shadow. Stands out as a call-to-action block.',
    'text_color' => '',       // Inherits white from .preset-section-cta
    'desc_color' => '',       // Inherits white from section
  ],
  [
    'key' => 'wp_fs',
    'preset' => 'feature-strip',
    'name' => 'feature-strip',
    'desc' => 'No background color, top border separator, padded. Clean separation between content areas.',
    'text_color' => '',
    'desc_color' => 'text-muted',  // Deliberate: lighter description text
  ],
];

foreach ($wrapper_presets as $wp) {
  $k = $wp['key'];

  $tree[] = canvas_wrapper(canvas_uuid($k), [
    'preset' => $wp['preset'],
  ], $slot_uuid, $slot);

  $heading_opts = [];
  if ($wp['text_color']) {
    $heading_opts['text_color'] = $wp['text_color'];
  }

  $tree[] = canvas_heading(canvas_uuid("{$k}_h"), 'preset: ' . $wp['name'], 'h3', $heading_opts, canvas_uuid($k), 'content');

  $desc_opts = [];
  if ($wp['desc_color']) {
    $desc_opts['text_color'] = $wp['desc_color'];
  }

  $tree[] = canvas_paragraph(canvas_uuid("{$k}_p"), '<p>' . $wp['desc'] . '</p>', $desc_opts, canvas_uuid($k), 'content');
}

// ===========================================================================
// SECTION 3: Heading Presets
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('hp_section'), [
  'preset' => 'content-section',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('hp_title'), 'Heading Presets', 'h2', [
  'preset' => 'section-title',
], canvas_uuid('hp_section'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('hp_intro'), '<p>The <strong>Heading</strong> component has 5 presets that combine display size, font weight, alignment, and text transform.</p>', [], canvas_uuid('hp_section'), 'content');

$heading_presets = [
  ['preset' => 'hero',            'text' => 'Hero Preset — Big, bold, attention-grabbing',    'level' => 'h1'],
  ['preset' => 'section-title',   'text' => 'Section Title Preset — Centered, medium display', 'level' => 'h2'],
  ['preset' => 'card-title',      'text' => 'Card Title Preset — Compact, semibold',           'level' => 'h3'],
  ['preset' => 'subtle',          'text' => 'Subtle Preset — Light weight, muted color',       'level' => 'h4'],
  ['preset' => 'uppercase-label', 'text' => 'Uppercase Label Preset — Small caps style',       'level' => 'h5'],
];

foreach ($heading_presets as $i => $hp) {
  $tree[] = canvas_wrapper(canvas_uuid("hp_row_{$i}"), [
    'custom_class' => 'border-bottom pb-3',
  ], canvas_uuid('hp_section'), 'content');

  $tree[] = canvas_paragraph(canvas_uuid("hp_label_{$i}"), '<p><code>preset: ' . $hp['preset'] . '</code></p>', [
    'preset' => 'caption',
  ], canvas_uuid("hp_row_{$i}"), 'content');

  $tree[] = canvas_heading(canvas_uuid("hp_demo_{$i}"), $hp['text'], $hp['level'], [
    'preset' => $hp['preset'],
  ], canvas_uuid("hp_row_{$i}"), 'content');
}

// ===========================================================================
// SECTION 4: Paragraph Presets
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('pp_section'), [
  'preset' => 'light-section',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('pp_title'), 'Paragraph Presets', 'h2', [
  'preset' => 'section-title',
], canvas_uuid('pp_section'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('pp_intro'), '<p>The <strong>Paragraph</strong> component has 3 presets for different text roles.</p>', [], canvas_uuid('pp_section'), 'content');

$para_presets = [
  ['preset' => 'lead',       'text' => 'Lead Preset — Larger introductory paragraph text. Use this for the first paragraph in a section to draw the reader in with a bigger, more prominent style.'],
  ['preset' => 'caption',    'text' => 'Caption Preset — Small muted text for image captions, timestamps, meta information, or secondary context beneath a heading.'],
  ['preset' => 'fine-print', 'text' => 'Fine Print Preset — Extra small, light-weight text for legal disclaimers, copyright notices, or supplementary notes that shouldn\'t compete with main content.'],
];

foreach ($para_presets as $i => $pp) {
  $tree[] = canvas_wrapper(canvas_uuid("pp_row_{$i}"), [
    'custom_class' => 'border-bottom pb-3',
  ], canvas_uuid('pp_section'), 'content');

  $tree[] = canvas_paragraph(canvas_uuid("pp_code_{$i}"), '<p><code>preset: ' . $pp['preset'] . '</code></p>', [
    'preset' => 'caption',
  ], canvas_uuid("pp_row_{$i}"), 'content');

  $tree[] = canvas_paragraph(canvas_uuid("pp_demo_{$i}"), '<p>' . $pp['text'] . '</p>', [
    'preset' => $pp['preset'],
  ], canvas_uuid("pp_row_{$i}"), 'content');
}

// ===========================================================================
// SECTION 5: Card Presets — grid of all presets
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('cp_section'), [
  'preset' => 'content-section',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('cp_title'), 'Card Presets', 'h2', [
  'preset' => 'section-title',
], canvas_uuid('cp_section'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('cp_intro'), '<p>The <strong>Card</strong> component has 5 presets controlling shadow, background, border, and rounding.</p>', [], canvas_uuid('cp_section'), 'content');

$tree[] = canvas_row(canvas_uuid('cp_row'), [
  'row_cols' => 'row-cols-1',
  'row_cols_md' => 'row-cols-md-3',
  'gap' => 'g-4',
], canvas_uuid('cp_section'), 'content');

$card_presets = [
  ['preset' => 'elevated', 'name' => 'Elevated', 'desc' => 'Shadow + rounded corners. The default "raised card" look.'],
  ['preset' => 'bordered', 'name' => 'Bordered', 'desc' => 'Primary color border with rounded corners. Draws attention without weight.'],
  ['preset' => 'dark',     'name' => 'Dark',     'desc' => 'Dark background, white text, shadow. For contrast sections.'],
  ['preset' => 'flat',     'name' => 'Flat',     'desc' => 'Light background, no border, no shadow. Subtle grouping.'],
  ['preset' => 'glass',    'name' => 'Glass',    'desc' => 'Semi-transparent white, shadow, no border. Modern frosted look.'],
];

foreach ($card_presets as $i => $cp) {
  $k = "cp_{$i}";

  $tree[] = canvas_column(canvas_uuid("{$k}_col"), [], canvas_uuid('cp_row'), 'row');

  $tree[] = canvas_card(canvas_uuid($k), [
    'preset' => $cp['preset'],
    'show_header' => FALSE,
    'show_image' => FALSE,
    'show_footer' => FALSE,
  ], canvas_uuid("{$k}_col"), 'column');

  $heading_opts = ['preset' => 'card-title'];
  if ($cp['preset'] === 'dark') {
    $heading_opts['text_color'] = 'text-white';
  }
  $tree[] = canvas_heading(canvas_uuid("{$k}_h"), $cp['name'], 'h3', $heading_opts, canvas_uuid($k), 'card_body');

  $tree[] = canvas_paragraph(canvas_uuid("{$k}_code"), '<p><code>preset: ' . $cp['preset'] . '</code></p>', [
    'preset' => 'caption',
  ], canvas_uuid($k), 'card_body');

  $para_opts = [];
  if ($cp['preset'] === 'dark') {
    $para_opts['text_color'] = 'text-light';
  }
  $tree[] = canvas_paragraph(canvas_uuid("{$k}_desc"), '<p>' . $cp['desc'] . '</p>', $para_opts, canvas_uuid($k), 'card_body');
}

// ===========================================================================
// SECTION 6: Presets in Action — realistic section built with just presets
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('action'), [
  'preset' => 'light-section',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('action_h'), 'Presets in Action', 'h2', [
  'preset' => 'section-title',
], canvas_uuid('action'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('action_p'), '<p>This entire section is built using only presets — no manual prop configuration. Here\'s a realistic "Features" section you\'d see on a landing page.</p>', [
  'preset' => 'lead',
], canvas_uuid('action'), 'content');

$tree[] = canvas_row(canvas_uuid('action_row'), [
  'row_cols' => 'row-cols-1',
  'row_cols_md' => 'row-cols-md-3',
  'gap' => 'g-4',
], canvas_uuid('action'), 'content');

$features = [
  ['key' => 'f1', 'icon' => '🚀', 'title' => 'Fast Setup', 'desc' => 'Pick a preset from the dropdown and you\'re done. No more tweaking 10 different prop fields to get a consistent look.'],
  ['key' => 'f2', 'icon' => '🎯', 'title' => 'Consistent Design', 'desc' => 'Presets enforce design system rules. Every "elevated" card looks the same across the site — guaranteed.'],
  ['key' => 'f3', 'icon' => '🔧', 'title' => 'Still Customizable', 'desc' => 'Presets are a starting point, not a cage. Override any individual prop and it takes priority over the preset.'],
];

foreach ($features as $f) {
  $k = $f['key'];

  $tree[] = canvas_column(canvas_uuid("{$k}_col"), [], canvas_uuid('action_row'), 'row');

  $tree[] = canvas_card(canvas_uuid($k), [
    'preset' => 'elevated',
    'show_header' => FALSE,
    'show_image' => FALSE,
    'show_footer' => FALSE,
  ], canvas_uuid("{$k}_col"), 'column');

  $tree[] = canvas_paragraph(canvas_uuid("{$k}_icon"), '<p class="display-4 mb-0">' . $f['icon'] . '</p>', [], canvas_uuid($k), 'card_body');

  $tree[] = canvas_heading(canvas_uuid("{$k}_h"), $f['title'], 'h3', [
    'preset' => 'card-title',
  ], canvas_uuid($k), 'card_body');

  $tree[] = canvas_paragraph(canvas_uuid("{$k}_desc"), '<p>' . $f['desc'] . '</p>', [
    'text_color' => 'text-muted',
  ], canvas_uuid($k), 'card_body');
}

// Final CTA using wrapper cta-banner preset
$tree[] = canvas_wrapper(canvas_uuid('final_cta'), [
  'preset' => 'cta-banner',
], $slot_uuid, $slot);

// No text_color needed — cta-banner preset sets white inherited color
$tree[] = canvas_heading(canvas_uuid('final_cta_h'), 'Start Using Presets Today', 'h2', [
  'preset' => 'hero',
], canvas_uuid('final_cta'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('final_cta_p'), '<p>Open any component in the Canvas editor, pick a preset from the dropdown, and watch the magic happen.</p>', [
  'preset' => 'lead',
], canvas_uuid('final_cta'), 'content');

$tree[] = canvas_button(canvas_uuid('final_cta_btn'), 'Back to Forge Demo', '/node/5', [
  'variant' => 'light',
  'size' => 'lg',
], canvas_uuid('final_cta'), 'content');

// ============================================================
// Step 5: Save
// ============================================================

$node->set('field_canvas_body', $tree);
try {
  $node->save();
  echo "\nSaved " . count($tree) . " components to 'Preset Showcase' (node/$node_id)\n";
  echo "View at: /node/$node_id\n\n";
}
catch (\Exception $e) {
  echo "\nERROR: Failed to save component tree: " . $e->getMessage() . "\n";
  echo "=== Aborted ===\n";
  return;
}

// ============================================================
// Step 6: Print tree
// ============================================================

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
      if (!empty($inputs['preset']) && $inputs['preset'] !== 'none') {
        $label = ' (preset: ' . $inputs['preset'] . ')';
      }
      if (!empty($inputs['text'])) {
        $text = strip_tags($inputs['text']);
        $label .= ' → "' . (strlen($text) > 40 ? substr($text, 0, 40) . '...' : $text) . '"';
      }
      echo $prefix . '├─ ' . strtoupper($component) . $slot_info . $label . "\n";
      print_tree($items, $uuid, $indent + 1);
    }
  }
}

echo "--- Component Tree ---\n";
print_tree($items_map, $slot_uuid);

echo "\nTotal components: " . count($tree) . "\n";
echo "=== Done ===\n";
