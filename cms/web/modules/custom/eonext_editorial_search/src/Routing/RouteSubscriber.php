<?php

declare(strict_types=1);

namespace Drupal\eonext_editorial_search\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Opens the editorial search REST endpoint without touching DPL's roles.
 *
 * The REST module protects every resource with a generated
 * "restful get <plugin>" permission. Granting that permission means editing
 * DPL CMS' own anonymous and authenticated roles, which puts them out of sync
 * with the platform and pins them in config_ignore_auto. The endpoint only
 * exposes published, already public content, so we drop the permission
 * requirement on our own route instead.
 */
class RouteSubscriber extends RouteSubscriberBase {

  private const ROUTE_NAME = 'rest.eonext_editorial_search.editorial_search.GET';

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $route = $collection->get(self::ROUTE_NAME);
    if ($route === NULL) {
      return;
    }

    $requirements = $route->getRequirements();
    unset($requirements['_permission'], $requirements['_csrf_request_header_token']);
    $requirements['_access'] = 'TRUE';

    $route->setRequirements($requirements);
  }

}
