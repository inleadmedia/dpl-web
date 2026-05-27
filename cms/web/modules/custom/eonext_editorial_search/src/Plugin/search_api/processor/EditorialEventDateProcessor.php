<?php

declare(strict_types=1);

namespace Drupal\eonext_editorial_search\Plugin\search_api\processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\dpl_event\ReoccurringDateFormatter;
use Drupal\recurring_events\Entity\EventSeries;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Indexes event dates and upcoming status for editorial search sorting/filtering.
 */
#[SearchApiProcessor(
  id: 'editorial_event_date',
  label: new TranslatableMarkup('Editorial event date'),
  description: new TranslatableMarkup('Indexes upcoming status and the next or most recent event date for sorting.'),
  stages: [
    'preprocess_index' => 0,
  ],
)]
final class EditorialEventDateProcessor extends ProcessorPluginBase {

  /**
   * Search API field ID for whether an event series has upcoming instances.
   */
  public const FIELD_HAS_UPCOMING = 'event_has_upcoming';

  /**
   * The recurring event date formatter.
   */
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
      $this->setEventIndexFields($item);
    }
  }

  /**
   * Sets event_has_upcoming and sort_date on an indexed event series item.
   */
  private function setEventIndexFields(ItemInterface $item): void {
    $entity = $item->getOriginalObject()->getValue();
    if (!($entity instanceof EventSeries)) {
      return;
    }

    $upcoming_event = $this->reoccurringDateFormatter->getUpcomingEventDetails($entity);
    $has_upcoming = $upcoming_event !== NULL;

    $has_upcoming_field = $item->getField(self::FIELD_HAS_UPCOMING, FALSE);
    if ($has_upcoming_field instanceof FieldInterface) {
      $has_upcoming_field->setValues([]);
      $has_upcoming_field->addValue($has_upcoming);
    }

    $sort_timestamp = NULL;
    if ($upcoming_event !== NULL) {
      $sort_timestamp = $upcoming_event['start']->getTimestamp();
    }
    else {
      $past_event = $this->reoccurringDateFormatter->getPastEventDetails($entity);
      if ($past_event !== NULL) {
        $sort_timestamp = $past_event['end']->getTimestamp();
      }
    }

    if ($sort_timestamp === NULL) {
      return;
    }

    $sort_date_field = $item->getField('sort_date', FALSE);
    if ($sort_date_field instanceof FieldInterface) {
      $sort_date_field->setValues([]);
      $sort_date_field->addValue($sort_timestamp);
    }
  }

}
