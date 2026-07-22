<?php

declare(strict_types=1);

namespace Drupal\eonext_editorial_search\Plugin\search_api\processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\dpl_event\Entity\EventInstance;
use Drupal\dpl_event\ReoccurringDateFormatter;
use Drupal\recurring_events\Entity\EventSeries;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Indexes whether an event series has a free upcoming instance.
 */
#[SearchApiProcessor(
  id: 'editorial_event_is_free',
  label: new TranslatableMarkup('Editorial event is free'),
  description: new TranslatableMarkup('Indexes a boolean when the next upcoming event instance is free to attend.'),
  stages: [
    'preprocess_index' => 0,
  ],
)]
final class EditorialEventIsFreeProcessor extends ProcessorPluginBase {

  public const FIELD_ID = 'editorial_is_free';

  protected ReoccurringDateFormatter $reoccurringDateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->reoccurringDateFormatter = $container->get('dpl_event.reoccurring_date_formatter');
    return $processor;
  }

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index): bool {
    foreach ($index->getDatasources() as $datasource) {
      if ($datasource->getEntityTypeId() === 'eventseries') {
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
      if (!($entity instanceof EventSeries)) {
        continue;
      }

      $field = $item->getField(self::FIELD_ID, FALSE);
      if (!$field instanceof FieldInterface) {
        continue;
      }

      $field->setValues([]);
      if ($this->isEventSeriesFree($entity)) {
        $field->addValue(1);
      }
    }
  }

  /**
   * Whether the next upcoming instance in a series is free to attend.
   */
  private function isEventSeriesFree(EventSeries $eventSeries): bool {
    $details = $this->reoccurringDateFormatter->getUpcomingEventDetails($eventSeries);
    if ($details === NULL) {
      return FALSE;
    }

    $upcoming_ids = $details['upcoming_ids'] ?? [];
    $upcoming_id = reset($upcoming_ids);
    if (!$upcoming_id) {
      return FALSE;
    }

    $instance = EventInstance::load($upcoming_id);
    return $instance instanceof EventInstance && $instance->isFreeToAttend();
  }

}
