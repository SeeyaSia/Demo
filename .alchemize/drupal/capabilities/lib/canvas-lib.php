<?php

/**
 * @file
 * Canvas Component Library — shared functions for building component trees.
 *
 * Provides the core API for AI agents and capability scripts to build
 * Canvas component trees programmatically. Eliminates boilerplate for
 * theme resolution, component version loading, UUID generation, and
 * tree item construction.
 *
 * Usage:
 *   require_once __DIR__ . '/../lib/canvas-lib.php';
 *   canvas_lib_init(['wrapper', 'heading', 'paragraph', 'button']);
 *
 *   $tree = [];
 *   $tree[] = canvas_wrapper(canvas_uuid('hero'), [
 *     'container_type' => 'container',
 *     'padding_y' => 'py-5',
 *     'flex_enabled' => TRUE,
 *     'flex_direction' => 'flex-column',
 *     'flex_gap' => 'gap-3',
 *   ], $slot_uuid, $slot_name);
 *   $tree[] = canvas_heading(canvas_uuid('title'), 'My Page', 'h1', [], canvas_uuid('hero'), 'content');
 *
 * Component Builders:
 *   canvas_heading()              — h1-h6 headings with text color + alignment
 *   canvas_paragraph()            — rich HTML text blocks
 *   canvas_button()               — CTA buttons (8 variants, outline, 3 sizes)
 *   canvas_link()                 — navigation links (target, stretched_link)
 *   canvas_blockquote()           — quoted text with attribution (PLAIN TEXT only)
 *   canvas_image()                — media display with aspect ratio
 *   canvas_wrapper()              — section containers with spacing/flex
 *   canvas_row()                  — grid row containers
 *   canvas_column()               — grid columns
 *   canvas_card()                 — grouped content with header/image/body/footer slots
 *   canvas_accordion_container()  — collapsible section wrapper
 *   canvas_accordion()            — individual collapsible item
 *
 * Media Helpers (separate library):
 *   For creating Drupal Media entities (placeholder images, file downloads),
 *   use media-lib.php instead:
 *     require_once __DIR__ . '/../lib/media-lib.php';
 *   See .alchemize/drupal/docs/data-model/media-handling.md for full docs.
 *
 * Composition Rules:
 *
 *   1. SECTION PATTERN — Every page section should follow this structure:
 *      Wrapper (section, container, flex-column + gap) → children
 *
 *      Always use flex_enabled + flex_direction: flex-column + flex_gap on
 *      section wrappers. This provides consistent vertical spacing between
 *      ALL children (headings, paragraphs, rows, cards, etc.) without
 *      requiring margin_bottom on each child.
 *
 *      Example:
 *        canvas_wrapper($uuid, [
 *          'html_tag'       => 'section',
 *          'container_type' => 'container',
 *          'padding_y'      => 'py-5',
 *          'flex_enabled'   => TRUE,
 *          'flex_direction'  => 'flex-column',
 *          'flex_gap'       => 'gap-3',   // gap-3 for content, gap-4 for spacious
 *        ])
 *
 *   2. GRID PATTERN — Use Row + Column for multi-column layouts:
 *      Wrapper → Row (row_cols_md) → Column → content
 *
 *      Row controls responsive column counts. Column children go in the
 *      'column' slot. Use gap on the Row (e.g., 'g-4') for gutter spacing.
 *
 *   3. BLOCKQUOTE TEXT — Pass PLAIN TEXT, never HTML:
 *      The blockquote Twig template wraps text in <p> tags automatically.
 *      Passing '<p>text</p>' causes double-wrapping: <p><p>text</p></p>.
 *
 *   4. PARAGRAPH TEXT — Pass HTML with <p> tags:
 *      The paragraph Twig template detects block-level tags and wraps in
 *      <div> if found, <p> otherwise. Always include <p> tags in HTML.
 *
 *   5. SPACING HIERARCHY — Prefer flex gap over individual margins:
 *      - Section spacing: flex_gap on parent wrapper (gap-3 or gap-4)
 *      - Grid spacing: gap prop on Row (g-3 or g-4)
 *      - Fine-tuning: margin_bottom on Paragraph only when needed
 *      - Card body: margin_bottom on Paragraph for spacing within cards
 *
 *   6. CONTAINER TYPES — Use appropriate container wrapping:
 *      - 'container': Fixed-width centered content (most sections)
 *      - 'container-fluid': Full-width edge-to-edge content
 *      - No container: For nested wrappers inside an already-contained parent
 *
 *   7. PAGE TEMPLATE CONTEXT — Canvas content renders inside:
 *      <div class="{{ container }}"> → <div class="row"> → <main> → <section>
 *      The page template already provides a container + row wrapper.
 *      Section wrappers with their own container_type create a nested
 *      container, which is the standard Bootstrap pattern for sections
 *      with different backgrounds or full-width borders.
 */

