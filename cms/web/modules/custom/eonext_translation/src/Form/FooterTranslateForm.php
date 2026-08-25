<?php

declare(strict_types=1);

namespace Drupal\eonext_translation\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\eonext_translation\TranslationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Translation form for the footer.
 */
final class FooterTranslateForm extends ConfigFormBase {


  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'eonext_translation.footer';

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'eonext_translation_footer_translate_form';
  }

  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The translation service.
   *
   * @var \Drupal\eonext_translation\TranslationServiceInterface
   */
  protected $translationService;

  /**
   * Constructs a new FooterTranslateForm.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The language manager.
   * @param \Drupal\eonext_translation\TranslationServiceInterface $translation_service
   *   The translation service.
   */
  public function __construct(StateInterface $state, TranslationServiceInterface $translation_service) {
    $this->state = $state;
    $this->translationService = $translation_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state'),
      $container->get('eonext_translation.service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config(static::SETTINGS);

    $form['translate_footer'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Translate footer'),
      '#default_value' => $config->get('translate_footer'),
    ];

    $form['edit'] = [
      '#type' => 'details',
      '#title' => $this->t('Edit translations'),
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="translate_footer"]' => ['checked' => TRUE],
        ],
      ],
      '#access' => $config->get('footer_items') ? TRUE : FALSE,
    ];

    $form['edit']['edit_translations'] = [
      '#type' => 'link',
      '#title' => $this->t('Edit translations'),
      '#url' => Url::fromUserInput('/admin/structure/footer/translate'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Save the footer settings.
    $config = $this->configFactory->getEditable(static::SETTINGS);

    // Save the footer settings as configs.
    $config
      ->set('translate_footer', $form_state->getValue('translate_footer'))
      ->save();

    $this->translationService->saveFooterToConfig();
  }

}
