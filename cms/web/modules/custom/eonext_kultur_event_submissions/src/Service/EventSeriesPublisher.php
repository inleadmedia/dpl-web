<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_event_submissions\Service;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\Entity\FieldConfig;
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

  /**
   * Matches field.storage.eventseries.field_teaser_text max_length.
   */
  private const TEASER_TEXT_MAX_LENGTH = 255;

  /**
   * Plain-text subtitle (field_description) copied from submission descriptions.
   */
  private const SUBTITLE_MAX_LENGTH = 250;

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
    $this->ensureTextBodyParagraphAllowed();

    if (!$submission->get('eventseries')->isEmpty()) {
      $existing = $submission->get('eventseries')->entity;
      if ($existing instanceof EventSeries) {
        $series = $this->syncFromSubmission($submission, $existing);
        $this->propagateSeriesLocationToInstances($series);
        return $series;
      }
    }

    [$descriptionGl, $descriptionDa, $titleGl, $titleDa] = $this->resolveSubmissionTexts($submission);

    $greenlandicLangcode = SubmissionConstants::LANG_GREENLANDIC;
    $danishLangcode = SubmissionConstants::LANG_DANISH;

    $values = $this->buildSharedSeriesValues($submission, $descriptionGl, $titleGl);
    $values['langcode'] = $greenlandicLangcode;
    $values['field_event_paragraphs'] = $this->createDescriptionParagraphs($descriptionGl, $descriptionDa);

    /** @var \Drupal\recurring_events\Entity\EventSeries $series */
    $series = $this->entityTypeManager->getStorage('eventseries')->create($values);
    $paragraph_ids = array_map(
      static fn(array $ref): int => (int) $ref['target_id'],
      $values['field_event_paragraphs'] ?? [],
    );

    try {
      $series->save();

      if ($series->isTranslatable() && $this->languageExists($danishLangcode)) {
        $translationValues = $this->buildDanishTranslationValues($submission, $series, $titleDa, $descriptionDa);
        $series->addTranslation($danishLangcode, $translationValues)->save();
      }
    }
    catch (\Exception $exception) {
      if (!$series->isNew()) {
        try {
          $series->delete();
        }
        catch (\Exception $deleteException) {
          $this->logger->error('Failed to roll back event series after publish error: @message', [
            '@message' => $deleteException->getMessage(),
          ]);
        }
      }
      $this->deleteParagraphs($paragraph_ids);
      throw $exception;
    }

    $this->propagateSeriesLocationToInstances($series);

    return $series;
  }

  /**
   * Updates an existing event series from the linked submission.
   */
  private function syncFromSubmission(KulturEventSubmission $submission, EventSeries $series): EventSeries {
    [$descriptionGl, $descriptionDa, $titleGl, $titleDa] = $this->resolveSubmissionTexts($submission);
    $greenlandicLangcode = SubmissionConstants::LANG_GREENLANDIC;
    $danishLangcode = SubmissionConstants::LANG_DANISH;

    $shared = $this->buildSharedSeriesValues($submission, $descriptionGl, $titleGl);
    $kl = $series->hasTranslation($greenlandicLangcode)
      ? $series->getTranslation($greenlandicLangcode)
      : $series;
    $this->applySharedSeriesValues($kl, $shared);
    $this->syncDescriptionParagraphs($kl, $descriptionGl, $descriptionDa);
    $kl->save();

    if ($series->isTranslatable() && $this->languageExists($danishLangcode)) {
      $daValues = $this->buildDanishTranslationValues($submission, $series, $titleDa, $descriptionDa);
      if ($series->hasTranslation($danishLangcode)) {
        $da = $series->getTranslation($danishLangcode);
        foreach ($daValues as $field => $value) {
          if ($da->hasField($field)) {
            $da->set($field, $value);
          }
        }
        $da->save();
      }
      else {
        $series->addTranslation($danishLangcode, $daValues)->save();
      }
    }

    return $series;
  }

  /**
   * @return array{0: string, 1: string, 2: string, 3: string}
   *   Greenlandic description, Danish description, GL title, DA title.
   */
  private function resolveSubmissionTexts(KulturEventSubmission $submission): array {
    $descriptionGl = trim((string) $submission->get('description_gl')->value);
    $descriptionDa = trim((string) $submission->get('description_da')->value);
    [$titleGl, $titleDa] = $this->resolveSubmissionTitles($submission);

    if ($descriptionDa === '') {
      $descriptionDa = $descriptionGl;
    }

    return [$descriptionGl, $descriptionDa, $titleGl, $titleDa];
  }

  /**
   * @param array<string, mixed> $values
   *   Values from buildSharedSeriesValues().
   */
  private function applySharedSeriesValues(EventSeries $series, array $values): void {
    unset($values['type']);
    foreach ($values as $field => $value) {
      if ($field === 'field_event_paragraphs' || !$series->hasField($field)) {
        continue;
      }
      $series->set($field, $value);
    }
  }

  /**
   * @return array<string, mixed>
   *   Field values for the Danish event series translation.
   */
  private function buildDanishTranslationValues(
    KulturEventSubmission $submission,
    EventSeries $series,
    string $titleDa,
    string $descriptionDa,
  ): array {
    $values = [
      'title' => $titleDa,
    ];

    if ($series->hasField('field_description')) {
      $values['field_description'] = $this->buildSubtitleText($submission, $descriptionDa);
    }
    if ($series->hasField('field_teaser_text')) {
      $values['field_teaser_text'] = $this->buildTeaserText($submission, $descriptionDa);
    }

    return $values;
  }

  /**
   * Updates or creates description paragraphs on an event series.
   */
  private function syncDescriptionParagraphs(EventSeries $series, string $descriptionGl, string $descriptionDa): void {
    if ($descriptionGl === '' || !$series->hasField('field_event_paragraphs')) {
      return;
    }

    $paragraph = $series->get('field_event_paragraphs')->entity;
    if (!$paragraph instanceof Paragraph) {
      $series->set('field_event_paragraphs', $this->createDescriptionParagraphs($descriptionGl, $descriptionDa));
      return;
    }

    $langcode = SubmissionConstants::LANG_GREENLANDIC;
    $body = $paragraph->hasTranslation($langcode)
      ? $paragraph->getTranslation($langcode)
      : $paragraph;
    $body->set('field_body', [
      'value' => $descriptionGl,
      'format' => 'basic',
    ]);
    $body->save();

    if ($descriptionDa !== '' && $descriptionDa !== $descriptionGl) {
      $this->addTextBodyTranslation($paragraph, SubmissionConstants::LANG_DANISH, $descriptionDa);
    }
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
    string $titleGl,
  ): array {
    $start = $submission->get('event_start')->value;
    $end = $submission->get('event_end')->value;

    $values = [
      'type' => 'default',
      'title' => $titleGl,
      'status' => TRUE,
      'recur_type' => 'custom',
      'custom_date' => [
        'value' => $start,
        'end_value' => $end,
      ],
      'field_event_address' => $submission->get('address')->getValue(),
      'field_event_place' => $this->buildEventPlace($submission),
      'field_event_location_type' => 'physical',
      'field_event_state' => 'Active',
      'field_description' => $this->buildSubtitleText($submission, $descriptionGl),
      'field_teaser_text' => $this->buildTeaserText($submission, $descriptionGl),
    ];

    $mediaTarget = $this->createImageMedia($submission);
    if ($mediaTarget !== NULL) {
      $values['field_teaser_image'] = ['target_id' => $mediaTarget];
      $values['field_event_image'] = ['target_id' => $mediaTarget];
    }

    $categoryIds = array_column($submission->get('event_categories')->getValue(), 'target_id');
    if ($categoryIds !== []) {
      $values['field_categories'] = array_map(
        static fn ($id): array => ['target_id' => (int) $id],
        $categoryIds,
      );
    }

    $organization = $this->resolveOrganizationFromAddress($submission);
    if ($organization !== '') {
      $values['field_event_partners'] = [
        ['value' => $organization],
      ];
    }

    return $values;
  }

  /**
   * Reads the organisation name from the submission address (Partners field).
   */
  private function resolveOrganizationFromAddress(KulturEventSubmission $submission): string {
    $address = $submission->get('address')->first();
    if ($address === NULL) {
      return '';
    }

    return trim((string) ($address->organization ?? ''));
  }

  /**
   * Resolves Greenlandic and Danish titles from a submission.
   *
   * @return array{0: string, 1: string}
   *   Greenlandic title and Danish title (falls back to Greenlandic when empty).
   */
  private function resolveSubmissionTitles(KulturEventSubmission $submission): array {
    $titleGl = trim((string) $submission->get('organisation_name')->value);
    $titleDa = trim((string) $submission->get('title_da')->value);
    if ($titleDa === '') {
      $titleDa = $titleGl;
    }

    return [$titleGl, $titleDa];
  }

  /**
   * Builds paragraph references for event body content.
   *
   * One shared text_body paragraph: Greenlandic default, Danish translation when
   * it differs. Event detail renders field_event_paragraphs in the active language.
   *
   * @return array<int, array{target_id: int, target_revision_id: int}>
   *   Paragraph reference values.
   */
  private function createDescriptionParagraphs(string $descriptionGl, string $descriptionDa): array {
    if ($descriptionGl === '') {
      return [];
    }

    $paragraph = $this->createDescriptionParagraph($descriptionGl, SubmissionConstants::LANG_GREENLANDIC);
    if ($descriptionDa !== '' && $descriptionDa !== $descriptionGl) {
      $this->addTextBodyTranslation($paragraph, SubmissionConstants::LANG_DANISH, $descriptionDa);
    }

    return [[
      'target_id' => (int) $paragraph->id(),
      'target_revision_id' => (int) $paragraph->getRevisionId(),
    ]];
  }

  /**
   * Builds plain subtitle (field_description) from rich submission description.
   */
  private function buildSubtitleText(KulturEventSubmission $submission, string $description): string {
    $plain = trim(strip_tags($description));
    if ($plain !== '') {
      return $this->truncatePlainText($plain, self::SUBTITLE_MAX_LENGTH);
    }

    return $this->truncatePlainText(
      (string) $submission->get('organisation_name')->value,
      self::SUBTITLE_MAX_LENGTH,
    );
  }

  /**
   * Builds a short teaser shown in cards and the event hero.
   */
  private function buildTeaserText(KulturEventSubmission $submission, string $description): string {
    $plain = trim(strip_tags($description));
    if ($plain !== '') {
      return $this->truncatePlainText($plain, self::TEASER_TEXT_MAX_LENGTH);
    }

    return $this->truncatePlainText(
      (string) $submission->get('organisation_name')->value,
      self::TEASER_TEXT_MAX_LENGTH,
    );
  }

  /**
   * Truncates plain text to a maximum length (word-safe when possible).
   */
  private function truncatePlainText(string $text, int $maxLength): string {
    $text = trim($text);
    if ($text === '') {
      return '';
    }

    if (mb_strlen($text) <= $maxLength) {
      return $text;
    }

    return Unicode::truncate($text, $maxLength, TRUE, TRUE);
  }

  /**
   * Builds a human-readable place label from the submission address.
   */
  private function buildEventPlace(KulturEventSubmission $submission): string {
    $address = $submission->get('address')->first();
    if ($address !== NULL) {
      $parts = array_filter([
        trim((string) ($address->organization ?? '')),
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
   * Ensures text_body paragraphs can be referenced on event paragraph fields.
   *
   * Matches eonext_kultur_events update 9010 when config sync is not imported.
   */
  private function ensureTextBodyParagraphAllowed(): void {
    foreach (['eventseries.default.field_event_paragraphs', 'eventinstance.default.field_event_paragraphs'] as $field_id) {
      $field = FieldConfig::load($field_id);
      if ($field === NULL) {
        continue;
      }

      $settings = $field->getSettings();
      $handler_settings = $settings['handler_settings'] ?? [];
      $handler_settings['target_bundles']['text_body'] = 'text_body';
      $drag_drop = $handler_settings['target_bundles_drag_drop'] ?? [];
      if (isset($drag_drop['text_body'])) {
        $drag_drop['text_body']['enabled'] = TRUE;
      }
      else {
        $drag_drop['text_body'] = [
          'weight' => 10,
          'enabled' => TRUE,
        ];
      }
      $handler_settings['target_bundles_drag_drop'] = $drag_drop;
      $settings['handler_settings'] = $handler_settings;
      $field->set('settings', $settings);
      $field->save();
    }
  }

  /**
   * Copies series address onto instances (field inheritance only maps GSearch address).
   */
  private function propagateSeriesLocationToInstances(EventSeries $series): void {
    if (!$series->hasField('field_event_address') || $series->get('field_event_address')->isEmpty()) {
      return;
    }

    $address_values = $series->get('field_event_address')->getValue();
    foreach ($series->get('event_instances')->referencedEntities() as $instance) {
      if (!$instance->hasField('field_event_address')) {
        continue;
      }
      $instance->set('field_event_address', $address_values);
      $instance->save();
    }
  }

  /**
   * Creates a description paragraph using an bundle allowed on field_event_paragraphs.
   */
  private function createDescriptionParagraph(string $body, string $langcode): Paragraph {
    $bundle = $this->resolveDescriptionParagraphBundle();
    $paragraph = Paragraph::create([
      'type' => $bundle,
      'langcode' => $langcode,
      'field_body' => [
        'value' => $body,
        'format' => 'basic',
      ],
    ]);
    $paragraph->save();

    return $paragraph;
  }

  /**
   * Resolves text_body or go_text_body depending on field handler settings.
   */
  private function resolveDescriptionParagraphBundle(): string {
    $field = FieldConfig::load('eventseries.default.field_event_paragraphs');
    if ($field === NULL) {
      return 'text_body';
    }

    $handler = \Drupal::service('plugin.manager.entity_reference_selection')
      ->getSelectionHandler($field);

    foreach (['text_body', 'go_text_body'] as $bundle) {
      $probe = Paragraph::create([
        'type' => $bundle,
        'field_body' => [
          'value' => 'probe',
          'format' => 'basic',
        ],
      ]);
      $probe->save();
      $valid = array_map('intval', $handler->validateReferenceableEntities([(int) $probe->id()]));
      $probe->delete();
      if ($valid !== []) {
        return $bundle;
      }
    }

    return 'text_body';
  }

  /**
   * Adds or updates a translated body on an existing text_body paragraph.
   */
  private function addTextBodyTranslation(Paragraph $paragraph, string $langcode, string $body): void {
    if (!$paragraph->isTranslatable() || !$this->languageExists($langcode)) {
      return;
    }

    $values = [
      'field_body' => [
        'value' => $body,
        'format' => 'basic',
      ],
    ];

    if ($paragraph->hasTranslation($langcode)) {
      $translation = $paragraph->getTranslation($langcode);
      $translation->set('field_body', $values['field_body']);
    }
    else {
      $translation = $paragraph->addTranslation($langcode, $values);
    }

    $translation->save();
  }

  /**
   * Creates a media entity from the submission image field.
   */
  /**
   * Deletes paragraphs created during a failed publish rollback.
   *
   * @param int[] $paragraph_ids
   */
  private function deleteParagraphs(array $paragraph_ids): void {
    if ($paragraph_ids === []) {
      return;
    }

    $storage = $this->entityTypeManager->getStorage('paragraph');
    foreach ($paragraph_ids as $paragraph_id) {
      if ($paragraph_id <= 0) {
        continue;
      }
      try {
        $paragraph = $storage->load($paragraph_id);
        $paragraph?->delete();
      }
      catch (\Exception $exception) {
        $this->logger->warning('Could not delete paragraph @id during publish rollback: @message', [
          '@id' => $paragraph_id,
          '@message' => $exception->getMessage(),
        ]);
      }
    }
  }

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
