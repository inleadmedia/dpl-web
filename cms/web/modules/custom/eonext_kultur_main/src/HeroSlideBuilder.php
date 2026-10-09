<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_main;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\dpl_event\ReoccurringDateFormatter;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\recurring_events\Entity\EventInstance;
use Drupal\recurring_events\Entity\EventSeries;
use Drupal\taxonomy\TermInterface;

/**
 * Builds render variables for hero carousel slides.
 */
final class HeroSlideBuilder {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    TranslationInterface $translation,
    private readonly ReoccurringDateFormatter $recurringDateFormatter,
  ) {
    $this->stringTranslation = $translation;
  }

  /**
   * Build hero slide variables from a referenced entity.
   *
   * @return array<string, mixed>|null
   *   Slide variables or NULL when the entity cannot be rendered.
   */
  public function build(EntityInterface $entity): ?array {
    return match ($entity->getEntityTypeId()) {
      'eventseries' => $entity instanceof EventSeries ? $this->fromEventSeries($entity) : NULL,
      'eventinstance' => $entity instanceof EventInstance ? $this->fromEventInstance($entity) : NULL,
      'node' => $entity instanceof NodeInterface ? $this->fromNode($entity) : NULL,
      default => NULL,
    };
  }

  /**
   * @return array<string, mixed>
   */
  private function fromEventSeries(EventSeries $eventSeries): array {
    $details = $this->recurringDateFormatter->getUpcomingEventDetails($eventSeries);
    $start = $details['start'] ?? NULL;
    $end = $details['end'] ?? NULL;

    return [
      'title' => $eventSeries->label(),
      'category' => $this->getCategoryLabel($eventSeries),
      'tagline' => $this->getTagline($eventSeries, 'field_teaser_text'),
      'place' => $this->getPlaceLabel($eventSeries),
      'date_display' => ($start instanceof DrupalDateTime)
        ? $this->formatHeroDate($start, $end instanceof DrupalDateTime ? $end : NULL, $this->recurringDateFormatter->isAllDay($eventSeries))
        : NULL,
      'url' => $eventSeries->toUrl()->toString(),
      'image' => $this->buildBannerImage($eventSeries, ['field_event_image', 'field_teaser_image']),
    ];
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

    return [
      'title' => $eventInstance->label(),
      'category' => $this->getCategoryLabel($eventInstance),
      'tagline' => $this->getTagline($eventInstance, 'field_teaser_text', 'event_teaser_text'),
      'place' => $this->getPlaceLabel($eventInstance),
      'date_display' => ($start instanceof DrupalDateTime)
        ? $this->formatHeroDate($start, $end instanceof DrupalDateTime ? $end : NULL, $allDay)
        : NULL,
      'url' => Url::fromRoute('entity.eventinstance.canonical', [
        'eventinstance' => $eventInstance->id(),
      ])->toString(),
      'image' => $this->buildBannerImage($eventInstance, ['event_image', 'field_event_image', 'event_teaser_image', 'field_teaser_image']),
    ];
  }

  /**
   * @return array<string, mixed>
   */
  private function fromNode(NodeInterface $node): array {
    return [
      'title' => $node->label(),
      'category' => $this->getCategoryLabel($node),
      'tagline' => $this->getTagline($node, 'field_teaser_text', 'body'),
      'date_display' => NULL,
      'url' => $node->toUrl()->toString(),
      'image' => $this->buildBannerImage($node, ['field_teaser_image']),
    ];
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The content entity.
   * @param string[] $fieldNames
   *   Image field candidates in priority order.
   *
   * @return array<string, mixed>|null
   *   A media render array.
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
   * Resolve the primary category label for a hero slide.
   */
  private function getCategoryLabel(EntityInterface $entity): ?string {
    foreach (['event_categories', 'field_categories'] as $fieldName) {
      if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
        continue;
      }

      foreach ($entity->get($fieldName)->referencedEntities() as $term) {
        if ($term instanceof TermInterface) {
          return $term->label();
        }
      }
    }

    if (in_array($entity->getEntityTypeId(), ['eventseries', 'eventinstance'], TRUE)) {
      return (string) $this->t('Event');
    }

    if ($entity instanceof NodeInterface && $entity->type->entity !== NULL) {
      return $entity->type->entity->label();
    }

    return NULL;
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The content entity.
   * @param string ...$fieldNames
   *   Text field candidates in priority order.
   */
  private function getTagline(EntityInterface $entity, string ...$fieldNames): ?string {
    foreach ($fieldNames as $fieldName) {
      if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
        continue;
      }

      $field = $entity->get($fieldName);
      $value = $field->value ?? NULL;
      if (!is_string($value) || trim($value) === '') {
        continue;
      }

      $text = Html::decodeEntities(strip_tags($value));
      $text = str_replace("\xc2\xa0", ' ', $text);
      $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';
      if ($text !== '') {
        return Unicode::truncate($text, 255, TRUE, TRUE);
      }
    }

    return NULL;
  }

  /**
   * Resolves a short place/address label for cards.
   */
  private function getPlaceLabel(EntityInterface $entity): ?string {
    foreach (['field_event_place', 'event_place'] as $fieldName) {
      if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
        continue;
      }
      $value = trim((string) $entity->get($fieldName)->value);
      if ($value !== '') {
        return $value;
      }
    }

    foreach (['field_event_address', 'event_address'] as $fieldName) {
      $formatted = $this->formatAddressField($entity, $fieldName);
      if ($formatted !== NULL) {
        return $formatted;
      }
    }

    if ($entity instanceof EventInstance) {
      $series = $entity->get('eventseries_id')->entity;
      if ($series instanceof EventSeries) {
        return $this->getPlaceLabel($series);
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

    return $parts === [] ? NULL : implode(', ', $parts);
  }

  /**
   * Format date/time like "13. SEP. | KL 15.00 - 17.00".
   */
  private function formatHeroDate(DrupalDateTime $start, ?DrupalDateTime $end, bool $allDay): string {
    $formatter = $this->recurringDateFormatter;
    $datePart = mb_strtoupper($formatter->formatDate($start, 'j. M.'));

    if ($allDay) {
      return $datePart;
    }

    $startTime = $formatter->formatDate($start, 'H.i');
    $endTime = $end instanceof DrupalDateTime ? $formatter->formatDate($end, 'H.i') : NULL;

    if ($endTime) {
      return (string) $this->t('@date | KL @start - @end', [
        '@date' => $datePart,
        '@start' => $startTime,
        '@end' => $endTime,
      ]);
    }

    return (string) $this->t('@date | KL @start', [
      '@date' => $datePart,
      '@start' => $startTime,
    ]);
  }

}
