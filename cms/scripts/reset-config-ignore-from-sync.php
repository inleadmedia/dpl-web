<?php

/**
 * @file
 * Reset active config_ignore.settings from cms/config/sync.
 *
 * Run after updb and before cim — update hooks can flatten import/export lists.
 *
 * Usage: drush php:script scripts/reset-config-ignore-from-sync.php
 */

declare(strict_types=1);

use Drupal\Core\Config\FileStorage;

$sync = new FileStorage(DRUPAL_ROOT . '/../config/sync');
$data = $sync->read('config_ignore.settings');
if (!$data) {
  throw new \RuntimeException('config/sync/config_ignore.settings.yml missing.');
}

\Drupal::configFactory()->getEditable('config_ignore.settings')
  ->setData($data)
  ->save(TRUE);

echo "config_ignore.settings reset from config/sync (mode=" . ($data['mode'] ?? '?') . ").\n";
