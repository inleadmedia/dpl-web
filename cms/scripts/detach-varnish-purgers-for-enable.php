<?php

/**
 * @file
 * Detach Varnish purgers before drush en varnish_purger.
 *
 * Active purge.plugins often lists varnishbundled instances while
 * varnish_purger is disabled; module install triggers cache invalidation and
 * purge tries to load VarnishPurgerSettings before the entity type exists
 * (NoCorrespondingEntityClassException). Removing purger entries for the
 * enable window avoids that; cim/updb can restore purge.plugins from sync later.
 *
 * Usage: drush php:script scripts/detach-varnish-purgers-for-enable.php
 */

declare(strict_types=1);

$config_factory = \Drupal::configFactory();
$plugins = $config_factory->getEditable('purge.plugins');
$data = $plugins->getRawData();
if (!is_array($data)) {
  echo "purge.plugins missing or empty; nothing to do.\n";
  return;
}

$purgers = $data['purgers'] ?? [];
if (!is_array($purgers)) {
  echo "purge.plugins has no purgers list; nothing to do.\n";
  return;
}

$before = count($purgers);
$data['purgers'] = array_values(array_filter(
  $purgers,
  static fn ($row): bool => is_array($row)
    && ($row['plugin_id'] ?? '') !== 'varnishbundled',
));
$removed = $before - count($data['purgers']);

if ($removed === 0) {
  echo "No varnishbundled purgers in purge.plugins.\n";
  return;
}

$plugins->setData($data)->save();
echo "Detached {$removed} varnishbundled purger(s) from purge.plugins (re-import via cim/sync).\n";
