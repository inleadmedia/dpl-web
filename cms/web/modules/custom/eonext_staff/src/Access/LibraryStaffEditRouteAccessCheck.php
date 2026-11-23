<?php

declare(strict_types=1);

namespace Drupal\eonext_staff\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Allows staff profile edit when the user may edit or administer library staff.
 */
final class LibraryStaffEditRouteAccessCheck implements AccessInterface {

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): AccessResult {
    return AccessResult::allowedIfHasPermissions(
      $account,
      ['edit eonext_library_staff', 'administer eonext_library_staff'],
      'OR',
    )->cachePerPermissions();
  }

}