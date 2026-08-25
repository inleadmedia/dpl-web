<?php

/**
 * @file
 * Remove leftover config for modules that are gone from the codebase.
 *
 * Drupal cannot bootstrap or run hook_update_n while core.extension still
 * lists those modules, or while REST still points at their plugins.
 *
 * Safe to run repeatedly. Does not bootstrap Drupal.
 */

declare(strict_types=1);

$missing = [
  'eonext_easysearch',
  'eonext_event_material_paragraphs',
];

$host = getenv('MARIADB_HOST') ?: getenv('MYSQL_HOST') ?: 'db';
$port = (int) (getenv('MARIADB_PORT') ?: getenv('MYSQL_PORT') ?: 3306);
$db = getenv('MARIADB_DATABASE') ?: getenv('MYSQL_DATABASE') ?: 'db';
$user = getenv('MARIADB_USERNAME') ?: getenv('MYSQL_USER') ?: 'db';
$pass = getenv('MARIADB_PASSWORD') ?: getenv('MYSQL_PASSWORD') ?: 'db';

$pdo = new PDO(
  sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db),
  $user,
  $pass,
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

$log = static function (string $message): void {
  fwrite(STDOUT, $message . PHP_EOL);
};

$loadConfig = static function (PDO $pdo, string $name) use ($log): ?array {
  $stmt = $pdo->prepare('SELECT data FROM config WHERE collection = :c AND name = :n');
  $stmt->execute([':c' => '', ':n' => $name]);
  $raw = $stmt->fetchColumn();
  if ($raw === FALSE) {
    return NULL;
  }
  $data = @unserialize($raw, ['allowed_classes' => FALSE]);
  if (!is_array($data)) {
    throw new RuntimeException("Could not unserialize config $name");
  }
  return $data;
};

$saveConfig = static function (PDO $pdo, string $name, array $data): void {
  $stmt = $pdo->prepare('UPDATE config SET data = :d WHERE collection = :c AND name = :n');
  $stmt->execute([':d' => serialize($data), ':c' => '', ':n' => $name]);
};

$extension = $loadConfig($pdo, 'core.extension');
if ($extension !== NULL) {
  $changed = FALSE;
  foreach ($missing as $module) {
    if (isset($extension['module'][$module])) {
      unset($extension['module'][$module]);
      $changed = TRUE;
      $log("Removed $module from core.extension.");
    }
  }
  if ($changed) {
    $saveConfig($pdo, 'core.extension', $extension);
  }
  else {
    $log('core.extension has no missing modules listed.');
  }
}

$delete_names = [
  'eonext_easysearch.settings',
  'rest.resource.eonext_easysearch.material_semantic_search',
];
$del = $pdo->prepare('DELETE FROM config WHERE name = :n');
foreach ($delete_names as $name) {
  $del->execute([':n' => $name]);
  $log(sprintf('Deleted config %s (%d row(s)).', $name, $del->rowCount()));
}

$roles = $pdo->query("SELECT name FROM config WHERE collection = '' AND name LIKE 'user.role.%'")->fetchAll(PDO::FETCH_COLUMN);
foreach ($roles as $role_name) {
  $role = $loadConfig($pdo, $role_name);
  if ($role === NULL) {
    continue;
  }
  $changed = FALSE;
  if (!empty($role['permissions']) && is_array($role['permissions'])) {
    $filtered = array_values(array_filter(
      $role['permissions'],
      static fn ($perm) => is_string($perm) && !str_contains($perm, 'eonext_easysearch') && !str_contains($perm, 'material_semantic_search'),
    ));
    if ($filtered !== array_values($role['permissions'])) {
      $role['permissions'] = $filtered;
      $changed = TRUE;
    }
  }
  foreach (['config', 'module'] as $dep_type) {
    if (empty($role['dependencies'][$dep_type]) || !is_array($role['dependencies'][$dep_type])) {
      continue;
    }
    $filtered = array_values(array_filter(
      $role['dependencies'][$dep_type],
      static fn ($dep) => is_string($dep) && !str_contains($dep, 'eonext_easysearch') && !str_contains($dep, 'material_semantic_search'),
    ));
    if ($filtered !== array_values($role['dependencies'][$dep_type])) {
      $role['dependencies'][$dep_type] = $filtered;
      if ($role['dependencies'][$dep_type] === []) {
        unset($role['dependencies'][$dep_type]);
      }
      $changed = TRUE;
    }
  }
  if ($changed) {
    $saveConfig($pdo, $role_name, $role);
    $log("Removed easysearch leftovers from $role_name.");
  }
}

$ignore = $loadConfig($pdo, 'config_ignore_auto.settings');
if ($ignore !== NULL) {
  $changed = FALSE;
  foreach (['ignored_config_entities', 'ignored'] as $key) {
    if (empty($ignore[$key]) || !is_array($ignore[$key])) {
      continue;
    }
    $filtered = array_values(array_filter(
      $ignore[$key],
      static function ($item) use ($missing): bool {
        $haystack = is_string($item) ? $item : print_r($item, TRUE);
        foreach ($missing as $module) {
          if (str_contains($haystack, $module)) {
            return FALSE;
          }
        }
        return !str_contains($haystack, 'material_semantic_search');
      },
    ));
    if ($filtered !== array_values($ignore[$key])) {
      $ignore[$key] = $filtered;
      $changed = TRUE;
    }
  }
  if ($changed) {
    $saveConfig($pdo, 'config_ignore_auto.settings', $ignore);
    $log('Removed easysearch entries from config_ignore_auto.settings.');
  }
}

$schema = $pdo->prepare("DELETE FROM key_value WHERE collection = 'system.schema' AND name = :n");
foreach ($missing as $module) {
  $schema->execute([':n' => $module]);
  $log(sprintf('Deleted system.schema %s (%d row(s)).', $module, $schema->rowCount()));
}

foreach (['cache_container', 'cache_bootstrap', 'cache_config', 'cache_discovery'] as $table) {
  $pdo->exec("TRUNCATE TABLE $table");
}
$log('Truncated container/bootstrap/config/discovery caches.');
