<?php

declare(strict_types=1);

namespace Drupal\eonext_opening_hours\EventSubscriber;

use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber to translate opening hours categories in API responses.
 */
class OpeningHoursResponseSubscriber implements EventSubscriberInterface {

  /**
   * Constructs an OpeningHoursResponseSubscriber object.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected LanguageManagerInterface $languageManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Priority 10 to run early, before cache is saved.
    return [
      KernelEvents::RESPONSE => ['onResponse', 10],
    ];
  }

  /**
   * Translates category titles in opening hours API responses.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onResponse(ResponseEvent $event): void {
    $response = $event->getResponse();
    $request = $event->getRequest();

    $pathInfo = $request->getPathInfo();

    // Only process opening hours API requests (with/without language prefix).
    if (strpos($pathInfo, '/api/v1/opening_hours') === FALSE) {
      return;
    }

    $content = $response->getContent();
    if (!$content) {
      return;
    }

    $data = json_decode($content, TRUE);
    if (!is_array($data) || empty($data)) {
      return;
    }

    // Get the current language from URL.
    $currentLanguage = $this
      ->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_URL)
      ->getId();

    $taxonomyStorage = $this
      ->entityTypeManager
      ->getStorage('taxonomy_term');

    $translationTemp = [];

    $allTerms = $taxonomyStorage->loadByProperties([
      'vid' => 'opening_hours_categories',
    ]);

    // Build a map of all possible names (in all languages) to their TIDs.
    $nameToTidMap = [];
    foreach ($allTerms as $term) {
      if (!($term instanceof ContentEntityInterface)) {
        continue;
      }
      // Add default language name.
      $nameToTidMap[(string) $term->label()] = $term->id();

      // Add translated names from all available translations.
      foreach ($term->getTranslationLanguages() as $language) {
        $translatedTerm = $term->getTranslation($language->getId());
        $nameToTidMap[(string) $translatedTerm->label()] = $term->id();
      }
    }

    // Translate categories in each opening hours instance.
    foreach ($data as &$instance) {
      if (!isset($instance['category']['title'])) {
        continue;
      }

      $originalTitle = $instance['category']['title'];

      // Check if we've already translated this category.
      if (!isset($translationTemp[$originalTitle])) {
        // Find the term ID from any language version of the name.
        $tid = $nameToTidMap[$originalTitle] ?? NULL;

        if ($tid && isset($allTerms[$tid])) {
          $term = $allTerms[$tid];
          if ($term instanceof ContentEntityInterface && $term->hasTranslation($currentLanguage)) {
            $translatedTerm = $term->getTranslation($currentLanguage);
            $translationTemp[$originalTitle] = (string) $translatedTerm->label();
          }
          else {
            // Keep the original if no translation exists.
            $translationTemp[$originalTitle] = $originalTitle;
          }
        }
        else {
          // Term not found, keep original.
          $translationTemp[$originalTitle] = $originalTitle;
        }
      }

      $instance['category']['title'] = $translationTemp[$originalTitle];
    }

    // Update the response content.
    $response->setContent(json_encode($data));

    // Add language cache context to ensure responses are cached per language.
    if ($response instanceof CacheableResponseInterface) {
      $response->getCacheableMetadata()->addCacheContexts(['languages:language_url']);
    }
  }

}
