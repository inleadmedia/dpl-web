<?php

namespace Drupal\eonext_ext_fields\Form;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigTarget;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Additional fields settings form class.
 */
class AdditionalFieldsSettingsForm extends ConfigFormBase {

  public const FORM_ID = 'eonext_ext_fields.settings_form';

  public const CONFIG_ID = 'eonext_ext_fields.settings';

  public const FBI_FIELD_PATTERN = '[a-z]+(\.[a-z]+)*';

  /**
   * The module extension list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * Constructs the AdditionalFieldsSettingsForm object.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list service.
   */
  public function __construct(ModuleExtensionList $module_extension_list) {
    $this->moduleExtensionList = $module_extension_list;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('extension.list.module')
    );
  }

  /**
   * {@inheritDoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      self::CONFIG_ID,
    ];
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
    // Get the module path.
    $module_path = '/' . $this->moduleExtensionList->getPath('eonext_ext_fields') . '/help/extended_fields.md';

    $form['additional_fields'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Additional fields to expose inside Works object'),
      '#description' => $this->t('See the <a href="@url" target="_blank">documentation</a> for the detailed JSON schema.', [
        '@url' => $module_path,
      ]),
      '#rows' => 20,
      '#config_target' => self::CONFIG_ID . ':' . 'additional_fields',
    ];

    $form['text_fields'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Text fields'),
      '#description' => $this->t('Replace text fields for translation. Insert a key value format where key is camelCase string ending inText, and the value is a string.'),
      '#rows' => 5,
      '#config_target' => self::CONFIG_ID . ':text_fields',
    ];

    $textData = $this->config(self::CONFIG_ID)->get('text_fields');

    if (!empty($textData)) {

      $form['text_data'] = [
        '#type' => 'details',
        '#title' => $this->t('Generated text attributes'),
        '#open' => TRUE,
      ];

      $textData = json_decode($textData, TRUE);

      $table = [
        '#type' => 'table',
        '#header' => [
          $this->t('Attribute'),
          $this->t('Value'),
        ],
      ];

      foreach ($textData as $key => $value) {
        // Transform CamelCase to kebab-case. And remove Text suffix.
        $keyAttribute = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $key));
        $table[] = [
          'attribute' => [
            '#markup' => $keyAttribute,
          ],
          'value' => [
            '#markup' => $this->t($value),
          ],
        ];
      }
      $form['text_data']['text_data'] = $table;
    }

    $form['cover_override'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User covers from this path within Works object'),
      '#description' => $this->t('Fetch alternative covers from FBI well field, e.g., "manifestations.latest.cover.detail".'),
      '#config_target' => self::CONFIG_ID . ':cover_override',
    ];

    $form['block_loans'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Block loans for items from this group'),
      '#description' => $this->t('Block / blacklist loans for items from this group. Add a comma separated list of values. e.g., "group1,group2".'),
      '#config_target' => new ConfigTarget(
        self::CONFIG_ID,
        'block_loans',
        toConfig: static::class . '::formatBlockLoans'
      ),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $json_input = $form_state->getValue('additional_fields');
    $trimmed_input = trim(preg_replace('/\s+/', '', $json_input));

    if (json_decode($trimmed_input) === NULL && json_last_error() !== JSON_ERROR_NONE) {
      $form_state->setErrorByName('additional_fields', $this->t('The provided input is not valid JSON.'));
    }

    $text_fields = $form_state->getValue('text_fields');

    if (!empty($text_fields)) {
      $trimmed_text_fields = trim(preg_replace('/\s+/', '', $text_fields));
      $json_text_fields = json_decode($trimmed_text_fields, TRUE);
      if ($json_text_fields === NULL && json_last_error() !== JSON_ERROR_NONE) {
        $form_state->setErrorByName('text_fields', $this->t('The provided input is not valid JSON.'));
      }
      else {
        // Validate keys and values.
        foreach ($json_text_fields as $key => $value) {
          if (!preg_match('/^[a-z][a-zA-Z0-9]*Text$/', $key)) {
            $form_state->setErrorByName('text_fields', $this->t('Invalid key: %key. Keys must be camelCase and end with "Text".', ['%key' => $key]));
            return;
          }
          if (!is_string($value)) {
            $form_state->setErrorByName('text_fields', $this->t('Invalid value for %key. Values must be strings.', ['%key' => $key]));
            return;
          }
        }
      }

    }

    $cover_override = trim($form_state->getValue('cover_override'));
    if (!empty($cover_override) && !preg_match('/^' . self::FBI_FIELD_PATTERN . '$/i', $cover_override)) {
      $form_state->setErrorByName('cover_override', $this->t('Failed to validate pattern.'));
    }
    $form_state->setValue('cover_override', $cover_override);

    $block_loans = trim($form_state->getValue('block_loans'));
    // Check if the input is a comma separated list of values,
    // allow spaces between values.
    // valid input: "group1, group2, group3"
    // valid input: "group1,group2,group3".
    // valid input: "group1".
    if (!empty($block_loans) && !preg_match('/^([a-z0-9]+(, ?)?)+$/i', $block_loans)) {
      $form_state->setErrorByName('block_loans', $this->t('Failed to validate pattern.'));
    }
  }

  /**
   * Formats the block loans value.
   *
   * Adding one space after each comma and removing trailing spaces.
   *
   * @param string $value
   *   The value to format.
   *
   * @return string
   *   The formatted value.
   */
  public static function formatBlockLoans(string $value): string {
    return preg_replace('/, ?/', ', ', trim($value));
  }

}
