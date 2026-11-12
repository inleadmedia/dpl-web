<?php

/**
 * @file
 * Turn on Korsør kultur stack after cim (not in DPL sync).
 *
 * Fixes empty event detail (tags, org, body, ticket, price) and calendar
 * filters when eonext_kultur_* / theme / facets / Search API are incomplete.
 *
 * Usage: drush php:script scripts/ensure-korsor-kultur-runtime.php
 * Then: drush updb -y && drush cr
 */

declare(strict_types=1);

use Drupal\Core\Config\FileStorage;

$modules = [
  'gsearch',
  'eonext_kultur_main',
  'eonext_kultur_locale',
  'eonext_kultur_events',
  'eonext_kultur_branches',
  'eonext_kultur_event_submissions',
];

$list = \Drupal::service('extension.list.module');
$installer = \Drupal::service('module_installer');
$to_enable = [];
foreach ($modules as $name) {
  if (\Drupal::moduleHandler()->moduleExists($name)) {
    continue;
  }
  try {
    if ($list->getPathname($name) !== '' && is_file(DRUPAL_ROOT . '/' . $list->getPathname($name))) {
      $to_enable[] = $name;
    }
  }
  catch (\Drupal\Core\Extension\Exception\UnknownExtensionException) {
    continue;
  }
}
if ($to_enable !== []) {
  $installer->install($to_enable);
  echo 'Enabled modules: ' . implode(', ', $to_enable) . "\n";
}

$theme_handler = \Drupal::service('theme_handler');
if ($theme_handler->themeExists('eonext_kultur')) {
  $extension = \Drupal::configFactory()->getEditable('core.extension');
  $themes = $extension->get('theme') ?? [];
  if (!isset($themes['eonext_kultur'])) {
    $themes['eonext_kultur'] = 0;
    asort($themes);
    $extension->set('theme', $themes)->save();
    \Drupal::service('extension.list.theme')->reset();
  }
  \Drupal::service('theme_installer')->install(['eonext_kultur']);
  $default = \Drupal::config('system.theme')->get('default');
  if ($default !== 'eonext_kultur') {
    \Drupal::configFactory()->getEditable('system.theme')->set('default', 'eonext_kultur')->save();
    echo "Default theme set to eonext_kultur (was {$default}).\n";
  }
}

if (!\Drupal::moduleHandler()->moduleExists('eonext_kultur_events')) {
  echo "eonext_kultur_events still off — skip facets/index.\n";
  return;
}

$module_path = $list->getPath('eonext_kultur_events');
$install = new FileStorage($module_path . '/config/install');
$imported = [];
foreach ([
  'facets.facet.event_tags',
  'facets.facet.event_screen_names',
  'facets.facet.event_is_free',
] as $name) {
  if (!\Drupal::configFactory()->get($name)->isNew()) {
    continue;
  }
  $data = $install->read($name);
  if ($data) {
    \Drupal::configFactory()->getEditable($name)->setData($data)->save(TRUE);
    $imported[] = $name;
  }
}
if ($imported !== []) {
  echo 'Imported missing facet config: ' . implode(', ', $imported) . "\n";
}

if (function_exists('eonext_kultur_events_ensure_index_fields')) {
  eonext_kultur_events_ensure_index_fields();
}
if (function_exists('eonext_kultur_events_ensure_free_processor')) {
  eonext_kultur_events_ensure_free_processor();
}
if (function_exists('eonext_kultur_events_configure_events_view')) {
  eonext_kultur_events_configure_events_view();
}
if (function_exists('eonext_kultur_events_configure_facet_weights')) {
  eonext_kultur_events_configure_facet_weights();
}
if (function_exists('eonext_kultur_events_reindex')) {
  eonext_kultur_events_reindex();
  echo "Reindexed Search API index \"events\".\n";
}

echo "Korsør kultur runtime ensured. Run drush updb -y if any eonext_kultur_* updates are pending.\n";
