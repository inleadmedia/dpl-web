<?php

declare(strict_types=1);

namespace Drupal\eonext_opening_hours\EventSubscriber;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber to normalize opening hours category titles in API requests.
 *
 * Converts translated category titles back to their original language
 * so the backend can properly match them.
 */
class OpeningHoursRequestSubscriber implements EventSubscriberInterface {

  /**
   * Constructs an OpeningHoursRequestSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Priority 10 to run early, before the REST resource processes the request.
    return [
      KernelEvents::REQUEST => ['onRequest', 10],
    ];
  }

  /**
   * Normalizes category titles in opening hours API requests.
   *
   * Converts translated category titles to their original language so the
   * backend can find the corresponding taxonomy term.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event): void {
    $request = $event->getRequest();
    $pathInfo = $request->getPathInfo();

    // Only process opening hours API requests (with/without language prefix).
    if (strpos($pathInfo, '/api/v1/opening_hours') === FALSE) {
      return;
    }

    // Only process POST and PATCH requests (create/update operations).
    $method = $request->getMethod();
    if (!in_array($method, ['POST', 'PATCH'])) {
      return;
    }

    $content = $request->getContent();
    if (!$content) {
      return;
    }

    $data = json_decode($content, TRUE);
    if (!is_array($data) || !isset($data['category']['title'])) {
      return;
    }

    $translatedTitle = $data['category']['title'];

    // Convert the translated title to the original language.
    $originalTitle = $this->getOriginalCategoryTitle($translatedTitle);

    if ($originalTitle && $originalTitle !== $translatedTitle) {
      // Update the request content with the original title.
      $data['category']['title'] = $originalTitle;
      $request->initialize(
        $request->query->all(),
        $request->request->all(),
        $request->attributes->all(),
        $request->cookies->all(),
        $request->files->all(),
        $request->server->all(),
        json_encode($data)
      );
    }
  }

  /**
   * Gets the original (default language) title for a category.
   *
   * @param string $title
   *   The category title (potentially translated).
   *
   * @return string|null
   *   The original category title, or NULL if not found.
   */
  protected function getOriginalCategoryTitle(string $title): ?string {
    $taxonomyStorage = $this->entityTypeManager->getStorage('taxonomy_term');

    $allTerms = $taxonomyStorage->loadByProperties([
      'vid' => 'opening_hours_categories',
    ]);

    // Search through all terms and their translations to find a match.
    foreach ($allTerms as $term) {
      if (!($term instanceof ContentEntityInterface)) {
        continue;
      }

      // Check default language name.
      if ((string) $term->label() === $title) {
        return (string) $term->label();
      }

      // Check all translation names.
      foreach ($term->getTranslationLanguages() as $language) {
        $translatedTerm = $term->getTranslation($language->getId());
        if ((string) $translatedTerm->label() === $title) {
          // Return the original (default language) title.
          return (string) $term->label();
        }
      }
    }

    return NULL;
  }

}
