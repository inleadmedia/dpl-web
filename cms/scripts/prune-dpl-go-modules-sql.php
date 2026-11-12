<?php

/**
 * @file
 * Remove dpl_go / dpl_patron_redirect from core.extension without Drupal bootstrap.
 *
 * Korsør does not run GO; patron_redirect breaks the container if enabled alone.
 *
 * Usage (from cms/):
 *   php scripts/prune-dpl-go-modules-sql.php
 *
 * Lagoon / manual DB (no settings.php bootstrap):
 *   MARIADB_DATABASE=korsor_ddb MARIADB_USERNAME=... MARIADB_PASSWORD=... \
 *     MARIADB_HOST=localhost MARIADB_PORT=3306 php scripts/prune-dpl-go-modules-sql.php
 */

declare(strict_types=1);

/**
 * @return array<string, mixed>
 */
function prune_dpl_go_db_conn(): array {
  $database = getenv('MARIADB_DATABASE') ?: getenv('PRUNE_DB_DATABASE');
  if (is_string($database) && $database !== '') {
    return [
      'driver' => 'mysql',
      'database' => $database,
      'username' => getenv('MARIADB_USERNAME') ?: getenv('PRUNE_DB_USER') ?: '',
      'password' => getenv('MARIADB_PASSWORD') ?: getenv('PRUNE_DB_PASSWORD') ?: '',
      'host' => getenv('MARIADB_HOST') ?: getenv('PRUNE_DB_HOST') ?: 'localhost',
      'port' => (int) (getenv('MARIADB_PORT') ?: getenv('PRUNE_DB_PORT') ?: 3306),
      'prefix' => getenv('PRUNE_DB_PREFIX') ?: '',
    ];
  }

  $cms_dir = dirname(__DIR__);
  $app_root = $cms_dir . '/web';
  $site_path = getenv('DRUPAL_SITE_PATH') ?: 'sites/default';
  $settings = [];
  $config = [];
  $databases = [];

  $autoload = $cms_dir . '/vendor/autoload.php';
  if (is_file($autoload)) {
    require_once $autoload;
  }

  if (!is_file("$app_root/$site_path/settings.php")) {
    fwrite(STDERR, "No DB env (MARIADB_DATABASE) and no settings.php.\n");
    exit(1);
  }

  chdir($app_root);
  require "$app_root/$site_path/settings.php";

  if (empty($databases['default']['default'])) {
    fwrite(STDERR, "No database credentials in settings.php\n");
    exit(1);
  }

  return $databases['default']['default'];
}

$db = prune_dpl_go_db_conn();
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
