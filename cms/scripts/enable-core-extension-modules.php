<?php

/**
 * @file
 * Enable modules in core.extension before updb/cim (field types must be available).
 *
 * Usage: drush php:script scripts/enable-core-extension-modules.php
 * Env: ENABLE_MODULES=geofield,dpl_biblio (default: geofield)
 */

declare(strict_types=1);

use Drupal\Core\Extension\Exception\UnknownExtensionException;

$names = array_filter(array_map('trim', explode(',', getenv('ENABLE_MODULES') ?: 'geofield')));
$list = \Drupal::service('extension.list.module');
$extension = \Drupal::configFactory()->getEditable('core.extension');
$modules = $extension->get('module') ?? [];
$enabled = [];

foreach ($names as $name) {
  try {
    $pathname = $list->getPathname($name);
    if ($pathname === '' || !is_file(DRUPAL_ROOT . '/' . $pathname)) {
      echo "Skip {$name}: not on disk.\n";
      continue;
    }
  }
  catch (UnknownExtensionException) {
    echo "Skip {$name}: unknown extension.\n";
    continue;
  }
  if (!isset($modules[$name])) {
    $modules[$name] = 0;
    $enabled[] = $name;
  }
}

if ($enabled === []) {
  echo "No modules to add to core.extension.\n";
  return;
}

asort($modules);
$extension->set('module', $modules)->save();
echo 'Added to core.extension: ' . implode(', ', $enabled) . "\n";
