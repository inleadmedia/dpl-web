<?php

/**
 * @file
 * Uninstall and reinstall easyemail so owned overlay + field schema match D11 code.
 *
 * Destructive: deletes all newsletters and subscribers. Backs up and restores
 * easyemail.settings, easyemail.sender, and easyemail.tokens (credentials / copy).
 *
 * Usage: drush php:script scripts/reinstall-easyemail-for-upgrade.php
 * Skip:  SKIP_EASYEMAIL_REINSTALL=1 in the shell (playbook respects this).
 */

declare(strict_types=1);

if (getenv('SKIP_EASYEMAIL_REINSTALL') === '1') {
  echo "SKIP_EASYEMAIL_REINSTALL=1 — skipping easyemail reinstall.\n";
  return;
}

$owned_config = [
  'easyemail.settings',
  'easyemail.sender',
  'easyemail.tokens',
];

$backup = [];
$config_factory = \Drupal::configFactory();
foreach ($owned_config as $name) {
  $config = $config_factory->get($name);
  if (!$config->isNew()) {
    $backup[$name] = $config->getRawData();
  }
}

$entity_types = [
  'easyemail_newsletter',
  'easyemail_subscriber',
];
$entity_type_manager = \Drupal::entityTypeManager();
foreach ($entity_types as $entity_type_id) {
  if (!$entity_type_manager->hasDefinition($entity_type_id)) {
    continue;
  }
  $storage = $entity_type_manager->getStorage($entity_type_id);
  $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
  if ($ids === []) {
    continue;
  }
  $entities = $storage->loadMultiple($ids);
  $storage->delete($entities);
  echo 'Deleted ' . count($ids) . ' ' . $entity_type_id . " entity(ies).\n";
}

$module_handler = \Drupal::moduleHandler();
$module_installer = \Drupal::service('module_installer');

$dependents = [];
foreach (['easyemail_flaskepost'] as $module) {
  if ($module_handler->moduleExists($module)) {
    $dependents[] = $module;
  }
}
if ($dependents !== []) {
  $module_installer->uninstall($dependents);
  echo 'Uninstalled dependent module(s): ' . implode(', ', $dependents) . ".\n";
}

if (!$module_handler->moduleExists('easyemail')) {
  $module_installer->install(['easyemail']);
  echo "Installed easyemail (was not enabled).\n";
}
else {
  $module_installer->uninstall(['easyemail']);
  echo "Uninstalled easyemail.\n";
  $module_installer->install(['easyemail']);
  echo "Reinstalled easyemail (owned config + field schema from module overlay).\n";
}

foreach ($backup as $name => $data) {
  $config_factory->getEditable($name)->setData($data)->save();
  echo "Restored {$name}.\n";
}

\Drupal::service('cache_tags.invalidator')->invalidateTags([
  'config:easyemail.settings',
  'config:easyemail.sender',
  'config:easyemail.tokens',
]);
