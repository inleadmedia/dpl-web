<?php

namespace Drupal\eonext_translation\EventSubscriber;

use Drupal\dpl_go\GoSite;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Event subscriber to clean language prefixes from GO redirects.
 */
class GoRedirectCleanerSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(protected GoSite $goSite) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      // Listen for the response to modify redirect URLs.
      'kernel.response' => [
        ['cleanRedirectResponse', -10],
      ],
    ];
  }

  /**
   * Clean language prefixes from redirect responses.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function cleanRedirectResponse(ResponseEvent $event) {
    $response = $event->getResponse();

    // Only process redirect responses.
    if (!$response instanceof RedirectResponse) {
      return;
    }

    $targetUrl = $response->getTargetUrl();

    // Check if this is a redirect to the GO site with a language prefix.
    $goBaseUrl = $this->goSite->getGoBaseUrl();
    if (strpos($targetUrl, $goBaseUrl) === 0) {
      // Remove the GO base URL to get just the path part.
      $path = substr($targetUrl, strlen($goBaseUrl));

      // Check if the path has a language prefix.
      if (preg_match('/^\/[a-z]{2}\//', $path)) {
        $cleanPath = preg_replace('/^\/[a-z]{2}\//', '/', $path);
        $cleanUrl = $goBaseUrl . $cleanPath;

        // Create a new redirect response with the cleaned URL.
        $cleanResponse = new RedirectResponse($cleanUrl, $response->getStatusCode());
        $event->setResponse($cleanResponse);
      }
    }
  }

}
