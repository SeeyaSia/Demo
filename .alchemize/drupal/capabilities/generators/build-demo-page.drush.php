<?php

/**
 * @file
 * Builds the "Alchemize Forge Demo" page — a polished component showcase.
 *
 * Creates a Basic Page node with styled, opinionated sections demonstrating
 * the design system's preset classes and the Canvas Bootstrap components.
 *
 * DESIGN SYSTEM PATTERNS demonstrated:
 *   - Wrapper presets (hero-dark, light-section, content-section, cta-banner)
 *     instead of manual utility classes for section styling
 *   - Heading presets (hero, section-title, card-title) instead of manual
 *     display_size/font_weight
 *   - Paragraph presets (lead, caption) instead of manual is_lead/text_color
 *   - Card presets (elevated, dark) instead of manual shadow/bg_color
 *   - Dark section presets set inherited text color — no text_color needed on
 *     children
 *   - Individual prop overrides only when presets don't cover the need
 *
 * Sections:
 *   1. Hero Section (preset: hero-dark, display headings, lead paragraph, CTA)
 *   2. Services Grid (preset: light-section, 3-column cards with elevated preset)
 *   3. Feature Highlight (preset: content-section, two-column text + dark card)
 *   4. Testimonials (preset: light-section, blockquotes with elevated cards)
 *   5. FAQ Accordion (preset: content-section)
 *   6. Call-to-Action Footer Section (preset: cta-banner)
 *
 * See component-strategy.md for the 8-layer design system architecture.
 *
 * Usage: ddev drush php:script .alchemize/drupal/capabilities/generators/build-demo-page.drush.php
 */

use Drupal\canvas\Entity\ContentTemplate;

require_once __DIR__ . '/../lib/canvas-lib.php';

echo "=== Alchemize Forge Demo Page Builder ===\n\n";

// ============================================================
// Step 1: Resolve theme and load component versions
// ============================================================

