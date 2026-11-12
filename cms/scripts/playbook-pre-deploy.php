<?php

/**
 * @file
 * Pre-updb: config_ignore from sync + pin dpl_update schema (Korsør playbook).
 *
 * Kulturnat already uses Danish/Kalaallisut events, so dpl_update_update_10074
 * (flip en → da) is unsafe. Hooks 10076–10077 are handled by
 * remove-address-dawa-remnants.php instead.
 *
 * Usage: drush php:script scripts/playbook-pre-deploy.php
 * Opt out: PLAYBOOK_PIN_DPL_UPDATE=0
 */

declare(strict_types=1);

use Drupal\Core\Config\FileStorage;

$sync = new FileStorage(DRUPAL_ROOT . '/../config/sync');
$data = $sync->read('config_ignore.settings');
if ($data) {
  \Drupal::configFactory()->getEditable('config_ignore.settings')
    ->setData($data)
    ->save(TRUE);
  echo "Imported config_ignore.settings from config/sync.\n";
}

if (getenv('PLAYBOOK_PIN_DPL_UPDATE') === '0') {
  echo "PLAYBOOK_PIN_DPL_UPDATE=0 — not pinning dpl_update schema.\n";
  return;
}

$current = (int) (\Drupal::keyValue('system.schema')->get('dpl_update') ?? 0);
if ($current >= 10081) {
  echo "dpl_update schema already at {$current} (>= 10081); no pin needed.\n";
  return;
}

\Drupal::keyValue('system.schema')->set('dpl_update', 10081);
echo "Pinned system.schema dpl_update to 10081 (skips 10074–10081; address_dawa cleanup is separate).\n";
echo "Before cim, ensure modules from skipped hooks exist if your site needs them ";
echo "(e.g. facets_date_range, dpl_biblio, dpl_metrics).\n";
