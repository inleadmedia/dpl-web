<?php

declare(strict_types=1);

namespace Drupal\eonext_staff;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * Defines the access control handler for the library staff entity type.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 *
 * @see https://www.drupal.org/project/coder/issues/3185082
 */
final class LibraryStaffAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    $admin_permission = $this->entityType->getAdminPermission();
    if (is_string($admin_permission) && $account->hasPermission($admin_permission)) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    $result = match ($operation) {
      'view' => AccessResult::allowedIfHasPermission($account, 'access content'),
      'update' => AccessResult::allowedIfHasPermission($account, 'edit eonext_library_staff'),
      'delete' => AccessResult::allowedIfHasPermission($account, 'delete eonext_library_staff'),
      default => AccessResult::neutral(),
    };

    if ($operation === 'update' && $entity instanceof LibraryStaffInterface) {
      $owner = $entity->get('uid')->entity;
      if ($owner instanceof UserInterface) {
        $result = $result->orIf(
          AccessResult::allowedIf($owner->access('update', $account))
            ->addCacheableDependency($owner)
            ->cachePerUser()
        );
      }
      $result = $result->addCacheableDependency($entity);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   * @param mixed[] $context
   *   Additional context for create access.
   * @param string|null $entity_bundle
   *   The entity bundle, if applicable.
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermissions($account, ['edit eonext_library_staff', 'administer eonext_library_staff'], 'OR')
      ->orIf(AccessResult::allowedIf($account->isAuthenticated())->cachePerUser());
  }

}
