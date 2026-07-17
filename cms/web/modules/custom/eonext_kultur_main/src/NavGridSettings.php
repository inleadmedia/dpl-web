<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_main;

use Drupal\paragraphs\ParagraphInterface;

/**
 * Nav grid paragraph display settings.
 */
final class NavGridSettings {

  public const DISPLAY_MODE_FIELD = 'field_kultur_grid_display_mode';

  public const DISPLAY_NORMAL = 'normal';

  public const DISPLAY_BOXES = 'boxes';

  /**
   * Whether the paragraph should render as image-backed boxes.
   */
  public static function isBoxesMode(ParagraphInterface $paragraph): bool {
    if ($paragraph->bundle() !== 'nav_grid_manual') {
      return FALSE;
    }

    if (!$paragraph->hasField(self::DISPLAY_MODE_FIELD)
      || $paragraph->get(self::DISPLAY_MODE_FIELD)->isEmpty()) {
      return FALSE;
    }

    $value = $paragraph->get(self::DISPLAY_MODE_FIELD)->value;

    return $value === self::DISPLAY_BOXES || $value === 'profile_box';
  }

}
