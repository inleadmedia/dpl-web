<?php

declare(strict_types=1);

namespace Drupal\eonext_event_material_paragraphs\Hook;

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\eonext_event_material_paragraphs\EventMaterialParagraphBundles;
use Drupal\eonext_event_material_paragraphs\ShowAllConfig;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Form alters for the show-all button toggle on event and material paragraphs.
 */
final class FormHooks {

  /**
   * Toggles the Explore page link on the standalone paragraph edit form.
   *
   * @param array<mixed> $form
   *   The paragraph form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  #[Hook('form_paragraph_form_alter')]
  public function paragraphFormAlter(array &$form, FormStateInterface $form_state): void {
    $paragraph = $this->paragraphFromFormState($form_state);
    if ($paragraph === NULL || !EventMaterialParagraphBundles::hasShowAll($paragraph->bundle())) {
      return;
    }

    ShowAllConfig::applyLinkFieldStates($form);
  }

  /**
   * Toggles the Explore page link on embedded paragraph widget subforms.
   *
   * @param array<mixed> $element
   *   The paragraph widget form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<mixed> $context
   *   The widget context.
   */
  #[Hook('field_widget_single_element_paragraphs_form_alter')]
  public function paragraphsWidgetAlter(array &$element, FormStateInterface $form_state, array $context): void {
    $paragraph = $this->paragraphFromWidget($element, $form_state, $context);
    if ($paragraph === NULL || !isset($element['subform'])) {
      return;
    }

    if (!EventMaterialParagraphBundles::hasShowAll($paragraph->bundle())) {
      return;
    }

    $field_parents = $element['#field_parents'];
    if (!is_array($field_parents)) {
      return;
    }

    $behavior_selector = ShowAllConfig::nestedBehaviorSelector(
      $field_parents,
      $context['items']->getFieldDefinition()->getName(),
      $element['#delta'],
    );

    ShowAllConfig::applyLinkFieldStates($element['subform'], $behavior_selector);
  }

  /**
   * Resolves the paragraph entity from a standalone paragraph form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\paragraphs\ParagraphInterface|null
   *   The paragraph entity, or NULL when it cannot be resolved.
   */
  private function paragraphFromFormState(FormStateInterface $form_state): ?ParagraphInterface {
    $form_object = $form_state->getFormObject();
    if (!method_exists($form_object, 'getEntity')) {
      return NULL;
    }

    $paragraph = $form_object->getEntity();

    return $paragraph instanceof Paragraph ? $paragraph : NULL;
  }

  /**
   * Resolves the paragraph entity from a paragraphs widget element.
   *
   * @param array<mixed> $element
   *   The paragraph widget form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<mixed> $context
   *   The widget context.
   *
   * @return \Drupal\paragraphs\ParagraphInterface|null
   *   The paragraph entity, or NULL when it cannot be resolved.
   */
  private function paragraphFromWidget(array $element, FormStateInterface $form_state, array $context): ?ParagraphInterface {
    $field_name = $context['items']->getFieldDefinition()->getName();
    $field_parents = $element['#field_parents'];
    if (!is_array($field_parents)) {
      return NULL;
    }

    $widget_state = WidgetBase::getWidgetState($field_parents, $field_name, $form_state);
    $paragraph = $widget_state['paragraphs'][$element['#delta']]['entity'] ?? NULL;

    return $paragraph instanceof Paragraph ? $paragraph : NULL;
  }

}
