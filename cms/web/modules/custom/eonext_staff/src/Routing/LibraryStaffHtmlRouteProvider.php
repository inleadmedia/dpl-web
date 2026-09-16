<?php

declare(strict_types=1);

namespace Drupal\eonext_staff\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides HTML routes for library staff entities.
 */
final class LibraryStaffHtmlRouteProvider extends DefaultHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getCanonicalRoute(EntityTypeInterface $entity_type): ?Route {
    if ($route = parent::getCanonicalRoute($entity_type)) {
      $route->setDefault('_controller', '\Drupal\eonext_staff\Controller\LibraryStaffController::profile');
      $route->setDefault('_title_callback', '\Drupal\eonext_staff\Controller\LibraryStaffController::staffTitle');
      return $route;
    }

    return NULL;
  }

}
