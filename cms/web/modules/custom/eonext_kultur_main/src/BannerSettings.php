<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_main;

use Drupal\paragraphs\ParagraphInterface;

/**
 * Banner paragraph display settings.
 */
final class BannerSettings {

  public const BACKGROUND_COLOR_FIELD = 'field_kultur_banner_bg_color';

  /**
   * Default image-less banner background from the design system.
   */
  public const DEFAULT_BACKGROUND = '#eee9e5';

  /**
   * Resolves the configured background color for image-less banners.
   */
  public static function getBackgroundColor(ParagraphInterface $paragraph): string {
    if ($paragraph->bundle() !== 'banner') {
      return '';
    }

    if ($paragraph->hasField('field_banner_image') && !$paragraph->get('field_banner_image')->isEmpty()) {
      return '';
    }

    if (!$paragraph->hasField(self::BACKGROUND_COLOR_FIELD)
      || $paragraph->get(self::BACKGROUND_COLOR_FIELD)->isEmpty()) {
      return '';
    }

    return (string) ($paragraph->get(self::BACKGROUND_COLOR_FIELD)->first()->color ?? '');
  }

  /**
   * Whether the banner paragraph has a background image.
   */
  public static function hasBackgroundImage(ParagraphInterface $paragraph): bool {
    return $paragraph->bundle() === 'banner'
      && $paragraph->hasField('field_banner_image')
      && !$paragraph->get('field_banner_image')->isEmpty();
  }

  /**
   * Resolves the effective background for image-less banners.
   */
  public static function getEffectiveBackgroundColor(ParagraphInterface $paragraph): string {
    if (self::hasBackgroundImage($paragraph)) {
      return '';
    }

    $background_color = self::getBackgroundColor($paragraph);

    return $background_color !== '' ? $background_color : self::DEFAULT_BACKGROUND;
  }

  /**
   * Whether image-less banner text should use light-background styling.
   */
  public static function isLightBackground(ParagraphInterface $paragraph): bool {
    if (self::hasBackgroundImage($paragraph)) {
      return FALSE;
    }

    return ColorContrast::isLightBannerBackground(self::getEffectiveBackgroundColor($paragraph));
  }

}
