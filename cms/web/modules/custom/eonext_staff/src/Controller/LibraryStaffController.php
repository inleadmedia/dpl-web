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
   *
   * @return mixed[]
   *   A render array.
   */
  public function profile(LibraryStaffInterface $eonext_library_staff): array {
    // Build field render arrays so the profile template can print
    // content.field_* individually. EntityViewBuilder::view() wraps the entity
    // in a #pre_render callback, which Twig does not run when accessing child
    // keys like content.field_image.
    $content = EntityViewDisplay::collectRenderDisplay($eonext_library_staff, 'profile')
      ->build($eonext_library_staff);

    $has_interest_description = $eonext_library_staff->hasField('field_interest_description')
      && !$eonext_library_staff->get('field_interest_description')->isEmpty();

    return [
      '#theme' => 'eonext_library_staff_profile',
      '#staff_name' => $eonext_library_staff->getFullName(),
      '#interest_description' => $has_interest_description,
      '#content' => $content,
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
    return $eonext_library_staff->getFullName();
  }

}
