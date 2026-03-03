<?php

/**
 * @file
 * Media Library — shared functions for creating Drupal media entities.
 *
 * Provides helpers for AI agents and capability scripts to create properly
 * structured Drupal Media entities, including downloading placeholder images
 * from external services and saving them as persistent file + media entities.
 *
 * RULE: Always use Drupal Media for images. Never attach images directly to
 * content fields via raw file upload. Media entities provide reuse, central
 * management, alt text governance, revision tracking, and Canvas compatibility.
 *
 * Usage:
 *   require_once __DIR__ . '/../lib/media-lib.php';
 *
 *   // Create a media entity from a remote URL (e.g., placeholder image):
 *   $media = media_lib_create_from_url(
 *     'https://picsum.photos/1200/800',
 *     'Hero background image',
 *     'A wide landscape placeholder image'
 *   );
 *
 *   // Create a media entity from a local file already on disk:
 *   $media = media_lib_create_from_file(
 *     '/path/to/image.jpg',
 *     'My Image',
 *     'Description of the image'
 *   );
 *
 *   // Attach media to a node's entity_reference field:
 *   $node->set('field_media_image', ['target_id' => $media->id()]);
 *
 *   // Attach file to a node's direct image field (legacy, for existing fields like article field_image):
 *   $file = media_lib_download_to_file('https://picsum.photos/800/600', 'article-image.jpg');
 *   $node->set('field_image', [
 *     'target_id' => $file->id(),
 *     'alt' => 'Article placeholder image',
 *   ]);
 *
 * Placeholder Image Service:
 *   This library uses https://picsum.photos for placeholder images.
 *   - picsum.photos/{width}/{height} — returns a random photo at exact dimensions
 *   - Images are downloaded ONCE and saved as Drupal file entities
 *   - Each download gets a unique random image (server-side random selection)
 *   - Once saved, the image is stable — it does NOT change on page load
 *   - This is safe because we download and persist; we do NOT use the URL as an img src
 *
 * Common placeholder dimensions:
 *   - Hero/banner:   1200×600, 1920×800
 *   - Card image:    800×600, 600×400
 *   - Thumbnail:     400×400, 300×300
 *   - Portrait:      600×800, 400×600
 *   - Square:        800×800
 */

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

/**
 * Download a file from a URL and save it as a Drupal file entity.
 *
 * Downloads the content from the given URL and saves it to the public
 * file system. Returns a saved File entity that can be used in image
 * fields or to create Media entities.
 *
 * @param string $url
 *   The URL to download from (e.g., 'https://picsum.photos/1200/800').
 * @param string $filename
 *   The filename to save as (e.g., 'hero-placeholder.jpg').
 *   If the filename has no extension, '.jpg' is appended.
 * @param string $directory
 *   The file directory within public:// (e.g., 'placeholders').
 *   Defaults to 'placeholders'.
 *
 * @return \Drupal\file\Entity\File|null
 *   The saved File entity, or NULL on failure.
 */
function media_lib_download_to_file(string $url, string $filename, string $directory = 'placeholders'): ?File {
  // Ensure filename has an extension.
  if (!pathinfo($filename, PATHINFO_EXTENSION)) {
    $filename .= '.jpg';
  }

  // Sanitize filename.
  $filename = preg_replace('/[^a-zA-Z0-9._-]/', '-', $filename);

  // Prepare destination directory.
  $destination_dir = "public://$directory";
  \Drupal::service('file_system')->prepareDirectory($destination_dir, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);

  $destination = "$destination_dir/$filename";

  // Download the file.
  try {
    $response = \Drupal::httpClient()->get($url, [
      'timeout' => 30,
      'allow_redirects' => TRUE,
    ]);

    if ($response->getStatusCode() !== 200) {
      echo "  WARNING: HTTP {$response->getStatusCode()} from $url\n";
      return NULL;
    }

    $data = $response->getBody()->getContents();
    if (empty($data)) {
      echo "  WARNING: Empty response from $url\n";
      return NULL;
    }
  }
  catch (\Exception $e) {
    echo "  WARNING: Failed to download $url — " . $e->getMessage() . "\n";
    return NULL;
  }

  // Save to filesystem.
  $file_system = \Drupal::service('file_system');
  $uri = $file_system->saveData($data, $destination, \Drupal\Core\File\FileExists::Rename);

  if (!$uri) {
    echo "  WARNING: Failed to save file to $destination\n";
    return NULL;
  }

  // Create file entity.
  $file = File::create([
    'uri' => $uri,
    'uid' => 1,
    'status' => 1,  // FILE_STATUS_PERMANENT
  ]);
  $file->save();

  return $file;
}

