<?php

/**
 * @file
 * Post update hooks for the eonext_editorial_search module.
 */

declare(strict_types=1);

/**
 * Imports the module's configuration overlay.
 *
 * This runs in the post update phase, after every module's update hooks have
 * finished releasing configuration back to DPL CMS. Importing any earlier would
 * snapshot the configuration while another module still holds objects pinned,
 * and the import would write those stale objects back.
 */
function eonext_editorial_search_post_update_import_config(): string {
  \Drupal::moduleHandler()->loadInclude('eonext_editorial_search', 'install');
  eonext_editorial_search_update_config();

  return 'Editorial search configuration is part of the configuration import.';
}
