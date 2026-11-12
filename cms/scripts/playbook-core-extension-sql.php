<?php

/**
 * @file
 * Shared drush sql:* helpers for playbook scripts (no full bootstrap).
 */

declare(strict_types=1);

/**
 * Run drush sql:query and return stdout.
 */
function playbook_cms_drush_sql(string $drush, string $cms_dir, string $sql, bool $raw = FALSE): string {
  $uri = getenv('DRUSH_URI');
  $parts = [escapeshellarg($drush)];
  if (is_string($uri) && $uri !== '') {
    $parts[] = '--uri=' . escapeshellarg($uri);
  }
  $parts[] = 'sql:query';
  if ($raw) {
    $parts[] = '--extra=--skip-column-names';
    $parts[] = '--extra=--raw';
  }
  $parts[] = escapeshellarg($sql);
  $cmd = implode(' ', $parts);
  $descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
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

/**
 * Extract a hex payload line from drush sql output (PHP 8.4 deprecations on stdout).
 */
function playbook_extract_hex_line(string $out): string {
  $hex = '';
  foreach (preg_split('/\R/', trim($out)) ?: [] as $line) {
    $line = trim($line);
    if ($line !== '' && preg_match('/^[0-9A-Fa-f]+$/', $line) && strlen($line) > strlen($hex)) {
      $hex = $line;
    }
  }
  return $hex;
}

/**
 * Load and unserialize core.extension module list from the active store.
 *
 * @return array{module: array<string, int|string>, theme?: array<string, int|string>}
 */
function playbook_load_core_extension(string $drush, string $cms_dir): array {
  $hex_out = playbook_cms_drush_sql(
    $drush,
    $cms_dir,
    "SELECT HEX(data) FROM config WHERE name = 'core.extension' AND collection = ''",
    TRUE,
  );
  $hex = playbook_extract_hex_line($hex_out);
  if ($hex === '') {
    fwrite(STDERR, "Empty core.extension row.\n");
    exit(1);
  }
  $raw = hex2bin($hex);
  if ($raw === FALSE) {
    fwrite(STDERR, "Invalid hex for core.extension.\n");
    exit(1);
  }
  $data = unserialize($raw, ['allowed_classes' => FALSE]);
  if (!is_array($data)) {
    fwrite(STDERR, "Could not unserialize core.extension.\n");
    exit(1);
  }
  return $data;
}

/**
 * Persist core.extension and truncate bootstrap caches.
 */
function playbook_save_core_extension(string $drush, string $cms_dir, array $data): void {
  $hex = bin2hex(serialize($data));
  playbook_cms_drush_sql(
    $drush,
    $cms_dir,
    "UPDATE config SET data = UNHEX('{$hex}') WHERE name = 'core.extension' AND collection = ''",
  );
  playbook_cms_drush_sql(
    $drush,
    $cms_dir,
    'TRUNCATE cache_container; TRUNCATE cache_bootstrap; TRUNCATE cache_discovery;',
  );
  $uri = getenv('DRUSH_URI');
  $cr_cmd = escapeshellarg($drush);
  if (is_string($uri) && $uri !== '') {
    $cr_cmd .= ' --uri=' . escapeshellarg($uri);
  }
  $cr_cmd .= ' cr -y 2>/dev/null';
  $descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
  $cr = proc_open($cr_cmd, $descriptor, $pipes, $cms_dir, NULL);
  if (is_resource($cr)) {
    stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($cr);
  }
}
