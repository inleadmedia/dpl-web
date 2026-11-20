<?php

/**
 * @file
 * Korsør: after `task drupal:upgrade` — re-grant prod role permissions cim dropped.
 *
 * Usage: drush php:script scripts/korsor-post-upgrade.php
 */

declare(strict_types=1);

use Drupal\user\Entity\Role;

$state = \Drupal::state();
$saved = $state->get('korsor_upgrade.role_permissions') ?: [];
$available = \Drupal::service('user.permissions')->getPermissions();
foreach ($saved as $name => $permissions) {
  $role = Role::load(substr($name, strlen('user.role.')));
  if (!$role) {
    continue;
  }
  $missing = array_filter(
    array_diff($permissions, $role->getPermissions()),
    static fn (string $permission): bool => isset($available[$permission]),
  );
  if ($missing) {
    foreach ($missing as $permission) {
      $role->grantPermission($permission);
    }
    $role->save();
    echo "{$name}: re-granted " . implode(', ', $missing) . "\n";
  }
}
$state->delete('korsor_upgrade.role_permissions');

if (\Drupal::moduleHandler()->moduleExists('dk_address_gsearch_migrate')) {
  \Drupal::service('module_installer')->uninstall(['dk_address_gsearch_migrate']);
  echo "Uninstalled dk_address_gsearch_migrate (gsearch kept).\n";
}

$etm = \Drupal::entityTypeManager();
foreach (['organization', 'organization_event', 'municipality'] as $type) {
  echo $type . '=' . $etm->getStorage($type)->getQuery()->accessCheck(FALSE)->count()->execute() . ' ';
}
echo 'default theme=' . \Drupal::config('system.theme')->get('default') . "\n";