[$theme, $components, $versions] = canvas_lib_init([
  'wrapper', 'heading', 'paragraph', 'button', 'link', 'blockquote',
  'row', 'column', 'card', 'accordion', 'accordion-container',
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

$slot_uuid = $exposed_slots['field_canvas_body']['component_uuid'];
$slot = $exposed_slots['field_canvas_body']['slot_name'];
echo "Slot target: component=$slot_uuid, slot=$slot\n\n";

// ============================================================
// Step 3: Create or load the page node
// ============================================================

$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$existing = $node_storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'page')
  ->condition('title', 'Alchemize Forge Demo')
  ->execute();

if (!empty($existing)) {
  $node_id = reset($existing);
  $node = $node_storage->load($node_id);
  echo "Loaded existing 'Alchemize Forge Demo' page (ID: $node_id)\n";
}
else {
  $node = $node_storage->create([
    'type' => 'page',
    'title' => 'Alchemize Forge Demo',
    'status' => 1,
    'uid' => 1,
  ]);
  $node->save();
  $node_id = $node->id();
  echo "Created 'Alchemize Forge Demo' page (ID: $node_id)\n";
}

// ============================================================
// Step 4: Build the component tree
// ============================================================

$tree = [];

// ===========================================================================
// SECTION 1: Hero Section
// ===========================================================================
// DESIGN SYSTEM: Uses wrapper preset: hero-dark
//   → .preset-section-hero-dark sets: bg-brand-900, white text, centered,
//     flex-column, gap, aligned. No manual flex/padding/class needed.
//   → Child text inherits white color from the section — no text_color overrides.
//   → Heading preset: hero → .role-heading-hero (3.5rem, bold)
//   → Paragraph preset: lead → .role-text-lead (1.25rem, light weight)
//   → Paragraph preset: caption → .role-text-caption (0.875rem, muted)

$tree[] = canvas_wrapper(canvas_uuid('hero'), [
  'preset' => 'hero-dark',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('hero_h'), 'Build Smarter. Ship Faster.', 'h1', [
  'preset' => 'hero',
], canvas_uuid('hero'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('hero_p'), '<p>Alchemize Forge is our opinionated component library — designed for speed, consistency, and elegance. Every element is crafted with Bootstrap 5 and enhanced with shadows, typography controls, and modern styling.</p>', [
  'preset' => 'lead',
], canvas_uuid('hero'), 'content');

$tree[] = canvas_button(canvas_uuid('hero_btn'), 'Explore Components', '/node/1', [
  'variant' => 'primary',
  'size' => 'lg',
], canvas_uuid('hero'), 'content');

// DESIGN SYSTEM: caption preset provides small + muted styling.
// In a dark section, the muted color from .role-text-caption may be overridden
// by inherited white — that's fine for this context.
$tree[] = canvas_paragraph(canvas_uuid('hero_sub'), '<p>Powered by Canvas &middot; Built on Bootstrap 5</p>', [
  'preset' => 'caption',
], canvas_uuid('hero'), 'content');

// ===========================================================================
// SECTION 2: Services Grid — 3-column cards with shadows
// ===========================================================================
// DESIGN SYSTEM: Uses wrapper preset: light-section
//   → .preset-section-light sets: bg-light, padding, flex-column, gap.
//   → Heading preset: section-title → .role-heading-section (2.5rem, centered)
//   → Paragraph preset: lead → .role-text-lead (1.25rem)
//   → Card preset: elevated → .preset-card-elevated (shadow + border-radius)
//   → Card heading preset: card-title → .role-heading-card (1.25rem, semibold)

$tree[] = canvas_wrapper(canvas_uuid('services'), [
  'preset' => 'light-section',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('services_h'), 'What We Offer', 'h2', [
  'preset' => 'section-title',
], canvas_uuid('services'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('services_p'), '<p class="text-center">Purpose-built tools for modern development teams.</p>', [
  'preset' => 'lead',
], canvas_uuid('services'), 'content');

$tree[] = canvas_row(canvas_uuid('services_row'), [
  'row_cols' => 'row-cols-1',
  'row_cols_md' => 'row-cols-md-3',
  'gap' => 'g-4',
], canvas_uuid('services'), 'content');

$service_cards = [
  [
    'key' => 'svc1',
    'title' => 'AI-Powered Development',
    'desc' => 'Intelligent code generation, refactoring, and review — integrated directly into your workflow. Ship quality code faster with AI pair programming.',
    'icon' => '⚡',
  ],
  [
    'key' => 'svc2',
    'title' => 'Media Production',
    'desc' => 'Generate images, audio, and video with structured AI pipelines. From concept to finished asset in minutes, not hours.',
    'icon' => '🎨',
  ],
  [
    'key' => 'svc3',
    'title' => 'Infrastructure Automation',
    'desc' => 'Automated deployment, monitoring, and scaling. Focus on building features while infrastructure manages itself.',
    'icon' => '🔧',
  ],
];

foreach ($service_cards as $sc) {
  $k = $sc['key'];

  $tree[] = canvas_column(canvas_uuid("{$k}_col"), [], canvas_uuid('services_row'), 'row');

  // DESIGN SYSTEM: preset: elevated → .preset-card-elevated (shadow + rounded)
  $tree[] = canvas_card(canvas_uuid($k), [
    'preset' => 'elevated',
    'position' => 'static',
  ], canvas_uuid("{$k}_col"), 'column');

  $tree[] = canvas_paragraph(canvas_uuid("{$k}_icon"), '<p class="display-4 mb-0">' . $sc['icon'] . '</p>', [], canvas_uuid($k), 'card_body');

  // DESIGN SYSTEM: preset: card-title → .role-heading-card (1.25rem, semibold)
  $tree[] = canvas_heading(canvas_uuid("{$k}_h"), $sc['title'], 'h3', [
    'preset' => 'card-title',
  ], canvas_uuid($k), 'card_body');

  $tree[] = canvas_paragraph(canvas_uuid("{$k}_desc"), '<p>' . $sc['desc'] . '</p>', [], canvas_uuid($k), 'card_body');
}

// ===========================================================================
// SECTION 3: Feature Highlight — two-column layout
// ===========================================================================
// DESIGN SYSTEM: Uses wrapper preset: content-section
//   → .preset-section-content sets: padding, flex-column, gap. No bg color
//     (inherits page default). Good for neutral content sections.

$tree[] = canvas_wrapper(canvas_uuid('feature'), [
  'preset' => 'content-section',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('feature_h'), 'Why Alchemize?', 'h2', [
  'preset' => 'section-title',
], canvas_uuid('feature'), 'content');

$tree[] = canvas_row(canvas_uuid('feature_row'), [
  'row_cols' => 'row-cols-1',
  'row_cols_md' => 'row-cols-md-2',
  'gap' => 'g-4',
], canvas_uuid('feature'), 'content');

// Left column — text content
$tree[] = canvas_column(canvas_uuid('feature_left'), [], canvas_uuid('feature_row'), 'row');

// DESIGN SYSTEM: card-title gives semibold weight from the type scale.
// fw-bold override via individual prop for extra emphasis here.
$tree[] = canvas_heading(canvas_uuid('feature_left_h'), 'Designed for Real Projects', 'h3', [
  'preset' => 'card-title',
  'font_weight' => 'fw-bold',  // Individual prop override — Bootstrap utility wins
], canvas_uuid('feature_left'), 'column');

$tree[] = canvas_paragraph(canvas_uuid('feature_left_p1'), '<p>Every component in Alchemize Forge has been tested in production across multiple sites. We don\'t ship experimental widgets — we ship battle-tested building blocks.</p>', [], canvas_uuid('feature_left'), 'column');

$tree[] = canvas_paragraph(canvas_uuid('feature_left_p2'), '<p>Our approach is simple: <strong>Bootstrap 5 foundations</strong> enhanced with opinionated defaults. Shadows, typography scales, spacing — all pre-configured so you spend time creating, not configuring.</p>', [], canvas_uuid('feature_left'), 'column');

$tree[] = canvas_button(canvas_uuid('feature_btn'), 'View the Showcase', '/node/1', [
  'variant' => 'primary',
], canvas_uuid('feature_left'), 'column');

// Right column — feature list card
$tree[] = canvas_column(canvas_uuid('feature_right'), [], canvas_uuid('feature_row'), 'row');

// DESIGN SYSTEM: preset: dark → .preset-card-dark sets bg-brand-900,
// white text, shadow, border-radius. No manual bg_color/card_rounding needed.
// shadow: shadow-lg is an individual prop override for extra elevation.
$tree[] = canvas_card(canvas_uuid('feature_card'), [
  'preset' => 'dark',
  'shadow' => 'shadow-lg',  // Individual override — more elevation than preset default
], canvas_uuid('feature_right'), 'column');

// No text_color needed — .preset-card-dark sets color: $neutral-0 (white)
$tree[] = canvas_heading(canvas_uuid('feature_card_h'), 'Included Components', 'h4', [
  'preset' => 'card-title',
], canvas_uuid('feature_card'), 'card_body');

// No text_color needed — inherits white from dark card preset.
// Note: inline classes in HTML content (text-light) still work alongside presets.
$tree[] = canvas_paragraph(canvas_uuid('feature_card_list'), '<ul class="list-unstyled mb-0">
<li class="mb-2">✓ Wrapper — Sections with shadow, radius, overflow</li>
<li class="mb-2">✓ Heading — Display sizes, font weights, transforms</li>
<li class="mb-2">✓ Paragraph — Lead text, font sizing, weights</li>
<li class="mb-2">✓ Card — Shadow variants, full slot system</li>
<li class="mb-2">✓ Row &amp; Column — Responsive Bootstrap grid</li>
<li class="mb-2">✓ Button — 8 variants, outline, 3 sizes</li>
<li class="mb-2">✓ Link — Stretched links, button styles</li>
<li class="mb-2">✓ Accordion — Collapsible FAQ sections</li>
<li class="mb-2">✓ Blockquote — Styled quotes with attribution</li>
<li>✓ Hero Carousel — Full-width slide system</li>
</ul>', [], canvas_uuid('feature_card'), 'card_body');

// ===========================================================================
// SECTION 4: Testimonials — blockquotes in styled cards
// ===========================================================================
// DESIGN SYSTEM: Uses wrapper preset: light-section (same as Services above)
//   → Heading preset: section-title
//   → Card preset: elevated (shadow + rounded)

$tree[] = canvas_wrapper(canvas_uuid('testimonials'), [
  'preset' => 'light-section',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('test_h'), 'What People Are Saying', 'h2', [
  'preset' => 'section-title',
], canvas_uuid('testimonials'), 'content');

$tree[] = canvas_row(canvas_uuid('test_row'), [
  'row_cols' => 'row-cols-1',
  'row_cols_md' => 'row-cols-md-3',
  'gap' => 'g-4',
], canvas_uuid('testimonials'), 'content');

$testimonials = [
  [
    'key' => 'tq1',
    'quote' => 'Alchemize cut our development time in half. The component library is exactly what we needed.',
    'author' => 'Sarah Chen',
    'role' => 'Lead Developer, TechCorp',
  ],
  [
    'key' => 'tq2',
    'quote' => 'The design system is thoughtful and complete. Every component just works out of the box.',
    'author' => 'Marcus Rivera',
    'role' => 'Creative Director, PixelWorks',
  ],
  [
    'key' => 'tq3',
    'quote' => 'We shipped our entire site in two weeks using Alchemize Forge. No compromises on quality.',
    'author' => 'Priya Patel',
    'role' => 'CTO, StartupLab',
  ],
];

foreach ($testimonials as $t) {
  $k = $t['key'];

  $tree[] = canvas_column(canvas_uuid("{$k}_col"), [], canvas_uuid('test_row'), 'row');

  // DESIGN SYSTEM: preset: elevated → .preset-card-elevated (shadow + rounded)
  $tree[] = canvas_card(canvas_uuid($k), [
    'preset' => 'elevated',
  ], canvas_uuid("{$k}_col"), 'column');

  $tree[] = canvas_blockquote(canvas_uuid("{$k}_bq"), $t['quote'], [
    'footer' => $t['author'],
    'cite' => $t['role'],
    'italic' => TRUE,
  ], canvas_uuid($k), 'card_body');
}

// ===========================================================================
// SECTION 5: FAQ Accordion
// ===========================================================================
// DESIGN SYSTEM: Uses wrapper preset: content-section (neutral, no bg)

$tree[] = canvas_wrapper(canvas_uuid('faq'), [
  'preset' => 'content-section',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('faq_h'), 'Frequently Asked Questions', 'h2', [
  'preset' => 'section-title',
], canvas_uuid('faq'), 'content');

$tree[] = canvas_accordion_container(canvas_uuid('faq_acc'), [
  'flush' => FALSE,
], canvas_uuid('faq'), 'content');

$faqs = [
  [
    'key' => 'faq1',
    'q' => 'What is Alchemize Forge?',
    'a' => '<p>Alchemize Forge is an opinionated component library built on Bootstrap 5 and Canvas SDC. It provides pre-styled, production-ready components for building Drupal sites quickly and consistently.</p>',
    'open' => TRUE,
  ],
  [
    'key' => 'faq2',
    'q' => 'How does it relate to Canvas Bootstrap?',
    'a' => '<p>Alchemize Forge extends the Canvas Bootstrap module with enhanced props like shadow, display sizes, font weights, and text transforms. It adds styling opinions on top of the neutral Bootstrap foundation.</p>',
  ],
  [
    'key' => 'faq3',
    'q' => 'Can I customize the brand colors?',
    'a' => '<p>Absolutely. Brand colors are defined in <code>scss/_variables.scss</code>. Change the primary and secondary color variables, run <code>ddev theme-build</code>, and every component updates automatically.</p>',
  ],
  [
    'key' => 'faq4',
    'q' => 'Is this compatible with the Canvas visual editor?',
    'a' => '<p>Yes. Every component is a standard Canvas SDC component with full drag-and-drop support. The enhanced props appear in the Canvas sidebar editor alongside the standard Bootstrap options.</p>',
  ],
];

foreach ($faqs as $f) {
  $k = $f['key'];

  $opts = ['heading_level' => 3];
  if (!empty($f['open'])) {
    $opts['open_by_default'] = TRUE;
  }

  $tree[] = canvas_accordion(canvas_uuid($k), $f['q'], $opts, canvas_uuid('faq_acc'), 'accordion_content');

  $tree[] = canvas_paragraph(canvas_uuid("{$k}_a"), $f['a'], [], canvas_uuid($k), 'accordion_body');
}

// ===========================================================================
// SECTION 6: CTA Footer Section
// ===========================================================================
// DESIGN SYSTEM: Uses wrapper preset: cta-banner
//   → .preset-section-cta sets: bg-primary, white text, centered, rounded,
//     shadow-lg, flex-column, gap, aligned. All in one class.
//   → Child text inherits white from the section — no text_color overrides.
//   → Heading preset: hero → bigger CTA headline (3.5rem, bold)
//   → Paragraph preset: lead → emphasis text (1.25rem)
//   → Paragraph preset: caption → small sub-text (0.875rem)

$tree[] = canvas_wrapper(canvas_uuid('cta'), [
  'preset' => 'cta-banner',
], $slot_uuid, $slot);

// No text_color needed — .preset-section-cta sets color: $neutral-0
$tree[] = canvas_heading(canvas_uuid('cta_h'), 'Ready to Build?', 'h2', [
  'preset' => 'hero',
], canvas_uuid('cta'), 'content');

// No text_color needed — inherits white from cta-banner section
$tree[] = canvas_paragraph(canvas_uuid('cta_p'), '<p>Start building with Alchemize Forge today. Every component is ready for production.</p>', [
  'preset' => 'lead',
], canvas_uuid('cta'), 'content');

$tree[] = canvas_button(canvas_uuid('cta_btn1'), 'View Full Showcase', '/node/1', [
  'variant' => 'light',
  'size' => 'lg',
], canvas_uuid('cta'), 'content');

// No text_color needed — inherits white from cta-banner section
$tree[] = canvas_paragraph(canvas_uuid('cta_sub'), '<p>Open source &middot; Bootstrap 5 &middot; Canvas SDC</p>', [
  'preset' => 'caption',
], canvas_uuid('cta'), 'content');

// ============================================================
// Step 5: Save the tree to the node
// ============================================================

$node->set('field_canvas_body', $tree);
try {
  $node->save();
  echo "\nSaved " . count($tree) . " components to 'Alchemize Forge Demo' (node/$node_id)\n";
  echo "View at: /node/$node_id\n\n";
}
catch (\Exception $e) {
  echo "\nERROR: Failed to save component tree: " . $e->getMessage() . "\n";
  echo "=== Aborted ===\n";
  return;
}

// ============================================================
// Step 6: Print the tree hierarchy for verification
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
      if (!empty($inputs['text'])) {
        $text = strip_tags($inputs['text']);
        $label = ' → "' . (strlen($text) > 50 ? substr($text, 0, 50) . '...' : $text) . '"';
      }
      elseif (!empty($inputs['title'])) {
        $label = ' → "' . $inputs['title'] . '"';
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
