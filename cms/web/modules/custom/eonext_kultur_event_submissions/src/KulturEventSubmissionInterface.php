<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_event_submissions;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface for kultur event submissions.
 */
interface KulturEventSubmissionInterface extends ContentEntityInterface {

  /**
   * Returns TRUE when the submission can be approved.
   */
  public function isPending(): bool;

  /**
   * Returns TRUE when Nuuk-specific validation rules apply.
   */
  public function isNuuk(): bool;

}
