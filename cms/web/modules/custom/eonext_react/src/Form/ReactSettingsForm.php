<?php

namespace Drupal\eonext_react\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eonext_react\InjectedJavascript;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for the JavaScript injected into the React pages.
 */
class ReactSettingsForm extends ConfigFormBase {

  /**
   * The injected JavaScript handler.
   */
  protected InjectedJavascript $injectedJavascript;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $form = parent::create($container);
    $form->injectedJavascript = $container->get('eonext_react.injected_javascript');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'eonext_react_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [InjectedJavascript::CONFIG_ID];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    $form['javascript'] = [
      '#type' => 'textarea',
      '#title' => $this->t('JavaScript'),
      '#description' => $this->t('Runs on every non-administrative page, after the dpl-react webpack runtime and before the React apps are mounted. Intended for testing standalone injection bundles. Paste the contents of the built file, not a script tag. Leave empty to remove it again.'),
      '#rows' => 20,
      '#config_target' => InjectedJavascript::CONFIG_ID . ':javascript',
      '#attributes' => [
        'style' => 'font-family: monospace;',
        'spellcheck' => 'false',
      ],
    ];

    $path = $this->injectedJavascript->getPath();
    if ($path) {
      $form['javascript']['#field_prefix'] = $this->t('Currently served from @path', [
        '@path' => $path,
      ]);
    }

    $form['order'] = [
      '#type' => 'radios',
      '#title' => $this->t('When to run'),
      '#options' => [
        InjectedJavascript::ORDER_BEFORE_MOUNT => $this->t('Before mount.js, while the React apps have not been mounted yet'),
        InjectedJavascript::ORDER_AFTER_MOUNT => $this->t('After mount.js, once the React apps are mounted'),
      ],
      '#config_target' => InjectedJavascript::CONFIG_ID . ':order',
    ];

    $form['hook_to_react'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Load hook-to-react.js'),
      '#config_target' => InjectedJavascript::CONFIG_ID . ':hook_to_react',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);
    $this->injectedJavascript->save((string) $form_state->getValue('javascript'));
  }

}
