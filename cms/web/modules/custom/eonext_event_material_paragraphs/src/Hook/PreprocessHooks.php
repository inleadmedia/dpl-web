<?php

declare(strict_types=1);

namespace Drupal\eonext_event_material_paragraphs\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Template\Attribute;
use Drupal\eonext_event_material_paragraphs\ShowAllConfig;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Paragraph theme preprocess hooks for the "Show all" toggle.
 *
 * In every case the configured Explore link is exposed through the
 * `data-show-all-link-config` data attribute (the contract the frontend reads
 * to render a link instead of an in-page "Show all" button). No theme template
 * changes are required.
 */
final class PreprocessHooks {

  /**
   * Material grid automatic React app data.
   *
   * @param array<string|int, mixed> &$variables
   *   Theme variables for the paragraph template.
   */
  #[Hook('preprocess_paragraph__material_grid_automatic')]
  public function materialGridAutomatic(array &$variables): void {
    $this->addReactConfig($variables);
  }

  /**
   * Material grid manual React app data.
   *
   * @param array<string|int, mixed> &$variables
   *   Theme variables for the paragraph template.
   */
  #[Hook('preprocess_paragraph__material_grid_manual')]
  public function materialGridManual(array &$variables): void {
    $this->addReactConfig($variables);
  }

  /**
   * Event list (automatic) wrapper attribute.
   *
   * The list is rendered through the dpl_related_content theme. The link is
   * stashed on that render array and turned into a wrapper data attribute by
   * dplRelatedContent(), so no theme template change is needed.
   *
   * @param array<string|int, mixed> &$variables
   *   Theme variables for the paragraph template.
   */
  #[Hook('preprocess_paragraph__filtered_event_list')]
  public function filteredEventList(array &$variables): void {
    $show_all_link = $this->resolveLink($variables);
    if ($show_all_link === NULL || !isset($variables['content']['view']['#theme'])) {
      return;
    }

    $variables['content']['view']['#show_all_link'] = $show_all_link;
  }

  /**
   * Renders the stashed Explore link as a wrapper data attribute.
   *
   * The `show_all_link` variable is declared on the dpl_related_content theme
   * via hook_theme_registry_alter() in the .module file.
   *
   * @param array<string|int, mixed> &$variables
   *   Theme variables for the dpl_related_content template.
   */
  #[Hook('preprocess_dpl_related_content')]
  public function dplRelatedContent(array &$variables): void {
    $show_all_link = $variables['show_all_link'] ?? NULL;
    if ($show_all_link === NULL || $show_all_link === '') {
      return;
    }

    $attributes = $variables['attributes'] ?? NULL;
    if (!$attributes instanceof Attribute) {
      $attributes = new Attribute(is_array($attributes) ? $attributes : []);
    }

    $attributes->setAttribute(ShowAllConfig::DATA_ATTRIBUTE, $show_all_link);
    $variables['attributes'] = $attributes;
    $this->attachShowAllLinkLibrary($variables);
  }

  /**
   * Event list (manual) wrapper attribute.
   *
   * @param array<string|int, mixed> &$variables
   *   Theme variables for the paragraph template.
   */
  #[Hook('preprocess_paragraph__manual_event_list')]
  public function manualEventList(array &$variables): void {
    $show_all_link = $this->resolveLink($variables);
    if ($show_all_link === NULL || !isset($variables['attributes'])) {
      return;
    }

    $variables['attributes'][ShowAllConfig::DATA_ATTRIBUTE] = $show_all_link;
    $this->attachShowAllLinkLibrary($variables);
  }

  /**
   * Adds the Explore link to a material grid React app render array.
   *
   * @param array<string|int, mixed> &$variables
   *   Theme variables for the paragraph template.
   */
  private function addReactConfig(array &$variables): void {
    $show_all_link = $this->resolveLink($variables);
    if ($show_all_link === NULL) {
      return;
    }

    $content = $variables['content'] ?? NULL;
    if (!is_array($content)) {
      return;
    }

    $material_grid = $content['material_grid'] ?? NULL;
    if (!is_array($material_grid)) {
      return;
    }

    $react_data = $material_grid['#data'] ?? NULL;
    if (!is_array($react_data)) {
      return;
    }

    $react_data[ShowAllConfig::REACT_CONFIG_KEY] = $show_all_link;
    $material_grid['#data'] = $react_data;
    $content['material_grid'] = $material_grid;
    $variables['content'] = $content;
  }

  /**
   * Attaches the JS that swaps the expand button for an Explore page link.
   *
   * @param array<string|int, mixed> &$element
   *   A render array or preprocess variables array.
   */
  private function attachShowAllLinkLibrary(array &$element): void {
    $element['#attached']['library'][] = 'eonext_event_material_paragraphs/show_all_link';
  }

  /**
   * Resolves the configured Explore link for the paragraph in the variables.
   *
   * @param array<string|int, mixed> $variables
   *   Theme variables for the paragraph template.
   */
  private function resolveLink(array $variables): ?string {
    $paragraph = $variables['paragraph'] ?? NULL;
    if (!$paragraph instanceof ParagraphInterface) {
      return NULL;
    }

    return ShowAllConfig::resolveLinkUrl($paragraph);
  }

}
