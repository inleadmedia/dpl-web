<?php

/**
 * @file
 * Post update functions for the eonext_staff module.
 */

/**
 * Extend staff list with branch filter, profiles, and interest descriptions.
 */
function eonext_staff_post_update_extend_staff_list(): string {
  \Drupal::moduleHandler()->loadInclude('eonext_staff', 'install');
  eonext_staff_install_extension_config();
  eonext_staff_update_staff_view_branch_filter();
  eonext_staff_add_paragraph_type_to_content_types();

  return 'Extended staff list with branch filter, public profiles, and interest descriptions.';
}
