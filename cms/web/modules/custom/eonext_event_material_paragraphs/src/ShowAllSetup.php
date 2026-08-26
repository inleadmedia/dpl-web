<?php

declare(strict_types=1);

namespace Drupal\eonext_event_material_paragraphs;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Installs "Show all" field display and GraphQL configuration per bundle.
 *
 * Only the form display is configured: every supported bundle replaces its
 * rendered content with a React app or a related-content list, so the fields
 * are never shown through the view display.
 */
final class ShowAllSetup {

  /**
   * Form widget components for the behavior + link fields.
   */
  private const FORM_COMPONENTS = [
    ShowAllConfig::FIELD_BEHAVIOR => [
      'type' => 'options_buttons',
      'weight' => 50,
      'region' => 'content',
      'settings' => [],
    ],
    ShowAllConfig::FIELD_LINK => [
      'type' => 'link_default',
      'weight' => 51,
      'region' => 'content',
      'settings' => [
        'placeholder_url' => '',
        'placeholder_title' => '',
      ],
    ],
  ];

  /**
   * Constructs the setup service.
   */
  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Runs all "Show all" setup tasks for every supported bundle.
   */
  public function install(): void {
    foreach (EventMaterialParagraphBundles::SHOW_ALL as $bundle) {
      $this->configureFormDisplay($bundle);
      $this->enableGraphqlComposeFields($bundle);
    }
  }

  /**
   * Adds the behavior and link fields to a bundle form display.
   *
   * @param string $bundle
   *   The paragraph bundle machine name.
   */
  private function configureFormDisplay(string $bundle): void {
    $display = EntityFormDisplay::load("paragraph.$bundle.default");
    if (!$display instanceof EntityFormDisplayInterface) {
      return;
    }

    foreach (self::FORM_COMPONENTS as $field_name => $component) {
      $display->setComponent($field_name, $component);
    }

    $display->save();
  }

  /**
   * Exposes the behavior and link fields in GraphQL Compose.
   *
   * @param string $bundle
   *   The paragraph bundle machine name.
   */
  private function enableGraphqlComposeFields(string $bundle): void {
    if (!$this->moduleHandler->moduleExists('graphql_compose')) {
      return;
    }

    $config = $this->configFactory->getEditable('graphql_compose.settings');
    $config_key = "field_config.paragraph.$bundle";
    $field_config = $config->get($config_key) ?? [];

    foreach ([ShowAllConfig::FIELD_BEHAVIOR, ShowAllConfig::FIELD_LINK] as $field_name) {
      $field_config[$field_name] = ['enabled' => TRUE];
    }

    $config->set($config_key, $field_config);
    $config->save(TRUE);
  }

}
