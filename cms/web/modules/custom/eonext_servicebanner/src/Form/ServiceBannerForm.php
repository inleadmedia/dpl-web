<?php

namespace Drupal\eonext_servicebanner\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eonext_servicebanner\ServiceBannerSettings;

/**
 * Service banner configuration form.
 */
class ServiceBannerForm extends ConfigFormBase {

  const FORM_ID = 'eonext_servicebanner_form';
  const CONFIG_ID = ServiceBannerSettings::CONFIG_ID;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return self::FORM_ID;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [self::CONFIG_ID];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config(self::CONFIG_ID);

    $form['status'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Visibility'),
    ];

    $form['status']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable service banner'),
      '#description' => $this->t('When enabled, the banner is shown to all visitors of the library website.'),
      '#config_target' => self::CONFIG_ID . ':enabled',
    ];

    $form['status']['unpublish_on'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Auto-disable on'),
      '#description' => $this->t('Optionally set a date and time when the banner should be automatically disabled. Leave empty to keep it active indefinitely.'),
      '#default_value' => $config->get('unpublish_on')
        ? DrupalDateTime::createFromTimestamp(strtotime($config->get('unpublish_on')))
        : NULL,
    ];

    $form['content'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Content'),
    ];

    $form['content']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('A short, prominent headline for the banner.'),
      '#maxlength' => 255,
      '#config_target' => self::CONFIG_ID . ':title',
    ];

    $form['content']['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body text'),
      '#description' => $this->t('The main message of the service banner.'),
      '#rows' => 4,
      '#config_target' => self::CONFIG_ID . ':body',
    ];

    $form['link'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Link (optional)'),
    ];

    $form['link']['url'] = [
      '#type' => 'url',
      '#title' => $this->t('URL'),
      '#description' => $this->t('The page to redirect the user to when clicking the banner. Leave empty for no link.'),
      '#maxlength' => 2048,
      '#config_target' => self::CONFIG_ID . ':url',
    ];

    $form['link']['url_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link text'),
      '#description' => $this->t('Label for the link button. Defaults to "Read more" if left empty.'),
      '#maxlength' => 255,
      '#config_target' => self::CONFIG_ID . ':url_text',
    ];

    $form['preview'] = [
      '#type' => 'details',
      '#title' => $this->t('Preview'),
      '#open' => FALSE,
    ];

    $form['preview']['banner_preview'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'service-banner-preview'],
      'content' => $this->buildPreview($config),
    ];

    $form['preview']['refresh_preview'] = [
      '#type' => 'button',
      '#value' => $this->t('Refresh preview'),
      '#ajax' => [
        'callback' => '::ajaxRefreshPreview',
        'wrapper' => 'service-banner-preview',
        'effect' => 'fade',
      ],
      '#limit_validation_errors' => [],
    ];

    return $form;
  }

  /**
   * AJAX callback to refresh the banner preview.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The updated preview element.
   */
  public function ajaxRefreshPreview(array &$form, FormStateInterface $form_state): array {
    $values = $form_state->getValues();

    $preview_config = [
      'enabled' => TRUE,
      'title' => $values['title'] ?? '',
      'body' => $values['body'] ?? '',
      'url' => $values['url'] ?? '',
      'url_text' => $values['url_text'] ?? '',
    ];

    $form['preview']['banner_preview']['content'] = $this->buildPreview(NULL, $preview_config);
    return $form['preview']['banner_preview'];
  }

  /**
   * Builds the preview render array using the service_banner_preview theme.
   *
   * @param \Drupal\Core\Config\ImmutableConfig|null $config
   *   Saved config to use as source, or NULL when using $override_values.
   * @param mixed[] $override_values
   *   Live form values to preview instead of saved config.
   *
   * @return array
   *   Render array for the preview.
   */
  protected function buildPreview($config, array $override_values = []): array {
    $title = $override_values['title'] ?? ($config?->get('title') ?? '');
    $body = $override_values['body'] ?? ($config?->get('body') ?? '');
    $url = $override_values['url'] ?? ($config?->get('url') ?? '');

    if (empty($title) && empty($body)) {
      return [
        '#markup' => '<p><em>' . $this->t('Fill in the title or body above and click "Refresh preview".') . '</em></p>',
      ];
    }

    return [
      '#theme' => 'service_banner_preview',
      '#title' => $title,
      '#body' => $body,
      '#url' => $url,
      '#attached' => ['library' => ['eonext_servicebanner/service-banner-preview']],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $enabled = (bool) $form_state->getValue('enabled');
    $title = trim($form_state->getValue('title') ?? '');
    $body = trim($form_state->getValue('body') ?? '');

    if ($enabled && empty($title) && empty($body)) {
      $form_state->setErrorByName('title', $this->t('Please provide at least a title or body text before enabling the banner.'));
    }

    $url = trim($form_state->getValue('url') ?? '');
    if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL)) {
      $form_state->setErrorByName('url', $this->t('The URL is not valid.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $unpublish_on = $form_state->getValue('unpublish_on');
    $unpublish_on_value = '';
    if ($unpublish_on instanceof DrupalDateTime) {
      $unpublish_on_value = $unpublish_on->format(\DateTimeInterface::ATOM);
    }

    $this->config(self::CONFIG_ID)
      ->set('enabled', (bool) $form_state->getValue('enabled'))
      ->set('unpublish_on', $unpublish_on_value)
      ->set('title', trim($form_state->getValue('title') ?? ''))
      ->set('body', trim($form_state->getValue('body') ?? ''))
      ->set('url', trim($form_state->getValue('url') ?? ''))
      ->set('url_text', trim($form_state->getValue('url_text') ?? ''))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
