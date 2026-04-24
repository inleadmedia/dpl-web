<?php

namespace Drupal\eonext_translation;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Translation service.
 */
class TranslationService implements TranslationServiceInterface {


  /**
   * The translation settings.
   *
   * @var string
   */
  const TRANSLATION_SETTINGS = 'eonext_translation.settings';

  /**
   * Footer config settings.
   *
   * @var string
   */
  const FOOTER_SETTINGS = 'eonext_translation.footer';

  /**
   * The footer state key.
   *
   * @var string
   */
  const FOOTER_SETTINGS_STATE = 'dpl_footer_values';

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new TranslationService object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(
    StateInterface $state,
    ConfigFactoryInterface $config_factory,
    LanguageManagerInterface $language_manager,
    PathMatcherInterface $path_matcher,
    RouteMatchInterface $route_match) {
    $this->state = $state;
    $this->configFactory = $config_factory;
    $this->languageManager = $language_manager;
    $this->pathMatcher = $path_matcher;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public function getFooterState(): array {
    return $this->state->get(static::FOOTER_SETTINGS_STATE, []);
  }

  /**
   * {@inheritdoc}
   */
  public function saveFooterToConfig(): void {

    $footerState = $this->getFooterState();

    // Save the footer settings.
    $config = $this->configFactory->getEditable(static::FOOTER_SETTINGS);
    $config
      ->set('footer_items', $footerState['footer_items'])
      ->set('secondary_links', $footerState['secondary_links'])
      ->set('facebook', $footerState['facebook'])
      ->set('instagram', $footerState['instagram'])
      ->set('youtube', $footerState['youtube'])
      ->set('spotify', $footerState['spotify'])
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getFootersettings(): array {
    $config = $this->configFactory->get(static::FOOTER_SETTINGS) ?: [];
    $data = $config->get();

    // Remove the _core and langcode key.
    unset($data['_core']);
    unset($data['langcode']);

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguageLinks(): array {

    // Generate the language links.
    $languageManagerLinks = $this
      ->languageManager
      ->getLanguageSwitchLinks('language_interface', $this->getCurrentUrl());

    $enabledLanguages = $this->getEnabledLanguages();

    $links = [];

    // Sort the enabledLanguages, set the active language first.
    foreach ($enabledLanguages as $langCode => $langName) {

      $langUrl = $languageManagerLinks->links[$langCode]['url'] ?? FALSE;

      // This may happen if the language is enabled in eonext config
      // but disabled later in the language manager.
      if (!$langUrl) {
        continue;
      }

      $langUrl->setOption('language', $languageManagerLinks->links[$langCode]['language']);

      $links[$langCode] = [
        'name' => $langName,
        'path' => $langUrl->toString(),
      ];
    }

    return $links;
  }

  /**
   * Get the current URL.
   *
   * @return \Drupal\Core\Url
   *   The current URL.
   */
  private function getCurrentUrl(): Url {

    $url = NULL;

    if ($this->pathMatcher->isFrontPage() || !$this->routeMatch->getRouteObject()) {
      // We are skipping the route match on both 404 and front page.
      // Example: If on front page, there is no route match like when creating
      // blocks on 404 pages for logged-in users with big_pipe enabled, use the
      // front page.
      $url = Url::fromRoute('<front>');
    }
    else {
      $url = Url::fromRouteMatch($this->routeMatch);
    }

    return $url;
  }

  /**
   * Get the enabled languages.
   *
   * @return array
   *   The enabled languages.
   */
  private function getEnabledLanguages(): array {

    $currentLanguage = $this
      ->languageManager
      ->getCurrentLanguage()
      ->getId();

    $defaultLanguage = $this
      ->languageManager
      ->getDefaultLanguage()
      ->getId();

    $enabledLanguages = $this
      ->configFactory
      ->get(static::TRANSLATION_SETTINGS)
      ->get('drupal_translation_available_languages') ?: [];

    // We are storing an array of language ids/codes in the config,
    // Converting it to an array of ids as keys and language names as values.
    // And translate the language names to the current interface language.
    $enabledLanguages = array_combine($enabledLanguages, array_map(function ($lang) {
      return $this->languageManager->getLanguageName($lang);
    }, $enabledLanguages));

    // Sort the enabledLanguages, set the active language first.
    uksort($enabledLanguages, function ($a, $b) use ($currentLanguage, $defaultLanguage) {
      if ($a === $currentLanguage) {
        return -1;
      }
      if ($b === $currentLanguage) {
        return 1;
      }
      if ($a === $defaultLanguage) {
        return -1;
      }
      if ($b === $defaultLanguage) {
        return 1;
      }
      return strcasecmp($a, $b);
    });

    return $enabledLanguages;
  }

  /**
   * {@inheritdoc}
   */
  public static function addBranchAttributes(array &$variables, NodeInterface|Null $branch = NULL): void {
    if ($branch) {
      $branch_label = $branch->label();
      $variables['attributes']['class'][] = Html::getClass('branch-name-' . $branch_label);
      $variables['attributes']['class'][] = Html::getClass('branch-id-' . $branch->id());

      $variables['attributes']['data-branch-name'] = $branch_label;
      $variables['attributes']['data-branch-id'] = $branch->id();
    }
    else {
      $variables['attributes']['class'][] = 'branch-name-default';
      $variables['attributes']['class'][] = 'branch-id-default';
      $variables['attributes']['data-branch-name'] = 'default';
      $variables['attributes']['data-branch-id'] = 'default';
    }
  }

}
