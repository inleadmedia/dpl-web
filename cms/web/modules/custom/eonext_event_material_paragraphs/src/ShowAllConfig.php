<?php

declare(strict_types=1);

namespace Drupal\eonext_event_material_paragraphs;

use Drupal\link\LinkItemInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Shared "Show all" button configuration for event and material paragraphs.
 */
final class ShowAllConfig {

  public const BEHAVIOR_EXPAND = 'expand';

  public const BEHAVIOR_LINK = 'link';

  public const FIELD_BEHAVIOR = 'field_show_all_behavior';

  public const FIELD_LINK = 'field_show_all_link';

  /**
   * The render array #data key (React mounter prefixes it with "data-").
   */
  public const REACT_CONFIG_KEY = 'show-all-link-config';

  /**
   * The rendered data attribute the frontend reads for the Explore link.
   */
  public const DATA_ATTRIBUTE = 'data-' . self::REACT_CONFIG_KEY;

  /**
   * Resolves the "Show all" link URL, or NULL when the list should expand.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph to read the show-all configuration from.
   *
   * @return string|null
   *   The Explore page URL, or NULL when the list should expand.
   */
  public static function resolveLinkUrl(ParagraphInterface $paragraph): ?string {
    if (!self::usesLinkBehavior($paragraph)) {
      return NULL;
    }

    $link_item = $paragraph->get(self::FIELD_LINK)->first();
    if (!$link_item instanceof LinkItemInterface) {
      return NULL;
    }

    return $link_item->getUrl()->toString();
  }

  /**
   * Applies conditional form states to the Explore page link field.
   *
   * @param array &$form
   *   The form or subform array to alter.
   * @param string|null $behavior_selector
   *   A CSS selector for the behavior field input. When omitted, the
   *   standalone paragraph edit form selector is used.
   *
   * @phpstan-param array<string|int,mixed> $form
   */
  public static function applyLinkFieldStates(array &$form, ?string $behavior_selector = NULL): void {
    if (!isset($form[self::FIELD_LINK])) {
      return;
    }

    $selector = $behavior_selector ?? self::standaloneBehaviorSelector();
    $visible_when_linking = [
      $selector => ['value' => self::BEHAVIOR_LINK],
    ];

    $link_field = $form[self::FIELD_LINK];
    if (!is_array($link_field)) {
      return;
    }

    $link_field['#states'] = [
      'visible' => $visible_when_linking,
      'required' => $visible_when_linking,
    ];
    $form[self::FIELD_LINK] = $link_field;
  }

  /**
   * Builds the #states selector for a nested paragraph widget subform.
   *
   * Mirrors how Drupal builds the input "name" attribute from the element
   * parents, so it works at any nesting depth (including top-level paragraph
   * fields, where $field_parents is empty).
   *
   * @param array $field_parents
   *   The widget field parents from the form element.
   * @param string $field_name
   *   The paragraph reference field machine name.
   * @param int $delta
   *   The paragraph widget delta.
   *
   * @phpstan-param array<int,string|int> $field_parents
   */
  public static function nestedBehaviorSelector(array $field_parents, string $field_name, int $delta): string {
    $parents = array_merge($field_parents, [$field_name, $delta, 'subform', self::FIELD_BEHAVIOR]);
    $first = array_shift($parents);
    $input_name = $first . implode('', array_map(
      static fn ($parent): string => '[' . $parent . ']',
      $parents,
    ));

    return ':input[name="' . $input_name . '"]';
  }

  /**
   * CSS selector for the behavior field on standalone paragraph forms.
   */
  private static function standaloneBehaviorSelector(): string {
    return ':input[name="' . self::FIELD_BEHAVIOR . '"]';
  }

  /**
   * Whether the paragraph is configured to link rather than expand.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph to check.
   *
   * @return bool
   *   TRUE when link behavior is selected and a link is provided.
   */
  private static function usesLinkBehavior(ParagraphInterface $paragraph): bool {
    return $paragraph->hasField(self::FIELD_BEHAVIOR)
      && $paragraph->get(self::FIELD_BEHAVIOR)->getString() === self::BEHAVIOR_LINK
      && $paragraph->hasField(self::FIELD_LINK)
      && !$paragraph->get(self::FIELD_LINK)->isEmpty();
  }

}
