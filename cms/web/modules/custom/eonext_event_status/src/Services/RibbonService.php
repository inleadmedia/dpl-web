<?php

declare(strict_types=1);

namespace Drupal\eonext_event_status\Services;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\dpl_event\Entity\EventInstance;
use Drupal\recurring_events\Entity\EventSeries;

/**
 * Service for managing event ribbons.
 */
class RibbonService {

  /**
   * Gets the ribbon text and color for an event series or instance.
   *
   * @return array{text: string, color: string}|null
   *   Ribbon data, or NULL when no ribbon text is configured.
   */
  public function getRibbon(EventSeries $event_series, ?EventInstance $event_instance = NULL): ?array {
    $ribbon_text = $this->getStringValue($event_series, 'field_ribbon_text');
    $ribbon_color = $this->getColorValue($event_series, 'field_ribbon_color');

    if ($event_instance instanceof EventInstance) {
      $instance_text = $this->getStringValue($event_instance, 'field_ribbon_text');
      $instance_color = $this->getColorValue($event_instance, 'field_ribbon_color');
      $apply_to_instances = $this->getStringValue($event_series, 'field_ribbon_instances_apply') === '1';

      if ($instance_text !== '' || !$apply_to_instances) {
        $ribbon_text = $instance_text;
        $ribbon_color = $instance_color;
      }
    }

    if ($ribbon_text === '') {
      return NULL;
    }

    return [
      'text' => $ribbon_text,
      'color' => $ribbon_color !== '' ? $ribbon_color : '#000000',
    ];
  }

  /**
   * Reads a field value as a string, tolerating a field that is not installed.
   */
  private function getStringValue(FieldableEntityInterface $entity, string $field_name): string {
    if (!$entity->hasField($field_name)) {
      return '';
    }

    return $entity->get($field_name)->getString();
  }

  /**
   * Reads a color_field value as a hex color string.
   */
  private function getColorValue(FieldableEntityInterface $entity, string $field_name): string {
    if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
      return '';
    }

    $value = $entity->get($field_name)->first()?->getValue() ?? [];
    if (!empty($value['color']) && is_string($value['color'])) {
      return $value['color'];
    }

    return $entity->get($field_name)->getString();
  }

}
