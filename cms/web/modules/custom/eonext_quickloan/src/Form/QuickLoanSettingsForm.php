<?php

namespace Drupal\eonext_quickloan\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eonext_quickloan\QuickLoanSettings;

/**
 * Quick loan configuration form.
 */
class QuickLoanSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'eonext_quickloan_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [QuickLoanSettings::CONFIG_ID];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show quick loan information'),
      '#description' => $this->t('When enabled, the material page shows which libraries offer quick loan (Kviklån) for the current title.'),
      '#config_target' => QuickLoanSettings::CONFIG_ID . ':enabled',
    ];

    return $form;
  }

}
