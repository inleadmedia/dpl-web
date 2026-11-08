<?php

declare(strict_types=1);

namespace Drupal\eonext_staff;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
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
    if ($account->hasPermission($this->entityType->getAdminPermission())) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    return match($operation) {
      'view' => $this->checkViewAccess($entity, $account),
      'update' => AccessResult::allowedIfHasPermission($account, 'edit eonext_library_staff'),
      'delete' => AccessResult::allowedIfHasPermission($account, 'delete eonext_library_staff'),
      default => AccessResult::neutral(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermissions($account, ['edit eonext_library_staff', 'administer eonext_library_staff'], 'OR');
  }

  /**
   * Checks view access, hiding staff of blocked users from the public.
   */
  private function checkViewAccess(EntityInterface $entity, AccountInterface $account): AccessResultInterface {
    $user = $entity->get('uid')->entity;
    $result = AccessResult::allowedIfHasPermission($account, 'access content')
      ->cachePerPermissions()
      ->addCacheableDependency($entity);

    if (!$user instanceof UserInterface) {
      return $result;
    }

    $result->addCacheableDependency($user);

    // Staff linked to a blocked account is not publicly viewable.
    if (!$user->isActive()) {
      $manage_access = AccessResult::allowedIfHasPermissions($account, [
        'edit eonext_library_staff',
        'delete eonext_library_staff',
      ], 'OR')
        ->cachePerPermissions()
        ->addCacheableDependency($entity)
        ->addCacheableDependency($user);

      if ($manage_access->isAllowed()) {
        return $manage_access;
      }

      return AccessResult::forbidden('The staff profile is not available because the linked user account is blocked.')
        ->cachePerPermissions()
        ->addCacheableDependency($entity)
        ->addCacheableDependency($user);
    }

    return $result;
  }

}
