<?php

/**
 * @file
 * Deploy hooks for EO Next Editorial Search.
 *
 * The hooks that used to rewrite DPL CMS' content_events search index on every
 * deployment are gone. The index is now shipped as part of this module's
 * config/sync overlay, so the platform's own configuration import puts it in
 * place instead.
 *
 * @see \Drupal\eonext_editorial_search\EventSubscriber\OverlayConfigEventSubscriber
 */

declare(strict_types=1);

use Drupal\eonext_editorial_search\ArticleMaterialSync;

/**
 * Populates field_material on existing articles from material grid paragraphs.
 */
function eonext_editorial_search_deploy_sync_article_materials(): string {
  \Drupal::moduleHandler()->loadInclude('eonext_editorial_search', 'install');

  $count = _eonext_editorial_search_enqueue_article_material_sync();

  return sprintf(
    'Queued field_material sync for %d article(s). Process with: drush queue:run %s',
    $count,
    ArticleMaterialSync::QUEUE_NAME,
  );
}
