<?php

/**
 * @file
 * Turn on Korsør kultur stack after cim (not in DPL sync).
 *
 * Fixes empty event detail (tags, org, body, ticket, price) and calendar
 * filters when eonext_kultur_* / theme / facets / Search API are incomplete.
 * Re-imports kultur facets and eventinstance field_inheritance if cim removed them.
 *
 * Usage: drush php:script scripts/ensure-korsor-kultur-runtime.php
 * Then: drush updb -y && drush cr
 */

declare(strict_types=1);

use Drupal\Core\Config\FileStorage;

$modules = [
  'gsearch',
  'field_inheritance',
  'eonext_translation',
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
  'facets.facet.event_categories',
  'eonext_kultur_events.settings',
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
  echo 'Imported missing kultur config: ' . implode(', ', $imported) . "\n";
}

$translation_path = $list->getPath('eonext_translation');
if ($translation_path && is_dir($translation_path . '/config/install')) {
  $translation_install = new FileStorage($translation_path . '/config/install');
  if (\Drupal::configFactory()->get('eonext_translation.settings')->isNew()) {
    $data = $translation_install->read('eonext_translation.settings');
    if ($data) {
      \Drupal::configFactory()->getEditable('eonext_translation.settings')->setData($data)->save(TRUE);
      echo "Imported eonext_translation.settings.\n";
    }
  }
}

$sync_dir = dirname(DRUPAL_ROOT) . '/config/sync';
if (is_dir($sync_dir)) {
  $sync = new FileStorage($sync_dir);
  $inheritance_imported = [];
  foreach ($sync->listAll('field_inheritance.field_inheritance.eventinstance_default_') as $name) {
    if (!\Drupal::configFactory()->get($name)->isNew()) {
      continue;
    }
    $data = $sync->read($name);
    if ($data) {
      \Drupal::configFactory()->getEditable($name)->setData($data)->save(TRUE);
      $inheritance_imported[] = $name;
    }
  }
  if ($inheritance_imported !== []) {
    echo 'Imported missing field_inheritance config: ' . count($inheritance_imported) . " item(s).\n";
  }
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
if (function_exists('eonext_kultur_events_disable_active_facet_reorder')) {
  eonext_kultur_events_disable_active_facet_reorder();
}
if (function_exists('eonext_kultur_events_reindex')) {
  eonext_kultur_events_reindex();
  echo "Reindexed Search API index \"events\".\n";
}

echo "Korsør kultur runtime ensured. Run drush updb -y if any eonext_kultur_* updates are pending.\n";
