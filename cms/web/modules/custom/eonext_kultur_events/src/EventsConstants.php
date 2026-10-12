<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_events;

/**
 * Constants for the kultur events listing.
 */
final class EventsConstants {

  public const VIEW_ID = 'events';

  public const VIEW_DISPLAY = 'all';

  public const INDEX_ID = 'events';

  public const FACET_SOURCE_ID = 'search_api:views_page__events__all';

  public const CARD_VIEW_MODE = 'card';

  /**
   * Site content languages used on the public Kulturnat calendar.
   *
   * Event instances are indexed once (per langcode on the entity). The listing
   * matches any of these so /kl and /da show the same events; inherited fields
   * still render in the active interface language. Other languages (e.g. en) stay
   * excluded to avoid duplicate cards.
   */
  public const CALENDAR_SEARCH_LANGUAGES = ['kl', 'da'];

}
