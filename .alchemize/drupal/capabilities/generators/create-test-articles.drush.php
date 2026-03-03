<?php

/**
 * @file
 * Creates test article nodes with body text, taxonomy tags, and placeholder images.
 *
 * Downloads a unique placeholder image from picsum.photos for each article
 * and attaches it to the article's field_image field. Images are saved as
 * permanent file entities so they persist across cache clears.
 *
 * Usage:
 *   ddev drush php:script .alchemize/drupal/capabilities/generators/create-test-articles.drush.php
 *
 * Parameters (set as environment variables — use ddev exec for custom values):
 *   COUNT     — Number of articles to create (default: 6)
 *   TAG_VOCAB — Taxonomy vocabulary to pull tags from (default: 'tags')
 *   IMAGES    — Set to '0' to skip placeholder image download (default: '1')
 *
 * Examples:
 *   # Create 6 articles with images (default)
 *   ddev drush php:script .alchemize/drupal/capabilities/generators/create-test-articles.drush.php
 *
 *   # Create 3 articles without images
 *   ddev exec "COUNT=3 IMAGES=0 drush php:script \
 *     .alchemize/drupal/capabilities/generators/create-test-articles.drush.php"
 *
 * Note: The article content type uses a direct image field (field_image),
 * not a media reference. For new content types, always use media references
 * instead — see entity-types.md and content-types.md.
 */

require_once __DIR__ . '/../lib/media-lib.php';

use Drupal\node\Entity\Node;

// --- Parameters ---
$count      = (int) (getenv('COUNT') ?: 6);
$tag_vocab  = getenv('TAG_VOCAB') ?: 'tags';
$with_images = getenv('IMAGES') !== '0';

// --- Load available tags ---
$term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$terms = $term_storage->loadByProperties(['vid' => $tag_vocab]);
$term_ids = array_keys($terms);
$term_names = array_map(fn($t) => $t->label(), $terms);

if (empty($term_ids)) {
  echo "WARNING: No terms found in vocabulary '$tag_vocab'. Articles will be created without tags.\n";
}

// --- Sample article data ---
$articles = [
  [
    'title' => 'Getting Started with Modern Web Development',
    'body' => '<p>Modern web development has evolved significantly over the past decade. From simple HTML pages to complex single-page applications, the landscape continues to shift. In this article, we explore the fundamental tools and frameworks that every developer should know.</p><p>Whether you are just starting out or looking to update your skills, understanding the basics of HTML5, CSS3, and JavaScript ES6+ is essential. We also cover popular frameworks like React, Vue, and Svelte.</p>',
    'tags' => ['Technology', 'Design'],
  ],
  [
    'title' => 'The Art of Minimalist Design',
    'body' => '<p>Minimalism in design is more than just a trend — it is a philosophy that emphasizes clarity, purpose, and intentionality. By stripping away the unnecessary, we create experiences that are both beautiful and functional.</p><p>This article explores key principles of minimalist design: whitespace, typography hierarchy, limited color palettes, and content-first layouts. We look at real-world examples from leading brands.</p>',
    'tags' => ['Design'],
  ],
  [
    'title' => 'Building Scalable Business Systems',
    'body' => '<p>Scaling a business requires more than just growth in revenue. It demands systems, processes, and technology that can handle increasing complexity without breaking. In this guide, we cover the essential pillars of scalable business operations.</p><p>From CRM systems to automated workflows, learn how successful companies build infrastructure that grows with them. We examine case studies from startups to enterprise organizations.</p>',
    'tags' => ['Business', 'Technology'],
  ],
  [
    'title' => 'Wellness in the Digital Age',
    'body' => '<p>As our lives become increasingly digital, maintaining physical and mental wellness presents new challenges. Screen time, sedentary work, and information overload all take their toll. But technology can also be part of the solution.</p><p>Discover practical strategies for balancing digital life with wellness: mindful tech use, ergonomic setups, digital detox practices, and apps that actually help rather than distract.</p>',
    'tags' => ['Health', 'Technology'],
  ],
  [
    'title' => 'Exploring the Science of Climate Change',
    'body' => '<p>Climate science has made remarkable advances in recent decades. Through satellite observations, ice core analysis, and sophisticated computer models, scientists have built a detailed picture of how our climate is changing and why.</p><p>This article breaks down the key findings in accessible language: the greenhouse effect, feedback loops, tipping points, and the difference between weather and climate. We also look at what the latest IPCC reports tell us about the future.</p>',
    'tags' => ['Science'],
  ],
  [
    'title' => 'Hidden Gems: Off-the-Beaten-Path Travel Destinations',
    'body' => '<p>While popular tourist destinations have their appeal, some of the most memorable travel experiences come from venturing off the beaten path. From remote mountain villages to coastal towns untouched by mass tourism, the world is full of hidden gems waiting to be discovered.</p><p>In this guide, we share six under-the-radar destinations across three continents. Each offers unique culture, stunning landscapes, and authentic experiences that you will not find in a guidebook.</p>',
    'tags' => ['Travel'],
  ],
  [
    'title' => 'The Future of Artificial Intelligence in Healthcare',
    'body' => '<p>Artificial intelligence is transforming healthcare in ways that were unimaginable just a decade ago. From diagnostic imaging to drug discovery, AI is accelerating medical breakthroughs and improving patient outcomes.</p><p>We examine the most promising applications of AI in healthcare, including early disease detection, personalized treatment plans, robotic surgery assistance, and mental health chatbots. We also discuss the ethical considerations that come with these advances.</p>',
    'tags' => ['Science', 'Health', 'Technology'],
  ],
  [
    'title' => 'Sustainable Design Principles for the Modern Web',
    'body' => '<p>Sustainability is not just for physical products — digital design has an environmental footprint too. Every page load consumes energy, every image requires server resources, and every unnecessary script adds to carbon emissions.</p><p>Learn how to design websites that are both beautiful and sustainable. Topics include performance optimization, green hosting, efficient asset delivery, and designing for longevity rather than disposability.</p>',
    'tags' => ['Design', 'Technology', 'Science'],
  ],
  [
    'title' => 'Entrepreneurship Lessons from Around the World',
    'body' => '<p>Entrepreneurship looks different in every culture. From Silicon Valley startups to African mobile-first businesses, from Japanese kaizen-inspired companies to Scandinavian social enterprises, there is no single formula for success.</p><p>This article explores entrepreneurial lessons from diverse global ecosystems. Learn how geography, culture, and local challenges shape innovation and business strategies across continents.</p>',
    'tags' => ['Business', 'Travel'],
  ],
  [
    'title' => 'A Beginner Guide to Outdoor Photography',
    'body' => '<p>Outdoor photography combines the joy of nature with the art of visual storytelling. Whether you are hiking through forests, exploring coastlines, or simply walking through your neighborhood, there are stunning images waiting to be captured.</p><p>This beginner-friendly guide covers essential techniques: composition rules, natural lighting, camera settings for landscapes, and post-processing tips. You do not need expensive gear — a smartphone and a good eye can take you far.</p>',
    'tags' => ['Travel', 'Design'],
  ],
];

