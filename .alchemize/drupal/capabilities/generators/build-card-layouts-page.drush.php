<?php

/**
 * @file
 * Builds a "Card Layout Designs" page — mockups of card layout variations.
 *
 * Creates a Basic Page node showcasing 7 different card layout compositions
 * using the card component's presets, grid system, and various slot
 * configurations. Designed as a design review tool so the team can evaluate
 * card patterns for different use cases.
 *
 * Sections:
 *   1. Hero / Introduction
 *   2. Feature Cards — 3-column elevated cards with CTAs
 *   3. Pricing Cards — header/footer cards with bordered preset
 *   4. Testimonial Cards — 2-column with blockquote-style content
 *   5. Compact Service Cards — 4-column flat cards, centered
 *   6. Dark Showcase Cards — dark preset on a light section
 *   7. Hero + Supporting Layout — 1 large card + 2 stacked
 *   8. CTA Cards with Alternating Colors — mixed bg colors in a grid
 *
 * Usage: ddev drush php:script .alchemize/drupal/capabilities/generators/build-card-layouts-page.drush.php
 */

use Drupal\canvas\Entity\ContentTemplate;

require_once __DIR__ . '/../lib/canvas-lib.php';

echo "=== Card Layout Designs Page Builder ===\n\n";

// ============================================================
// Step 1: Init
// ============================================================