use Drupal\Component\Uuid\Php as UuidGenerator;

// ============================================================
// Core Infrastructure
// ============================================================

/**
 * Initialize the Canvas component library.
 *
 * Resolves the active theme, loads Canvas component entities, and extracts
 * version hashes. Must be called before using any component builder functions.
 *
 * @param array $component_names
 *   List of short component names to load, e.g.:
 *   ['wrapper', 'heading', 'paragraph', 'button', 'row', 'column', 'card',
 *    'link', 'blockquote', 'image', 'accordion', 'accordion-container']
 * @param bool $verbose
 *   If TRUE, echo component loading progress.
 *
 * @return array
 *   [$theme, $components, $versions] where:
 *   - $theme: string — active default theme machine name
 *   - $components: array — map of short name => full component ID
 *   - $versions: array — map of short name => version hash
 *   Returns empty arrays and echoes error on failure.
 */
function canvas_lib_init(array $component_names, bool $verbose = FALSE): array {
  $theme = \Drupal::config('system.theme')->get('default');

  if ($verbose) {
    echo "Active theme: $theme\n";
  }

  $comp_storage = \Drupal::entityTypeManager()->getStorage('component');
  $components = [];
  $versions = [];

  foreach ($component_names as $short) {
    $full_id = "sdc.{$theme}.{$short}";
    $components[$short] = $full_id;

    $entity = $comp_storage->load($full_id);
    if (!$entity) {
      echo "ERROR: Component $full_id not found!\n";
      return [$theme, [], []];
    }
    $versions[$short] = $entity->toArray()['active_version'];

    if ($verbose) {
      echo "  $short => {$versions[$short]}\n";
    }
  }

  // Store in globals for component builder functions.
  $GLOBALS['_canvas_lib_theme'] = $theme;
  $GLOBALS['_canvas_lib_components'] = $components;
  $GLOBALS['_canvas_lib_versions'] = $versions;

  // Initialize UUID generator.
  if (!isset($GLOBALS['_canvas_lib_uuid_gen'])) {
    $GLOBALS['_canvas_lib_uuid_gen'] = new UuidGenerator();
    $GLOBALS['_canvas_lib_uuids'] = [];
  }

  if ($verbose) {
    echo "Loaded " . count($versions) . " components.\n";
  }

  return [$theme, $components, $versions];
}

/**
 * Generate or retrieve a named UUID.
 *
 * Calling with the same name always returns the same UUID within a script run.
 * This allows referencing parent/child relationships by meaningful names
 * rather than tracking UUID variables.
 *
 * @param string|null $name
 *   A meaningful name for this UUID (e.g., 'hero_wrapper', 'card_1').
 *   Pass NULL to generate an anonymous (non-cached) UUID.
 *
 * @return string
 *   The UUID string.
 */
function canvas_uuid(?string $name = NULL): string {
  if ($name === NULL) {
    return $GLOBALS['_canvas_lib_uuid_gen']->generate();
  }
  if (!isset($GLOBALS['_canvas_lib_uuids'][$name])) {
    $GLOBALS['_canvas_lib_uuids'][$name] = $GLOBALS['_canvas_lib_uuid_gen']->generate();
  }
  return $GLOBALS['_canvas_lib_uuids'][$name];
}

/**
 * Build a raw component tree item.
 *
 * Low-level function — prefer the component-specific builders below.
 *
 * @param string $uuid
 *   Instance UUID for this tree item.
 * @param string $component_id
 *   Full component ID (e.g., 'sdc.alchemize_forge.heading').
 * @param string $version
 *   Component version hash.
 * @param array $inputs
 *   Prop values — will be json_encode()'d.
 * @param string|null $parent
 *   Parent instance UUID, or NULL for root-level items.
 * @param string|null $slot
 *   Target slot name in parent, or NULL for root-level items.
 *
 * @return array
 *   The tree item array ready for the component_tree field.
 */
