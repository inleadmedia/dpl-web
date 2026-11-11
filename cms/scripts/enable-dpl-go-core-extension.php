<?php

/**
 * @file
 * Enable dpl_go in active core.extension using only Drush sql:* (no PDO / no MARIADB_*).
 *
 * Drush sql:query/bootstrap stops at CONFIGURATION, so this works when full
 * bootstrap fails on dpl_patron_redirect without dpl_go.
 *
 * Run manually when Drush dies on dpl_go.go_site; playbook also runs this after updb.
 * After cim, protect-korsor-no-dpl-go.php turns GO modules off again.
 *
 * Usage (from cms/):
 *   php scripts/enable-dpl-go-core-extension.php
 */

declare(strict_types=1);

$cms_dir = dirname(__DIR__);
$drush = $cms_dir . '/vendor/bin/drush';
if (!is_executable($drush)) {
  fwrite(STDERR, "Drush not found: {$drush}\n");
  exit(1);
}

/**
 * Run drush sql:query and return stdout.
 */
function enable_dpl_go_drush_sql(string $drush, string $cms_dir, string $sql, bool $raw = FALSE): string {
  $parts = [escapeshellarg($drush), 'sql:query'];
  if ($raw) {
    $parts[] = '--extra=--skip-column-names';
    $parts[] = '--extra=--raw';
  }
  $parts[] = escapeshellarg($sql);
  $cmd = implode(' ', $parts);
  $descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
  // Inherit the shell environment (HOME, PATH, …). Passing only $_ENV often
  // omits HOME and Drush/Symfony fails on Lagoon SSH.
  $proc = proc_open($cmd, $descriptor, $pipes, $cms_dir, NULL);
  if (!is_resource($proc)) {
    fwrite(STDERR, "Failed to run drush sql:query\n");
    exit(1);
  }
  $out = stream_get_contents($pipes[1]);
  $err = stream_get_contents($pipes[2]);
  fclose($pipes[1]);
  fclose($pipes[2]);
  $code = proc_close($proc);
  if ($code !== 0) {
    fwrite(STDERR, trim($err ?: "drush sql:query failed (exit {$code})\n"));
    exit(1);
  }
  return $out;
}

$raw = enable_dpl_go_drush_sql(
  $drush,
  $cms_dir,
  "SELECT data FROM config WHERE name = 'core.extension' AND collection = ''",
  TRUE,
);
$raw = rtrim($raw, "\r\n");
if ($raw === '') {
  fwrite(STDERR, "Empty core.extension row.\n");
  exit(1);
}

$data = unserialize($raw, ['allowed_classes' => FALSE]);
if (!is_array($data)) {
  fwrite(STDERR, "Could not unserialize core.extension.\n");
  exit(1);
}

$modules = $data['module'] ?? [];
if (isset($modules['dpl_go'])) {
  echo "dpl_go already enabled in core.extension.\n";
  exit(0);
}

$modules['dpl_go'] = 0;
$data['module'] = $modules;
$hex = bin2hex(serialize($data));

enable_dpl_go_drush_sql(
  $drush,
  $cms_dir,
  "UPDATE config SET data = UNHEX('{$hex}') WHERE name = 'core.extension' AND collection = ''",
);

enable_dpl_go_drush_sql(
  $drush,
  $cms_dir,
  'TRUNCATE cache_container; TRUNCATE cache_bootstrap; TRUNCATE cache_discovery;',
);

echo "Enabled dpl_go in core.extension (via drush sql:query).\n";
