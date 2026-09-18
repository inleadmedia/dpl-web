<?php

/**
 * @file
 * Post update functions for the eonext_staff module.
 */

declare(strict_types=1);

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

/**
 * Repair missing field_interest_description table on existing sites.
 */
function eonext_staff_post_update_repair_interest_description_storage(): string {
  \Drupal::moduleHandler()->loadInclude('eonext_staff', 'install');
  _eonext_staff_ensure_field_storage_table('eonext_library_staff', 'field_interest_description');

  return 'Ensured field_interest_description database tables exist.';
}

/**
 * Allow the staff view to filter by more than one branch.
 */
function eonext_staff_post_update_staff_view_multiple_branches(): string {
  \Drupal::moduleHandler()->loadInclude('eonext_staff', 'install');
  eonext_staff_update_staff_view_branch_filter();

  return 'Updated the staff view so multiple branch filters return matching staff.';
}
