<?php

/**
 * @file
 * Ensure bnf is in core.extension when bnf_client is enabled (sql only).
 *
 * bnf_client autowires Drupal\bnf\Services\BnfImporter; without bnf, container
 * compile fails (e.g. dpl_update_update_10082). Kulturnat later uninstalls the
 * whole BNF stack in eonext_kultur_main updates.
 *
 * Usage (from cms/):
 *   php scripts/enable-bnf-for-bnf-client-core-extension.php
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

if (!isset($modules['bnf_client'])) {
  echo "bnf_client not enabled — nothing to do.\n";
  exit(0);
}
if (isset($modules['bnf'])) {
  echo "bnf already enabled with bnf_client.\n";
  exit(0);
}

$modules['bnf'] = 0;
$data['module'] = $modules;
playbook_save_core_extension($drush, $cms_dir, $data);

echo "Enabled bnf in core.extension (bnf_client was on without bnf).\n";
