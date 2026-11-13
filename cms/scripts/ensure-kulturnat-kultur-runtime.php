<?php

/**
 * @file
 * Turn on Kulturnat kultur stack after cim (not fully in DPL sync).
 *
 * Same role as Korsør ensure-korsor-kultur-runtime.php: theme, facets, index,
 * field_inheritance. Then run pending eonext_kultur_* update hooks.
 *
 * Usage: drush php:script scripts/ensure-kulturnat-kultur-runtime.php
 * Then: drush updb -y && drush cr
 */

declare(strict_types=1);

use Drupal\Core\Config\FileStorage;

$modules = [
  'gsearch',
  'field_inheritance',
  'eonext_kultur_main',
  'eonext_kultur_locale',
  'eonext_kultur_events',
  'eonext_kultur_branches',
  'eonext_kultur_event_submissions',
  'eonext_editorial_search',
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

\Drupal::moduleHandler()->loadInclude('eonext_kultur_events', 'install');

$module_path = $list->getPath('eonext_kultur_events');
$install = new FileStorage($module_path . '/config/install');
$imported = [];
foreach ([
  'facets.facet.event_city',
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
if (function_exists('eonext_kultur_events_ensure_city_processor')) {
  eonext_kultur_events_ensure_city_processor();
}
if (function_exists('eonext_kultur_events_install_event_city_facet')) {
  eonext_kultur_events_install_event_city_facet();
}
if (function_exists('eonext_kultur_events_reapply_eonext_437_fixes')) {
  echo eonext_kultur_events_reapply_eonext_437_fixes() . "\n";
}
elseif (function_exists('eonext_kultur_events_disable_dpl_library_facets')) {
  eonext_kultur_events_disable_dpl_library_facets();
  if (function_exists('eonext_kultur_events_configure_events_view')) {
    eonext_kultur_events_configure_events_view();
  }
}
if (function_exists('eonext_kultur_events_reindex')) {
  eonext_kultur_events_reindex();
  echo "Reindexed Search API index \"events\".\n";
}

if (\Drupal::moduleHandler()->moduleExists('eonext_translation')) {
  \Drupal::moduleHandler()->loadInclude('eonext_translation', 'install');
  if (function_exists('_eonext_translation_ensure_footer_config')) {
    echo _eonext_translation_ensure_footer_config() . "\n";
  }
}

if (\Drupal::moduleHandler()->moduleExists('eonext_kultur_event_submissions')
  && function_exists('eonext_kultur_event_submissions_reapply_eonext_437_fixes')) {
  echo eonext_kultur_event_submissions_reapply_eonext_437_fixes() . "\n";
}

if (\Drupal::moduleHandler()->moduleExists('eonext_kultur_main')) {
  \Drupal::moduleHandler()->loadInclude('eonext_kultur_main', 'install');
  if (function_exists('_eonext_kultur_main_reapply_eonext_437_site_policy')) {
    $messages = _eonext_kultur_main_reapply_eonext_437_site_policy();
    echo implode(' ', $messages) . "\n";
  }
}

echo "Kulturnat kultur runtime ensured. Run drush updb -y if any eonext_kultur_* updates are pending.\n";
