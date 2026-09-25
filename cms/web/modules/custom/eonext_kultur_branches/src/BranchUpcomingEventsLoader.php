<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_branches;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\eonext_kultur_events\EventsConstants;
use Drupal\eonext_kultur_main\HeroSlideBuilder;
use Drupal\recurring_events\Entity\EventInstance;

/**
 * Loads upcoming events for a branch and builds card render arrays.
 */
final class BranchUpcomingEventsLoader {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly HeroSlideBuilder $heroSlideBuilder,
  ) {}

  /**
   * Builds compact event rows for foreninger branch cards.
   *
   * @return array{
   *   items: list<array{title: string, url: string, date: string}>,
   *   calendar_url: \Drupal\Core\Url,
   *   cache_tags: list<string>
   * }
   */
  public function buildSummaryForBranch(int $branch_nid, int $limit = 3): array {
    $instances = $this->loadUpcomingInstances($branch_nid, $limit);
    $items = [];
    $cache_tags = [];

    foreach ($instances as $instance) {
      $slide = $this->heroSlideBuilder->build($instance);
      if ($slide === NULL) {
        continue;
      }

      $items[] = [
        'title' => (string) ($slide['title'] ?? $instance->label()),
        'url' => (string) ($slide['url'] ?? $instance->toUrl()->toString()),
        'date' => (string) ($slide['date_display'] ?? ''),
      ];
      $cache_tags = array_merge($cache_tags, $instance->getCacheTags());
    }

    return [
      'items' => $items,
      'calendar_url' => $this->buildCalendarUrl($branch_nid),
      'cache_tags' => array_values(array_unique($cache_tags)),
    ];
  }

  /**
   * Builds event grid card render arrays for a branch detail page.
   *
   * @return array{
   *   events: list<array<string, mixed>>,
   *   calendar_url: \Drupal\Core\Url,
   *   cache_tags: list<string>
   * }
   */
  public function buildEventCardsForBranch(
    int $branch_nid,
    int $limit = ForeningerConstants::BRANCH_PAGE_EVENTS_LIMIT,
  ): array {
    $instances = $this->loadUpcomingInstances($branch_nid, $limit);
    $events = [];
    $cache_tags = [];

    foreach ($instances as $instance) {
      $slide = $this->heroSlideBuilder->build($instance);
      $events[] = [
        '#theme' => 'eventinstance__card__eonext_kultur_grid',
        '#eventinstance' => $instance,
        '#view_mode' => EventsConstants::CARD_VIEW_MODE,
        '#hero_slide' => $slide,
        '#cache' => [
          'tags' => $instance->getCacheTags(),
          'contexts' => ['languages:language_interface'],
        ],
      ];
      $cache_tags = array_merge($cache_tags, $instance->getCacheTags());
    }

    return [
      'events' => $events,
      'calendar_url' => $this->buildCalendarUrl($branch_nid),
      'cache_tags' => array_values(array_unique($cache_tags)),
    ];
  }

  /**
   * @return \Drupal\recurring_events\Entity\EventInstance[]
   */
  private function loadUpcomingInstances(int $branch_nid, int $limit): array {
    $date = new DrupalDateTime('now');
    $date->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
    $formatted = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    $ids = array_map('intval', array_values($this->entityTypeManager->getStorage('eventinstance')->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', TRUE)
      ->condition('field_branch.target_id', $branch_nid)
      ->condition('date.end_value', $formatted, '>=')
      ->sort('date.value', 'ASC')
      ->range(0, $limit)
      ->execute()));

    if ($ids === []) {
      return [];
    }

    $loaded = EventInstance::loadMultiple($ids);
    $ordered = [];
    foreach ($ids as $id) {
      if (isset($loaded[$id])) {
        $ordered[] = $loaded[$id];
      }
    }

    return $ordered;
  }

  /**
   * @return \Drupal\Core\Url
   *   Filtered events overview URL for a branch.
   */
  public function buildCalendarUrl(int $branch_nid): Url {
    return Url::fromUserInput('/arrangementer', [
      'query' => [
        'f' => ['branch:' . $branch_nid],
      ],
    ]);
  }

}
