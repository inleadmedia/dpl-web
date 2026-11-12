<?php

/**
 * @file
 * Compare active config with config/sync (simulated cex / cim impact).
 *
 * Lists differences and flags whether each active-only or "would export" item
 * is covered by config_ignore (import/export) or config_ignore_auto.
 *
 * Usage (from cms/): drush php:script scripts/simulate-cex-diff.php
 */

declare(strict_types=1);

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageComparer;

$sync = new FileStorage(DRUPAL_ROOT . '/../config/sync');
$active = \Drupal::service('config.storage');
$comparer = new StorageComparer($sync, $active);

$changelist = $comparer->createChangelist()->getChangelist();

$ignore_import = [];
$ignore_export = [];
$auto_ignored = [];

$ignore = \Drupal::config('config_ignore.settings');
$mode = $ignore->get('mode') ?: 'simple';
$entities = $ignore->get('ignored_config_entities') ?: [];
if ($mode === 'intermediate' && isset($entities['import'])) {
  $ignore_import = $entities['import'] ?? [];
  $ignore_export = $entities['export'] ?? [];
}
elseif (is_array($entities)) {
  $ignore_import = $entities;
  $ignore_export = $entities;
}

$auto_ignored = \Drupal::config('config_ignore_auto.settings')->get('ignored_config_entities') ?: [];

/**
 * Pattern match (same semantics as config_ignore: * suffix wildcard).
 */
$matches_pattern = static function (string $name, array $patterns): bool {
  foreach ($patterns as $pattern) {
    if (!is_string($pattern) || $pattern === '') {
      continue;
    }
    if (str_ends_with($pattern, '.*')) {
      $prefix = substr($pattern, 0, -2);
      if (str_starts_with($name, $prefix . '.')) {
        return TRUE;
      }
    }
    elseif (str_contains($pattern, '*')) {
      $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';
      if (preg_match($regex, $name)) {
        return TRUE;
      }
    }
    elseif ($name === $pattern) {
      return TRUE;
    }
  }
  return FALSE;
};

$classify = static function (string $name) use ($ignore_import, $ignore_export, $auto_ignored, $matches_pattern): string {
  $tags = [];
  if ($matches_pattern($name, $ignore_export)) {
    $tags[] = 'ignore:export';
  }
  if ($matches_pattern($name, $ignore_import)) {
    $tags[] = 'ignore:import';
  }
  if ($matches_pattern($name, $auto_ignored)) {
    $tags[] = 'auto';
  }
  return $tags === [] ? 'UNPROTECTED' : implode(',', $tags);
};

// source=sync, target=active: delete = only active, create = only sync, update = both differ.
$normalize_names = static function (array $list): array {
  if ($list === []) {
    return [];
  }
  $first = reset($list);
  // Changelist values are config names; keys may be numeric hashes.
  return array_values(is_int(array_key_first($list)) && is_string($first) ? $list : array_keys($list));
};

$only_active = $normalize_names($changelist['delete'] ?? []);
$only_sync = $normalize_names($changelist['create'] ?? []);
$different = $normalize_names($changelist['update'] ?? []);

echo "=== Simulated config diff (sync vs active) ===\n";
echo 'Only in active — cex would add / cim would DELETE: ' . count($only_active) . "\n";
echo 'Only in sync — cim would CREATE on active: ' . count($only_sync) . "\n";
echo 'Different values: ' . count($different) . "\n\n";

$unprotected_export = [];
foreach ($only_active as $name) {
  $class = $classify($name);
  if ($class === 'UNPROTECTED') {
    $unprotected_export[] = $name;
  }
}

$unprotected_diff = [];
foreach ($different as $name) {
  $class = $classify($name);
  if ($class === 'UNPROTECTED' || !str_contains($class, 'ignore:export')) {
    $unprotected_diff[] = $name . ' [' . $class . ']';
  }
}

$cim_would_overwrite = [];
foreach ($different as $name) {
  $class = $classify($name);
  if ($class === 'UNPROTECTED' || !str_contains($class, 'ignore:import')) {
    $cim_would_overwrite[] = $name . ' [' . $class . ']';
  }
}

$lost_on_cim = [];
foreach ($only_active as $name) {
  $class = $classify($name);
  if (str_contains($class, 'ignore:import')) {
    continue;
  }
  $lost_on_cim[] = $name . ' [' . $class . ']';
}

echo "--- Active-only configs NOT import-ignored (LOST on cim) ---\n";
if ($lost_on_cim === []) {
  echo "(none)\n";
}
else {
  sort($lost_on_cim);
  foreach (array_slice($lost_on_cim, 0, 80) as $line) {
    echo "  $line\n";
  }
  if (count($lost_on_cim) > 80) {
    echo '  ... +' . (count($lost_on_cim) - 80) . " more\n";
  }
}

echo "\n--- Active-only configs NOT on export ignore (would pollute sync on cex) ---\n";
if ($unprotected_export === []) {
  echo "(none)\n";
}
else {
  sort($unprotected_export);
  foreach (array_slice($unprotected_export, 0, 80) as $name) {
    echo "  $name\n";
  }
  if (count($unprotected_export) > 80) {
    echo '  ... +' . (count($unprotected_export) - 80) . " more\n";
  }
}

echo "\n--- Different configs NOT export-ignored (cex would change sync) ---\n";
if ($unprotected_diff === []) {
  echo "(none)\n";
}
else {
  sort($unprotected_diff);
  foreach (array_slice($unprotected_diff, 0, 60) as $line) {
    echo "  $line\n";
  }
  if (count($unprotected_diff) > 60) {
    echo '  ... +' . (count($unprotected_diff) - 60) . " more\n";
  }
}

echo "\n--- Different configs NOT import-ignored (cim would overwrite active) ---\n";
if ($cim_would_overwrite === []) {
  echo "(none)\n";
}
else {
  sort($cim_would_overwrite);
  foreach (array_slice($cim_would_overwrite, 0, 60) as $line) {
    echo "  $line\n";
  }
  if (count($cim_would_overwrite) > 60) {
    echo '  ... +' . (count($cim_would_overwrite) - 60) . " more\n";
  }
}

echo "\n--- Kultur-related active-only (spot-check) ---\n";
$kultur = array_values(array_filter($only_active, static fn ($n) => preg_match('/eonext_kultur|kultur_event|facets\.facet\.event_|lokale|eonext_editorial/', $n)));
sort($kultur);
foreach ($kultur as $name) {
  echo "  $name [" . $classify($name) . "]\n";
}

$critical = [
  'views.view.events',
  'system.theme',
  'eonext_kultur_main.settings',
  'eonext_kultur_events.settings',
  'openid_connect.settings',
  'mailsystem.settings',
  'field_inheritance.config',
  'user.role.administrator',
  'user.role.editor',
  'user.role.local_administrator',
];
echo "\n--- Critical configs (must stay import+export ignored) ---\n";
foreach ($critical as $name) {
  $in_sync = !$sync->exists($name) ? 'missing-in-sync' : 'in-sync';
  $in_active = $active->exists($name) ? 'in-active' : 'missing-in-active';
  $class = $active->exists($name) ? $classify($name) : 'n/a';
  $in_diff = in_array($name, $different, TRUE) ? 'DIFFERS' : (in_array($name, $only_active, TRUE) ? 'active-only' : 'same');
  echo "  $name: $in_sync, $in_active, $in_diff [$class]\n";
}

echo "\nSummary: lost-on-cim=" . count($lost_on_cim)
  . '; unprotected-cex=' . count($unprotected_export)
  . '; cim-overwrite-risk=' . count($cim_would_overwrite) . "\n";
