<?php

namespace Drupal\eonext_advanced_search\Form;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Advanced search settings form.
 */
class AdvancedSearchConfigForm extends ConfigFormBase {
  public const FORM_ID = 'advanced_search_config_form';

  public const CONFIG_ID = 'eonext_advanced_search.advanced_search_config_settings';

  /**
   * The module extension list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * Constructs the AdvancedSearchConfigForm object.
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
  public function getFormId(): string {
    return self::FORM_ID;
  }

  /**
   * {@inheritDoc}
   */
  protected function getEditableConfigNames(): array {
    return [self::CONFIG_ID];
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    $form['advanced_search_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Advanced Search'),
      '#config_target' => self::CONFIG_ID . ':advanced_search_enabled',
    ];

    $form['disable_filter_by_branches'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable filter by branches on advanced search page'),
      '#config_target' => self::CONFIG_ID . ':disable_filter_by_branches',
    ];

    // Get the module path.
    $module_path = '/' . $this->moduleExtensionList->getPath('eonext_advanced_search') . '/Help/additional_settings.md';

    $form['additional_settings'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Advanced search settings'),
      '#description' => $this->t('See the <a href="@url" target="_blank">documentation</a> for the detailed JSON schema.', [
        '@url' => $module_path,
      ]),
      '#rows' => 20,
      '#config_target' => self::CONFIG_ID . ':' . 'advanced_search_settings',
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $json_input = $form_state->getValue('additional_settings');
    $trimmed_input = trim(preg_replace('/\s+/', '', $json_input));

    if (!empty($trimmed_input) && json_decode($trimmed_input) === NULL && json_last_error() !== JSON_ERROR_NONE) {
      $form_state->setErrorByName('additional_settings', $this->t('The provided input is not valid JSON.'));
    }
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->messenger()->addWarning(
      $this->t('Clear caches for changes to take effect.')
    );

    parent::submitForm($form, $form_state);
  }

}
