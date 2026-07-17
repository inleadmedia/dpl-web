<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_events\Plugin\search_api\processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\dpl_event\Entity\EventInstance;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Indexes whether an event is free to attend.
 */
#[SearchApiProcessor(
  id: 'event_is_free',
  label: new TranslatableMarkup('Event is free'),
  description: new TranslatableMarkup('Indexes a boolean when all ticket categories are free.'),
  stages: [
    'preprocess_index' => 0,
  ],
)]
final class EventIsFreeProcessor extends ProcessorPluginBase {

  public const FIELD_ID = 'event_is_free';

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index): bool {
    foreach ($index->getDatasources() as $datasource) {
      if ($datasource->getEntityTypeId() === 'eventinstance') {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array $items): void {
    foreach ($items as $item) {
      $entity = $item->getOriginalObject()->getValue();
      if (!$entity instanceof EventInstance) {
        continue;
      }

      $field = $item->getField(self::FIELD_ID, FALSE);
      if (!$field instanceof FieldInterface) {
        continue;
      }

      $field->setValues([]);
      $field->addValue($entity->isFreeToAttend() ? 1 : 0);
    }
  }

}
