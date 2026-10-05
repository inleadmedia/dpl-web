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

/**
 * Ensures the interest description field table exists before later migrations.
 *
 * Runs after extend_staff_list and before interest_description_* hooks
 * (post updates are executed in alphabetical order).
 */
function eonext_staff_post_update_fix_interest_description_storage(): string {
  \Drupal::moduleHandler()->loadInclude('eonext_staff', 'install');
  _eonext_staff_ensure_interest_description_field();

  return 'Ensured field_interest_description database tables exist.';
}

/**
 * Legacy post update name; table repair now runs in fix_interest_description_storage.
 */
function eonext_staff_post_update_repair_interest_description_storage(): string {
  \Drupal::moduleHandler()->loadInclude('eonext_staff', 'install');
  _eonext_staff_ensure_interest_description_field();

  return 'Ensured field_interest_description database tables exist.';
}

/**
 * Use Basic HTML (rich text) for staff interest descriptions.
 */
function eonext_staff_post_update_interest_description_basic_html(): string {
  \Drupal::moduleHandler()->loadInclude('eonext_staff', 'install');
  _eonext_staff_migrate_interest_description_to_text();

  return 'Interest description uses the Basic HTML text format with a rich text editor.';
}

/**
 * Ensures interest description rich text migration completed (idempotent).
 */
function eonext_staff_post_update_interest_description_rich_text(): string {
  \Drupal::moduleHandler()->loadInclude('eonext_staff', 'install');
  _eonext_staff_migrate_interest_description_to_text();

  return 'Interest description rich text field is configured.';
}
