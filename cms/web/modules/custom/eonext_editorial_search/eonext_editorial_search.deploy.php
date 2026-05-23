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
