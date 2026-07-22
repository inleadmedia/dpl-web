<?php

/**
 * @file
 * Deploy hooks for EO Next Editorial Search.
 */

declare(strict_types=1);

/**
 * Loads install-time helpers for deploy hooks.
 */
function _eonext_editorial_search_load_install(): void {
  \Drupal::moduleHandler()->loadInclude('eonext_editorial_search', 'install');
}

/**
 * Ensures Search API fields required by editorial search facets exist on the index.
 */
function eonext_editorial_search_deploy_ensure_search_index_fields(): string {
  _eonext_editorial_search_load_install();

  return implode(' ', [
    eonext_editorial_search_ensure_facet_index_fields(),
    eonext_editorial_search_ensure_sort_index_fields(),
  ]);
}

/**
 * Populates field_material on existing articles from material grid paragraphs.
 */
function eonext_editorial_search_deploy_sync_article_materials(): string {
  _eonext_editorial_search_load_install();

  $count = _eonext_editorial_search_enqueue_article_material_sync();

  return sprintf(
    'Queued field_material sync for %d article(s). Process with: drush queue:run %s',
    $count,
    \Drupal\eonext_editorial_search\ArticleMaterialSync::QUEUE_NAME,
  );
}

/**
 * Enables editorial event date indexing and marks the index for re-indexing.
 */
function eonext_editorial_search_deploy_reindex_content_events(): string {
  _eonext_editorial_search_load_install();

  $messages = [
    eonext_editorial_search_ensure_editorial_event_date_processor(),
  ];

  $index = \Drupal\search_api\Entity\Index::load('content_events');
  if (!$index) {
    return 'Search API index content_events was not found; no re-index performed.';
  }

  $sort_date = $index->getField('sort_date');
  if ($sort_date) {
    $configuration = $sort_date->getConfiguration();
    $configuration['fields'] = ['entity:node/created'];
    $sort_date->setConfiguration($configuration);
    $index->save();
    $messages[] = 'Updated sort_date configuration on index content_events.';
  }

  $index->reindex();
  $messages[] = 'Marked index content_events for re-indexing. Run: drush search-api:reset-tracker content_events && drush search-api:index content_events';
  $messages[] = eonext_editorial_search_remove_expired_event_series_from_index();

  return implode(' ', $messages);
}

/**
 * Removes expired event series still present in the editorial search index.
 */
function eonext_editorial_search_deploy_remove_expired_events_from_index(): string {
  _eonext_editorial_search_load_install();

  return eonext_editorial_search_remove_expired_event_series_from_index();
}

/**
 * Enables full editorial content indexing and marks the index for re-indexing.
 */
function eonext_editorial_search_deploy_index_editorial_content(): string {
  _eonext_editorial_search_load_install();

  return eonext_editorial_search_ensure_editorial_content_indexing();
}

/**
 * Excludes GO articles from editorial search index and facet filters.
 */
function eonext_editorial_search_deploy_exclude_go_articles_from_index(): string {
  _eonext_editorial_search_load_install();

  return eonext_editorial_search_exclude_go_articles_from_index();
}

/**
 * Indexes past events and marks the editorial search index for re-indexing.
 */
function eonext_editorial_search_deploy_index_past_events(): string {
  _eonext_editorial_search_load_install();

  return eonext_editorial_search_ensure_event_has_upcoming_indexing();
}

/**
 * Excludes unpublished content from the editorial search index and facet counts.
 */
function eonext_editorial_search_deploy_exclude_unpublished_from_index(): string {
  _eonext_editorial_search_load_install();

  return eonext_editorial_search_ensure_published_content_indexing();
}

/**
 * Switches editorial search results to card view mode for grid display.
 */
function eonext_editorial_search_deploy_card_grid_results(): string {
  _eonext_editorial_search_load_install();

  return eonext_editorial_search_configure_card_grid_view();
}

/**
 * Adds event target group, free events facets, and date range filter.
 */
function eonext_editorial_search_deploy_event_search_facets(): string {
  _eonext_editorial_search_load_install();

  $messages = [
    eonext_editorial_search_ensure_facet_index_fields(),
    eonext_editorial_search_ensure_editorial_is_free_processor(),
    eonext_editorial_search_ensure_event_facets(),
    eonext_editorial_search_configure_editorial_search_view(),
    eonext_editorial_search_configure_facet_weights(),
  ];

  $index = \Drupal\search_api\Entity\Index::load('content_events');
  if ($index) {
    $index->reindex();
    $messages[] = 'Marked index content_events for re-indexing. Run: drush search-api:index content_events';
  }

  return implode(' ', array_filter($messages));
}
