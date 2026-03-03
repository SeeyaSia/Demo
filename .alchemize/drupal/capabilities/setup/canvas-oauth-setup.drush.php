<?php

/**
 * @file
 * Checks Canvas OAuth setup for CLI integration.
 *
 * Verifies that canvas_oauth and simple_oauth are installed,
 * RSA keys are configured, and reports the site URL and
 * existing OAuth consumers for CLI configuration.
 *
 * Usage: ddev drush php:script .alchemize/drupal/capabilities/setup/canvas-oauth-setup.drush.php
 */

$moduleHandler = \Drupal::moduleHandler();

echo "=== Canvas OAuth Setup Check ===\n\n";

// Check canvas_oauth module.
echo "1. Module status:\n";
$canvasOauthEnabled = $moduleHandler->moduleExists('canvas_oauth');
echo "   canvas_oauth: " . ($canvasOauthEnabled ? '✅ enabled' : '❌ NOT enabled') . "\n";

$simpleOauthEnabled = $moduleHandler->moduleExists('simple_oauth');
echo "   simple_oauth: " . ($simpleOauthEnabled ? '✅ enabled' : '❌ NOT enabled') . "\n";

if (!$canvasOauthEnabled) {
  echo "\n⚠️  canvas_oauth is not enabled. To enable:\n";
  echo "   composer require drupal/simple_oauth:^6\n";
  echo "   ddev drush en canvas_oauth -y\n";
  echo "   ddev drush cex -y\n\n";
}

if (!$simpleOauthEnabled) {
  echo "\n⚠️  simple_oauth is not enabled. It is required by canvas_oauth.\n\n";
}

// Check RSA keys (only if simple_oauth is enabled).
echo "\n2. RSA key configuration:\n";
if ($simpleOauthEnabled) {
  $config = \Drupal::config('simple_oauth.settings');
  $privateKey = $config->get('private_key');
  $publicKey = $config->get('public_key');

  if ($privateKey && file_exists($privateKey)) {
    echo "   Private key: ✅ $privateKey\n";
  }
  else {
    echo "   Private key: ❌ " . ($privateKey ? "File not found: $privateKey" : 'Not configured') . "\n";
    echo "   Generate with: openssl genrsa -out private.key 2048\n";
  }

  if ($publicKey && file_exists($publicKey)) {
    echo "   Public key:  ✅ $publicKey\n";
  }
  else {
    echo "   Public key:  ❌ " . ($publicKey ? "File not found: $publicKey" : 'Not configured') . "\n";
    echo "   Generate with: openssl rsa -in private.key -pubout > public.key\n";
  }

  echo "   Configure at: /admin/config/people/simple_oauth\n";
}
else {
  echo "   (skipped — simple_oauth not enabled)\n";
}

// Check OAuth consumers (only if simple_oauth is enabled).
echo "\n3. OAuth consumers:\n";
if ($simpleOauthEnabled) {
  try {
    $consumerStorage = \Drupal::entityTypeManager()->getStorage('consumer');
    $consumers = $consumerStorage->loadMultiple();

    if (empty($consumers)) {
      echo "   ❌ No consumers found.\n";
      echo "   Create one at: /admin/config/services/consumer\n";
    }
    else {
      foreach ($consumers as $consumer) {
        $label = $consumer->label() ?? '(unlabeled)';
        $clientId = $consumer->get('client_id')->value ?? '(none)';
        echo "   - $label (client_id: $clientId)\n";
      }
    }
    echo "   Manage at: /admin/config/services/consumer\n";
  }
  catch (\Exception $e) {
    echo "   ERROR: Could not load OAuth consumers: " . $e->getMessage() . "\n";
  }
}
else {
  echo "   (skipped — simple_oauth not enabled)\n";
}

// Check OAuth scopes.
echo "\n4. OAuth scopes:\n";
if ($canvasOauthEnabled) {
  try {
    $scopeStorage = \Drupal::entityTypeManager()->getStorage('oauth2_scope');
    $scopes = $scopeStorage->loadMultiple();

    $canvasScopes = array_filter($scopes, fn($s) => str_starts_with($s->id(), 'canvas_'));
    if (empty($canvasScopes)) {
      echo "   ❌ No Canvas OAuth scopes found.\n";
    }
    else {
      foreach ($canvasScopes as $scope) {
        echo "   - " . $scope->id() . ": " . ($scope->get('description') ?? $scope->label()) . "\n";
      }
    }
  }
  catch (\Exception $e) {
    echo "   ERROR: Could not load OAuth scopes: " . $e->getMessage() . "\n";
  }
}
else {
  echo "   (skipped — canvas_oauth not enabled)\n";
}

// Site URL for CLI config.
echo "\n5. CLI configuration values:\n";
$baseUrl = \Drupal::request()->getSchemeAndHttpHost();
echo "   CANVAS_SITE_URL=$baseUrl\n";
echo "   CANVAS_CLIENT_ID=<from step 3 above>\n";
echo "   CANVAS_CLIENT_SECRET=<your secret>\n";
echo "   CANVAS_COMPONENT_DIR=canvas-components\n";
echo "   CANVAS_SCOPE=\"canvas:js_component canvas:asset_library\"\n";

echo "\n=== End Check ===\n";