function canvas_tree_item(string $uuid, string $component_id, string $version, array $inputs, ?string $parent = NULL, ?string $slot = NULL): array {
  return [
    'uuid'              => $uuid,
    'component_id'      => $component_id,
    'component_version' => $version,
    'parent_uuid'       => $parent,
    'slot'              => $slot,
    'inputs'            => json_encode($inputs),
    'label'             => NULL,
  ];
}

// ============================================================
// Test Helpers
// ============================================================

/**
 * Assert a condition and track pass/fail counts.
 *
 * Test scripts should initialize counters before using:
 *   $GLOBALS['_canvas_test_pass'] = 0;
 *   $GLOBALS['_canvas_test_fail'] = 0;
 *
 * @param bool $condition
 *   The condition to test.
 * @param string $message
 *   Description of what was tested.
 */
function canvas_assert_true(bool $condition, string $message): void {
  if ($condition) {
    echo "  PASS: $message\n";
    $GLOBALS['_canvas_test_pass']++;
  }
  else {
    echo "  FAIL: $message\n";
    $GLOBALS['_canvas_test_fail']++;
  }
}

// ============================================================
// Component Builders
// ============================================================
//
// Each function encodes the minimum required props as explicit parameters,
// with all optional props in $opts. They automatically pull component_id
// and version from the library globals set by canvas_lib_init().
//
// Slot conventions:
//   wrapper  → 'content'
//   row      → 'row'
//   column   → 'column'
//   card     → 'card_header', 'card_image', 'card_body', 'card_footer'
//   accordion-container → 'accordion_content'
//   accordion → 'accordion_body'

/**
 * Resolve component ID and version for a short name.
 *
 * @param string $short
 *   Component short name (e.g., 'heading').
 *
 * @return array
 *   [$component_id, $version]
 */
function _canvas_resolve(string $short): array {
  return [
    $GLOBALS['_canvas_lib_components'][$short],
    $GLOBALS['_canvas_lib_versions'][$short],
  ];
}

/**
 * Heading component — h1-h6 with text color and alignment.
 *
 * @param string $uuid        Instance UUID.
 * @param string $text        Heading text.
 * @param string $level       Heading level: 'h1' through 'h6'.
 * @param array  $opts        Optional: text_color, alignment (text-start/center/end), preset.
 * @param string|null $parent Parent UUID.
 * @param string|null $slot   Slot name in parent.
 *
 * @return array Tree item.
 */
function canvas_heading(string $uuid, string $text, string $level = 'h2', array $opts = [], ?string $parent = NULL, ?string $slot = NULL): array {
  [$cid, $ver] = _canvas_resolve('heading');
  return canvas_tree_item($uuid, $cid, $ver, array_merge([
    'text' => $text,
    'level' => $level,
  ], $opts), $parent, $slot);
}

/**
 * Paragraph component — rich HTML text with spacing.
 *
 * @param string $uuid        Instance UUID.
 * @param string $html        HTML content (wrap in <p> tags).
 * @param array  $opts        Optional: text_color, margin_bottom, padding_*.
 * @param string|null $parent Parent UUID.
 * @param string|null $slot   Slot name in parent.
 *
 * @return array Tree item.
 */
function canvas_paragraph(string $uuid, string $html, array $opts = [], ?string $parent = NULL, ?string $slot = NULL): array {
  [$cid, $ver] = _canvas_resolve('paragraph');
  return canvas_tree_item($uuid, $cid, $ver, array_merge([
    'text' => $html,
  ], $opts), $parent, $slot);
}

/**
 * Button component — CTA with variant, size, and optional outline.
 *
 * Variants: primary, secondary, success, danger, warning, info, light, dark
 * Sizes: default, sm, lg
 *
 * @param string $uuid        Instance UUID.
 * @param string $text        Button label.
 * @param string $url         Button URL (must be a valid path, not '#').
 * @param array  $opts        Optional: variant (default: primary), size, outline (bool).
 * @param string|null $parent Parent UUID.
 * @param string|null $slot   Slot name in parent.
 *
 * @return array Tree item.
 */
function canvas_button(string $uuid, string $text, string $url, array $opts = [], ?string $parent = NULL, ?string $slot = NULL): array {
  [$cid, $ver] = _canvas_resolve('button');
  return canvas_tree_item($uuid, $cid, $ver, array_merge([
    'text' => $text,
    'url' => $url,
    'variant' => 'primary',
    'size' => 'default',
  ], $opts), $parent, $slot);
}

