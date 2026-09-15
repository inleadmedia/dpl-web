<?php

declare(strict_types=1);

namespace Drupal\eonext_staff\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\eonext_staff\LibraryStaffInterface;

/**
 * Controller for library staff pages.
 */
final class LibraryStaffController extends ControllerBase {

  /**
   * Builds the public staff profile page.
   */
  public function profile(LibraryStaffInterface $eonext_library_staff): array {
    /** @var \Drupal\Core\Entity\Entity\EntityViewDisplay $display */
    $display = $this->entityTypeManager()
      ->getStorage('entity_view_display')
      ->load('eonext_library_staff.eonext_library_staff.profile');

    return [
      '#theme' => 'eonext_library_staff_profile',
      '#staff_name' => self::formatStaffName($eonext_library_staff),
      '#interest_description' => !$eonext_library_staff->get('field_interest_description')->isEmpty(),
      '#content' => $display instanceof EntityViewDisplay ? $display->build($eonext_library_staff) : [],
      '#attached' => [
        'library' => [
          'eonext_staff/general',
        ],
      ],
      '#cache' => [
        'tags' => $eonext_library_staff->getCacheTags(),
      ],
    ];
  }

  /**
   * Builds the page title for a staff profile.
   */
  public function staffTitle(LibraryStaffInterface $eonext_library_staff): string {
    return self::formatStaffName($eonext_library_staff);
  }

  /**
   * Formats a staff member's full name.
   */
  public static function formatStaffName(LibraryStaffInterface $staff): string {
    return trim($staff->get('field_forename')->value . ' ' . $staff->get('field_surname')->value);
  }

}
