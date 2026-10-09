<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_events;

use Drupal\Component\Utility\Html;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\dpl_event\PriceFormatter;
use Drupal\dpl_event\ReoccurringDateFormatter;
use Drupal\drupal_typed\DrupalTyped;
use Drupal\media\MediaInterface;
use Drupal\recurring_events\Entity\EventInstance;
use Drupal\recurring_events\Entity\EventSeries;

/**
 * Builds render variables for kultur event detail pages.
 */
final class EventDetailBuilder {

  use StringTranslationTrait;

  /**
   *
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    TranslationInterface $translation,
    private readonly ReoccurringDateFormatter $recurringDateFormatter,
  ) {
    $this->stringTranslation = $translation;
  }

  /**
   * Build detail page variables from an event entity.
   *
   * @return array<string, mixed>|null
   *   Detail variables or NULL when the entity cannot be rendered.
   */
  public function build(EntityInterface $entity): ?array {
    return match ($entity->getEntityTypeId()) {
      'eventseries' => $entity instanceof EventSeries ? $this->fromEventSeries($entity) : NULL,
      'eventinstance' => $entity instanceof EventInstance ? $this->fromEventInstance($entity) : NULL,
      default => NULL,
    };
  }

  /**
   * @return array<string, mixed>
   */
  private function fromEventInstance(EventInstance $eventInstance): array {
    $start = NULL;
    $end = NULL;

    if ($eventInstance->hasField('date') && !$eventInstance->get('date')->isEmpty()) {
      $dateField = $eventInstance->get('date')->first();
      $start = $dateField->start_date ?? NULL;
      $end = $dateField->end_date ?? NULL;
    }

    $allDay = $eventInstance->hasField('event_all_day')
      && !empty($eventInstance->get('event_all_day')->getString());

    return $this->buildDetail(
      $eventInstance,
      $eventInstance->label(),
      $start,
      $end,
      $allDay,
      ['event_teaser_text', 'field_teaser_text'],
      ['event_image', 'field_event_image', 'event_teaser_image', 'field_teaser_image'],
      ['event_link', 'field_event_link'],
      ['event_place', 'field_event_place'],
      ['event_location', 'field_event_location'],
      ['branch', 'field_branch'],
      ['event_ticket_categories', 'field_ticket_categories'],
    );
  }

  /**
   * @return array<string, mixed>
   */
  private function fromEventSeries(EventSeries $eventSeries): array {
    $start = NULL;
    $end = NULL;
    $allDay = FALSE;

    $upcoming = $this->recurringDateFormatter->getUpcomingEventDetails($eventSeries);
    if (is_array($upcoming)) {
      $start = $upcoming['start'] ?? NULL;
      $end = $upcoming['end'] ?? NULL;

      $upcomingIds = $upcoming['upcoming_ids'] ?? [];
      if (count($upcomingIds) > 1) {
        $lastInstance = $this->entityTypeManager->getStorage('eventinstance')->load(end($upcomingIds));
        if ($lastInstance instanceof EventInstance && !$lastInstance->get('date')->isEmpty()) {
          $lastDate = $lastInstance->get('date')->first();
          $lastEnd = $lastDate->end_date ?? NULL;
          if ($lastEnd instanceof DrupalDateTime) {
            $end = $lastEnd;
          }
        }
      }
    }

    $allDay = $this->recurringDateFormatter->isAllDay($eventSeries);

    return $this->buildDetail(
      $eventSeries,
      $eventSeries->label(),
      $start,
      $end,
      $allDay,
      ['field_teaser_text'],
      ['field_event_image', 'field_teaser_image'],
      ['field_event_link'],
      ['field_event_place'],
      ['field_event_location'],
      ['field_branch'],
      ['field_ticket_categories'],
    );
  }

  /**
   * @param string[] $taglineFields
   * @param string[] $imageFields
   * @param string[] $linkFields
   * @param string[] $placeFields
   * @param string[] $locationFields
   * @param string[] $branchFields
   * @param string[] $ticketCategoryFields
   *
   * @return array<string, mixed>
   */
  private function buildDetail(
    EntityInterface $entity,
    string $title,
    ?DrupalDateTime $start,
    ?DrupalDateTime $end,
    bool $allDay,
    array $taglineFields,
    array $imageFields,
    array $linkFields,
    array $placeFields,
    array $locationFields,
    array $branchFields,
    array $ticketCategoryFields,
  ): array {
    $image = $this->buildBannerImage($entity, $imageFields);

    return [
      'title' => $title,
      'tagline' => $this->getTagline($entity, ...$taglineFields),
      'date_display' => $start instanceof DrupalDateTime
        ? $this->formatDetailDate($start, $end instanceof DrupalDateTime ? $end : NULL)
        : NULL,
      'time_display' => $this->formatDetailTime($start, $end, $allDay),
      'datetime_attribute' => $start instanceof DrupalDateTime
        ? $this->recurringDateFormatter->formatDate($start, DATE_ATOM)
        : NULL,
      'price_display' => $this->formatTicketPrice($entity, $ticketCategoryFields),
      'location_display' => $this->resolveLocationDisplay($entity, $placeFields, $locationFields, $branchFields),
      'ticket_url' => $this->getTicketUrl($entity, $linkFields),
      'image' => $image,
      'banner_image' => $image,
      'back_url' => Url::fromUserInput('/arrangementer')->toString(),
      'calendar_label' => (string) $this->t('Kalender', [], ['context' => 'eonext_kultur_events']),
    ];
  }

