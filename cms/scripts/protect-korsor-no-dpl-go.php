<?php

/**
 * @file
 * Keep dpl_go and dpl_patron_redirect disabled (Korsør has no GO site).
 *
 * Run before/after cim so sync cannot re-enable them.
 *
 * Usage: drush php:script scripts/protect-korsor-no-dpl-go.php
 */

declare(strict_types=1);

$extension = \Drupal::configFactory()->getEditable('core.extension');
$modules = $extension->get('module') ?? [];
$remove = ['dpl_go', 'dpl_patron_redirect'];
$removed = [];

foreach ($remove as $name) {
  if (isset($modules[$name])) {
    unset($modules[$name]);
    $removed[] = $name;
  }
}

if ($removed === []) {
  echo "core.extension: dpl_go and dpl_patron_redirect already off.\n";
  return;
}

$extension->set('module', $modules)->save();
echo 'Removed from core.extension: ' . implode(', ', $removed) . "\n";
