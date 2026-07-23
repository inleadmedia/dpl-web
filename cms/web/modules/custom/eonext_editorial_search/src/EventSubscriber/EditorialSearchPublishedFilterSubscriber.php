<?php

declare(strict_types=1);

namespace Drupal\eonext_editorial_search\EventSubscriber;

use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Aligns editorial facet counts with the view's published-only filter.
 *
 * The editorial search view filters on status = published, but facet count
 * queries reuse the page display search ID without inheriting view filters.
 */
final class EditorialSearchPublishedFilterSubscriber implements EventSubscriberInterface {

  /**
   * Search ID for the editorial search page display query.
   */
  private const EDITORIAL_PAGE_SEARCH_ID = 'views_page:editorial_search__page';

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    if (!class_exists(QueryPreExecuteEvent::class, TRUE)) {
      return [];
    }

    return [
      SearchApiEvents::QUERY_PRE_EXECUTE => ['onQueryPreExecute', 15],
    ];
  }

  /**
   * Restricts editorial search and facet queries to published content only.
   */
  public function onQueryPreExecute(QueryPreExecuteEvent $event): void {
    if ($event->getQuery()->getSearchId() !== self::EDITORIAL_PAGE_SEARCH_ID) {
      return;
    }

    $index = $event->getQuery()->getIndex();
    if ($index->getField('status') === NULL) {
      return;
    }

    $event->getQuery()->addCondition('status', TRUE);
  }

}
