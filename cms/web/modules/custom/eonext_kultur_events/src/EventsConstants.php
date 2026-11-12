<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_events;

/**
 * Constants for the kultur events listing.
 */
final class EventsConstants {

  public const VIEW_ID = 'events';

  public const VIEW_DISPLAY = 'all';

  /**
   * Paragraph bundle that embeds the events listing view.
   */
  public const PARAGRAPH_BUNDLE = 'eonext_kultur_events_listing';

  /**
   * Public path of the calendar page.
   */
  public const LISTING_ALIAS = '/arrangementer';

  public const INDEX_ID = 'events';

  public const FACET_SOURCE_ID = 'search_api:views_page__events__all';

  public const CARD_VIEW_MODE = 'card';

  /**
   * Kultur calendar facet machine names and admin labels (filter row titles).
   *
   * @var array<string, string>
   */
  public const FACET_LABELS = [
    'event_is_free' => 'Free events',
    'event_screen_names' => 'Target group',
    'event_categories' => 'Category',
    'event_tags' => 'Tags',
  ];

}
