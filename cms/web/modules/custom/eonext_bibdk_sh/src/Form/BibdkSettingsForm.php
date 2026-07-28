<?php

namespace Drupal\eonext_bibdk_sh\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class BibdkSettingsForm extends ConfigFormBase {
  public const FORM_ID = 'eonext_bibdk_sh.settings_form';

  public const CONFIG_ID = 'eonext_bibdk_sh.settings';

  /**
   * {@inheritDoc}
   */
  protected function getEditableConfigNames(): array {
    return [self::CONFIG_ID];
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId(): string {
    return self::FORM_ID;
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    $form['cron_interval'] = [
      '#type' => 'number',
      '#min' => 1,
      '#max' => 90,
      '#title' => t('CRON interval'),
      '#description' => t('Fetch hierarchy metadata this often (days).'),
      '#config_target' => self::CONFIG_ID . ':cron_interval',
      '#required' => TRUE,
    ];

    return $form;
  }

}
