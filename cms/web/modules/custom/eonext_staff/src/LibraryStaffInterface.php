<?php

declare(strict_types=1);

namespace Drupal\eonext_staff;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a library staff entity type.
 */
interface LibraryStaffInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Sets the owning user id.
   */
  public function setUserId(int $userId): static;

  /**
   * Returns the staff member's full name.
   */
  public function getFullName(): string;

}
