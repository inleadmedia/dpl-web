<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_event_submissions\Entity;

use CommerceGuys\Addressing\AddressFormat\AddressField;
use CommerceGuys\Addressing\AddressFormat\FieldOverride;
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
 *       "public" = "Drupal\eonext_kultur_event_submissions\Form\KulturEventSubmissionPublicForm",
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
 *     "settings-form" = "/admin/structure/kultur-event-submissions",
 *   },
 *   field_ui_base_route = "entity.kultur_event_submission.settings",
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
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 1,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ]);

    $fields['city'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('City', [], ['context' => 'eonext_kultur_event_submissions']))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', SubmissionConstants::cityOptions())
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 1,
      ]);

    $fields['address'] = BaseFieldDefinition::create('address')
      ->setLabel(new TranslatableMarkup('Address', [], ['context' => 'eonext_kultur_event_submissions']))
      ->setRequired(TRUE)
      ->setSettings([
        'field_overrides' => [
          AddressField::GIVEN_NAME => ['override' => FieldOverride::HIDDEN],
          AddressField::ADDITIONAL_NAME => ['override' => FieldOverride::HIDDEN],
          AddressField::FAMILY_NAME => ['override' => FieldOverride::HIDDEN],
          AddressField::ORGANIZATION => ['override' => FieldOverride::HIDDEN],
          AddressField::ADDRESS_LINE2 => ['override' => FieldOverride::HIDDEN],
          AddressField::ADDRESS_LINE3 => ['override' => FieldOverride::HIDDEN],
          AddressField::SORTING_CODE => ['override' => FieldOverride::HIDDEN],
          AddressField::DEPENDENT_LOCALITY => ['override' => FieldOverride::HIDDEN],
          AddressField::ADMINISTRATIVE_AREA => ['override' => FieldOverride::HIDDEN],
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'address_default',
        'weight' => 2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'address_default',
        'weight' => 2,
      ]);

    $fields['event_start'] = BaseFieldDefinition::create('datetime')
      ->setLabel(new TranslatableMarkup('Event start', [], ['context' => 'eonext_kultur_event_submissions']))
      ->setRequired(TRUE)
      ->setSetting('datetime_type', 'datetime')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => 3,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'datetime_default',
        'weight' => 3,
      ]);

    $fields['event_end'] = BaseFieldDefinition::create('datetime')
      ->setLabel(new TranslatableMarkup('Event end', [], ['context' => 'eonext_kultur_event_submissions']))
      ->setRequired(TRUE)
      ->setSetting('datetime_type', 'datetime')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => 4,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'datetime_default',
        'weight' => 4,
      ]);

    $fields['contact_name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Contact name', [], ['context' => 'eonext_kultur_event_submissions']))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 5,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 5,
      ]);

    $fields['contact_email'] = BaseFieldDefinition::create('email')
      ->setLabel(new TranslatableMarkup('Contact email', [], ['context' => 'eonext_kultur_event_submissions']))
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'email_default',
        'weight' => 6,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'email_mailto',
        'weight' => 6,
      ]);

    $fields['contact_phone'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Contact phone', [], ['context' => 'eonext_kultur_event_submissions']))
      ->setRequired(FALSE)
      ->setSetting('max_length', 64)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 7,
      ])
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
        'file_directory' => 'kultur-event-submissions',
        'file_extensions' => 'png jpg jpeg webp',
        'alt_field' => 0,
        'alt_field_required' => 0,
        'title_field' => 0,
        'title_field_required' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'image_image',
        'weight' => 8,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'image',
        'weight' => 8,
      ]);

    $fields['description_gl'] = BaseFieldDefinition::create('text_long')
      ->setLabel(new TranslatableMarkup('Event information in Greenlandic', [], ['context' => 'eonext_kultur_event_submissions']))
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 9,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 9,
      ]);

    $fields['description_da'] = BaseFieldDefinition::create('text_long')
      ->setLabel(new TranslatableMarkup('Event information in Danish', [], ['context' => 'eonext_kultur_event_submissions']))
      ->setRequired(FALSE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 10,
      ])
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
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 11,
      ])
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
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 12,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 12,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Submitted', [], ['context' => 'eonext_kultur_event_submissions']))
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 13,
      ]);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Updated', [], ['context' => 'eonext_kultur_event_submissions']))
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

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
