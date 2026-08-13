<?php

/**
 * @file
 * Post update hooks for the eonext_event_status module.
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
function eonext_event_status_post_update_import_config(): string {
  \Drupal::moduleHandler()->loadInclude('eonext_event_status', 'install');
  eonext_event_status_update_config();

  return 'Event status configuration is part of the configuration import.';
}
