<?php

declare(strict_types=1);

namespace Drupal\eonext_staff;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a library staff entity type.
 */
interface LibraryStaffInterface extends ContentEntityInterface, EntityChangedInterface {
  public function setUserId(int $userId);
}
