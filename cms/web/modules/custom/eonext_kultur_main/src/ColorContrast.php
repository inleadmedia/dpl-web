<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_main;

/**
 * WCAG contrast helpers aligned with design-system color-contrast.ts.
 */
final class ColorContrast {

  private const NAV_SPOT_DARK_TEXT = '#235881';

  private const NAV_SPOT_LIGHT_TEXT = '#fefaf1';

  private const NAV_TEASER_OVERLAY_OPACITY = 0.3;

  /**
   * Whether nav spot text should use the light-background styling.
   */
  public static function isLightNavSpotBackground(string $backgroundColor): bool {
    if ($backgroundColor === '' || self::parseColorToRgb($backgroundColor) === NULL) {
      return FALSE;
    }

    return self::getPreferredTextColor($backgroundColor, [
      'dark' => self::NAV_SPOT_DARK_TEXT,
      'light' => self::NAV_SPOT_LIGHT_TEXT,
    ]) === self::NAV_SPOT_DARK_TEXT;
  }

  /**
   * Whether nav teaser overlay text should use the light-background styling.
   */
  public static function isLightNavTeaserBackground(string $backgroundColor): bool {
    return self::isLightNavSpotBackground($backgroundColor);
  }

  /**
   * Whether image-less banner text should use the light-background styling.
   */
  public static function isLightBannerBackground(string $backgroundColor): bool {
    return self::isLightNavSpotBackground($backgroundColor);
  }

  /**
   * Converts a nav teaser overlay color to a translucent rgba() value.
   */
  public static function toNavTeaserOverlayRgba(string $color, float $opacity = self::NAV_TEASER_OVERLAY_OPACITY): ?string {
    $rgb = self::parseColorToRgb($color);
    if ($rgb === NULL) {
      return NULL;
    }

    $opacity = max(0.0, min(1.0, $opacity));

    return sprintf(
      'rgba(%d, %d, %d, %s)',
      $rgb['r'],
      $rgb['g'],
      $rgb['b'],
      rtrim(rtrim(sprintf('%.2f', $opacity), '0'), '.') ?: '0'
    );
  }

  /**
   * Pick dark or light text for a given background color.
   */
  public static function getPreferredTextColor(string $background, array $options = []): string {
    $dark = $options['dark'] ?? '#000000';
    $light = $options['light'] ?? '#ffffff';
    $lightBackgroundLuminance = $options['lightBackgroundLuminance'] ?? 0.6;

    $luminance = self::getRelativeLuminance($background);
    if ($luminance < $lightBackgroundLuminance) {
      return $light;
    }

    $darkRatio = self::getContrastRatio($dark, $background);
    $lightRatio = self::getContrastRatio($light, $background);

    return $lightRatio >= $darkRatio ? $light : $dark;
  }

  /**
   * Relative luminance per WCAG 2.x (0–1).
   */
  public static function getRelativeLuminance(string $color): float {
    $rgb = self::parseColorToRgb($color);
    if ($rgb === NULL) {
      throw new \InvalidArgumentException(sprintf('Unsupported color format: %s', $color));
    }

    $r = self::channelToLinear($rgb['r']);
    $g = self::channelToLinear($rgb['g']);
    $b = self::channelToLinear($rgb['b']);

    return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
  }

  /**
   * Contrast ratio between two colors (1–21).
   */
  public static function getContrastRatio(string $foreground, string $background): float {
    $foregroundLuminance = self::getRelativeLuminance($foreground);
    $backgroundLuminance = self::getRelativeLuminance($background);
    $lighter = max($foregroundLuminance, $backgroundLuminance);
    $darker = min($foregroundLuminance, $backgroundLuminance);

    return ($lighter + 0.05) / ($darker + 0.05);
  }

  /**
   * Parse hex (#fff, #ffffff) or rgb/rgba() into RGB channels (0–255).
   *
   * @return array{r: int, g: int, b: int}|null
   *   Parsed RGB channels, or NULL when unsupported.
   */
  public static function parseColorToRgb(string $color): ?array {
    $normalized = strtolower(trim($color));

    if (preg_match('/^#([\da-f]{3}|[\da-f]{6}|[\da-f]{8})$/i', $normalized, $hexMatch)) {
      $hex = $hexMatch[1];
      if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
      }

      return [
        'r' => (int) hexdec(substr($hex, 0, 2)),
        'g' => (int) hexdec(substr($hex, 2, 2)),
        'b' => (int) hexdec(substr($hex, 4, 2)),
      ];
    }

    if (preg_match('/^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)(?:\s*,\s*[\d.]+)?\s*\)$/', $normalized, $rgbMatch)) {
      return [
        'r' => (int) $rgbMatch[1],
        'g' => (int) $rgbMatch[2],
        'b' => (int) $rgbMatch[3],
      ];
    }

    return NULL;
  }

  /**
   * Converts an sRGB channel to linear luminance space.
   */
  private static function channelToLinear(int $channel): float {
    $value = $channel / 255;
    return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
  }

}
