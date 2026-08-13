<?php

declare(strict_types=1);

namespace Drupal\eonext_event_engine;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access control handler for organization event entities.
 */
class OrganizationEventAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    assert($entity instanceof OrganizationEventInterface);

    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished organization event');
        }
        return AccessResult::allowedIfHasPermission($account, 'view organization event');

      case 'update':
        if ($account->hasPermission('edit any organization event')) {
          return AccessResult::allowed()->cachePerPermissions();
        }
        return AccessResult::allowedIf($account->id() && $account->id() === $entity->getOwnerId())
          ->andIf(AccessResult::allowedIfHasPermission($account, 'edit own organization event'))
          ->cachePerPermissions()
          ->cachePerUser()
          ->addCacheableDependency($entity);

      case 'delete':
        if ($account->hasPermission('delete any organization event')) {
          return AccessResult::allowed()->cachePerPermissions();
        }
        return AccessResult::allowedIf($account->id() && $account->id() === $entity->getOwnerId())
          ->andIf(AccessResult::allowedIfHasPermission($account, 'delete own organization event'))
          ->cachePerPermissions()
          ->cachePerUser()
          ->addCacheableDependency($entity);

      default:
        return AccessResult::neutral();
    }
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermission($account, 'create organization event');
  }

}
