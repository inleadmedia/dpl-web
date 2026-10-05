<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_events\EventSubscriber;

use Drupal\Core\Url;
use Drupal\eonext_kultur_events\EventsConstants;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sends the old events view URL to the calendar content page.
 */
final class EventsListingRedirectSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['redirectLegacyListing', 30],
    ];
  }

  /**
   * Redirects /events to the content page that embeds the listing.
   */
  public function redirectLegacyListing(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();
    if (!$request->isMethodCacheable() || $request->isXmlHttpRequest()) {
      return;
    }

    if ($request->getPathInfo() !== '/events') {
      return;
    }

    $url = Url::fromUserInput(EventsConstants::LISTING_ALIAS, [
      'query' => $request->query->all(),
    ])->toString();
    $event->setResponse(new RedirectResponse($url, 301));
  }

}
