<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_locale;

use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Detects whether the DPL design-system dialog styles are available.
 */
final class DialogAvailability {

  public function __construct(
    private readonly ThemeHandlerInterface $themeHandler,
    private readonly ThemeExtensionList $themeExtensionList,
    private readonly ThemeManagerInterface $themeManager,
  ) {}

  /**
   * Whether the active theme chain ships DPL dialog CSS.
   */
  public function isAvailable(): bool {
    $theme_name = $this->themeManager->getActiveTheme()->getName();
    $checked = [];

    while ($theme_name && !isset($checked[$theme_name])) {
      $checked[$theme_name] = TRUE;
      if ($this->themeHasDialogStyles($theme_name)) {
        return TRUE;
      }

      $theme_name = $this->themeHandler->getTheme($theme_name)?->info['base theme'] ?? '';
      if (is_string($theme_name) && str_starts_with($theme_name, 'dpl_')) {
        $theme_name = substr($theme_name, 4);
      }
    }

    return FALSE;
  }

  /**
   * Checks a theme's bundled design-system CSS for dialog rules.
   */
  private function themeHasDialogStyles(string $theme_name): bool {
    $css_path = DRUPAL_ROOT . '/' . $this->themeExtensionList->getPath($theme_name)
      . '/assets/dpl-design-system/css/base.css';

    if (!is_readable($css_path)) {
      return FALSE;
    }

    $css = file_get_contents($css_path);
    return is_string($css) && str_contains($css, '.dialog');
  }

}
