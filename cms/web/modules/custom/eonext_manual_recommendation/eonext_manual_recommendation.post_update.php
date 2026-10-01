<?php

/**
 * @file
 * Post update functions for the manual recommendation module.
 */

declare(strict_types=1);

/**
 * Enable the manual recommendation paragraph on existing node paragraph fields.
 */
function eonext_manual_recommendation_post_update_enable_on_content_types(): string {
  \Drupal::moduleHandler()->loadInclude('eonext_manual_recommendation', 'install');
  eonext_manual_recommendation_add_paragraph_type_to_content_types();

  return 'Enabled the manual recommendation paragraph on node paragraph fields.';
}