  /**
   * @param string[] $fieldNames
   */
  private function getTagline(EntityInterface $entity, string ...$fieldNames): ?string {
    foreach ($fieldNames as $fieldName) {
      if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
        continue;
      }

      $value = $entity->get($fieldName)->value ?? NULL;
      if (!is_string($value) || trim($value) === '') {
        continue;
      }

      $text = Html::decodeEntities(strip_tags($value));
      $text = str_replace("\xc2\xa0", ' ', $text);
      $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';
      if ($text !== '') {
        return $text;
      }
    }

    return NULL;
  }

  /**
   * @param string[] $fieldNames
   *
   * @return array<string, mixed>|null
   */
  private function buildBannerImage(EntityInterface $entity, array $fieldNames): ?array {
    foreach ($fieldNames as $fieldName) {
      if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
        continue;
      }

      $media = $entity->get($fieldName)->entity;
      if ($media instanceof MediaInterface) {
        return $this->entityTypeManager->getViewBuilder('media')->view($media, 'banner');
      }
    }

    return NULL;
  }

  /**
   * @param string[] $linkFields
   */
  private function getTicketUrl(EntityInterface $entity, array $linkFields): ?string {
    foreach ($linkFields as $fieldName) {
      if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
        continue;
      }

      $uri = $entity->get($fieldName)->uri ?? NULL;
      if (is_string($uri) && $uri !== '') {
        return $uri;
      }
    }

    return NULL;
  }

  /**
   * @param string[] $placeFields
   * @param string[] $locationFields
   * @param string[] $branchFields
   */
  private function getLocationLabel(
    EntityInterface $entity,
    array $placeFields,
    array $locationFields,
    array $branchFields,
  ): ?string {
    foreach ($placeFields as $fieldName) {
      if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
        continue;
      }

      $value = trim((string) $entity->get($fieldName)->value);
      if ($value !== '') {
        return $value;
      }
    }

    foreach ($locationFields as $fieldName) {
      if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
        continue;
      }

      $value = trim((string) $entity->get($fieldName)->value);
      if ($value !== '') {
        return $value;
      }
    }

    foreach ($branchFields as $fieldName) {
      if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
        continue;
      }

      $branch = $entity->get($fieldName)->entity;
      if ($branch !== NULL) {
        return $branch->label();
      }
    }

    return NULL;
  }

  /**
   * Resolves a location line for the detail meta block.
   *
   * @param string[] $placeFields
   * @param string[] $locationFields
   * @param string[] $branchFields
   */
  private function resolveLocationDisplay(
    EntityInterface $entity,
    array $placeFields,
    array $locationFields,
    array $branchFields,
  ): ?string {
    $label = $this->getLocationLabel($entity, $placeFields, $locationFields, $branchFields);
    if ($label !== NULL) {
      return $label;
    }

    foreach (['field_event_address', 'event_address'] as $fieldName) {
      $formatted = $this->formatAddressField($entity, $fieldName);
      if ($formatted !== NULL) {
        return $formatted;
      }
    }

    foreach (['field_event_address_gsearch', 'event_address_gsearch'] as $fieldName) {
      $formatted = $this->getPlainTextField($entity, $fieldName);
      if ($formatted !== NULL) {
        return $formatted;
      }
    }

    if ($entity instanceof EventInstance) {
      $series = $entity->get('eventseries_id')->entity;
      if ($series instanceof EventSeries) {
        return $this->resolveLocationDisplay($series, ['field_event_place'], ['field_event_location'], ['field_branch']);
      }
    }

    return NULL;
  }

  /**
   * Builds a single-line label from an address field.
   */
  private function formatAddressField(EntityInterface $entity, string $fieldName): ?string {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return NULL;
    }

    $address = $entity->get($fieldName)->first();
    if ($address === NULL) {
      return NULL;
    }

    $parts = array_filter([
      trim((string) ($address->organization ?? '')),
      trim((string) ($address->address_line1 ?? '')),
      trim((string) ($address->postal_code ?? '')),
      trim((string) ($address->locality ?? '')),
    ]);

    if ($parts === []) {
      return NULL;
    }

    return implode(', ', $parts);
  }

  /**
   * Returns trimmed string field value when present.
   */
  private function getPlainTextField(EntityInterface $entity, string $fieldName): ?string {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return NULL;
    }

    $value = trim((string) ($entity->get($fieldName)->value ?? ''));
    return $value !== '' ? $value : NULL;
  }

  /**
   * @param string[] $ticketCategoryFields
   */
  private function formatTicketPrice(EntityInterface $entity, array $ticketCategoryFields): string {
    $prices = [];

    foreach ($ticketCategoryFields as $fieldName) {
      if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
        continue;
      }

      foreach ($entity->get($fieldName)->referencedEntities() as $category) {
        if ($category->hasField('field_ticket_category_price')
          && !$category->get('field_ticket_category_price')->isEmpty()) {
          $prices[] = (float) $category->get('field_ticket_category_price')->value;
        }
      }
    }

    $priceFormatter = DrupalTyped::service(PriceFormatter::class, 'dpl_event.price_formatter');
    $formatted = $priceFormatter->formatPriceRange($prices);

    return $this->formatKulturPriceLabel($formatted, $prices);
  }

  /**
   * Maps DPL price output to kultur "Kr." labelling where possible.
   *
   * @param float[]|int[] $rawPrices
   */
  private function formatKulturPriceLabel(string $formatted, array $rawPrices): string {
    if ($formatted === (string) $this->t('Free')) {
      return (string) $this->t('Gratis', [], ['context' => 'eonext_kultur_events']);
    }

    if (empty($rawPrices)) {
      return $formatted;
    }

    sort($rawPrices);
    $positivePrices = array_values(array_filter($rawPrices, static fn($price) => $price > 0));

    if (empty($positivePrices)) {
      return (string) $this->t('Gratis', [], ['context' => 'eonext_kultur_events']);
    }

    $lowest = min($positivePrices);
    $highest = max($positivePrices);
    $formatAmount = static fn(int|float $amount): string => number_format($amount, $amount == round($amount) ? 0 : 2, ',', '.');

    if ($lowest !== $highest) {
      return (string) $this->t('@low - @high Kr.', [
        '@low' => $formatAmount($lowest),
        '@high' => $formatAmount($highest),
      ], ['context' => 'eonext_kultur_events']);
    }

    return (string) $this->t('@price Kr.', [
      '@price' => $formatAmount($lowest),
    ], ['context' => 'eonext_kultur_events']);
  }

  /**
   *
   */
  private function formatDetailDate(DrupalDateTime $start, ?DrupalDateTime $end = NULL): string {
    $formatter = $this->recurringDateFormatter;

    if (!$end instanceof DrupalDateTime) {
      return $formatter->formatDate($start, 'j. F Y');
    }

    $startDay = $formatter->formatDate($start, 'Y-m-d');
    $endDay = $formatter->formatDate($end, 'Y-m-d');
    if ($startDay === $endDay) {
      return $formatter->formatDate($start, 'j. F Y');
    }

    $startYear = $formatter->formatDate($start, 'Y');
    $endYear = $formatter->formatDate($end, 'Y');
    if ($startYear === $endYear && $formatter->formatDate($start, 'n') === $formatter->formatDate($end, 'n')) {
      return $formatter->formatDate($start, 'j') . '.–' . $formatter->formatDate($end, 'j') . '. '
        . $formatter->formatDate($start, 'F Y');
    }

    if ($startYear === $endYear) {
      return $formatter->formatDate($start, 'j. F') . ' – ' . $formatter->formatDate($end, 'j. F Y');
    }

    return $formatter->formatDate($start, 'j. F Y') . ' – ' . $formatter->formatDate($end, 'j. F Y');
  }

  /**
   *
   */
  private function formatDetailTime(?DrupalDateTime $start, ?DrupalDateTime $end, bool $allDay): ?string {
    if (!$start instanceof DrupalDateTime) {
      return NULL;
    }

    if ($allDay) {
      return (string) $this->t('Hele dagen', [], ['context' => 'eonext_kultur_events']);
    }

    $formatter = $this->recurringDateFormatter;
    $startTime = $formatter->formatDate($start, 'H.i');
    $endTime = $end instanceof DrupalDateTime ? $formatter->formatDate($end, 'H.i') : NULL;

    if ($endTime) {
      return "$startTime – $endTime";
    }

    return $startTime;
  }

}
