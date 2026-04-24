<?php

declare(strict_types=1);

namespace Drupal\eonext_translation\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure EO Next Translation settings for this site.
 */
final class TranslationSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'eonext_translation.settings';

  /**
   * Translation type for google translate.
   *
   * @var string
   */
  const TRANSLATION_TYPE_GOOGLE_TRANSLATE = 'google_translate';

  /**
   * Translation type for drupal translate.
   *
   * @var string
   */
  const TRANSLATION_TYPE_DRUPAL_TRANSLATE = 'drupal_translate';

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new TranslationSettingsForm.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(LanguageManagerInterface $language_manager) {
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'eonext_translation_settings';
  }

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
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Chech the stored configuration for drupal_translation_language.
    // If the configuration contains values that are not in the active languages
    // then show a warning message.
    if (!$this->isActiveConfigValid()) {
      $this->messenger()->addWarning(
        $this->t('The stored configuration for active languages is not valid. Please update the configuration.'),
      );
    }

    $config = $this->config(static::SETTINGS);

    $form['translation_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Translation type'),
      '#options' => [
        static::TRANSLATION_TYPE_GOOGLE_TRANSLATE => $this->t('Enable Google Translate'),
        static::TRANSLATION_TYPE_DRUPAL_TRANSLATE => $this->t('Enable Drupal Language switcher'),
      ],
      '#description' => $this->t('Select the translation type.'),
      '#default_value' => $config->get('translation_type') ?? static::TRANSLATION_TYPE_GOOGLE_TRANSLATE,
    ];

    $form['drupal_translation'] = [
      '#type' => 'details',
      '#title' => $this->t('Drupal Language switcher settings'),
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="translation_type"]' => ['value' => static::TRANSLATION_TYPE_DRUPAL_TRANSLATE],
        ],
      ],
    ];

    // Get active languages.
    $languages = $this->languageManager->getLanguages();

    $form['drupal_translation']['drupal_translation_available_languages'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Active languages'),
      '#options' => array_map(
        static fn ($language) => $language->getName(),
        $languages,
      ),
      '#description' => $this->t('Select the languages that you want to be shown in UI.'),
      '#default_value' => $config->get('drupal_translation_available_languages') ?? [],
    ];

    $form['calendar_locale'] = [
      '#type' => 'details',
      '#title' => $this->t('Calendar and Date Locale Configuration'),
      '#open' => FALSE,
      '#description' => $this->t('Configure calendar locale settings for each language. The code property should match the language code (e.g., "en" for English, "kl" for Kalaallisut).'),
    ];

    // Create a separate textarea for each language.
    foreach ($languages as $langcode => $language) {
      $form['calendar_locale']['custom_locale_config_' . $langcode] = [
        '#type' => 'textarea',
        '#title' => $this->t('Custom Locale Configuration for @language (@langcode)', [
          '@language' => $language->getName(),
          '@langcode' => $langcode,
        ]),
        '#description' => $this->t('Enter custom JavaScript configuration for FullCalendar (window.DPL_fullCalendarCustomLocale) and DayJS (window.DPL_dayjsCustomLocale). Make sure the "code" property matches "@langcode".', [
          '@langcode' => $langcode,
        ]),
        '#default_value' => $config->get('custom_locale_config_' . $langcode) ?? '',
        '#rows' => 20,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {

    $translationType = $form_state->getValue('translation_type');

    if (static::TRANSLATION_TYPE_DRUPAL_TRANSLATE === $translationType) {
      $languages = array_keys(
        array_filter($form_state->getValue('drupal_translation_available_languages'))
      );

      if (empty($languages)) {
        $form_state->setErrorByName('drupal_translation_available_languages', $this->t('Please select at least one language.'));
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    // Store the active languages as an array of language ids.
    $languages = array_keys(
      array_filter($form_state->getValue('drupal_translation_available_languages'))
    );

    $translationType = $form_state->getValue('translation_type');

    // If the type is not drupal_translate then remove the languages.
    if (static::TRANSLATION_TYPE_DRUPAL_TRANSLATE !== $translationType) {
      $languages = [];
    }

    $config = $this->config(static::SETTINGS);
    $config->set('translation_type', $translationType);
    $config->set('drupal_translation_available_languages', $languages);

    // Save custom locale config for each language.
    $allLanguages = $this->languageManager->getLanguages();
    foreach ($allLanguages as $langcode => $language) {
      $fieldName = 'custom_locale_config_' . $langcode;
      if ($form_state->hasValue($fieldName)) {
        $config->set($fieldName, $form_state->getValue($fieldName));
      }
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Get the active languages.
   *
   * @return string[]
   *   The active languages.
   */
  private function getActiveLanguages(): array {
    return array_map(
      static fn ($language) => $language->getName(),
      $this->languageManager->getLanguages(),
    );
  }

  /**
   * Check if the stored configuration for active languages is valid.
   *
   * @return bool
   *   TRUE if the stored configuration is valid, FALSE otherwise.
   */
  private function isActiveConfigValid(): bool {

    $stored_languages = $this
      ->config(static::SETTINGS)
      ->get('drupal_translation_language') ?? [];

    $active_languages = array_keys(
      array_filter($this->getActiveLanguages())
    );

    $diff = array_diff($stored_languages, $active_languages);

    return empty($diff);
  }

}
