<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_main\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sends prefix-less public URLs to Greenlandic, matching kulturnat.gl.
 *
 * kulturnat.gl answers "/" and "/program" with a redirect to "/kl" and
 * "/kl/program". Danish stays on the /da prefix.
 */
final class DefaultLanguageRedirectSubscriber implements EventSubscriberInterface {

  /**
   * Path prefixes that must keep working without a language prefix.
   *
   * @var string[]
   */
  private const EXCLUDED_PREFIXES = [
    '/admin',
    '/user',
    '/batch',
    '/system',
    '/core',
    '/modules',
    '/themes',
    '/sites',
    '/libraries',
    '/graphql',
    '/jsonapi',
    '/oauth',
    '/openid-connect',
    '/devel',
    '/toolbar',
    '/contextual',
    '/history',
    '/quickedit',
    '/entity-reference',
    '/views/ajax',
    '/media-library',
    '/session',
    '/webform',
  ];

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // After routing language negotiation (high priority) and before the page
    // is rendered.
    return [
      KernelEvents::REQUEST => ['onRequest', 30],
    ];
  }

  /**
   * Redirects public GET requests that have no language prefix to /kl.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();
    if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
      return;
    }
    if ($request->isXmlHttpRequest()) {
      return;
    }

    $path = $request->getPathInfo();
    if ($path === '' || $this->isExcluded($path) || $this->hasLanguagePrefix($path)) {
      return;
    }

    $target = '/kl' . ($path === '/' ? '' : $path);
    $query = $request->getQueryString();
    if (is_string($query) && $query !== '') {
      $target .= '?' . $query;
    }

    $event->setResponse(new RedirectResponse($request->getBaseUrl() . $target, 302));
  }

  /**
   * Whether the path is an admin, asset, or machine route.
   */
  private function isExcluded(string $path): bool {
    if (preg_match('/\.[a-z0-9]{1,8}$/i', $path) === 1) {
      return TRUE;
    }

    foreach (self::EXCLUDED_PREFIXES as $prefix) {
      if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Whether the first URL segment is a configured language prefix.
   */
  private function hasLanguagePrefix(string $path): bool {
    $segment = explode('/', trim($path, '/'))[0] ?? '';
    if ($segment === '') {
      return FALSE;
    }

    $prefixes = $this->configFactory->get('language.negotiation')->get('url.prefixes') ?? [];
    if (!is_array($prefixes)) {
      return FALSE;
    }

    foreach ($prefixes as $prefix) {
      if (is_string($prefix) && $prefix !== '' && $prefix === $segment) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
