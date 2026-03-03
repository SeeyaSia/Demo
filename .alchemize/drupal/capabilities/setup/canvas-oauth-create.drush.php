<?php

/**
 * @file
 * Creates an OAuth consumer for Canvas CLI.
 *
 * Creates a simple_oauth consumer entity with client_credentials grant type
 * and Canvas-specific scopes. Idempotent — checks for existing consumer by
 * client_id before creating.
 *
 * Parameters (environment variables):
 *   CLIENT_ID     — OAuth client ID (default: "canvas_cli")
 *   CLIENT_SECRET — OAuth client secret (REQUIRED, no default)
 *   CLIENT_LABEL  — Human-readable label (default: "Canvas CLI")
 *
 * Usage: ddev exec "CLIENT_ID=canvas_cli CLIENT_SECRET=mysecret CLIENT_LABEL='Canvas CLI' drush php:script .alchemize/drupal/capabilities/setup/canvas-oauth-create.drush.php"
 */

$client_id = getenv('CLIENT_ID') ?: 'canvas_cli';
$client_secret = getenv('CLIENT_SECRET') ?: '';
$client_label = getenv('CLIENT_LABEL') ?: 'Canvas CLI';

echo "=== Create OAuth Consumer ===\n\n";
echo "Client ID:    $client_id\n";
echo "Client label: $client_label\n\n";

// ============================================================
// Step 1: Validate required parameters
// ============================================================

if (empty($client_secret)) {
  echo "ERROR: CLIENT_SECRET environment variable is required.\n";
  echo "=== Aborted ===\n";
  return;
}

// ============================================================
// Step 2: Check for existing consumer
// ============================================================

$storage = \Drupal::entityTypeManager()->getStorage('consumer');
$existing = $storage->loadByProperties(['client_id' => $client_id]);

if (!empty($existing)) {
  $consumer = reset($existing);
  echo "Consumer '$client_id' already exists (uuid: " . $consumer->uuid() . ")\n";
  echo "EXISTS:" . $consumer->uuid() . "\n";
  echo "\n=== Done (no changes) ===\n";
  return;
}

// ============================================================
// Step 3: Create consumer
// ============================================================

$consumer = $storage->create([
  'label' => $client_label,
  'client_id' => $client_id,
  'secret' => $client_secret,
  'is_default' => FALSE,
  'confidential' => TRUE,
  'user_id' => 1,
  'grant_types' => ['client_credentials'],
  'scopes' => [
    ['scope_id' => 'canvas_js_component'],
    ['scope_id' => 'canvas_asset_library'],
  ],
]);
$consumer->save();

echo "CREATED consumer '$client_label' (uuid: " . $consumer->uuid() . ")\n";
echo "CREATED:" . $consumer->uuid() . "\n";
echo "\n=== Done ===\n";
