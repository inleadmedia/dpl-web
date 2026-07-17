<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_main;

use Drupal\paragraphs\ParagraphInterface;

/**
 * Content slider paragraph display settings.
 */
final class ContentSliderSettings {

  public const DISPLAY_MODE_FIELD = 'field_kultur_slider_display_mode';

  public const ITEMS_PER_VIEW_FIELD = 'field_kultur_slider_per_view';

  public const FULL_WIDTH_FIELD = 'field_kultur_slider_full_width';

  public const DISPLAY_NORMAL = 'normal';

  public const DISPLAY_HERO = 'hero';

  public const DISPLAY_ARTICLES = 'articles';

  public const DEFAULT_ITEMS_PER_VIEW = 2;

  public const MIN_ITEMS_PER_VIEW = 1;

  public const MAX_ITEMS_PER_VIEW = 8;

  /**
   * Whether the paragraph should render in hero carousel mode.
   */
  public static function isHeroMode(ParagraphInterface $paragraph): bool {
    if ($paragraph->bundle() !== 'content_slider') {
      return FALSE;
    }

    if (!$paragraph->hasField(self::DISPLAY_MODE_FIELD)
      || $paragraph->get(self::DISPLAY_MODE_FIELD)->isEmpty()) {
      return FALSE;
    }

    return $paragraph->get(self::DISPLAY_MODE_FIELD)->value === self::DISPLAY_HERO;
  }

  /**
   * Whether the paragraph should render as the Korsør articles slider.
   */
  public static function isArticlesMode(ParagraphInterface $paragraph): bool {
    if ($paragraph->bundle() !== 'content_slider') {
      return FALSE;
    }

    if (!$paragraph->hasField(self::DISPLAY_MODE_FIELD)
      || $paragraph->get(self::DISPLAY_MODE_FIELD)->isEmpty()) {
      return FALSE;
    }

    return $paragraph->get(self::DISPLAY_MODE_FIELD)->value === self::DISPLAY_ARTICLES;
  }

  /**
   * Number of slides visible at once in hero mode.
   */
  public static function getItemsPerView(ParagraphInterface $paragraph): int {
    if (!$paragraph->hasField(self::ITEMS_PER_VIEW_FIELD)
      || $paragraph->get(self::ITEMS_PER_VIEW_FIELD)->isEmpty()) {
      return self::DEFAULT_ITEMS_PER_VIEW;
    }

    $value = (int) $paragraph->get(self::ITEMS_PER_VIEW_FIELD)->value;

    return max(self::MIN_ITEMS_PER_VIEW, min(self::MAX_ITEMS_PER_VIEW, $value));
  }

  /**
   * Whether the slider should break out to full viewport width.
   */
  public static function isFullWidth(ParagraphInterface $paragraph): bool {
    if ($paragraph->bundle() !== 'content_slider') {
      return FALSE;
    }

    if (!$paragraph->hasField(self::FULL_WIDTH_FIELD)
      || $paragraph->get(self::FULL_WIDTH_FIELD)->isEmpty()) {
      return self::isArticlesMode($paragraph);
    }

    return (bool) $paragraph->get(self::FULL_WIDTH_FIELD)->value;
  }

}
