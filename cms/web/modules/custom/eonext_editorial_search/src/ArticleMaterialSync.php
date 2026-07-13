<?php

declare(strict_types=1);

namespace Drupal\eonext_editorial_search;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\eonext_editorial_search\ComplexSearchClient;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Psr\Log\LoggerInterface;

/**
 * Syncs article field_material from material grid paragraphs.
 */
final class ArticleMaterialSync {

  /**
   * Queue name for bulk article field_material sync.
   */
  public const QUEUE_NAME = 'eonext_editorial_search_article_material_sync';

  private const MATERIAL_GRID_AUTOMATIC = 'material_grid_automatic';

  private const MATERIAL_GRID_MANUAL = 'material_grid_manual';

  /**
   * Paragraph reference fields on articles that may contain material grids.
   *
   * @var string[]
   */
  private const PARAGRAPH_FIELDS = [
    'field_paragraphs',
    'field_related_materials',
  ];

  public function __construct(
    private readonly ComplexSearchClient $complexSearchClient,
    private readonly QueueFactory $queueFactory,
    private readonly QueueWorkerManagerInterface $queueWorkerManager,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Queues field_material sync for all articles, or one article when $nid is set.
   */
  public function enqueueAllArticles(?int $nid = NULL): int {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'article');

    if ($nid !== NULL) {
      $query->condition('nid', $nid);
    }

    $nids = $query->execute();
    if ($nids === []) {
      return 0;
    }

    $queue = $this->queueFactory->get(self::QUEUE_NAME);
    foreach ($nids as $queued_nid) {
      $queue->createItem(['nid' => (int) $queued_nid]);
    }

    return count($nids);
  }

  /**
   * Processes queued field_material sync jobs until the queue is empty.
   */
  public function processQueue(): int {
    $queue = $this->queueFactory->get(self::QUEUE_NAME);
    $worker = $this->queueWorkerManager->createInstance(self::QUEUE_NAME);
    $processed = 0;

    while ($item = $queue->claimItem(60)) {
      try {
        $worker->processItem($item->data);
        $queue->deleteItem($item);
        $processed++;
      }
      catch (SuspendQueueException $exception) {
        $queue->releaseItem($item);
        break;
      }
    }

    return $processed;
  }

  /**
   * Syncs and saves field_material for a single article by node ID.
   */
  public function syncArticleById(int $nid): bool {
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'article') {
      return FALSE;
    }

    $this->syncArticle($node);
    $node->save();

    return TRUE;
  }

  /**
   * Populates field_material on an article from its material grid paragraphs.
   */
  public function syncArticle(NodeInterface $article): void {
    if ($article->bundle() !== 'article' || !$article->hasField('field_material')) {
      return;
    }

    $work_ids = $this->collectWorkIds($article);
    $values = [];

    foreach ($work_ids as $work_id) {
      $values[] = ['value' => $work_id];
    }

    $article->set('field_material', $values);
  }

  /**
   * Collects work IDs from material grid paragraphs on an article.
   *
   * @return string[]
   *   Unique work IDs in encounter order.
   */
  public function collectWorkIds(NodeInterface $article): array {
    $work_ids = [];

    foreach (self::PARAGRAPH_FIELDS as $field_name) {
      if (!$article->hasField($field_name)) {
        continue;
      }

      foreach ($article->get($field_name)->referencedEntities() as $paragraph) {
        if (!$paragraph instanceof ParagraphInterface) {
          continue;
        }

        $work_ids = [...$work_ids, ...$this->collectFromParagraph($paragraph)];
      }
    }

    return array_values(array_unique($work_ids));
  }

  /**
   * @return string[]
   */
  private function collectFromParagraph(ParagraphInterface $paragraph): array {
    return match ($paragraph->bundle()) {
      self::MATERIAL_GRID_MANUAL => $this->collectManualWorkIds($paragraph),
      self::MATERIAL_GRID_AUTOMATIC => $this->collectAutomaticWorkIds($paragraph),
      default => [],
    };
  }

  /**
   * @return string[]
   */
  private function collectManualWorkIds(ParagraphInterface $paragraph): array {
    $work_ids = [];

    if ($paragraph->hasField('field_material_grid_work_ids')) {
      foreach ($paragraph->get('field_material_grid_work_ids')->getValue() as $item) {
        if (!empty($item['value']) && is_string($item['value'])) {
          $work_ids[] = $item['value'];
        }
      }
    }

    if ($paragraph->hasField('field_work_id')) {
      foreach ($paragraph->get('field_work_id')->getValue() as $item) {
        if (!empty($item['value']) && is_string($item['value'])) {
          $work_ids[] = $item['value'];
        }
      }
    }

    return $work_ids;
  }

  /**
   * @return string[]
   */
  private function collectAutomaticWorkIds(ParagraphInterface $paragraph): array {
    if (!$paragraph->hasField('field_cql_search')) {
      return [];
    }

    $cql_search = $paragraph->get('field_cql_search')->getValue();
    if ($cql_search === []) {
      return [];
    }

    $row = $cql_search[0];
    $cql = $row['value'] ?? '';
    if (!is_string($cql) || $cql === '') {
      return [];
    }

    $limit = 8;
    if ($paragraph->hasField('field_material_amount')) {
      $amount = (int) $paragraph->get('field_material_amount')->getString();
      if ($amount > 0) {
        $limit = $amount;
      }
    }

    $work_ids = $this->complexSearchClient->getWorkIds($cql, $limit, $row);
    if ($work_ids === []) {
      $this->logger->warning('No work IDs returned for material_grid_automatic paragraph @id on save.', [
        '@id' => $paragraph->id() ?? 'new',
      ]);
    }

    return $work_ids;
  }

}