// --- Build term name → ID lookup ---
$name_to_id = [];
foreach ($terms as $term) {
  $name_to_id[$term->label()] = $term->id();
}

// --- Check if field_image exists on articles ---
$has_image_field = (bool) \Drupal::entityTypeManager()
  ->getStorage('field_config')
  ->load('node.article.field_image');

if ($with_images && !$has_image_field) {
  echo "WARNING: field_image not found on article content type. Skipping images.\n";
  $with_images = FALSE;
}

if ($with_images) {
  echo "Placeholder images: ENABLED (downloading from picsum.photos)\n\n";
}
else {
  echo "Placeholder images: DISABLED\n\n";
}

// --- Create articles (skip duplicates by title) ---
$created = 0;
$skipped = 0;
for ($i = 0; $i < $count && $i < count($articles); $i++) {
  $article = $articles[$i];

  // Idempotent: skip if an article with this title already exists.
  $existing = \Drupal::entityQuery('node')
    ->condition('type', 'article')
    ->condition('title', $article['title'])
    ->accessCheck(FALSE)
    ->range(0, 1)
    ->execute();
  if (!empty($existing)) {
    echo "  Skipped (exists): '{$article['title']}'\n";
    $skipped++;
    continue;
  }

  // Map tag names to term IDs.
  $tag_refs = [];
  foreach ($article['tags'] as $tag_name) {
    if (isset($name_to_id[$tag_name])) {
      $tag_refs[] = ['target_id' => $name_to_id[$tag_name]];
    }
  }

  // Download placeholder image for this article.
  $image_data = [];
  if ($with_images) {
    $safe_title = preg_replace('/[^a-zA-Z0-9-]/', '-', strtolower($article['title']));
    $filename = "article-$safe_title.jpg";
    echo "  Downloading placeholder image for '{$article['title']}'...\n";
    $file = media_lib_download_to_file(
      media_lib_picsum_url(1200, 800),
      $filename,
      'article-images'
    );
    if ($file) {
      $image_data = [
        'target_id' => $file->id(),
        'alt' => "Image for: {$article['title']}",
      ];
    }
  }

  $node_values = [
    'type' => 'article',
    'title' => $article['title'],
    'body' => [
      'value' => $article['body'],
      'format' => 'full_html',
    ],
    'field_tags' => $tag_refs,
    'status' => 1,
    'uid' => 1,
    // Stagger creation times so sorting by date is meaningful.
    'created' => \Drupal::time()->getRequestTime() - (($count - $i) * 3600),
  ];

  // Attach image if downloaded.
  if (!empty($image_data)) {
    $node_values['field_image'] = $image_data;
  }

  $node = Node::create($node_values);
  $node->save();
  $tag_labels = implode(', ', $article['tags']);
  $img_status = !empty($image_data) ? ', with image' : '';
  echo "  Created article: '{$article['title']}' (nid: {$node->id()}, tags: $tag_labels$img_status)\n";
  $created++;
}

echo "\nDone. Created $created test articles" . ($skipped ? ", skipped $skipped existing" : '') . ".\n";
