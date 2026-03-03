<?php

/**
 * @file
 * Creates taxonomy terms in a vocabulary.
 *
 * Usage:
 *   ddev drush php:script .alchemize/drupal/capabilities/generators/create-taxonomy-terms.drush.php
 *
 * Parameters (set as environment variables or edit defaults below):
 *   VOCABULARY, TERMS
 */

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

// --- Parameters ---
$vocabulary = getenv('VOCABULARY') ?: 'tags';
$terms_csv  = getenv('TERMS') ?: 'Technology,Design,Business,Health,Science,Travel';
$term_names = array_map('trim', explode(',', $terms_csv));

// --- Validate vocabulary exists ---
if (!Vocabulary::load($vocabulary)) {
  echo "ERROR: Vocabulary '$vocabulary' does not exist.\n";
  return;
}

// --- Load existing terms for idempotent check ---
$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$existing = $storage->loadByProperties(['vid' => $vocabulary]);
$existing_names = array_map(fn($t) => $t->label(), $existing);

$created = 0;
$skipped = 0;

foreach ($term_names as $name) {
  if (in_array($name, $existing_names, TRUE)) {
    echo "  Term '$name' already exists. Skipping.\n";
    $skipped++;
    continue;
  }

  Term::create([
    'vid' => $vocabulary,
    'name' => $name,
    'status' => 1,
  ])->save();
  echo "  Created term: $name\n";
  $created++;
}

echo "\nDone. Created: $created, Skipped: $skipped (already existed).\n";
