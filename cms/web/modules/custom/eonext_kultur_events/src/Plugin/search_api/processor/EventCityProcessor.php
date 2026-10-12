<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_events\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\dpl_event\Entity\EventInstance;
use Drupal\recurring_events\Entity\EventSeries;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Indexes the event city from address locality (or GSearch postal name).
 */
#[SearchApiProcessor(
  id: 'event_city',
  label: new TranslatableMarkup('Event city'),
  description: new TranslatableMarkup('Indexes city/locality from the event address for faceted search.'),
  stages: [
    'preprocess_index' => 0,
  ],
)]
final class EventCityProcessor extends ProcessorPluginBase {

  public const FIELD_ID = 'event_city';

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

      $city = self::resolveCity($entity);
      $field->setValues([]);
      if ($city !== NULL && $city !== '') {
        $field->addValue($city);
      }
    }
  }

  /**
   * Resolves a display city name from an event instance (and its series).
   */
  public static function resolveCity(EventInstance $instance): ?string {
    $series = $instance->getEventSeries();
    if ($series instanceof EventSeries) {
      $city = self::resolveCityFromEntity($series);
      if ($city !== NULL) {
        return $city;
      }
    }

    return self::resolveCityFromEntity($instance);
  }

  /**
   * Reads city/locality from standard or GSearch address fields.
   */
  private static function resolveCityFromEntity(EntityInterface $entity): ?string {
    foreach (['event_address', 'field_event_address'] as $fieldName) {
      if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
        continue;
      }

      $address = $entity->get($fieldName)->first();
      if ($address === NULL) {
        continue;
      }

      $locality = trim((string) ($address->locality ?? ''));
      if ($locality !== '') {
        return $locality;
      }
    }

    foreach (['event_address_gsearch', 'field_event_address_gsearch'] as $fieldName) {
      if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
        continue;
      }

      $address = $entity->get($fieldName)->first();
      if ($address === NULL) {
        continue;
      }

      $postalName = trim((string) ($address->postal_name ?? ''));
      if ($postalName !== '') {
        return $postalName;
      }
    }

    return NULL;
  }

}
