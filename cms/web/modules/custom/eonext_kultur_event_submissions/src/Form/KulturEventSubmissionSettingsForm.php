<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_event_submissions\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for Kulturnat event submission fields and displays.
 */
final class KulturEventSubmissionSettingsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'kultur_event_submission_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['help'] = [
      '#type' => 'item',
      '#markup' => $this->t(
        'Use the tabs above to manage fields, the public submission form, and admin displays for Kulturnat event submissions.',
        [],
        ['context' => 'eonext_kultur_event_submissions']
      ),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

}
