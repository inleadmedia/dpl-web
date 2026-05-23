<?php

/**
 * @file
 * Deploy hooks for EO Next Editorial Search.
 */

declare(strict_types=1);

use Drupal\eonext_editorial_search\ArticleMaterialSync;

/**
 * Populates field_material on existing articles from material grid paragraphs.
 */
function eonext_editorial_search_deploy_sync_article_materials(): string {
  /** @var \Drupal\eonext_editorial_search\ArticleMaterialSync $sync */
  $sync = \Drupal::service(ArticleMaterialSync::class);
  $count = $sync->enqueueAllArticles();

  return sprintf(
    'Queued field_material sync for %d article(s). Process with: drush queue:run %s',
    $count,
    ArticleMaterialSync::QUEUE_NAME,
  );
}

/**
 * Enables editorial event date indexing and marks the index for re-indexing.
 */
function eonext_editorial_search_deploy_reindex_content_events(): string {
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

  return implode(' ', $messages);
}
