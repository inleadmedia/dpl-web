<?php

/**
 * @file
 * Post update functions for EO Next Event Engine.
 */

declare(strict_types=1);

/**
 * Applies module configuration after code updates.
 */
function eonext_event_engine_post_update_apply_config(): void {
  \Drupal::moduleHandler()->loadInclude('eonext_event_engine', 'install');
  eonext_event_engine_update_config();
}
