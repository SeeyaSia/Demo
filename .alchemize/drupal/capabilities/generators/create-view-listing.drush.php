<?php

/**
 * @file
 * Creates a View with Block display for listing a content type.
 *
 * Usage:
 *   ddev drush php:script .alchemize/drupal/capabilities/generators/create-view-listing.drush.php
 *
 * Parameters (set as environment variables or edit defaults below):
 *   VIEW_ID, VIEW_LABEL, CONTENT_TYPE, VIEW_MODE, ITEMS_PER_PAGE,
 *   EXPOSED_FILTER_FIELD, EXPOSED_FILTER_VOCAB
 */

use Drupal\views\Entity\View;

// --- Parameters ---
$view_id       = getenv('VIEW_ID') ?: 'article_listing';
$view_label    = getenv('VIEW_LABEL') ?: 'Article Listing';
$content_type  = getenv('CONTENT_TYPE') ?: 'article';
$view_mode     = getenv('VIEW_MODE') ?: 'teaser';
$items_per_page = (int) (getenv('ITEMS_PER_PAGE') ?: 10);
$exposed_field = getenv('EXPOSED_FILTER_FIELD') ?: '';
$exposed_vocab = getenv('EXPOSED_FILTER_VOCAB') ?: '';

// --- Idempotent check ---
if (View::load($view_id)) {
  echo "View '$view_id' already exists. Skipping creation.\n";
  return;
}

// --- Build filters ---
$filters = [
  'type' => [
    'id' => 'type',
    'table' => 'node_field_data',
    'field' => 'type',
    'relationship' => 'none',
    'group_type' => 'group',
    'admin_label' => '',
    'entity_type' => 'node',
    'entity_field' => 'type',
    'plugin_id' => 'bundle',
    'value' => [$content_type => $content_type],
    'group' => 1,
    'expose' => ['operator' => FALSE],
    'is_grouped' => FALSE,
  ],
  'status' => [
    'id' => 'status',
    'table' => 'node_field_data',
    'field' => 'status',
    'relationship' => 'none',
    'group_type' => 'group',
    'admin_label' => '',
    'entity_type' => 'node',
    'entity_field' => 'status',
    'plugin_id' => 'boolean',
    'value' => '1',
    'group' => 1,
    'expose' => ['operator' => FALSE],
    'is_grouped' => FALSE,
  ],
];

// Add exposed taxonomy filter if specified.
if ($exposed_field && $exposed_vocab) {
  $filters[$exposed_field . '_target_id'] = [
    'id' => $exposed_field . '_target_id',
    'table' => 'node__' . $exposed_field,
    'field' => $exposed_field . '_target_id',
    'relationship' => 'none',
    'group_type' => 'group',
    'admin_label' => '',
    'plugin_id' => 'taxonomy_index_tid',
    'value' => [],
    'group' => 1,
    'exposed' => TRUE,
    'expose' => [
      'operator_id' => $exposed_field . '_target_id_op',
      'label' => 'Filter by tag',
      'description' => '',
      'use_operator' => FALSE,
      'operator' => $exposed_field . '_target_id_op',
      'operator_limit_selection' => FALSE,
      'operator_list' => [],
      'identifier' => $exposed_field . '_target_id',
      'required' => FALSE,
      'remember' => FALSE,
      'multiple' => FALSE,
      'remember_roles' => ['authenticated' => 'authenticated'],
      'reduce' => FALSE,
    ],
    'is_grouped' => FALSE,
    'type' => 'select',
    'limit' => TRUE,
    'vid' => $exposed_vocab,
    'hierarchy' => FALSE,
    'error_message' => TRUE,
  ];
}

// --- Build default display options ---
$use_ajax = (bool) (getenv('USE_AJAX') ?: TRUE);
$default_display_options = [
  'title' => $view_label,
  'use_ajax' => $use_ajax,
  'filters' => $filters,
  'filter_groups' => [
    'operator' => 'AND',
    'groups' => [1 => 'AND'],
  ],
  'sorts' => [
    'created' => [
      'id' => 'created',
      'table' => 'node_field_data',
      'field' => 'created',
      'relationship' => 'none',
      'group_type' => 'group',
      'admin_label' => '',
      'entity_type' => 'node',
      'entity_field' => 'created',
      'plugin_id' => 'date',
      'order' => 'DESC',
      'expose' => ['label' => ''],
      'granularity' => 'second',
    ],
  ],
  'style' => [
    'type' => 'default',
  ],
  'row' => [
    'type' => 'entity:node',
    'options' => ['view_mode' => $view_mode],
  ],
  'pager' => [
    'type' => $items_per_page > 0 ? 'full' : 'none',
    'options' => $items_per_page > 0 ? ['items_per_page' => $items_per_page] : [],
  ],
  'access' => [
    'type' => 'perm',
    'options' => ['perm' => 'access content'],
  ],
  'cache' => [
    'type' => 'tag',
    'options' => [],
  ],
  'query' => [
    'type' => 'views_query',
    'options' => [
      'query_comment' => '',
      'disable_sql_rewrite' => FALSE,
      'distinct' => FALSE,
    ],
  ],
];

// Add exposed form settings if we have an exposed filter.
if ($exposed_field) {
  $default_display_options['exposed_form'] = [
    'type' => 'basic',
    'options' => [
      'submit_button' => 'Filter',
      'reset_button' => TRUE,
      'reset_button_label' => 'Reset',
      'exposed_sorts_label' => 'Sort by',
      'expose_sort_order' => TRUE,
      'sort_asc_label' => 'Asc',
      'sort_desc_label' => 'Desc',
    ],
  ];
}

// --- Create the View ---
$view = View::create([
  'id' => $view_id,
  'label' => $view_label,
  'module' => 'views',
  'base_table' => 'node_field_data',
  'base_field' => 'nid',
  'display' => [
    'default' => [
      'id' => 'default',
      'display_title' => 'Default',
      'display_plugin' => 'default',
      'position' => 0,
      'display_options' => $default_display_options,
    ],
    'block_1' => [
      'id' => 'block_1',
      'display_title' => 'Block',
      'display_plugin' => 'block',
      'position' => 1,
      'display_options' => array_filter([
        'block_description' => $view_label,
        // When an exposed filter is present, render it as a separate block
        // so it can be placed independently in Canvas layouts.
        'exposed_block' => $exposed_field ? TRUE : NULL,
        'display_extenders' => [],
      ], fn($v) => $v !== NULL),
    ],
  ],
]);

$view->save();
echo "Created View '$view_id' with Block display.\n";
echo "Canvas component ID: block.views_block.{$view_id}-block_1\n";
if ($exposed_field) {
  echo "Canvas exposed filter component ID: block.views_exposed_filter_block.{$view_id}-block_1\n";
}
echo "\nRun 'ddev drush cr' for Canvas to discover the new block component.\n";
echo "Run 'ddev drush cex -y' to export config.\n";
