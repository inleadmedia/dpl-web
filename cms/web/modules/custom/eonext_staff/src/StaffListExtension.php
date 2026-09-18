<?php

declare(strict_types=1);

namespace Drupal\eonext_staff;

/**
 * Config names shipped by the staff list extension.
 */
final class StaffListExtension {

  /**
   * New configuration shipped by the staff list extension.
   *
   * @var string[]
   */
  public const NEW_CONFIG = [
    'field.storage.eonext_library_staff.field_interest_description',
    'field.field.eonext_library_staff.eonext_library_staff.field_interest_description',
    'core.entity_view_mode.eonext_library_staff.profile',
    'core.entity_view_display.eonext_library_staff.eonext_library_staff.profile',
    'field.field.paragraph.eonext_library_staff.field_filter_branches',
    'core.entity_form_display.paragraph.eonext_library_staff.default',
    'core.entity_view_display.paragraph.eonext_library_staff.default',
  ];

  /**
   * Existing configuration updated by the staff list extension.
   *
   * @var string[]
   */
  public const UPDATED_CONFIG = [
    'core.entity_form_display.eonext_library_staff.eonext_library_staff.default',
    'core.entity_view_display.eonext_library_staff.eonext_library_staff.default',
  ];

}
