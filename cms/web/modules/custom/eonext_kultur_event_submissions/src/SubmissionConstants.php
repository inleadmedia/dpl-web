<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_event_submissions;

/**
 * Constants for Kulturnat event submissions.
 */
final class SubmissionConstants {

  public const CITY_NUUK = 'nuuk';

  public const CITY_OTHER = 'other';

  public const STATUS_PENDING = 'pending';

  public const STATUS_APPROVED = 'approved';

  public const STATUS_REJECTED = 'rejected';

  public const LANG_GREENLANDIC = 'kl';

  public const LANG_DANISH = 'da';

  /**
   * City options for the public submission form.
   *
   * @return string[]
   *   Machine name => label.
   */
  public static function cityOptions(): array {
    return [
      self::CITY_NUUK => (string) t('Nuuk', [], ['context' => 'eonext_kultur_event_submissions']),
      self::CITY_OTHER => (string) t('All other cities', [], ['context' => 'eonext_kultur_event_submissions']),
    ];
  }

  /**
   * Submission status options.
   *
   * @return string[]
   *   Machine name => label.
   */
  public static function statusOptions(): array {
    return [
      self::STATUS_PENDING => (string) t('Pending', [], ['context' => 'eonext_kultur_event_submissions']),
      self::STATUS_APPROVED => (string) t('Approved', [], ['context' => 'eonext_kultur_event_submissions']),
      self::STATUS_REJECTED => (string) t('Rejected', [], ['context' => 'eonext_kultur_event_submissions']),
    ];
  }

}
