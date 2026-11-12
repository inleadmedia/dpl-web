<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_event_submissions\Service;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\eonext_kultur_event_submissions\Entity\KulturEventSubmission;
use Psr\Log\LoggerInterface;

/**
 * Sends transactional mail for Kulturnat event submissions.
 */
final class SubmissionMailNotifier {

  use StringTranslationTrait;

  public function __construct(
    private readonly MailManagerInterface $mailManager,
    private readonly LanguageManagerInterface $languageManager,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Notifies the submitter that their event was received.
   */
  public function sendReceived(KulturEventSubmission $submission): void {
    $this->send(
      $submission,
      'submission_received',
      (string) $this->t(
        'We received your Kulturnat event submission',
        [],
        ['context' => 'eonext_kultur_event_submissions']
      ),
      (string) $this->t(
        'Thank you for submitting “@title” to Kulturnat. We will review your event and get back to you by email.',
        ['@title' => $submission->get('organisation_name')->value ?? ''],
        ['context' => 'eonext_kultur_event_submissions']
      ),
    );
  }

  /**
   * Notifies the submitter that their event was approved.
   */
  public function sendApproved(KulturEventSubmission $submission): void {
    $this->send(
      $submission,
      'submission_approved',
      (string) $this->t(
        'Your Kulturnat event was approved',
        [],
        ['context' => 'eonext_kultur_event_submissions']
      ),
      (string) $this->t(
        'Your event “@title” has been approved and published on the Kulturnat programme.',
        ['@title' => $submission->get('organisation_name')->value ?? ''],
        ['context' => 'eonext_kultur_event_submissions']
      ),
    );
  }

  /**
   * Sends a mail when the submission has a contact email.
   */
  private function send(
    KulturEventSubmission $submission,
    string $key,
    string $subject,
    string $body,
  ): void {
    $to = trim((string) $submission->get('contact_email')->value);
    if ($to === '') {
      return;
    }

    $langcode = $this->languageManager->getDefaultLanguage()->getId();
    try {
      $this->mailManager->mail(
        'eonext_kultur_event_submissions',
        $key,
        $to,
        $langcode,
        [
          'subject' => $subject,
          'body' => $body,
          'submission' => $submission,
        ],
      );
    }
    catch (\Throwable $exception) {
      // Submission is already saved; a broken Azure mailer must not white-screen submitters.
      $this->logger->error('Kulturnat submission mail failed (@key to @to): @message', [
        '@key' => $key,
        '@to' => $to,
        '@message' => $exception->getMessage(),
      ]);
    }
  }

}
