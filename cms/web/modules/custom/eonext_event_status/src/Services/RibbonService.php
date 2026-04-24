<?php

declare(strict_types=1);

namespace Drupal\eonext_event_status\Services;

use Drupal\dpl_event\Entity\EventInstance;
use Drupal\recurring_events\Entity\EventSeries;

/**
 * Service for managing event ribbons.
 */
class RibbonService {

  /**
   * Gets the ribbon text and color for an event series or instance.
   *
   * @param \Drupal\recurring_events\Entity\EventSeries $event_series
   *   The event series entity.
   * @param \Drupal\dpl_event\Entity\EventInstance $event_instance
   *   The event instance entity, optional.
   *
   * @return array|null
   *   An array containing the ribbon text and color, or NULL if no ribbon.
   */
  public function getRibbon(EventSeries $event_series, EventInstance $event_instance = NULL) {
    // Default to series values.
    $ribbonText = $event_series->get('field_ribbon_text')->getString();
    $ribbonColor = $event_series->get('field_ribbon_color')->getString();

    // If we have an instance, check if its values should override series.
    if ($event_instance) {
      $instanceText = $event_instance->get('field_ribbon_text')->getString();
      $instanceColor = $event_instance->get('field_ribbon_color')->getString();

      // Use instance values if they exist,
      // otherwise use series values if they should apply.
      if (!empty($instanceText) || $event_series->get('field_ribbon_instances_apply')->getString() !== '1') {
        $ribbonText = $instanceText;
        $ribbonColor = $instanceColor;
      }
    }

    // Return ribbon data if text exists, otherwise return null.
    return !empty($ribbonText) ? [
      'text' => $ribbonText,
      'color' => $ribbonColor ?: '#000000',
    ] : NULL;
  }

}
