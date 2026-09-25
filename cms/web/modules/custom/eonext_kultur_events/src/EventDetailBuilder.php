<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_events;

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
    private readonly ReoccurringDateFormatter $recurringDateFormatter,
    TranslationInterface $translation,
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

    if (!$eventInstance->get('date')->isEmpty()) {
      $dateField = $eventInstance->get('date')->first();
      $start = $dateField->start_date ?? NULL;
      $end = $dateField->end_date ?? NULL;
    }

    $allDay = $eventInstance->hasField('event_all_day')
      && !empty($eventInstance->get('event_all_day')->getString());

    $series = $eventInstance->getEventSeries();

    return $this->buildDetail(
      $eventInstance,
      $eventInstance->label(),
      $start,
      $end,
      $allDay,
      ['event_teaser_text', 'field_teaser_text', 'event_description', 'field_description'],
      ['event_image', 'field_event_image', 'event_teaser_image', 'field_teaser_image'],
      ['event_link', 'field_event_link'],
      ['event_place', 'field_event_place'],
      ['event_location', 'field_event_location'],
      ['branch', 'field_branch'],
      ['event_ticket_categories', 'field_ticket_categories'],
      $series,
    );
  }

  /**
   * @return array<string, mixed>
   */
  private function fromEventSeries(EventSeries $eventSeries): array {
    $start = NULL;
    $end = NULL;
    $allDay = $this->recurringDateFormatter->isAllDay($eventSeries);

    $upcoming = $this->recurringDateFormatter->getUpcomingEventDetails($eventSeries);
    if ($upcoming !== NULL) {
      $start = $upcoming['start'] ?? NULL;
      $end = $upcoming['end'] ?? NULL;
    }
    elseif (!$eventSeries->get('date')->isEmpty()) {
      $dateField = $eventSeries->get('date')->first();
      $start = $dateField->start_date ?? NULL;
      $end = $dateField->end_date ?? NULL;
    }

    return $this->buildDetail(
      $eventSeries,
      $eventSeries->label(),
      $start,
      $end,
      $allDay,
      ['field_teaser_text', 'field_description'],
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
    ?EntityInterface $locationFallback = NULL,
  ): array {
    $image = $this->buildBannerImage($entity, $imageFields);

    return [
      'title' => $title,
      'tagline' => $this->getTagline($entity, ...$taglineFields),
      'date_display' => $start instanceof DrupalDateTime ? $this->formatDetailDate($start) : NULL,
      'time_display' => $this->formatDetailTime($start, $end, $allDay),
      'datetime_attribute' => $start instanceof DrupalDateTime
        ? $this->recurringDateFormatter->formatDate($start, DATE_ATOM)
        : NULL,
      'price_display' => $this->formatTicketPrice($entity, $ticketCategoryFields),
      'location_display' => $this->getLocationLabel($entity, $placeFields, $locationFields, $branchFields, $locationFallback),
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

      $text = trim(strip_tags($value));
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
    ?EntityInterface $fallback = NULL,
  ): ?string {
    $place = $this->getFirstNonEmptyStringField($entity, $placeFields, $fallback);
    $sublocation = $this->getFirstNonEmptyStringField($entity, $locationFields, $fallback);
    $branchLabel = $this->getBranchLabel($entity, $branchFields)
      ?? ($fallback !== NULL ? $this->getBranchLabel($fallback, $branchFields) : NULL);

    $primary = $place ?: $branchLabel;

    if ($primary === NULL && $sublocation !== NULL) {
      return $sublocation;
    }

    if ($primary === NULL) {
      return NULL;
    }

    if ($sublocation === NULL || strcasecmp($primary, $sublocation) === 0) {
      return $primary;
    }

    return $primary . ', ' . $sublocation;
  }

  /**
   * @param string[] $fieldNames
   */
  private function getFirstNonEmptyStringField(
    EntityInterface $entity,
    array $fieldNames,
    ?EntityInterface $fallback = NULL,
  ): ?string {
    $value = $this->readFirstNonEmptyStringField($entity, $fieldNames);
    if ($value !== NULL || $fallback === NULL) {
      return $value;
    }

    return $this->readFirstNonEmptyStringField($fallback, $fieldNames);
  }

  /**
   * @param string[] $fieldNames
   */
  private function readFirstNonEmptyStringField(EntityInterface $entity, array $fieldNames): ?string {
    foreach ($fieldNames as $fieldName) {
      if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
        continue;
      }

      $value = trim((string) $entity->get($fieldName)->value);
      if ($value !== '' && !ctype_space($value)) {
        return $value;
      }
    }

    return NULL;
  }

  /**
   * @param string[] $branchFields
   */
  private function getBranchLabel(EntityInterface $entity, array $branchFields): ?string {
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
  private function formatDetailDate(DrupalDateTime $start): string {
    $day = $this->recurringDateFormatter->formatDate($start, 'j');
    $month = mb_strtolower($this->recurringDateFormatter->formatDate($start, 'F'));
    $year = $this->recurringDateFormatter->formatDate($start, 'Y');

    return $day . '. ' . $month . ' ' . $year;
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

    $startTime = $this->recurringDateFormatter->formatDate($start, 'H.i');
    $endTime = $end instanceof DrupalDateTime
      ? $this->recurringDateFormatter->formatDate($end, 'H.i')
      : NULL;

    if ($endTime) {
      return "$startTime - $endTime";
    }

    return $startTime;
  }

}
