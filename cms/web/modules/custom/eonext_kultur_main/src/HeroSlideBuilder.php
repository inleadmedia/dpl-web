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
    private readonly ReoccurringDateFormatter $recurringDateFormatter,
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
    $details = $this->resolveEventSeriesSchedule($eventSeries);
    $start = $details['start'] ?? NULL;
    $end = $details['end'] ?? NULL;

    return [
      'title' => $eventSeries->label(),
      'category' => $this->getCategoryLabel($eventSeries),
      'tagline' => $this->getTagline($eventSeries, 'field_description'),
      'date_display' => ($start instanceof DrupalDateTime)
        ? $this->formatHeroDate($start, $end instanceof DrupalDateTime ? $end : NULL, $this->recurringDateFormatter->isAllDay($eventSeries))
        : NULL,
      'date_iso' => $start instanceof DrupalDateTime ? $this->formatIsoDate($start) : NULL,
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

    $series = $eventInstance->getEventSeries();
    $image = $series instanceof EventSeries
      ? $this->buildBannerImage($series, ['field_event_image', 'field_teaser_image'])
      : NULL;
    if ($image === NULL) {
      $image = $this->buildBannerImage($eventInstance, ['event_image', 'field_event_image', 'event_teaser_image', 'field_teaser_image']);
    }

    return [
      'title' => $eventInstance->label(),
      'category' => $this->getCategoryLabel($eventInstance),
      'tagline' => $this->getTagline($eventInstance, 'event_description', 'field_description'),
      'date_display' => ($start instanceof DrupalDateTime)
        ? $this->formatHeroDate($start, $end instanceof DrupalDateTime ? $end : NULL, $allDay)
        : NULL,
      'date_iso' => $start instanceof DrupalDateTime ? $this->formatIsoDate($start) : NULL,
      'url' => Url::fromRoute('entity.eventinstance.canonical', [
        'eventinstance' => $eventInstance->id(),
      ])->toString(),
      'image' => $image,
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
      'date_display' => $this->getNodeDateDisplay($node),
      'date_iso' => $this->getNodeDateIso($node),
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

      $text = trim(strip_tags($value));
      if ($text !== '') {
        return $text;
      }
    }

    return NULL;
  }

  /**
   * Resolve start/end for a series (upcoming first, else earliest instance).
   *
   * @return array{start: ?\Drupal\Core\Datetime\DrupalDateTime, end: ?\Drupal\Core\Datetime\DrupalDateTime}
   */
  private function resolveEventSeriesSchedule(EventSeries $eventSeries): array {
    $upcoming = $this->recurringDateFormatter->getUpcomingEventDetails($eventSeries);
    if ($upcoming !== NULL) {
      return [
        'start' => $upcoming['start'] ?? NULL,
        'end' => $upcoming['end'] ?? NULL,
      ];
    }

    $instanceIds = $this->entityTypeManager->getStorage('eventinstance')->getQuery()
      ->condition('eventseries_id', $eventSeries->id())
      ->condition('status', TRUE)
      ->accessCheck(TRUE)
      ->sort('date.value', 'ASC')
      ->range(0, 1)
      ->execute();

    $instanceId = reset($instanceIds);
    if ($instanceId === FALSE) {
      return ['start' => NULL, 'end' => NULL];
    }

    $eventInstance = EventInstance::load($instanceId);
    if (!$eventInstance instanceof EventInstance || $eventInstance->get('date')->isEmpty()) {
      return ['start' => NULL, 'end' => NULL];
    }

    $dateField = $eventInstance->get('date')->first();

    return [
      'start' => $dateField->start_date ?? NULL,
      'end' => $dateField->end_date ?? NULL,
    ];
  }

  /**
   * Publication date for editorial hero slides (articles/pages).
   */
  private function formatIsoDate(DrupalDateTime $date): string {
    return $date->format('Y-m-d\TH:i');
  }

  private function getNodeDateIso(NodeInterface $node): ?string {
    if (!$node->hasField('field_publication_date') || $node->get('field_publication_date')->isEmpty()) {
      return NULL;
    }

    $value = $node->get('field_publication_date')->value ?? NULL;
    if (!is_string($value) || trim($value) === '') {
      return NULL;
    }

    try {
      return $this->formatIsoDate(new DrupalDateTime($value));
    }
    catch (\Exception) {
      return NULL;
    }
  }

  private function getNodeDateDisplay(NodeInterface $node): ?string {
    if (!$node->hasField('field_publication_date') || $node->get('field_publication_date')->isEmpty()) {
      return NULL;
    }

    $value = $node->get('field_publication_date')->value ?? NULL;
    if (!is_string($value) || trim($value) === '') {
      return NULL;
    }

    try {
      $start = new DrupalDateTime($value);
    }
    catch (\Exception) {
      return NULL;
    }

    return mb_strtoupper($this->recurringDateFormatter->formatDate($start, 'j. M.'));
  }

  /**
   * Format date/time like "13. SEP. | KL 15.00 - 17.00".
   */
  private function formatHeroDate(DrupalDateTime $start, ?DrupalDateTime $end, bool $allDay): string {
    $datePart = mb_strtoupper($this->recurringDateFormatter->formatDate($start, 'j. M.'));

    if ($allDay) {
      return $datePart;
    }

    $startTime = $this->recurringDateFormatter->formatDate($start, 'H.i');
    $endTime = $end instanceof DrupalDateTime
      ? $this->recurringDateFormatter->formatDate($end, 'H.i')
      : NULL;

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