/**
 * Link component — navigation with optional new tab and stretched link.
 *
 * Target values: 'same' (default), 'new' (opens in new tab).
 *
 * @param string $uuid        Instance UUID.
 * @param string $text        Link text.
 * @param string $url         Link URL.
 * @param array  $opts        Optional: target ('same'|'new'), stretched_link (bool),
 *                            as_button (bool), button_variant, link_classes.
 * @param string|null $parent Parent UUID.
 * @param string|null $slot   Slot name in parent.
 *
 * @return array Tree item.
 */
function canvas_link(string $uuid, string $text, string $url, array $opts = [], ?string $parent = NULL, ?string $slot = NULL): array {
  [$cid, $ver] = _canvas_resolve('link');
  return canvas_tree_item($uuid, $cid, $ver, array_merge([
    'text' => $text,
    'url' => $url,
  ], $opts), $parent, $slot);
}

/**
 * Blockquote component — quoted text with attribution.
 *
 * IMPORTANT: The blockquote Twig template wraps text in <p> tags automatically.
 * Pass PLAIN TEXT only — never wrap in <p> tags or it will double-wrap.
 *
 * Alignment: text-start, text-center, text-end
 * Text colors: text-muted, text-secondary, text-body-secondary, text-body,
 *              text-dark, text-light, text-white
 *
 * @param string $uuid        Instance UUID.
 * @param string $text        Quote text (PLAIN TEXT — no <p> tags, the template adds them).
 * @param array  $opts        Optional: footer, cite, alignment, italic (bool),
 *                            opacity, text_color.
 * @param string|null $parent Parent UUID.
 * @param string|null $slot   Slot name in parent.
 *
 * @return array Tree item.
 */
function canvas_blockquote(string $uuid, string $text, array $opts = [], ?string $parent = NULL, ?string $slot = NULL): array {
  [$cid, $ver] = _canvas_resolve('blockquote');
  return canvas_tree_item($uuid, $cid, $ver, array_merge([
    'text' => $text,
  ], $opts), $parent, $slot);
}

/**
 * Image component — media display with aspect ratio.
 *
 * IMPORTANT: The $media array must contain a complete media object with
 * 'src', 'alt', 'width', and 'height' keys. Passing an empty array or NULL
 * causes AssertionError in StaticPropSource::isMinimalRepresentation().
 * Either omit the image component entirely or provide a complete media object.
 *
 * For creating placeholder media entities to populate image fields, use
 * media-lib.php (see .alchemize/drupal/docs/data-model/media-handling.md).
 *
 * Sizes (aspect ratios): 1:1, 2:1, 3:1, 4:1, 4:3, 16:9, 21:9
 * Radius: small, medium, large, etc.
 *
 * @param string $uuid        Instance UUID.
 * @param array  $media       Media object: ['src' => ..., 'alt' => ..., 'width' => ..., 'height' => ...]
 * @param array  $opts        Optional: size (aspect ratio string), radius.
 * @param string|null $parent Parent UUID.
 * @param string|null $slot   Slot name in parent.
 *
 * @return array Tree item.
 */
function canvas_image(string $uuid, array $media, array $opts = [], ?string $parent = NULL, ?string $slot = NULL): array {
  [$cid, $ver] = _canvas_resolve('image');
  return canvas_tree_item($uuid, $cid, $ver, array_merge([
    'media' => $media,
  ], $opts), $parent, $slot);
}

/**
 * Wrapper component — section/div container with spacing and flex.
 *
 * The most versatile component. Creates sections with container types,
 * padding/margin (0-5), flex layout, custom CSS classes, and width/height.
 *
 * Slot: 'content'
 *
 * @param string $uuid        Instance UUID.
 * @param array  $opts        Optional: html_tag ('div'|'section'), container_type,
 *                            padding_y, padding_all, padding_*, margin_*, custom_class,
 *                            flex_enabled (bool), flex_direction, flex_gap,
 *                            justify_content, align_items, width_class, height_class.
 * @param string|null $parent Parent UUID.
 * @param string|null $slot   Slot name in parent.
 *
 * @return array Tree item.
 */
function canvas_wrapper(string $uuid, array $opts = [], ?string $parent = NULL, ?string $slot = NULL): array {
  [$cid, $ver] = _canvas_resolve('wrapper');
  return canvas_tree_item($uuid, $cid, $ver, $opts, $parent, $slot);
}

