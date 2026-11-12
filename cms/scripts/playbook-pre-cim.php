<?php

/**
 * @file
 * Kulturnat pre-cim: reset config_ignore_auto from sync (Korsør playbook).
 *
 * Run after updb and reset-config-ignore-from-sync.php, before protect-* scripts.
 * updb hooks can flatten ignore lists; this restores the repo baseline so
 * protect-kulturnat-kultur-config.php can merge EO patterns on top.
 *
 * Usage: drush php:script scripts/playbook-pre-cim.php
 */

declare(strict_types=1);

use Drupal\Core\Config\FileStorage;

$sync = new FileStorage(DRUPAL_ROOT . '/../config/sync');
$data = $sync->read('config_ignore_auto.settings');
if ($data) {
  \Drupal::configFactory()->getEditable('config_ignore_auto.settings')
    ->setData($data)
    ->save(TRUE);
  echo "Imported config_ignore_auto.settings from config/sync.\n";
}
else {
  echo "No config_ignore_auto.settings in sync; active config unchanged.\n";
}