/**
 * Create a Drupal Media entity of type 'image' from a remote URL.
 *
 * Downloads the image from the URL, saves it as a file entity, then
 * creates and saves a Media entity that wraps it. The resulting media
 * entity can be referenced from entity_reference fields that target
 * media:image.
 *
 * @param string $url
 *   The URL to download from (e.g., 'https://picsum.photos/1200/800').
 * @param string $name
 *   The media entity name (used in Media Library listings).
 * @param string $alt
 *   Alt text for the image (required for accessibility).
 * @param string|null $filename
 *   Optional filename. If NULL, derived from the name.
 * @param string $directory
 *   File directory within public://. Defaults to 'placeholders'.
 *
 * @return \Drupal\media\Entity\Media|null
 *   The saved Media entity, or NULL on failure.
 */
function media_lib_create_from_url(string $url, string $name, string $alt, ?string $filename = NULL, string $directory = 'placeholders'): ?Media {
  if ($filename === NULL) {
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '-', strtolower($name)) . '.jpg';
  }

  $file = media_lib_download_to_file($url, $filename, $directory);
  if (!$file) {
    return NULL;
  }

  return media_lib_create_from_file_entity($file, $name, $alt);
}

/**
 * Create a Drupal Media entity of type 'image' from an existing File entity.
 *
 * @param \Drupal\file\Entity\File $file
 *   A saved Drupal File entity.
 * @param string $name
 *   The media entity name.
 * @param string $alt
 *   Alt text for the image.
 *
 * @return \Drupal\media\Entity\Media|null
 *   The saved Media entity, or NULL on failure.
 */
function media_lib_create_from_file_entity(File $file, string $name, string $alt): ?Media {
  try {
    $media = Media::create([
      'bundle' => 'image',
      'name' => $name,
      'uid' => 1,
      'status' => 1,
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => $alt,
      ],
    ]);
    $media->save();
    return $media;
  }
  catch (\Exception $e) {
    echo "  WARNING: Failed to create media entity — " . $e->getMessage() . "\n";
    return NULL;
  }
}

/**
 * Generate a picsum.photos URL for a given width and height.
 *
 * @param int $width
 *   Image width in pixels.
 * @param int $height
 *   Image height in pixels.
 *
 * @return string
 *   The picsum.photos URL.
 */
function media_lib_picsum_url(int $width, int $height): string {
  return "https://picsum.photos/$width/$height";
}

/**
 * Create multiple placeholder media entities in one batch.
 *
 * Generates N placeholder images from picsum.photos, each at the given
 * dimensions, saved as Drupal Media entities. Returns an array of Media
 * entities (skipping any that failed).
 *
 * @param int $count
 *   Number of placeholder images to create.
 * @param int $width
 *   Image width in pixels.
 * @param int $height
 *   Image height in pixels.
 * @param string $name_prefix
 *   Prefix for media names (e.g., 'Article hero').
 *   Each image is named "{prefix} 1", "{prefix} 2", etc.
 * @param string $alt_prefix
 *   Prefix for alt text. Same numbering pattern as names.
 *
 * @return \Drupal\media\Entity\Media[]
 *   Array of saved Media entities.
 */
function media_lib_create_placeholder_batch(int $count, int $width, int $height, string $name_prefix = 'Placeholder', string $alt_prefix = 'Placeholder image'): array {
  $media_entities = [];

  for ($i = 1; $i <= $count; $i++) {
    $name = "$name_prefix $i";
    $alt = "$alt_prefix $i";
    $url = media_lib_picsum_url($width, $height);

    echo "  Downloading placeholder $i/$count ({$width}x{$height})...\n";
    $media = media_lib_create_from_url($url, $name, $alt);

    if ($media) {
      echo "  Created media: '$name' (mid: {$media->id()})\n";
      $media_entities[] = $media;
    }
    else {
      echo "  FAILED: Could not create media for '$name'\n";
    }
  }

  return $media_entities;
}
