<?php

/**
 * @file
 * Creates placeholder media entities from picsum.photos.
 *
 * Downloads random images from picsum.photos and saves them as Drupal Media
 * entities of type 'image'. These persist in the media library and can be
 * referenced from any entity_reference field targeting media:image.
 *
 * Usage:
 *   ddev drush php:script .alchemize/drupal/capabilities/generators/create-placeholder-media.drush.php
 *
 * Parameters (set as environment variables):
 *   COUNT  — Number of images to create (default: 6)
 *   WIDTH  — Image width in pixels (default: 1200)
 *   HEIGHT — Image height in pixels (default: 800)
 *   PREFIX — Name prefix for media entities (default: 'Placeholder')
 *
 * Examples:
 *   # Create 6 default placeholders (1200x800)
 *   ddev drush php:script .alchemize/drupal/capabilities/generators/create-placeholder-media.drush.php
 *
 *   # With custom parameters (use ddev exec to pass env vars):
 *   ddev exec "COUNT=10 WIDTH=800 HEIGHT=800 PREFIX='Card image' drush php:script \
 *     .alchemize/drupal/capabilities/generators/create-placeholder-media.drush.php"
 *
 *   # Create 3 hero-sized images
 *   ddev exec "COUNT=3 WIDTH=1920 HEIGHT=800 PREFIX='Hero banner' drush php:script \
 *     .alchemize/drupal/capabilities/generators/create-placeholder-media.drush.php"
 *
 * Note: Use `ddev exec "ENV=val drush php:script ..."` instead of
 *   `ENV=val ddev drush php:script ...` — the latter does not pass env vars
 *   into the DDEV container.
 */

require_once __DIR__ . '/../lib/media-lib.php';

// --- Parameters ---
$count  = (int) (getenv('COUNT') ?: 6);
$width  = (int) (getenv('WIDTH') ?: 1200);
$height = (int) (getenv('HEIGHT') ?: 800);
$prefix = getenv('PREFIX') ?: 'Placeholder';

echo "=== Placeholder Media Generator ===\n\n";
echo "Settings: count=$count, dimensions={$width}x{$height}, prefix='$prefix'\n";
echo "Source: https://picsum.photos/{$width}/{$height}\n\n";

// --- Validate media module ---
if (!\Drupal::moduleHandler()->moduleExists('media')) {
  echo "ERROR: Media module is not enabled.\n";
  echo "=== Aborted ===\n";
  return;
}

// --- Validate media type exists ---
$media_type = \Drupal::entityTypeManager()->getStorage('media_type')->load('image');
if (!$media_type) {
  echo "ERROR: Media type 'image' does not exist.\n";
  echo "=== Aborted ===\n";
  return;
}

// --- Create placeholder media ---
$results = media_lib_create_placeholder_batch($count, $width, $height, $prefix, "$prefix image");

echo "\n=== Done. Created " . count($results) . " of $count placeholder media entities. ===\n";

if (!empty($results)) {
  echo "\nMedia IDs: " . implode(', ', array_map(fn($m) => $m->id(), $results)) . "\n";
  echo "View in Media Library: /admin/content/media\n";
}
