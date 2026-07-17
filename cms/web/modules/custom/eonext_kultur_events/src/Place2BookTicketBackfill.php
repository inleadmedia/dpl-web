<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_events;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\recurring_events\Entity\EventSeries;

/**
 * Adds ticket categories to Place2Book events that are missing them.
 */
final class Place2BookTicketBackfill {

  use StringTranslationTrait;

  private const PLACE2BOOK_HOST = 'place2book.com';

  /**
   * Manual ticket overrides keyed by event series ID.
   *
   * Used when the ticket price cannot be parsed from event text fields.
   *
   * @var array<int|string, array{name: string, price: string}>
   */
  private const SERIES_TICKET_OVERRIDES = [];

  /**
   * Constructs a Place2BookTicketBackfill object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Backfills ticket categories on Place2Book event series.
   *
   * @return array
   *   Summary with updated, skipped, and messages keys.
   */
  public function run(): array {
    $summary = [
      'updated' => [],
      'skipped' => [],
      'messages' => [],
    ];

    $storage = $this->entityTypeManager->getStorage('eventseries');
    /** @var \Drupal\recurring_events\Entity\EventSeries[] $series_list */
    $series_list = $storage->loadByProperties(['status' => 1]);

    foreach ($series_list as $series) {
      $label = $series->label() ?? (string) $series->id();

      if (!$this->hasPlace2BookLink($series)) {
        continue;
      }

      if (!$series->get('field_ticket_categories')->isEmpty()) {
        $summary['skipped'][] = $label;
        $summary['messages'][] = (string) $this->t('Skipped @title: ticket categories already exist.', [
          '@title' => $label,
        ]);
        continue;
      }

      $ticket = $this->resolveTicket($series);
      if ($ticket === NULL) {
        $summary['skipped'][] = $label;
        $summary['messages'][] = (string) $this->t('Skipped @title: could not determine ticket price.', [
          '@title' => $label,
        ]);
        continue;
      }

      $paragraph = Paragraph::create([
        'type' => 'event_ticket_category',
        'field_ticket_category_name' => $ticket['name'],
        'field_ticket_category_price' => $ticket['price'],
      ]);
      $paragraph->save();

      $series->set('field_ticket_categories', [
        [
          'target_id' => $paragraph->id(),
          'target_revision_id' => $paragraph->getRevisionId(),
        ],
      ]);
      $series->save();

      $summary['updated'][] = $label;
      $summary['messages'][] = (string) $this->t('Updated @title: @name @price kr.', [
        '@title' => $label,
        '@name' => $ticket['name'],
        '@price' => $ticket['price'],
      ]);
    }

    return $summary;
  }

  /**
   * Returns TRUE when the series links to Place2Book.
   */
  private function hasPlace2BookLink(EventSeries $series): bool {
    if (!$series->hasField('field_event_link') || $series->get('field_event_link')->isEmpty()) {
      return FALSE;
    }

    $uri = (string) $series->get('field_event_link')->uri;
    return stripos($uri, self::PLACE2BOOK_HOST) !== FALSE;
  }

  /**
   * Resolves ticket name and price for a series.
   *
   * @return array|null
   *   Ticket data with name and price keys, or NULL.
   */
  private function resolveTicket(EventSeries $series): ?array {
    $override = self::SERIES_TICKET_OVERRIDES[$series->id()] ?? NULL;
    if (is_array($override)) {
      return $override;
    }

    $text = $this->collectSeriesText($series);
    return $this->parseTicketFromText($text);
  }

  /**
   * Collects searchable text from the event series fields.
   */
  private function collectSeriesText(EventSeries $series): string {
    $parts = [];

    if ($series->hasField('field_description') && !$series->get('field_description')->isEmpty()) {
      $parts[] = (string) $series->get('field_description')->value;
    }

    foreach ($series->get('field_event_paragraphs')->referencedEntities() as $paragraph) {
      if ($paragraph->hasField('field_body') && !$paragraph->get('field_body')->isEmpty()) {
        $parts[] = (string) $paragraph->get('field_body')->value;
      }
    }

    return trim(strip_tags(implode("\n", $parts)));
  }

  /**
   * Parses ticket name and price from event text.
   *
   * @return array|null
   *   Ticket data with name and price keys, or NULL.
   */
  private function parseTicketFromText(string $text): ?array {
    if ($text === '') {
      return NULL;
    }

    if (preg_match('/\b(?:billet(?:pris)?)\s*(\d+)\s*kr\b/i', $text, $matches)) {
      return [
        'name' => 'Billet',
        'price' => $this->formatPrice((int) $matches[1]),
      ];
    }

    if (preg_match('/\b(\d+)\s*kr\.?\s*(?:\+|$)/i', $text, $matches)) {
      return [
        'name' => 'Billet',
        'price' => $this->formatPrice((int) $matches[1]),
      ];
    }

    if (preg_match('/\bgratis\b/i', $text)) {
      return [
        'name' => 'Gratis',
        'price' => '0.00',
      ];
    }

    return NULL;
  }

  /**
   * Formats an integer price as a decimal string.
   */
  private function formatPrice(int $price): string {
    return number_format($price, 2, '.', '');
  }

}
