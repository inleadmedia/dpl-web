<?php

declare(strict_types=1);

namespace Drupal\eonext_staff\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\eonext_staff\LibraryStaffInterface;

/**
 * Defines the library staff entity class.
 *
 * @ContentEntityType(
 *   id = "eonext_library_staff",
 *   label = @Translation("Library Staff", context = "eonext"),
 *   label_singular = @Translation("library staff", context = "eonext"),
 *   handlers = {
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\eonext_staff\LibraryStaffAccessControlHandler",
 *     "form" = {
 *       "edit" = "Drupal\eonext_staff\Form\LibraryStaffForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\eonext_staff\Routing\LibraryStaffHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "eonext_library_staff",
 *   admin_permission = "administer eonext_library_staff",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "id",
 *     "uuid" = "uuid",
 *     "uid" = "uid",
 *   },
 *   links = {
 *     "canonical" = "/staff/{eonext_library_staff}",
 *   },
 *   field_ui_base_route = "entity.eonext_library_staff.settings",
 * )
 */
final class LibraryStaff extends ContentEntityBase implements LibraryStaffInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on', [], ['context' => 'eonext']))
      ->setDescription(t('The time that the library staff was created.', [], ['context' => 'eonext']))
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed', [], ['context' => 'eonext']))
      ->setDescription(t('The time that the library staff was last edited.', [], ['context' => 'eonext']))
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User', [], ['context' => 'eonext']))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default:user')
      ->setCardinality(1)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function setUserId(int $userId): static {
    $this->set('uid', $userId);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFullName(): string {
    $forename = $this->hasField('field_forename')
      ? (string) ($this->get('field_forename')->value ?? '')
      : '';
    $surname = $this->hasField('field_surname')
      ? (string) ($this->get('field_surname')->value ?? '')
      : '';

    return trim($forename . ' ' . $surname);
  }

}