/**
 * Row component — grid row container.
 *
 * Controls how many columns appear at each breakpoint. Content goes inside
 * Column children placed in the 'row' slot.
 *
 * Slot: 'row'
 *
 * @param string $uuid        Instance UUID.
 * @param array  $opts        Optional: row_cols, row_cols_md, row_cols_lg, gap.
 * @param string|null $parent Parent UUID.
 * @param string|null $slot   Slot name in parent.
 *
 * @return array Tree item.
 */
function canvas_row(string $uuid, array $opts = [], ?string $parent = NULL, ?string $slot = NULL): array {
  [$cid, $ver] = _canvas_resolve('row');
  return canvas_tree_item($uuid, $cid, $ver, $opts, $parent, $slot);
}

/**
 * Column component — grid column.
 *
 * Place inside a Row's 'row' slot. Content goes in the 'column' slot.
 *
 * Slot: 'column'
 *
 * @param string $uuid        Instance UUID.
 * @param array  $opts        Optional: col (sizing class, default 'col').
 * @param string|null $parent Parent UUID.
 * @param string|null $slot   Slot name in parent.
 *
 * @return array Tree item.
 */
function canvas_column(string $uuid, array $opts = [], ?string $parent = NULL, ?string $slot = NULL): array {
  [$cid, $ver] = _canvas_resolve('column');
  return canvas_tree_item($uuid, $cid, $ver, array_merge([
    'col' => 'col',
  ], $opts), $parent, $slot);
}

/**
 * Card component — grouped content with optional header/image/body/footer.
 *
 * Slots: 'card_header', 'card_image', 'card_body', 'card_footer'
 *
 * Background colors: bg-primary, bg-secondary, bg-success, bg-danger,
 *   bg-warning, bg-info, bg-light, bg-dark, bg-body, bg-white, bg-transparent
 * Border colors: border-primary, border-secondary, border-success, etc.
 * Rounding: rounded-0 through rounded-5, rounded-pill
 *
 * @param string $uuid        Instance UUID.
 * @param array  $opts        Optional: show_header (bool), show_image (bool),
 *                            show_footer (bool), bg_color, border_color,
 *                            card_rounding, position, body_orientation,
 *                            card_class, header_class, body_class, footer_class.
 * @param string|null $parent Parent UUID.
 * @param string|null $slot   Slot name in parent.
 *
 * @return array Tree item.
 */
function canvas_card(string $uuid, array $opts = [], ?string $parent = NULL, ?string $slot = NULL): array {
  [$cid, $ver] = _canvas_resolve('card');
  return canvas_tree_item($uuid, $cid, $ver, array_merge([
    'show_header' => FALSE,
    'show_image' => FALSE,
    'show_footer' => FALSE,
  ], $opts), $parent, $slot);
}

/**
 * Accordion Container — wraps accordion items.
 *
 * Slot: 'accordion_content'
 *
 * @param string $uuid        Instance UUID.
 * @param array  $opts        Optional: flush (bool — removes borders for edge-to-edge).
 * @param string|null $parent Parent UUID.
 * @param string|null $slot   Slot name in parent.
 *
 * @return array Tree item.
 */
function canvas_accordion_container(string $uuid, array $opts = [], ?string $parent = NULL, ?string $slot = NULL): array {
  [$cid, $ver] = _canvas_resolve('accordion-container');
  return canvas_tree_item($uuid, $cid, $ver, array_merge([
    'flush' => FALSE,
  ], $opts), $parent, $slot);
}

/**
 * Accordion item — collapsible section with title and body slot.
 *
 * Slot: 'accordion_body'
 *
 * @param string $uuid            Instance UUID.
 * @param string $title           Accordion header/toggle text.
 * @param array  $opts            Optional: heading_level (int), open_by_default (bool).
 * @param string|null $parent     Parent UUID (typically an accordion-container).
 * @param string|null $slot       Slot name in parent (typically 'accordion_content').
 *
 * @return array Tree item.
 */
function canvas_accordion(string $uuid, string $title, array $opts = [], ?string $parent = NULL, ?string $slot = NULL): array {
  [$cid, $ver] = _canvas_resolve('accordion');
  return canvas_tree_item($uuid, $cid, $ver, array_merge([
    'title' => $title,
    'heading_level' => 3,
  ], $opts), $parent, $slot);
}
