<?php

/**
 * @file
 * Remove field_address_dawa and address_dawa from active config (module gone).
 *
 * Mirrors dpl_update_update_10076() + 10077() — required when dpl_update is pinned
 * at 10081 before updb (Slagbib playbook).
 *
 * Usage: drush php:script scripts/remove-address-dawa-remnants.php
 */

declare(strict_types=1);

use Drupal\Core\Database\Database;

$config_factory = \Drupal::configFactory();
$deleted = [];

$field_names = $config_factory->listAll('field.field.');
$field_names[] = 'field.storage.node.field_address_dawa';

foreach ($field_names as $config_name) {
  if (!str_ends_with($config_name, '.field_address_dawa')) {
    continue;
  }
  $config = $config_factory->getEditable($config_name);
  if ($config->isNew()) {
    continue;
  }
  $config->delete();
  $deleted[] = $config_name;
}

$schema = Database::getConnection()->schema();
foreach (['node__field_address_dawa', 'node_revision__field_address_dawa'] as $table) {
  if ($schema->tableExists($table)) {
    $schema->dropTable($table);
    $deleted[] = $table;
  }
}

foreach ($config_factory->listAll('core.entity_') as $config_name) {
  if (!str_contains($config_name, '.branch.')) {
    continue;
  }
  $config = $config_factory->getEditable($config_name);
  if ($config->isNew()) {
    continue;
  }
  $data = $config->getRawData();
  $changed = FALSE;
  if (isset($data['dependencies']['config'])) {
    $before = count($data['dependencies']['config']);
    $data['dependencies']['config'] = array_values(array_filter(
      $data['dependencies']['config'],
      static fn (string $dep): bool => !str_contains($dep, 'field_address_dawa'),
    ));
    if (count($data['dependencies']['config']) !== $before) {
      $changed = TRUE;
    }
  }
  foreach (['content', 'hidden'] as $section) {
    if (isset($data[$section]['field_address_dawa'])) {
      unset($data[$section]['field_address_dawa']);
      $changed = TRUE;
    }
  }
  if ($changed) {
    $config->setData($data)->save();
    $deleted[] = $config_name;
  }
}

$extension = $config_factory->getEditable('core.extension');
$modules = $extension->get('module') ?? [];
if (isset($modules['address_dawa'])) {
  unset($modules['address_dawa']);
  asort($modules);
  $extension->set('module', $modules)->save();
  $deleted[] = 'core.extension.module.address_dawa';
}

$storage = $config_factory->getEditable('field.storage.node.field_address_dawa');
if (!$storage->isNew()) {
  $storage->delete();
  $deleted[] = 'field.storage.node.field_address_dawa';
}

echo $deleted === []
  ? "No field_address_dawa / address_dawa remnants.\n"
  : 'Removed: ' . implode(', ', $deleted) . "\n";
