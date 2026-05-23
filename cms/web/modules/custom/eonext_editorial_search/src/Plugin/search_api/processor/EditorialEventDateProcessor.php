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
 * Excludes expired event series and indexes upcoming event dates for sorting.
 */
#[SearchApiProcessor(
  id: 'editorial_event_date',
  label: new TranslatableMarkup('Editorial event date'),
  description: new TranslatableMarkup('Excludes expired event series and indexes the next upcoming event date for sorting.'),
  stages: [
    'alter_items' => 0,
    'process' => 10,
  ],
)]
final class EditorialEventDateProcessor extends ProcessorPluginBase {

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
  public function alterIndexedItems(array &$items): void {
    foreach ($items as $item_id => $item) {
      $entity = $item->getOriginalObject()->getValue();
      if (!($entity instanceof EventSeries)) {
        continue;
      }

      if ($this->reoccurringDateFormatter->getUpcomingEventDetails($entity) === NULL) {
        unset($items[$item_id]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item): void {
    $entity = $item->getOriginalObject()->getValue();
    if (!($entity instanceof EventSeries)) {
      return;
    }

    $upcoming_event = $this->reoccurringDateFormatter->getUpcomingEventDetails($entity);
    if ($upcoming_event === NULL) {
      return;
    }

    $sort_date_field = $this->getItemField($item, 'sort_date');
    if ($sort_date_field instanceof FieldInterface) {
      $sort_date_field->setValues([]);
      $sort_date_field->addValue($upcoming_event['start']->getTimestamp());
    }
  }

  /**
   * Loads a field from an indexed item by identifier.
   */
  private function getItemField(ItemInterface $item, string $field_id): ?FieldInterface {
    foreach ($item->getFields() as $field) {
      if ($field->getFieldIdentifier() === $field_id) {
        return $field;
      }
    }

    return NULL;
  }

}
