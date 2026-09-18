<?php

declare(strict_types=1);

namespace Drupal\eonext_staff\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Field UI landing form for the library staff entity type.
 */
final class LibraryStaffSettingsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'eonext_library_staff_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['help'] = [
      '#markup' => $this->t('Manage Library Staff fields from the Field UI tabs on this page.', [], ['context' => 'eonext']),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Field UI landing page; nothing to persist.
  }

}
