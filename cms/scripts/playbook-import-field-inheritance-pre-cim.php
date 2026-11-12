<?php

/**
 * @file
 * Seed field_inheritance from sync before cim (prod DBs often lack rows).
 *
 * search_api.index.events references event_audiences via field_inheritance; cim
 * fails on views.view.webform_submissions when the inheritance config is missing.
 * protect-kulturnat-kultur-config ignores these on import — so active store must
 * be filled here.
 *
 * Usage: drush php:script scripts/playbook-import-field-inheritance-pre-cim.php
 */

declare(strict_types=1);

use Drupal\Core\Config\FileStorage;

$sync_dir = DRUPAL_ROOT . '/../config/sync';
if (!is_dir($sync_dir)) {
  echo "No config/sync — skip field_inheritance seed.\n";
  return;
}
$sync = new FileStorage($sync_dir);

$imported = [];
foreach ([
  'field_inheritance.config',
  ...$sync->listAll('field_inheritance.field_inheritance.eventinstance_default_'),
] as $name) {
  if (!$sync->exists($name)) {
    continue;
  }
  $active = \Drupal::configFactory()->getEditable($name);
  if (!$active->isNew()) {
    continue;
  }
  $data = $sync->read($name);
  if (!is_array($data)) {
    continue;
  }
  $active->setData($data)->save(TRUE);
  $imported[] = $name;
}

echo $imported === []
  ? "field_inheritance: nothing missing in active config.\n"
  : 'Imported field_inheritance from sync: ' . implode(', ', $imported) . "\n";
