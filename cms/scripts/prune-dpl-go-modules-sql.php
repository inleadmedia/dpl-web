<?php

/**
 * @file
 * Remove dpl_go / dpl_patron_redirect from core.extension without Drupal bootstrap.
 *
 * Korsør does not run GO; patron_redirect breaks the container if enabled alone.
 *
 * Usage (from cms/): php scripts/prune-dpl-go-modules-sql.php
 */

declare(strict_types=1);

$cms_dir = dirname(__DIR__);
$app_root = $cms_dir . '/web';
chdir($app_root);

$site_path = getenv('DRUPAL_SITE_PATH') ?: 'sites/default';
$settings = [];
$config = [];
$databases = [];

if (!is_file("$app_root/$site_path/settings.php")) {
  fwrite(STDERR, "settings.php not found at {$site_path}\n");
  exit(1);
}

require "$app_root/$site_path/settings.php";

if (empty($databases['default']['default'])) {
  fwrite(STDERR, "No database credentials in settings.php\n");
  exit(1);
}

$db = $databases['default']['default'];
$dsn = sprintf(
  'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
  $db['host'] ?? 'localhost',
  (int) ($db['port'] ?? 3306),
  $db['database'],
);
$pdo = new PDO($dsn, $db['username'], $db['password'], [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$prefix = is_string($db['prefix'] ?? NULL)
  ? $db['prefix']
  : ($db['prefix']['default'] ?? '');
$table = $prefix . 'config';

$stmt = $pdo->prepare("SELECT data FROM {$table} WHERE name = :name AND collection = :col");
$stmt->execute([':name' => 'core.extension', ':col' => '']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
  echo "No core.extension in config table.\n";
  exit(0);
}

$data = unserialize($row['data'], ['allowed_classes' => FALSE]);
if (!is_array($data)) {
  fwrite(STDERR, "Could not unserialize core.extension\n");
  exit(1);
}

$modules = $data['module'] ?? [];
$remove = ['dpl_patron_redirect', 'dpl_go'];
$removed = [];
foreach ($remove as $name) {
  if (isset($modules[$name])) {
    unset($modules[$name]);
    $removed[] = $name;
  }
}

if ($removed === []) {
  echo "core.extension already has no dpl_go / dpl_patron_redirect.\n";
  exit(0);
}

$data['module'] = $modules;
$upd = $pdo->prepare("UPDATE {$table} SET data = :data WHERE name = :name AND collection = ''");
$upd->execute([':data' => serialize($data), ':name' => 'core.extension']);

foreach (['cache_container', 'cache_bootstrap', 'cache_discovery'] as $cache_table) {
  $pdo->exec('TRUNCATE ' . $prefix . $cache_table);
}

echo 'Removed from core.extension: ' . implode(', ', $removed) . "\n";
