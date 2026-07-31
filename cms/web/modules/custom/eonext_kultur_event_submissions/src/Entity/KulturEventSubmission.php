<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_event_submissions\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eonext_kultur_event_submissions\KulturEventSubmissionInterface;
use Drupal\eonext_kultur_event_submissions\SubmissionConstants;

/**
 * Defines the Kulturnat event submission entity.
 *
 * @ContentEntityType(
 *   id = "kultur_event_submission",
 *   label = @Translation("Kulturnat event submission"),
 *   label_collection = @Translation("Kulturnat event submissions"),
 *   label_singular = @Translation("Kulturnat event submission"),
 *   label_plural = @Translation("Kulturnat event submissions"),
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "view_builder" = "Drupal\eonext_kultur_event_submissions\KulturEventSubmissionViewBuilder",
 *     "list_builder" = "Drupal\eonext_kultur_event_submissions\KulturEventSubmissionListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\eonext_kultur_event_submissions\KulturEventSubmissionAccessControlHandler",
 *     "form" = {
 *       "approve" = "Drupal\eonext_kultur_event_submissions\Form\KulturEventSubmissionApproveForm",
 *       "reject" = "Drupal\eonext_kultur_event_submissions\Form\KulturEventSubmissionRejectForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "kultur_event_submission",
 *   admin_permission = "administer kultur event submissions",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "organisation_name",
 *   },
 *   links = {
 *     "canonical" = "/admin/content/kultur-event-submissions/{kultur_event_submission}",
 *     "collection" = "/admin/content/kultur-event-submissions",
 *     "approve-form" = "/admin/content/kultur-event-submissions/{kultur_event_submission}/approve",
 *     "reject-form" = "/admin/content/kultur-event-submissions/{kultur_event_submission}/reject",
 *   },
 * )
 */
final class KulturEventSubmission extends ContentEntityBase implements KulturEventSubmissionInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['organisation_name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Organisation name', [], ['context' => 'eonext_kultur_event_submissions']))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ]);

    $fields['city'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('City', [], ['context' => 'eonext_kultur_event_submissions']))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', SubmissionConstants::cityOptions())
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 1,
      ]);

    $fields['address'] = BaseFieldDefinition::create('address')
      ->setLabel(new TranslatableMarkup('Address', [], ['context' => 'eonext_kultur_event_submissions']))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'address_default',
        'weight' => 2,
      ]);

    $fields['event_start'] = BaseFieldDefinition::create('datetime')
      ->setLabel(new TranslatableMarkup('Event start', [], ['context' => 'eonext_kultur_event_submissions']))
      ->setRequired(TRUE)
      ->setSetting('datetime_type', 'datetime')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'datetime_default',
        'weight' => 3,
      ]);

    $fields['event_end'] = BaseFieldDefinition::create('datetime')
      ->setLabel(new TranslatableMarkup('Event end', [], ['context' => 'eonext_kultur_event_submissions']))
      ->setRequired(TRUE)
      ->setSetting('datetime_type', 'datetime')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'datetime_default',
        'weight' => 4,
      ]);

    $fields['contact_name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Contact name', [], ['context' => 'eonext_kultur_event_submissions']))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 5,
      ]);

    $fields['contact_email'] = BaseFieldDefinition::create('email')
      ->setLabel(new TranslatableMarkup('Contact email', [], ['context' => 'eonext_kultur_event_submissions']))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'email_mailto',
        'weight' => 6,
      ]);

    $fields['contact_phone'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Contact phone', [], ['context' => 'eonext_kultur_event_submissions']))
      ->setRequired(FALSE)
      ->setSetting('max_length', 64)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 7,
      ]);

    $fields['image'] = BaseFieldDefinition::create('image')
      ->setLabel(new TranslatableMarkup('Image', [], ['context' => 'eonext_kultur_event_submissions']))
      ->setRequired(TRUE)
      ->setSettings([
        'uri_scheme' => 'public',
        'file_extensions' => 'png jpg jpeg webp',
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'image',
        'weight' => 8,
      ]);

    $fields['description_gl'] = BaseFieldDefinition::create('text_long')
      ->setLabel(new TranslatableMarkup('Info om arrangement på grønlandsk', [], ['context' => 'eonext_kultur_event_submissions']))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 9,
      ]);

    $fields['description_da'] = BaseFieldDefinition::create('text_long')
      ->setLabel(new TranslatableMarkup('Info om arrangement på dansk', [], ['context' => 'eonext_kultur_event_submissions']))
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 10,
      ]);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Status', [], ['context' => 'eonext_kultur_event_submissions']))
      ->setRequired(TRUE)
      ->setDefaultValue(SubmissionConstants::STATUS_PENDING)
      ->setSetting('allowed_values', SubmissionConstants::statusOptions())
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 11,
      ]);

    $fields['eventseries'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Published event series', [], ['context' => 'eonext_kultur_event_submissions']))
      ->setSetting('target_type', 'eventseries')
      ->setSetting('handler', 'default:eventseries')
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 12,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Submitted', [], ['context' => 'eonext_kultur_event_submissions']))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 13,
      ]);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Updated', [], ['context' => 'eonext_kultur_event_submissions']));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function isPending(): bool {
    return $this->get('status')->value === SubmissionConstants::STATUS_PENDING;
  }

  /**
   * {@inheritdoc}
   */
  public function isNuuk(): bool {
    return $this->get('city')->value === SubmissionConstants::CITY_NUUK;
  }

  /**
   * Title callback for submission routes.
   */
  public static function submissionTitle(KulturEventSubmissionInterface $kultur_event_submission): string {
    return (string) $kultur_event_submission->get('organisation_name')->value;
  }

}
