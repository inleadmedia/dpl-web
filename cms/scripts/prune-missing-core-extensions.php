<?php

/**
 * @file
 * Remove enabled extensions from core.extension that are not on disk (old DB).
 *
 * Playbook: run before `drush en` / drupal:upgrade when ghost modules break
 * extension discovery (e.g. address_dawa).
 *
 * Usage: drush php:script scripts/prune-missing-core-extensions.php
 */

declare(strict_types=1);

use Drupal\Core\Extension\Exception\UnknownExtensionException;

// Keep org/Event Engine enabled in DB even if VCS checkout is missing (install via composer).
$protect_modules = array_filter(array_map('trim', explode(',', getenv('PRUNE_PROTECT_MODULES') ?: 'eonext_event_engine')));
$protect_modules = array_fill_keys($protect_modules, TRUE);

$list = \Drupal::service('extension.list.module');
$extension = \Drupal::configFactory()->getEditable('core.extension');
$modules = $extension->get('module') ?? [];
$removed = [];

foreach (array_keys($modules) as $name) {
  if ($name === 'default' || isset($protect_modules[$name])) {
    continue;
  }
  try {
    $pathname = $list->getPathname($name);
    if ($pathname !== '' && is_file(DRUPAL_ROOT . '/' . $pathname)) {
      continue;
    }
  }
  catch (UnknownExtensionException) {
    // Module not on disk.
  }
  unset($modules[$name]);
  $removed[] = $name;
}

$theme_list = \Drupal::service('extension.list.theme');
$themes = $extension->get('theme') ?? [];
$removed_themes = [];

foreach (array_keys($themes) as $name) {
  if ($name === 'default') {
    continue;
  }
  try {
    $pathname = $theme_list->getPathname($name);
    if ($pathname !== '' && is_file(DRUPAL_ROOT . '/' . $pathname)) {
      continue;
    }
  }
  catch (UnknownExtensionException) {
  }
  unset($themes[$name]);
  $removed_themes[] = $name;
}

if ($removed === [] && $removed_themes === []) {
  echo "No missing extensions in core.extension.\n";
  return;
}

if ($removed !== []) {
  asort($modules);
  $extension->set('module', $modules);
}
if ($removed_themes !== []) {
  asort($themes);
  $extension->set('theme', $themes);
}
$extension->save();

$msg = [];
if ($removed !== []) {
  $msg[] = 'modules: ' . implode(', ', $removed);
}
if ($removed_themes !== []) {
  $msg[] = 'themes: ' . implode(', ', $removed_themes);
}
echo 'Removed from core.extension — ' . implode('; ', $msg) . "\n";