[$theme, $components, $versions] = canvas_lib_init([
  'wrapper', 'heading', 'paragraph', 'button', 'link',
  'row', 'column', 'card',
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
if (!isset($exposed_slots['field_canvas_body'])) {
  echo "ERROR: No 'field_canvas_body' exposed slot!\n";
  return;
}

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
  ->condition('title', 'Card Layout Designs')
  ->execute();

if (!empty($existing)) {
  $node_id = reset($existing);
  $node = $node_storage->load($node_id);
  echo "Loaded existing 'Card Layout Designs' page (ID: $node_id)\n";
}
else {
  $node = $node_storage->create([
    'type' => 'page',
    'title' => 'Card Layout Designs',
    'status' => 1,
    'uid' => 1,
  ]);
  $node->save();
  $node_id = $node->id();
  echo "Created 'Card Layout Designs' page (ID: $node_id)\n";
}

// ============================================================
// Step 4: Build tree
// ============================================================

$tree = [];

// ===========================================================================
// SECTION 1: Hero / Introduction
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('hero'), [
  'preset' => 'hero-primary',
  'container_type' => 'container',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('hero_h'), 'Card Layout Designs', 'h1', [
  'preset' => 'hero',
], canvas_uuid('hero'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('hero_p'), '<p class="lead">A collection of card layout variations for design review. Each section demonstrates a different card composition pattern using our component system — presets, grid layouts, and slot configurations.</p>', [], canvas_uuid('hero'), 'content');

// ===========================================================================
// SECTION 2: Feature Cards — 3-column elevated preset
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('s2'), [
  'preset' => 'content-section',
  'container_type' => 'container',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('s2_h'), 'Layout 1: Feature Cards', 'h2', [
  'preset' => 'section-title',
], canvas_uuid('s2'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('s2_desc'), '<p class="text-muted">Three-column elevated cards with heading, description, and a call-to-action link. Uses the <strong>elevated</strong> preset for subtle shadow and rounded corners. Great for product features, service offerings, or content highlights.</p>', [], canvas_uuid('s2'), 'content');

$tree[] = canvas_row(canvas_uuid('s2_row'), [
  'row_cols' => 'row-cols-1',
  'row_cols_md' => 'row-cols-md-3',
  'gap' => 'g-4',
], canvas_uuid('s2'), 'content');

$feature_cards = [
  [
    'id' => 'f1',
    'title' => 'Lightning Fast',
    'icon' => '⚡',
    'desc' => 'Optimized for performance with lazy loading, code splitting, and server-side rendering. Your pages load in under 2 seconds.',
    'cta' => 'Explore Performance',
  ],
  [
    'id' => 'f2',
    'title' => 'Fully Responsive',
    'icon' => '📱',
    'desc' => 'Every layout adapts seamlessly from mobile to desktop. Built on a 12-column grid with 6 responsive breakpoints.',
    'cta' => 'See Grid System',
  ],
  [
    'id' => 'f3',
    'title' => 'Design Tokens',
    'icon' => '🎨',
    'desc' => 'Consistent visual language powered by design tokens. Change one variable and watch colors, spacing, and shadows update everywhere.',
    'cta' => 'View Tokens',
  ],
];

foreach ($feature_cards as $fc) {
  $tree[] = canvas_column(canvas_uuid("{$fc['id']}_col"), [], canvas_uuid('s2_row'), 'row');

  $tree[] = canvas_card(canvas_uuid($fc['id']), [
    'preset' => 'elevated',
    'position' => 'relative',
    'body_justify_content' => 'justify-content-between',
  ], canvas_uuid("{$fc['id']}_col"), 'column');

  $tree[] = canvas_heading(canvas_uuid("{$fc['id']}_icon"), $fc['icon'], 'h2', [], canvas_uuid($fc['id']), 'card_body');

  $tree[] = canvas_heading(canvas_uuid("{$fc['id']}_h"), $fc['title'], 'h4', [], canvas_uuid($fc['id']), 'card_body');

  $tree[] = canvas_paragraph(canvas_uuid("{$fc['id']}_p"), '<p>' . $fc['desc'] . '</p>', [
    'margin_bottom' => 'mb-3',
  ], canvas_uuid($fc['id']), 'card_body');

  $tree[] = canvas_link(canvas_uuid("{$fc['id']}_link"), $fc['cta'] . ' →', '/card-layout-designs', [
    'stretched_link' => TRUE,
  ], canvas_uuid($fc['id']), 'card_body');
}

// ===========================================================================
// SECTION 3: Pricing Cards — bordered preset with header/footer
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('s3'), [
  'preset' => 'light-section',
  'container_type' => 'container',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('s3_h'), 'Layout 2: Pricing Cards', 'h2', [
  'preset' => 'section-title',
], canvas_uuid('s3'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('s3_desc'), '<p class="text-muted">Three-column cards with header badge, pricing details in the body, and a CTA button in the footer. Uses the <strong>bordered</strong> preset with the featured card highlighted using <strong>dark</strong> preset. Classic pricing table pattern.</p>', [], canvas_uuid('s3'), 'content');

$tree[] = canvas_row(canvas_uuid('s3_row'), [
  'row_cols' => 'row-cols-1',
  'row_cols_md' => 'row-cols-md-3',
  'gap' => 'g-4',
], canvas_uuid('s3'), 'content');

$pricing_cards = [
  [
    'id' => 'p1',
    'tier' => 'Starter',
    'price' => '$19',
    'period' => '/month',
    'features' => '<ul><li>5 Projects</li><li>10 GB Storage</li><li>Email Support</li><li>Basic Analytics</li></ul>',
    'preset' => 'bordered',
    'btn_variant' => 'primary',
    'btn_outline' => TRUE,
  ],
  [
    'id' => 'p2',
    'tier' => 'Professional',
    'price' => '$49',
    'period' => '/month',
    'features' => '<ul><li>Unlimited Projects</li><li>100 GB Storage</li><li>Priority Support</li><li>Advanced Analytics</li><li>API Access</li></ul>',
    'preset' => 'dark',
    'btn_variant' => 'light',
    'btn_outline' => FALSE,
  ],
  [
    'id' => 'p3',
    'tier' => 'Enterprise',
    'price' => '$149',
    'period' => '/month',
    'features' => '<ul><li>Everything in Pro</li><li>1 TB Storage</li><li>Dedicated Support</li><li>Custom Integrations</li><li>SLA Guarantee</li></ul>',
    'preset' => 'bordered',
    'btn_variant' => 'primary',
    'btn_outline' => TRUE,
  ],
];

foreach ($pricing_cards as $pc) {
  $tree[] = canvas_column(canvas_uuid("{$pc['id']}_col"), [], canvas_uuid('s3_row'), 'row');

  $tree[] = canvas_card(canvas_uuid($pc['id']), [
    'preset' => $pc['preset'],
    'show_header' => TRUE,
    'show_footer' => TRUE,
    'position' => 'static',
  ], canvas_uuid("{$pc['id']}_col"), 'column');

  // Header: tier name
  $tree[] = canvas_heading(canvas_uuid("{$pc['id']}_tier"), $pc['tier'], 'h6', [
    'alignment' => 'text-center',
  ], canvas_uuid($pc['id']), 'card_header');

  // Body: price + features
  $tree[] = canvas_heading(canvas_uuid("{$pc['id']}_price"), $pc['price'], 'h1', [
    'alignment' => 'text-center',
  ], canvas_uuid($pc['id']), 'card_body');

  $tree[] = canvas_paragraph(canvas_uuid("{$pc['id']}_period"), '<p class="text-center mb-3"><small>' . $pc['period'] . '</small></p>', [], canvas_uuid($pc['id']), 'card_body');

  $tree[] = canvas_paragraph(canvas_uuid("{$pc['id']}_feat"), $pc['features'], [], canvas_uuid($pc['id']), 'card_body');

  // Footer: CTA button
  $tree[] = canvas_button(canvas_uuid("{$pc['id']}_btn"), 'Get Started', '/card-layout-designs', [
    'variant' => $pc['btn_variant'],
    'outline' => $pc['btn_outline'],
  ], canvas_uuid($pc['id']), 'card_footer');
}

// ===========================================================================
// SECTION 4: Testimonial Cards — 2-column glass preset
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('s4'), [
  'preset' => 'content-section',
  'container_type' => 'container',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('s4_h'), 'Layout 3: Testimonial Cards', 'h2', [
  'preset' => 'section-title',
], canvas_uuid('s4'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('s4_desc'), '<p class="text-muted">Two-column layout with the <strong>glass</strong> preset, perfect for testimonials or quotes. Each card has a quote in the body and attribution in the footer. The semi-transparent background adds depth without visual heaviness.</p>', [], canvas_uuid('s4'), 'content');

$tree[] = canvas_row(canvas_uuid('s4_row'), [
  'row_cols' => 'row-cols-1',
  'row_cols_md' => 'row-cols-md-2',
  'gap' => 'g-4',
], canvas_uuid('s4'), 'content');

$testimonials = [
  [
    'id' => 't1',
    'quote' => '"The component system completely transformed how we build pages. What used to take days now takes hours. The preset system means our designs are always consistent."',
    'name' => 'Sarah Chen',
    'role' => 'Lead Designer, TechCorp',
  ],
  [
    'id' => 't2',
    'quote' => '"We switched from a custom page builder to Canvas and haven\'t looked back. The Bootstrap integration means we already know the utility classes, and the SDC architecture keeps everything maintainable."',
    'name' => 'Marcus Rivera',
    'role' => 'Senior Developer, WebAgency',
  ],
  [
    'id' => 't3',
    'quote' => '"Our content editors love the visual editor. They can build beautiful pages without needing developer help, and the preset guardrails keep everything on-brand."',
    'name' => 'Aisha Patel',
    'role' => 'Content Manager, MediaGroup',
  ],
  [
    'id' => 't4',
    'quote' => '"The design token system is brilliant. We rebranded our entire site by changing three SCSS variables and running a build. Every color, shadow, and border updated automatically."',
    'name' => 'James O\'Brien',
    'role' => 'CTO, StartupLabs',
  ],
];

foreach ($testimonials as $tm) {
  $tree[] = canvas_column(canvas_uuid("{$tm['id']}_col"), [], canvas_uuid('s4_row'), 'row');

  $tree[] = canvas_card(canvas_uuid($tm['id']), [
    'preset' => 'glass',
    'show_footer' => TRUE,
    'position' => 'static',
  ], canvas_uuid("{$tm['id']}_col"), 'column');

  // Body: quote text
  $tree[] = canvas_paragraph(canvas_uuid("{$tm['id']}_q"), '<p class="fst-italic fs-5">' . $tm['quote'] . '</p>', [], canvas_uuid($tm['id']), 'card_body');

  // Footer: attribution
  $tree[] = canvas_paragraph(canvas_uuid("{$tm['id']}_attr"), '<p class="fw-semibold mb-0">' . $tm['name'] . '</p><p class="text-muted small mb-0">' . $tm['role'] . '</p>', [], canvas_uuid($tm['id']), 'card_footer');
}

// ===========================================================================
// SECTION 5: Compact Service Cards — 4-column flat preset, centered
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('s5'), [
  'preset' => 'light-section',
  'container_type' => 'container',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('s5_h'), 'Layout 4: Service Cards', 'h2', [
  'preset' => 'section-title',
], canvas_uuid('s5'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('s5_desc'), '<p class="text-muted">Four-column compact cards using the <strong>flat</strong> preset with centered content. An icon/number, title, and short description create a scannable services overview. Collapses to 2 columns on tablet and 1 on mobile.</p>', [], canvas_uuid('s5'), 'content');

$tree[] = canvas_row(canvas_uuid('s5_row'), [
  'row_cols' => 'row-cols-1',
  'row_cols_sm' => 'row-cols-sm-2',
  'row_cols_lg' => 'row-cols-lg-4',
  'gap' => 'g-4',
], canvas_uuid('s5'), 'content');

$services = [
  ['id' => 'sv1', 'icon' => '🔍', 'title' => 'Discovery', 'desc' => 'We audit your current systems, identify bottlenecks, and map the path to a modern component-based architecture.'],
  ['id' => 'sv2', 'icon' => '📐', 'title' => 'Design', 'desc' => 'Our team designs the token system, component hierarchy, and content model tailored to your brand.'],
  ['id' => 'sv3', 'icon' => '🛠️', 'title' => 'Build', 'desc' => 'We develop SDC components, configure Canvas, build content templates, and integrate your existing content.'],
  ['id' => 'sv4', 'icon' => '🚀', 'title' => 'Launch', 'desc' => 'Thorough QA, editor training, performance optimization, and a smooth deployment to production.'],
];

foreach ($services as $sv) {
  $tree[] = canvas_column(canvas_uuid("{$sv['id']}_col"), [], canvas_uuid('s5_row'), 'row');

  $tree[] = canvas_card(canvas_uuid($sv['id']), [
    'preset' => 'flat',
    'position' => 'static',
    'body_justify_content' => 'justify-content-start',
    'body_align_items' => 'align-items-center',
  ], canvas_uuid("{$sv['id']}_col"), 'column');

  $tree[] = canvas_heading(canvas_uuid("{$sv['id']}_icon"), $sv['icon'], 'h1', [
    'alignment' => 'text-center',
  ], canvas_uuid($sv['id']), 'card_body');

  $tree[] = canvas_heading(canvas_uuid("{$sv['id']}_h"), $sv['title'], 'h5', [
    'alignment' => 'text-center',
  ], canvas_uuid($sv['id']), 'card_body');

  $tree[] = canvas_paragraph(canvas_uuid("{$sv['id']}_p"), '<p class="text-center">' . $sv['desc'] . '</p>', [], canvas_uuid($sv['id']), 'card_body');
}

// ===========================================================================
// SECTION 6: Dark Showcase Cards — dark preset on content section
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('s6'), [
  'preset' => 'hero-dark',
  'container_type' => 'container',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('s6_h'), 'Layout 5: Dark Showcase', 'h2', [
  'preset' => 'section-title',
], canvas_uuid('s6'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('s6_desc'), '<p>Cards on a dark section background using the <strong>glass</strong> preset. The semi-transparent cards create beautiful contrast against the dark backdrop. Ideal for portfolio pieces, featured content, or highlight sections.</p>', [], canvas_uuid('s6'), 'content');

$tree[] = canvas_row(canvas_uuid('s6_row'), [
  'row_cols' => 'row-cols-1',
  'row_cols_md' => 'row-cols-md-3',
  'gap' => 'g-4',
], canvas_uuid('s6'), 'content');

$showcases = [
  [
    'id' => 'dk1',
    'title' => 'Brand Identity',
    'category' => 'Design',
    'desc' => 'Complete visual identity system including logo, color palette, typography, and usage guidelines for a Fortune 500 client.',
  ],
  [
    'id' => 'dk2',
    'title' => 'E-Commerce Platform',
    'category' => 'Development',
    'desc' => 'Full-stack commerce solution with custom product configurator, real-time inventory, and headless CMS integration.',
  ],
  [
    'id' => 'dk3',
    'title' => 'Mobile Application',
    'category' => 'Product',
    'desc' => 'Cross-platform mobile app with offline-first architecture, push notifications, and biometric authentication.',
  ],
];

foreach ($showcases as $dk) {
  $tree[] = canvas_column(canvas_uuid("{$dk['id']}_col"), [], canvas_uuid('s6_row'), 'row');

  $tree[] = canvas_card(canvas_uuid($dk['id']), [
    'preset' => 'glass',
    'show_header' => TRUE,
    'position' => 'relative',
  ], canvas_uuid("{$dk['id']}_col"), 'column');

  // Header: category tag
  $tree[] = canvas_paragraph(canvas_uuid("{$dk['id']}_cat"), '<p class="text-uppercase fw-semibold small mb-0 text-primary">' . $dk['category'] . '</p>', [], canvas_uuid($dk['id']), 'card_header');

  // Body: title + description
  $tree[] = canvas_heading(canvas_uuid("{$dk['id']}_h"), $dk['title'], 'h4', [], canvas_uuid($dk['id']), 'card_body');

  $tree[] = canvas_paragraph(canvas_uuid("{$dk['id']}_p"), '<p>' . $dk['desc'] . '</p>', [
    'margin_bottom' => 'mb-3',
  ], canvas_uuid($dk['id']), 'card_body');

  $tree[] = canvas_link(canvas_uuid("{$dk['id']}_link"), 'View Case Study →', '/card-layout-designs', [
    'stretched_link' => TRUE,
  ], canvas_uuid($dk['id']), 'card_body');
}

// ===========================================================================
// SECTION 7: Hero Card + Supporting Cards — asymmetric layout
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('s7'), [
  'preset' => 'content-section',
  'container_type' => 'container',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('s7_h'), 'Layout 6: Hero Card + Supporting', 'h2', [
  'preset' => 'section-title',
], canvas_uuid('s7'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('s7_desc'), '<p class="text-muted">Asymmetric layout with one large featured card spanning 8 columns and two smaller supporting cards in a 4-column sidebar. The hero card uses <strong>dark</strong> preset for emphasis while supporting cards use <strong>elevated</strong>. Great for featured content + related items.</p>', [], canvas_uuid('s7'), 'content');

$tree[] = canvas_row(canvas_uuid('s7_row'), [
  'gap' => 'g-4',
], canvas_uuid('s7'), 'content');

// Hero card — 8 columns
$tree[] = canvas_column(canvas_uuid('s7_hero_col'), [
  'col' => 'col-12',
  'col_md' => 'col-md-8',
], canvas_uuid('s7_row'), 'row');

$tree[] = canvas_card(canvas_uuid('s7_hero'), [
  'preset' => 'dark',
  'position' => 'relative',
  'card_class' => 'h-100',
  'body_justify_content' => 'justify-content-center',
], canvas_uuid('s7_hero_col'), 'column');

$tree[] = canvas_paragraph(canvas_uuid('s7_hero_badge'), '<p><span class="badge bg-primary">Featured</span></p>', [], canvas_uuid('s7_hero'), 'card_body');

$tree[] = canvas_heading(canvas_uuid('s7_hero_h'), 'Building the Future of Content Management', 'h2', [], canvas_uuid('s7_hero'), 'card_body');

$tree[] = canvas_paragraph(canvas_uuid('s7_hero_p'), '<p>Our latest whitepaper explores how component-based architecture is revolutionizing content management. Learn how design tokens, SDC components, and visual page builders work together to create maintainable, scalable digital experiences.</p>', [
  'margin_bottom' => 'mb-3',
], canvas_uuid('s7_hero'), 'card_body');

$tree[] = canvas_link(canvas_uuid('s7_hero_link'), 'Read the Whitepaper →', '/card-layout-designs', [
  'stretched_link' => TRUE,
  'link_classes' => 'text-white',
], canvas_uuid('s7_hero'), 'card_body');

// Supporting cards — 4 columns
$tree[] = canvas_column(canvas_uuid('s7_side_col'), [
  'col' => 'col-12',
  'col_md' => 'col-md-4',
], canvas_uuid('s7_row'), 'row');

// Wrapper to stack 2 cards vertically with gap
$tree[] = canvas_wrapper(canvas_uuid('s7_side_wrap'), [
  'flex_enabled' => TRUE,
  'flex_direction' => 'flex-column',
  'flex_gap' => 'gap-4',
  'height_class' => 'h-100',
], canvas_uuid('s7_side_col'), 'column');

// Supporting card 1
$tree[] = canvas_card(canvas_uuid('s7_sup1'), [
  'preset' => 'elevated',
  'position' => 'relative',
  'card_class' => 'flex-fill',
], canvas_uuid('s7_side_wrap'), 'content');

$tree[] = canvas_heading(canvas_uuid('s7_sup1_h'), 'Getting Started Guide', 'h5', [], canvas_uuid('s7_sup1'), 'card_body');

$tree[] = canvas_paragraph(canvas_uuid('s7_sup1_p'), '<p>Step-by-step walkthrough for setting up your first Canvas page with components and presets.</p>', [
  'margin_bottom' => 'mb-2',
], canvas_uuid('s7_sup1'), 'card_body');

$tree[] = canvas_link(canvas_uuid('s7_sup1_link'), 'Read Guide →', '/card-layout-designs', [
  'stretched_link' => TRUE,
], canvas_uuid('s7_sup1'), 'card_body');

// Supporting card 2
$tree[] = canvas_card(canvas_uuid('s7_sup2'), [
  'preset' => 'elevated',
  'position' => 'relative',
  'card_class' => 'flex-fill',
], canvas_uuid('s7_side_wrap'), 'content');

$tree[] = canvas_heading(canvas_uuid('s7_sup2_h'), 'Component API Reference', 'h5', [], canvas_uuid('s7_sup2'), 'card_body');

$tree[] = canvas_paragraph(canvas_uuid('s7_sup2_p'), '<p>Complete documentation for all 14 SDC components — props, slots, presets, and composition patterns.</p>', [
  'margin_bottom' => 'mb-2',
], canvas_uuid('s7_sup2'), 'card_body');

$tree[] = canvas_link(canvas_uuid('s7_sup2_link'), 'View API Docs →', '/card-layout-designs', [
  'stretched_link' => TRUE,
], canvas_uuid('s7_sup2'), 'card_body');

// ===========================================================================
// SECTION 8: CTA Cards with Alternating Colors
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('s8'), [
  'preset' => 'feature-strip',
  'container_type' => 'container',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('s8_h'), 'Layout 7: Colored CTA Cards', 'h2', [
  'preset' => 'section-title',
], canvas_uuid('s8'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('s8_desc'), '<p class="text-muted">Four-column cards using manual <strong>bg_color</strong> overrides for brand-colored backgrounds. Each card pairs a colored background with white text and a contrasting button. Useful for calls-to-action, category navigation, or event highlights.</p>', [], canvas_uuid('s8'), 'content');

$tree[] = canvas_row(canvas_uuid('s8_row'), [
  'row_cols' => 'row-cols-1',
  'row_cols_sm' => 'row-cols-sm-2',
  'row_cols_lg' => 'row-cols-lg-4',
  'gap' => 'g-4',
], canvas_uuid('s8'), 'content');

$cta_cards = [
  [
    'id' => 'cta1',
    'bg' => 'bg-primary',
    'title' => 'Get a Demo',
    'desc' => 'See the platform in action with a personalized walkthrough.',
    'btn_text' => 'Schedule Demo',
    'btn_variant' => 'light',
  ],
  [
    'id' => 'cta2',
    'bg' => 'bg-success',
    'title' => 'Free Trial',
    'desc' => '14 days of full access, no credit card required.',
    'btn_text' => 'Start Free',
    'btn_variant' => 'light',
  ],
  [
    'id' => 'cta3',
    'bg' => 'bg-info',
    'title' => 'Documentation',
    'desc' => 'Comprehensive guides, API references, and tutorials.',
    'btn_text' => 'Read Docs',
    'btn_variant' => 'light',
  ],
  [
    'id' => 'cta4',
    'bg' => 'bg-secondary',
    'title' => 'Community',
    'desc' => 'Join thousands of developers building with our tools.',
    'btn_text' => 'Join Now',
    'btn_variant' => 'light',
  ],
];

foreach ($cta_cards as $ct) {
  $tree[] = canvas_column(canvas_uuid("{$ct['id']}_col"), [], canvas_uuid('s8_row'), 'row');

  $tree[] = canvas_card(canvas_uuid($ct['id']), [
    'bg_color' => $ct['bg'],
    'card_rounding' => 'rounded-4',
    'show_footer' => TRUE,
    'position' => 'static',
    'card_class' => 'text-white border-0',
    'footer_class' => 'bg-transparent border-0',
    'body_justify_content' => 'justify-content-start',
    'body_align_items' => 'align-items-start',
  ], canvas_uuid("{$ct['id']}_col"), 'column');

  $tree[] = canvas_heading(canvas_uuid("{$ct['id']}_h"), $ct['title'], 'h4', [
    'text_color' => 'text-white',
  ], canvas_uuid($ct['id']), 'card_body');

  $tree[] = canvas_paragraph(canvas_uuid("{$ct['id']}_p"), '<p>' . $ct['desc'] . '</p>', [], canvas_uuid($ct['id']), 'card_body');

  $tree[] = canvas_button(canvas_uuid("{$ct['id']}_btn"), $ct['btn_text'], '/card-layout-designs', [
    'variant' => $ct['btn_variant'],
    'size' => 'sm',
  ], canvas_uuid($ct['id']), 'card_footer');
}

// ===========================================================================
// SECTION 9: Preset Reference — All 5 card presets side by side
// ===========================================================================

$tree[] = canvas_wrapper(canvas_uuid('s9'), [
  'preset' => 'light-section',
  'container_type' => 'container',
], $slot_uuid, $slot);

$tree[] = canvas_heading(canvas_uuid('s9_h'), 'Card Preset Reference', 'h2', [
  'preset' => 'section-title',
], canvas_uuid('s9'), 'content');

$tree[] = canvas_paragraph(canvas_uuid('s9_desc'), '<p class="text-muted">All five card presets shown side-by-side for direct comparison. Each preset maps to a single semantic CSS class in <code>_layout-presets.scss</code>, keeping the design system maintainable and consistent.</p>', [], canvas_uuid('s9'), 'content');

$tree[] = canvas_row(canvas_uuid('s9_row'), [
  'row_cols' => 'row-cols-1',
  'row_cols_sm' => 'row-cols-sm-2',
  'row_cols_lg' => 'row-cols-lg-5',
  'gap' => 'g-4',
], canvas_uuid('s9'), 'content');

$presets = [
  ['id' => 'pr_elev', 'preset' => 'elevated', 'label' => 'Elevated', 'desc' => 'Subtle shadow + rounded corners. The default choice for most card layouts.', 'class' => '.preset-card-elevated'],
  ['id' => 'pr_bord', 'preset' => 'bordered', 'label' => 'Bordered', 'desc' => 'Primary color border + rounded corners. Good for highlighting or grouping.', 'class' => '.preset-card-bordered'],
  ['id' => 'pr_dark', 'preset' => 'dark', 'label' => 'Dark', 'desc' => 'Dark background with white text + shadow. High contrast emphasis.', 'class' => '.preset-card-dark'],
  ['id' => 'pr_flat', 'preset' => 'flat', 'label' => 'Flat', 'desc' => 'Light background, no shadow or border. Minimal, understated.', 'class' => '.preset-card-flat'],
  ['id' => 'pr_glass', 'preset' => 'glass', 'label' => 'Glass', 'desc' => 'Semi-transparent white with shadow. Modern, layered look.', 'class' => '.preset-card-glass'],
];

foreach ($presets as $pr) {
  $tree[] = canvas_column(canvas_uuid("{$pr['id']}_col"), [], canvas_uuid('s9_row'), 'row');

  $tree[] = canvas_card(canvas_uuid($pr['id']), [
    'preset' => $pr['preset'],
    'position' => 'static',
  ], canvas_uuid("{$pr['id']}_col"), 'column');

  $tree[] = canvas_heading(canvas_uuid("{$pr['id']}_h"), $pr['label'], 'h5', [
    'alignment' => 'text-center',
  ], canvas_uuid($pr['id']), 'card_body');

  $tree[] = canvas_paragraph(canvas_uuid("{$pr['id']}_p"), '<p class="small text-center">' . $pr['desc'] . '</p>', [], canvas_uuid($pr['id']), 'card_body');

  $tree[] = canvas_paragraph(canvas_uuid("{$pr['id']}_code"), '<p class="text-center"><code>' . $pr['class'] . '</code></p>', [], canvas_uuid($pr['id']), 'card_body');
}

// ============================================================
// Step 5: Save
// ============================================================

echo "Built tree with " . count($tree) . " components.\n";

$node->set('field_canvas_body', $tree);
try {
  $node->save();
  echo "Saved Card Layout Designs page (node/$node_id)\n";
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
