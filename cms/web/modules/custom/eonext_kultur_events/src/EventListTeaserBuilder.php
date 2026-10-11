<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_events;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\dpl_event\PriceFormatter;
use Drupal\dpl_event\ReoccurringDateFormatter;
use Drupal\recurring_events\Entity\EventInstance;
use Drupal\recurring_events\Entity\EventSeries;

/**
 * Localized dates and price labels for event list teasers.
 */
final class EventListTeaserBuilder {

  use StringTranslationTrait;

  /**
   * Kalaallisut month names (PHP Intl has no kl locale for dates).
   *
   * @var string[]
   */
  private const KALAALLISUT_MONTHS = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Marts',
    4 => 'April',
    5 => 'Maaji',
    6 => 'Juuni',
    7 => 'Juuli',
    8 => 'Aggust',
    9 => 'Septembari',
    10 => 'Oktober',
    11 => 'Novembari',
    12 => 'Decembari',
  ];

  public function __construct(
    private readonly ReoccurringDateFormatter $recurringDateFormatter,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly LanguageManagerInterface $languageManager,
    private readonly PriceFormatter $priceFormatter,
    TranslationInterface $translation,
  ) {
    $this->stringTranslation = $translation;
  }

  /**
   * Builds a localized date string for an event series list teaser.
   */
  public function formatSeriesListDate(EventSeries $series): ?string {
    $details = $this->recurringDateFormatter->getUpcomingEventDetails($series);
    if ($details === NULL) {
      return (string) $this->t('Expired', [], ['context' => 'eonext_kultur_events']);
    }

    return $this->formatDateRange($details['start'], $details['end']);
  }

  /**
   * Builds a localized date string for an event instance list teaser.
   */
  public function formatInstanceListDate(EventInstance $instance): ?string {
    if ($instance->get('date')->isEmpty()) {
      return NULL;
    }

    $date_field = $instance->get('date')->first();
    $start = $date_field->start_date ?? NULL;
    $end = $date_field->end_date ?? NULL;

    if (!$start instanceof DrupalDateTime) {
      return NULL;
    }

    return $this->formatDateRange(
      $start,
      $end instanceof DrupalDateTime ? $end : NULL,
    );
  }

  /**
   * Maps DPL "Free" price output to kultur labels (da/kl).
   */
  public function localizePriceDisplay(?string $formatted): ?string {
    if ($formatted === NULL || $formatted === '') {
      return $formatted;
    }

    $free_label = (string) $this->t('Free');
    if ($formatted === $free_label || $formatted === (string) $this->priceFormatter->formatPriceRange([0])) {
      return (string) $this->t('Gratis', [], ['context' => 'eonext_kultur_events']);
    }

    return $formatted;
  }

  /**
   * Formats a date range for list teasers (aligned with event detail pages).
   */
  private function formatDateRange(DrupalDateTime $start, ?DrupalDateTime $end = NULL): string {
    if (!$end instanceof DrupalDateTime) {
      return $this->formatCustom($start, 'j. F Y');
    }

    if ($this->formatCustom($start, 'Y-m-d') === $this->formatCustom($end, 'Y-m-d')) {
      return $this->formatCustom($start, 'j. F Y');
    }

    $startYear = $this->formatCustom($start, 'Y');
    $endYear = $this->formatCustom($end, 'Y');
    if ($startYear === $endYear && $this->formatCustom($start, 'n') === $this->formatCustom($end, 'n')) {
      return $this->formatCustom($start, 'j') . '. - ' . $this->formatCustom($end, 'j') . '. '
        . $this->formatCustom($start, 'F Y');
    }

    if ($startYear === $endYear) {
      return $this->formatCustom($start, 'j. F') . ' - ' . $this->formatCustom($end, 'j. F Y');
    }

    return $this->formatCustom($start, 'j. F Y') . ' - ' . $this->formatCustom($end, 'j. F Y');
  }

  /**
   * Formats a datetime with interface language (kl month names when needed).
   */
  private function formatCustom(DrupalDateTime $datetime, string $format): string {
    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    if ($langcode !== 'kl' || !str_contains($format, 'F')) {
      return $this->dateFormatter->format($datetime->getTimestamp(), 'custom', $format, NULL, $langcode);
    }

    $formatted = $this->dateFormatter->format($datetime->getTimestamp(), 'custom', $format, NULL, 'en');
    $month = self::KALAALLISUT_MONTHS[(int) $datetime->format('n')] ?? $datetime->format('F');

    return preg_replace('/\b(January|February|March|April|May|June|July|August|September|October|November|December)\b/', $month, $formatted) ?? $formatted;
  }

}
