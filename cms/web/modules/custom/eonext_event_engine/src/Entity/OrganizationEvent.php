<?php

declare(strict_types=1);

namespace Drupal\eonext_event_engine\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\eonext_event_engine\OrganizationEventInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the organization event entity.
 *
 * @ContentEntityType(
 *   id = "organization_event",
 *   label = @Translation("Event (Pre-event)", context = "eonext_event_engine"),
 *   label_collection = @Translation("Events (Pre-event)", context = "eonext_event_engine"),
 *   label_singular = @Translation("event (Pre-event)", context = "eonext_event_engine"),
 *   label_plural = @Translation("events (Pre-event)", context = "eonext_event_engine"),
 *   bundle_label = @Translation("Event (Pre-event) type", context = "eonext_event_engine"),
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\eonext_event_engine\OrganizationEventListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\eonext_event_engine\OrganizationEventAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\eonext_event_engine\Form\OrganizationEventForm",
 *       "add" = "Drupal\eonext_event_engine\Form\OrganizationEventForm",
 *       "edit" = "Drupal\eonext_event_engine\Form\OrganizationEventForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "organization_event",
 *   data_table = "organization_event_field_data",
 *   translatable = TRUE,
 *   admin_permission = "administer organization event types",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "title",
 *     "langcode" = "langcode",
 *     "owner" = "uid",
 *     "published" = "status",
 *     "bundle" = "type",
 *   },
 *   links = {
 *     "canonical" = "/admin/content/organization-event/{organization_event}",
 *     "add-page" = "/admin/content/organization-event/add",
 *     "add-form" = "/admin/content/organization-event/add/{organization_event_type}",
 *     "edit-form" = "/admin/content/organization-event/{organization_event}/edit",
 *     "delete-form" = "/admin/content/organization-event/{organization_event}/delete",
 *     "collection" = "/admin/content/organization-event",
 *   },
 *   bundle_entity_type = "organization_event_type",
 *   field_ui_base_route = "entity.organization_event_type.edit_form",
 * )
 */
class OrganizationEvent extends ContentEntityBase implements OrganizationEventInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;
  use EntityPublishedTrait;

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function getTitle(): string {
    return $this->get('title')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function setTitle(string $title): OrganizationEventInterface {
    $this->set('title', $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function getCreatedTime(): int {
    return (int) ($this->get('created')->value ?? 0);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function setCreatedTime(int $timestamp): OrganizationEventInterface {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * Default value callback for the author field.
   *
   * @return int[]
   *   The current user ID.
   */
  public static function getCurrentUserId(): array {
    return [\Drupal::currentUser()->id()];
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title', [], ['context' => 'eonext_event_engine']))
      ->setDescription(t('The title of the event.', [], ['context' => 'eonext_event_engine']))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author', [], ['context' => 'eonext_event_engine']))
      ->setDescription(t('The user who created the event.', [], ['context' => 'eonext_event_engine']))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(static::class . '::getCurrentUserId')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 50,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields += static::publishedBaseFieldDefinitions($entity_type);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created', [], ['context' => 'eonext_event_engine']))
      ->setDescription(t('The time the event was created.', [], ['context' => 'eonext_event_engine']));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed', [], ['context' => 'eonext_event_engine']))
      ->setDescription(t('The time the event was last edited.', [], ['context' => 'eonext_event_engine']));

    return $fields;
  }

}
