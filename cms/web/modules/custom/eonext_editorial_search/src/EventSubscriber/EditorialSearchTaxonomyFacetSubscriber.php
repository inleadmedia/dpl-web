<?php

namespace Drupal\eonext_editorial_search\EventSubscriber;

use Drupal\eonext_editorial_search\EonextEditorialSearchTaxonomyContext;
use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Aligns editorial facet queries with taxonomy term landing page filters.
 *
 * Facet blocks use the editorial search "page" display as their source. On tag
 * and category term pages the results view runs the "term_page" display with
 * contextual filters, so facet counts must receive the same constraints.
 */
final class EditorialSearchTaxonomyFacetSubscriber implements EventSubscriberInterface {

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
      SearchApiEvents::QUERY_PRE_EXECUTE => ['onQueryPreExecute', 20],
    ];
  }

  /**
   * Applies taxonomy term filters to facet queries on term landing pages.
   */
  public function onQueryPreExecute(QueryPreExecuteEvent $event): void {
    if ($event->getQuery()->getSearchId() !== self::EDITORIAL_PAGE_SEARCH_ID) {
      return;
    }

    $context = EonextEditorialSearchTaxonomyContext::fromRoute();
    if ($context === NULL) {
      return;
    }

    $query = $event->getQuery();
    $index = $query->getIndex();

    if ($index->getField($context->field) !== NULL) {
      $query->addCondition($context->field, (string) $context->tid);
    }

    if ($index->getField('field_automatic_list_hide') !== NULL) {
      $query->addCondition('field_automatic_list_hide', '1', '<>');
    }
  }

}
