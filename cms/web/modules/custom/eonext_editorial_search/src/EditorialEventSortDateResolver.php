<?php

declare(strict_types=1);

namespace Drupal\eonext_editorial_search;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\recurring_events\Entity\EventInstance;
use Drupal\recurring_events\Entity\EventSeries;

/**
 * Resolves event series sort dates for editorial search indexing.
 */
final class EditorialEventSortDateResolver {

  /**
   * Constructs an EditorialEventSortDateResolver.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Retrieves event instance dates for search indexing sort fields.
   *
   * Includes all event instances regardless of publish status. The indexed
   * EventSeries is already published, and editorial search needs a stable sort
   * date even when generated instances were unpublished after the event ended.
   *
   * @return null|array{'start': \Drupal\Core\Datetime\DrupalDateTime, 'end': \Drupal\Core\Datetime\DrupalDateTime}
   *   The next upcoming or most recent past instance dates, or NULL.
   */
  public function resolveSortDateDetails(EventSeries $event_series): array|null {
    $formatted_now = $this->getCurrentStorageDateTime();
    $storage = $this->entityTypeManager->getStorage('eventinstance');

    $upcoming_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('eventseries_id', $event_series->id())
      ->condition('date.end_value', $formatted_now, '>=')
      ->sort('date.value', 'ASC')
      ->range(0, 1)
      ->execute();
    if ($upcoming_ids !== []) {
      $upcoming_details = $this->loadEventInstanceDateDetails((int) reset($upcoming_ids));
      if ($upcoming_details !== NULL) {
        return $upcoming_details;
      }
    }

    $past_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('eventseries_id', $event_series->id())
      ->condition('date.end_value', $formatted_now, '<')
      ->sort('date.value', 'DESC')
      ->range(0, 1)
      ->execute();
    if ($past_ids !== []) {
      $past_details = $this->loadEventInstanceDateDetails((int) reset($past_ids));
      if ($past_details !== NULL) {
        return $past_details;
      }
    }

    return $this->getSeriesScheduleSortDateDetails($event_series);
  }

  /**
   * Returns the current datetime formatted for event instance storage queries.
   */
  private function getCurrentStorageDateTime(): string {
    $date = new DrupalDateTime();
    $date->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));

    return $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
  }

  /**
   * Loads start/end dates from an event instance.
   *
   * @return null|array{'start': \Drupal\Core\Datetime\DrupalDateTime, 'end': \Drupal\Core\Datetime\DrupalDateTime}
   *   The instance date range, or NULL when missing.
   */
  private function loadEventInstanceDateDetails(int $event_instance_id): array|null {
    $event_instance = EventInstance::load($event_instance_id);

    if (!($event_instance instanceof EventInstance) || $event_instance->get('date')->isEmpty()) {
      return NULL;
    }

    /** @var \Drupal\datetime_range\Plugin\Field\FieldType\DateRangeItem $event_instance_date */
    $event_instance_date = $event_instance->get('date')->first();

    $start_date = $event_instance_date->get('start_date')->getValue();
    $end_date = $event_instance_date->get('end_date')->getValue();

    if (!($start_date instanceof DrupalDateTime)) {
      return NULL;
    }

    return [
      'start' => $start_date,
      'end' => $end_date instanceof DrupalDateTime ? $end_date : NULL,
    ];
  }

  /**
   * Falls back to the latest configured series schedule date for sorting.
   *
   * @return null|array{'start': \Drupal\Core\Datetime\DrupalDateTime, 'end': \Drupal\Core\Datetime\DrupalDateTime}
   *   The latest schedule date range, or NULL.
   */
  private function getSeriesScheduleSortDateDetails(EventSeries $event_series): array|null {
    $schedule_dates = array_values(array_filter(
      $event_series->getCustomDates(),
      static fn (array $date): bool => ($date['start_date'] ?? NULL) instanceof DrupalDateTime,
    ));
    if ($schedule_dates === []) {
      return NULL;
    }

    usort($schedule_dates, static function (array $first, array $second): int {
      return $second['start_date']->getTimestamp() <=> $first['start_date']->getTimestamp();
    });

    $latest = reset($schedule_dates);
    if ($latest === FALSE) {
      return NULL;
    }

    $end = $latest['end_date'] ?? NULL;

    return [
      'start' => $latest['start_date'],
      'end' => $end instanceof DrupalDateTime ? $end : NULL,
    ];
  }

}
