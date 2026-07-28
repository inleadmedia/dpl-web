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
 *   label = @Translation("Library Staff"),
 *   label_singular = @Translation("library staff"),
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
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the library staff was created.'))
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the library staff was last edited.'))
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default:user')
      ->setSetting('handler_settings', [

      ])
      ->setCardinality(1)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    return $fields;
  }

  public function setUserId(int $userId) {
    $this->set('uid', $userId);
  }

}
