<?php

/**
 * @file
 * Enable dpl_go in active core.extension using only Drush sql:* (no PDO / no MARIADB_*).
 *
 * Drush sql:query/bootstrap stops at CONFIGURATION, so this works when full
 * bootstrap fails on eonext_translation (or dpl_patron_redirect) without dpl_go.
 *
 * Run manually when Drush dies on dpl_go.go_site; playbook also runs this after updb.
 * After cim, protect-kulturnat-no-dpl-go.php turns GO modules off again.
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

require $cms_dir . '/scripts/playbook-core-extension-sql.php';

$data = playbook_load_core_extension($drush, $cms_dir);
$modules = $data['module'] ?? [];
if (isset($modules['dpl_go'])) {
  echo "dpl_go already enabled in core.extension.\n";
  exit(0);
}

$modules['dpl_go'] = 0;
$data['module'] = $modules;
playbook_save_core_extension($drush, $cms_dir, $data);

echo "Enabled dpl_go in core.extension (via drush sql:query).\n";
