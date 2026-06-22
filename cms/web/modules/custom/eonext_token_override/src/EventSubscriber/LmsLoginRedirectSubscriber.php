<?php

declare(strict_types=1);

namespace Drupal\eonext_token_override\EventSubscriber;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\eonext_token_override\Lms\LmsAuthorizationUrlBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Rewrites LMS OAuth authorize redirects to use a fully encoded redirect_uri.
 */
final class LmsLoginRedirectSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::RESPONSE => ['onResponse', -100],
    ];
  }

  /**
   * Fixes redirect_uri encoding on the patron login redirect to LMS.
   */
  public function onResponse(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    if ($event->getRequest()->attributes->get('_route') !== 'dpl_login.login') {
      return;
    }

    if (!$this->usesLmsUserApi()) {
      return;
    }

    $response = $event->getResponse();
    if (!$response instanceof TrustedRedirectResponse) {
      return;
    }

    $parsed = UrlHelper::parse($response->getTargetUrl());
    $query = $parsed['query'] ?? [];
    if (empty($query['redirect_uri']) || empty($query['state'])) {
      return;
    }

    $authorizationEndpoint = $parsed['path'] ?? '';
    if ($authorizationEndpoint === '') {
      return;
    }

    $targetUrl = LmsAuthorizationUrlBuilder::build($authorizationEndpoint, [
      'client_id' => $query['client_id'] ?? '',
      'response_type' => $query['response_type'] ?? '',
      'scope' => $query['scope'] ?? '',
      'redirect_uri' => $query['redirect_uri'],
      'state' => $query['state'] ?? NULL,
      'prompt' => $query['prompt'] ?? NULL,
      'agency' => $query['agency'] ?? NULL,
    ]);

    $response->setTrustedTargetUrl($targetUrl);
  }

  /**
   * Whether the site uses Cicero LMS as the OAuth authorization proxy.
   */
  private function usesLmsUserApi(): bool {
    return (bool) $this->configFactory
      ->get('dpl_library_agency.general_settings')
      ->get('use_lms_user_api');
  }

}
