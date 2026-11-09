<?php

/**
 * @file
 * Post update hooks for the eonext_event_status module.
 */

declare(strict_types=1);

/**
 * Installs the module's owned configuration.
 */
function eonext_event_status_post_update_import_config(): string {
  \Drupal::moduleHandler()->loadInclude('eonext_event_status', 'install');
  eonext_event_status_update_config();

  return 'Installed event ribbon field configuration.';
}

/**
 * Installs ribbon fields without a full site import.
 */
function eonext_event_status_post_update_install_owned_config(): string {
  \Drupal::moduleHandler()->loadInclude('eonext_event_status', 'install');
  _eonext_event_status_install_owned_config();

  return 'Installed event ribbon field configuration.';
}

/**
 * Ensures ribbon fields exist on sites that enabled an older module copy.
 */
function eonext_event_status_post_update_ensure_ribbon_fields(): string {
  \Drupal::moduleHandler()->loadInclude('eonext_event_status', 'install');
  _eonext_event_status_install_owned_config();

  return 'Ensured event ribbon field configuration is installed.';
}
