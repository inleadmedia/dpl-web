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

}
