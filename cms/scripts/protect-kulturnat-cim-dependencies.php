<?php

/**
 * @file
 * Kulturnat: drush cim fixes (ghost modules, varnish, address stack).
 *
 * Mirrors Korsør protect-korsor-cim-dependencies.php — import-time only patterns
 * on config_ignore.settings. Run after reset-config-ignore-from-sync.php and
 * protect-kulturnat-kultur-config.php, before cim.
 *
 * Usage: drush php:script scripts/protect-kulturnat-cim-dependencies.php
 */

declare(strict_types=1);

use Drupal\Core\Extension\Exception\UnknownExtensionException;

$config_factory = \Drupal::configFactory();
$extension = $config_factory->getEditable('core.extension');
$modules = $extension->get('module') ?? [];
$changed = FALSE;

foreach (['address_dawa'] as $ghost) {
  if (isset($modules[$ghost])) {
    unset($modules[$ghost]);
    $changed = TRUE;
  }
}

$enable_if_present = ['dk_address', 'dpl_service_message', 'dpl_biblio'];
$list = \Drupal::service('extension.list.module');
foreach ($enable_if_present as $name) {
  try {
    $pathname = $list->getPathname($name);
    if ($pathname === '' || !is_file(DRUPAL_ROOT . '/' . $pathname)) {
      continue;
    }
  }
  catch (UnknownExtensionException) {
    continue;
  }
  if (!isset($modules[$name])) {
    $modules[$name] = 0;
    $changed = TRUE;
  }
}

if ($changed) {
  asort($modules);
  $extension->set('module', $modules)->save();
}

$patterns = [
  'varnish_purger.settings.*',
];

$ignore = $config_factory->getEditable('config_ignore.settings');
$mode = $ignore->get('mode') ?: 'intermediate';
$entities = $ignore->get('ignored_config_entities') ?: [];
if ($mode !== 'intermediate' || !isset($entities['import'])) {
  $entities = [
    'import' => is_array($entities['import'] ?? $entities) ? ($entities['import'] ?? $entities) : [],
    'export' => is_array($entities['export'] ?? []) ? ($entities['export'] ?? []) : [],
  ];
}
foreach ($patterns as $pattern) {
  if (!in_array($pattern, $entities['import'], TRUE)) {
    $entities['import'][] = $pattern;
  }
}
sort($entities['import']);
$ignore->set('mode', 'intermediate')->set('ignored_config_entities', $entities)->save();

echo "protect-kulturnat-cim-dependencies: core.extension repaired; import ignore patterns=" . count($patterns) . ".\n";
