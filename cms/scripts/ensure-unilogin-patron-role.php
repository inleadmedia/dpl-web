<?php

/**
 * @file
 * Create unilogin_patron when missing (deploy hook expects it; Korsør sync may omit it).
 *
 * Usage: drush php:script scripts/ensure-unilogin-patron-role.php
 */

declare(strict_types=1);

use Drupal\user\Entity\Role;

$storage = \Drupal::entityTypeManager()->getStorage('user_role');
if ($storage->load('unilogin_patron')) {
  echo "Role unilogin_patron already exists.\n";
  return;
}

$data = \Drupal::service('config.storage.sync')->read('user.role.unilogin_patron');
if (is_array($data)) {
  $storage->create($data)->save();
  echo "Created unilogin_patron from config sync.\n";
  return;
}

Role::create([
  'id' => 'unilogin_patron',
  'label' => 'Unilogin patron',
  'weight' => 6,
  'is_admin' => FALSE,
])->save();
echo "Created minimal unilogin_patron role.\n";
