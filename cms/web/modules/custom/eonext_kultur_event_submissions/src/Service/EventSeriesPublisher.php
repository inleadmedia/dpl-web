<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_event_submissions\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\eonext_kultur_event_submissions\Entity\KulturEventSubmission;
use Drupal\eonext_kultur_event_submissions\SubmissionConstants;
use Drupal\file\FileInterface;
use Drupal\media\Entity\Media;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\recurring_events\Entity\EventSeries;

/**
 * Creates event series entities from approved Kulturnat submissions.
 */
final class EventSeriesPublisher {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Publishes an approved submission as a multilingual event series.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   When saving the event series fails.
   */
  public function publish(KulturEventSubmission $submission): EventSeries {
    _eonext_kultur_event_submissions_ensure_languages();

    $descriptionGl = trim((string) $submission->get('description_gl')->value);
    $descriptionDa = trim((string) $submission->get('description_da')->value);

    if ($descriptionDa === '') {
      $descriptionDa = $descriptionGl;
    }

    $greenlandicLangcode = SubmissionConstants::LANG_GREENLANDIC;
    $danishLangcode = SubmissionConstants::LANG_DANISH;

    $values = $this->buildSharedSeriesValues($submission, $descriptionGl, $descriptionDa);
    $values['langcode'] = $greenlandicLangcode;
    $values['field_event_paragraphs'] = $this->createDescriptionParagraphs($descriptionGl, $descriptionDa);

    /** @var \Drupal\recurring_events\Entity\EventSeries $series */
    $series = $this->entityTypeManager->getStorage('eventseries')->create($values);
    $series->save();

    if ($series->isTranslatable() && $this->languageExists($danishLangcode)) {
      $translationValues = [
        'title' => $submission->get('organisation_name')->value,
      ];
      if ($series->hasField('field_description') && $descriptionDa !== $descriptionGl) {
        $translationValues['field_description'] = $descriptionDa;
      }
      if ($series->hasField('field_teaser_text')) {
        $translationValues['field_teaser_text'] = $this->buildTeaserText($submission, $descriptionDa);
      }

      $translation = $series->addTranslation($danishLangcode, $translationValues);
      $translation->save();
    }

    return $series;
  }

  /**
   * Builds values shared across language versions.
   *
   * @return array<string, mixed>
   *   Event series field values.
   */
  private function buildSharedSeriesValues(
    KulturEventSubmission $submission,
    string $descriptionGl,
    string $descriptionDa,
  ): array {
    $start = $submission->get('event_start')->value;
    $end = $submission->get('event_end')->value;

    $values = [
      'type' => 'default',
      'title' => $submission->get('organisation_name')->value,
      'status' => TRUE,
      'recur_type' => 'custom',
      'custom_date' => [
        'value' => $start,
        'end_value' => $end,
      ],
      'field_event_address' => $submission->get('address')->getValue(),
      'field_event_place' => $this->buildEventPlace($submission),
      'field_event_state' => 'Active',
      'field_event_partners' => [
        ['value' => $submission->get('organisation_name')->value],
      ],
      'field_description' => $descriptionDa,
      'field_teaser_text' => $this->buildTeaserText($submission, $descriptionGl),
    ];

    $mediaTarget = $this->createImageMedia($submission);
    if ($mediaTarget !== NULL) {
      $values['field_teaser_image'] = ['target_id' => $mediaTarget];
      $values['field_event_image'] = ['target_id' => $mediaTarget];
    }

    return $values;
  }

  /**
   * Builds paragraph references for event body content.
   *
   * @return array<int, array{target_id: int, target_revision_id: int}>
   *   Paragraph reference values.
   */
  private function createDescriptionParagraphs(string $descriptionGl, string $descriptionDa): array {
    $paragraphs = [];

    if ($descriptionGl !== '') {
      $paragraphs[] = $this->createTextBodyParagraph($descriptionGl);
    }

    if ($descriptionDa !== '' && $descriptionDa !== $descriptionGl) {
      $paragraphs[] = $this->createTextBodyParagraph($descriptionDa);
    }

    return $paragraphs;
  }

  /**
   * Builds a short teaser shown in cards and the event hero.
   */
  private function buildTeaserText(KulturEventSubmission $submission, string $description): string {
    $description = trim($description);
    if ($description !== '') {
      return $description;
    }

    return (string) $submission->get('organisation_name')->value;
  }

  /**
   * Builds a human-readable place label from the submission address.
   */
  private function buildEventPlace(KulturEventSubmission $submission): string {
    $address = $submission->get('address')->first();
    if ($address !== NULL) {
      $parts = array_filter([
        trim((string) ($address->address_line1 ?? '')),
        trim((string) ($address->postal_code ?? '')),
        trim((string) ($address->locality ?? '')),
      ]);
      if ($parts !== []) {
        return implode(', ', $parts);
      }
    }

    return (string) $submission->get('organisation_name')->value;
  }

  /**
   * Creates a go_text_body paragraph for event descriptions.
   *
   * @return array{target_id: int, target_revision_id: int}
   *   Paragraph reference value.
   */
  private function createTextBodyParagraph(string $body): array {
    $paragraph = Paragraph::create([
      'type' => 'go_text_body',
      'field_body' => [
        'value' => $body,
        'format' => 'basic',
      ],
    ]);
    $paragraph->save();

    return [
      'target_id' => (int) $paragraph->id(),
      'target_revision_id' => (int) $paragraph->getRevisionId(),
    ];
  }

  /**
   * Creates a media entity from the submission image field.
   */
  private function createImageMedia(KulturEventSubmission $submission): ?int {
    if ($submission->get('image')->isEmpty()) {
      return NULL;
    }

    $imageItem = $submission->get('image')->first();
    if ($imageItem === NULL) {
      return NULL;
    }

    $file = $imageItem->entity;
    if (!$file instanceof FileInterface) {
      return NULL;
    }

    $media = Media::create([
      'bundle' => 'image',
      'name' => $file->getFilename(),
      'status' => TRUE,
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => $submission->get('organisation_name')->value,
      ],
    ]);
    $media->save();

    return (int) $media->id();
  }

  /**
   * Returns TRUE when the given language is configured on the site.
   */
  private function languageExists(string $langcode): bool {
    return $this->entityTypeManager->getStorage('configurable_language')->load($langcode) !== NULL;
  }

}
