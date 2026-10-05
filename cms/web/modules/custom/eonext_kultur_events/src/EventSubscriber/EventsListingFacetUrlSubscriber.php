<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_events\EventSubscriber;

use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Url;
use Drupal\eonext_kultur_events\EventsConstants;
use Drupal\facets\Event\UrlCreated;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Keeps events facet links on the page that embeds the listing.
 */
final class EventsListingFacetUrlSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly RequestStack $requestStack,
    private readonly CurrentPathStack $currentPath,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      UrlCreated::class => 'onUrlCreated',
    ];
  }

  /**
   * Points facet links at the embedding page instead of /events.
   */
  public function onUrlCreated(UrlCreated $event): void {
    if ($event->getFacet()->getFacetSourceId() !== EventsConstants::FACET_SOURCE_ID) {
      return;
    }

    $request = $this->requestStack->getCurrentRequest();
    if ($request === NULL || !$request->attributes->get('eonext_kultur_events_listing')) {
      return;
    }

    $query = $event->getUrl()->getOption('query') ?? [];
    $path = $request->attributes->get('_route') === 'views.ajax'
      ? $this->listingPagePath()
      : NULL;

    if ($path !== NULL) {
      $event->setUrl(Url::fromUserInput($path, ['query' => $query]));
      return;
    }

    $event->setUrl(Url::fromRoute('<current>', [], ['query' => $query]));
  }

  /**
   * Returns the listing page path during a Views AJAX request.
   */
  private function listingPagePath(): ?string {
    $path = $this->currentPath->getPath();
    if ($path === '' || $path === '/views/ajax') {
      return NULL;
    }

    return $path;
  }

}
