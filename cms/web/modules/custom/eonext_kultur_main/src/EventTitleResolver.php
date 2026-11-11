<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_main;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\recurring_events\Entity\EventInstance;
use Drupal\recurring_events\Entity\EventSeries;

/**
 * Resolves display titles for events when instance labels are empty.
 */
final class EventTitleResolver {

  use StringTranslationTrait;

  public function __construct(TranslationInterface $translation) {
    $this->stringTranslation = $translation;
  }

  /**
   * Resolve a human-readable title for an event entity.
   */
  public function resolve(EntityInterface $entity): string {
    return match ($entity->getEntityTypeId()) {
      'eventinstance' => $entity instanceof EventInstance
        ? $this->resolveInstance($entity)
        : $this->fallbackLabel(),
      'eventseries' => $entity instanceof EventSeries
        ? $this->resolveSeries($entity)
        : $this->fallbackLabel(),
      default => $this->readLabel($entity) ?? $this->fallbackLabel(),
    };
  }

  private function resolveInstance(EventInstance $instance): string {
    $own = $this->readTitleFields($instance);
    if ($own !== NULL) {
      return $own;
    }

    $series = $instance->getEventSeries();
    if ($series instanceof EventSeries) {
      $inherited = $this->resolveSeries($series);
      if ($inherited !== $this->fallbackLabel()) {
        return $inherited;
      }
    }

    return $this->fallbackLabel();
  }

  private function resolveSeries(EventSeries $series): string {
    return $this->readTitleFields($series)
      ?? $this->readLabel($series)
      ?? $this->fallbackLabel();
  }

  private function readTitleFields(EntityInterface $entity): ?string {
    foreach (['event_title', 'field_event_title', 'title'] as $fieldName) {
      if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
        continue;
      }
      $value = trim((string) ($entity->get($fieldName)->value ?? ''));
      if ($value !== '') {
        return $value;
      }
    }

    return NULL;
  }

  private function readLabel(EntityInterface $entity): ?string {
    $label = $entity->label();
    if (!is_string($label)) {
      return NULL;
    }
    $trimmed = trim($label);
    return $trimmed !== '' ? $trimmed : NULL;
  }

  private function fallbackLabel(): string {
    return (string) $this->t('Arrangement', [], ['context' => 'eonext_kultur_main']);
  }

}
