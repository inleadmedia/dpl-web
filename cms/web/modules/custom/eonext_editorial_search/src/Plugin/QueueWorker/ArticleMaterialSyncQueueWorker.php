<?php

declare(strict_types=1);

namespace Drupal\eonext_editorial_search\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\eonext_editorial_search\ArticleMaterialSync;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Syncs field_material on a single article from material grid paragraphs.
 *
 * @QueueWorker(
 *   id = "eonext_editorial_search_article_material_sync",
 *   title = @Translation("Sync article field_material from material grids"),
 *   cron = {"time" = 60}
 * )
 */
final class ArticleMaterialSyncQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ArticleMaterialSync $articleMaterialSync,
    private readonly LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(ArticleMaterialSync::class),
      $container->get('logger.channel.eonext_editorial_search'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $nid = (int) ($data['nid'] ?? 0);
    if ($nid <= 0) {
      return;
    }

    try {
      if (!$this->articleMaterialSync->syncArticleById($nid)) {
        $this->logger->warning('Skipped field_material sync for missing or invalid article @nid.', [
          '@nid' => $nid,
        ]);
      }
    }
    catch (\Throwable $exception) {
      $this->logger->error('Failed field_material sync for article @nid: @message', [
        '@nid' => $nid,
        '@message' => $exception->getMessage(),
      ]);

      throw $exception;
    }
  }

}
