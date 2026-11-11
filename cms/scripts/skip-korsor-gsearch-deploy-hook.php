<?php

/**
 * @file
 * Korsør: keep gsearch; mark dpl_update_deploy_uninstall_gsearch as done.
 *
 * DPL deploy hook uninstalls gsearch after dk_address migration. Korsør
 * (Event Engine / organization_event) still uses field_*_address_gsearch.
 *
 * Usage: drush php:script scripts/skip-korsor-gsearch-deploy-hook.php
 */

declare(strict_types=1);

$kv = \Drupal::keyValue('deploy_hook');
$done = $kv->get('existing_updates') ?: [];
$name = 'dpl_update_deploy_uninstall_gsearch';

if (!in_array($name, $done, TRUE)) {
  $done[] = $name;
  $kv->set('existing_updates', $done);
  echo "Marked {$name} as done (gsearch kept on Korsør).\n";
}
else {
  echo "{$name} already marked done.\n";
}

$installer = \Drupal::service('module_installer');
if (!\Drupal::moduleHandler()->moduleExists('gsearch')) {
  $installer->install(['gsearch']);
  echo "Re-enabled gsearch module.\n";
}
