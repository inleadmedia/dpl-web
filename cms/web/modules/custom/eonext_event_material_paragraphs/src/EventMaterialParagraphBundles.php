<?php

declare(strict_types=1);

namespace Drupal\eonext_event_material_paragraphs;

/**
 * Paragraph bundles that support the "Show all" button configuration.
 */
final class EventMaterialParagraphBundles {

  public const MATERIAL_GRID_AUTOMATIC = 'material_grid_automatic';

  public const MATERIAL_GRID_MANUAL = 'material_grid_manual';

  public const FILTERED_EVENT_LIST = 'filtered_event_list';

  public const MANUAL_EVENT_LIST = 'manual_event_list';

  /**
   * Bundles that expose the "Show all" behavior + link fields.
   */
  public const SHOW_ALL = [
    self::MATERIAL_GRID_AUTOMATIC,
    self::MATERIAL_GRID_MANUAL,
    self::FILTERED_EVENT_LIST,
    self::MANUAL_EVENT_LIST,
  ];

  /**
   * Checks whether a bundle exposes the "Show all" configuration.
   */
  public static function hasShowAll(string $bundle): bool {
    return in_array($bundle, self::SHOW_ALL, TRUE);
  }

}
