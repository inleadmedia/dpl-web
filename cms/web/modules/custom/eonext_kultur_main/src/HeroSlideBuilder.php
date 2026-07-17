<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_main;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\dpl_event\ReoccurringDateFormatter;
use Drupal\drupal_typed\DrupalTyped;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\recurring_events\Entity\EventInstance;
use Drupal\recurring_events\Entity\EventSeries;

/**
 * Builds render variables for hero carousel slides.
 */
final class HeroSlideBuilder {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    TranslationInterface $translation,
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
    $formatter = DrupalTyped::service(ReoccurringDateFormatter::class, 'dpl_event.reoccurring_date_formatter');
    $details = $formatter->getUpcomingEventDetails($eventSeries);
    $start = $details['start'] ?? NULL;
    $end = $details['end'] ?? NULL;

    return [
      'title' => $eventSeries->label(),
      'tagline' => $this->getTagline($eventSeries, 'field_description'),
      'date_display' => ($start instanceof DrupalDateTime)
        ? $this->formatHeroDate($start, $end instanceof DrupalDateTime ? $end : NULL, $formatter->isAllDay($eventSeries))
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
      'tagline' => $this->getTagline($eventInstance, 'event_description', 'field_description'),
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

      $text = trim(strip_tags($value));
      if ($text !== '') {
        return $text;
      }
    }

    return NULL;
  }

  /**
   * Format date/time like "13. SEP. | KL 15.00 - 17.00".
   */
  private function formatHeroDate(DrupalDateTime $start, ?DrupalDateTime $end, bool $allDay): string {
    $datePart = mb_strtoupper($start->format('j. M.'));

    if ($allDay) {
      return $datePart;
    }

    $startTime = $start->format('H.i');
    $endTime = $end instanceof DrupalDateTime ? $end->format('H.i') : NULL;

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
