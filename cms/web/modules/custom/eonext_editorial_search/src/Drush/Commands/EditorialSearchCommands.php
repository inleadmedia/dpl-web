<?php

declare(strict_types=1);

namespace Drupal\eonext_editorial_search\Drush\Commands;

use Drupal\eonext_editorial_search\ArticleMaterialSync;
use Drush\Attributes\Command;
use Drush\Attributes\Option;
use Drush\Attributes\Usage;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for editorial search maintenance.
 */
final class EditorialSearchCommands extends DrushCommands {

  public function __construct(
    private readonly ArticleMaterialSync $articleMaterialSync,
  ) {
    parent::__construct();
  }

  /**
   * Queue or process field_material sync for articles.
   */
  #[Command(name: 'eonext-editorial-search:sync-article-materials')]
  #[Option(name: 'nid', description: 'Sync one article immediately by node ID.')]
  #[Option(name: 'process', description: 'Process all queued field_material sync jobs.')]
  #[Usage(name: 'drush eonext-editorial-search:sync-article-materials', description: 'Queue sync for all articles.')]
  #[Usage(name: 'drush eonext-editorial-search:sync-article-materials --process', description: 'Process the sync queue.')]
  #[Usage(name: 'drush eonext-editorial-search:sync-article-materials --nid=42', description: 'Sync one article immediately.')]
  public function syncArticleMaterials(array $options = ['nid' => NULL, 'process' => FALSE]): void {
    if (!empty($options['process'])) {
      $count = $this->articleMaterialSync->processQueue();
      $this->logger()->success(sprintf('Processed %d queued field_material sync job(s).', $count));
      return;
    }

    $nid = !empty($options['nid']) ? (int) $options['nid'] : NULL;

    if ($nid !== NULL) {
      if (!$this->articleMaterialSync->syncArticleById($nid)) {
        $this->logger()->warning('Article @nid was not found or is not an article.', ['@nid' => $nid]);
        return;
      }

      $this->logger()->success(sprintf('Synced field_material on article %d.', $nid));
      return;
    }

    $count = $this->articleMaterialSync->enqueueAllArticles();
    if ($count === 0) {
      $this->logger()->notice('No articles found to queue.');
      return;
    }

    $this->logger()->success(sprintf(
      'Queued field_material sync for %d article(s). Run with --process or: drush queue:run %s',
      $count,
      ArticleMaterialSync::QUEUE_NAME,
    ));
  }

}
