<?php

declare(strict_types=1);

namespace Drupal\eonext_editorial_search\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Prevents unpublished editorial entities from being stored in the search index.
 */
#[SearchApiProcessor(
  id: 'editorial_published_content',
  label: new TranslatableMarkup('Editorial published content'),
  description: new TranslatableMarkup(
    'Excludes unpublished nodes and event series from the editorial search index.'
  ),
  stages: [
    'preprocess_index' => 0,
  ],
)]
final class EditorialPublishedContentProcessor extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index): bool {
    foreach ($index->getDatasources() as $datasource) {
      if (self::isSupportedDatasource($datasource)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items): void {
    foreach ($items as $item_id => $item) {
      $entity = $item->getOriginalObject()->getValue();
      if ($entity instanceof EntityPublishedInterface && !$entity->isPublished()) {
        unset($items[$item_id]);
      }
    }
  }

  /**
   * Whether a datasource can provide publishable editorial entities.
   */
  private static function isSupportedDatasource(DatasourceInterface $datasource): bool {
    return in_array($datasource->getEntityTypeId(), ['node', 'eventseries'], TRUE);
  }

}
