<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_main;

use Drupal\paragraphs\ParagraphInterface;

/**
 * Nav spot paragraph display settings.
 */
final class NavSpotSettings {

  public const DISPLAY_MODE_FIELD = 'field_kultur_nspot_display_mode';

  public const FULL_WIDTH_FIELD = 'field_kultur_nspot_full_width';

  public const DISPLAY_NORMAL = 'normal';

  public const DISPLAY_NOTIFICATION = 'notification';

  public const DISPLAY_PROMO = 'promo';

  /**
   * Resolves the configured display mode for a nav spot paragraph.
   */
  public static function getDisplayMode(ParagraphInterface $paragraph): string {
    if ($paragraph->bundle() !== 'nav_spots_manual') {
      return self::DISPLAY_NORMAL;
    }

    if (!$paragraph->hasField(self::DISPLAY_MODE_FIELD)
      || $paragraph->get(self::DISPLAY_MODE_FIELD)->isEmpty()) {
      return self::DISPLAY_NORMAL;
    }

    $value = (string) $paragraph->get(self::DISPLAY_MODE_FIELD)->value;

    return in_array($value, [
      self::DISPLAY_NORMAL,
      self::DISPLAY_NOTIFICATION,
      self::DISPLAY_PROMO,
    ], TRUE) ? $value : self::DISPLAY_NORMAL;
  }

  /**
   * Whether the paragraph uses a Korsør nav spot layout.
   */
  public static function isKulturMode(ParagraphInterface $paragraph): bool {
    return in_array(self::getDisplayMode($paragraph), [
      self::DISPLAY_NOTIFICATION,
      self::DISPLAY_PROMO,
    ], TRUE);
  }

  /**
   * Whether the nav spot should break out to full viewport width.
   */
  public static function isFullWidth(ParagraphInterface $paragraph): bool {
    if ($paragraph->bundle() !== 'nav_spots_manual') {
      return FALSE;
    }

    if (!$paragraph->hasField(self::FULL_WIDTH_FIELD)
      || $paragraph->get(self::FULL_WIDTH_FIELD)->isEmpty()) {
      return FALSE;
    }

    return (bool) $paragraph->get(self::FULL_WIDTH_FIELD)->value;
  }

}
