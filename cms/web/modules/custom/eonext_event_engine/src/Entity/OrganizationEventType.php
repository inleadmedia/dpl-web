<?php

declare(strict_types=1);

namespace Drupal\eonext_event_engine\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\eonext_event_engine\OrganizationEventTypeInterface;

/**
 * Defines the organization event bundle type.
 *
 * @ConfigEntityType(
 *   id = "organization_event_type",
 *   label = @Translation("Event (Pre-event) type", context = "eonext_event_engine"),
 *   label_collection = @Translation("Event (Pre-event) types", context = "eonext_event_engine"),
 *   handlers = {
 *     "list_builder" = "Drupal\eonext_event_engine\OrganizationEventTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\eonext_event_engine\Form\OrganizationEventTypeForm",
 *       "edit" = "Drupal\eonext_event_engine\Form\OrganizationEventTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "organization_event_type",
 *   admin_permission = "administer organization event types",
 *   bundle_of = "organization_event",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/organization-event/types/add",
 *     "edit-form" = "/admin/structure/organization-event/types/manage/{organization_event_type}",
 *     "delete-form" = "/admin/structure/organization-event/types/manage/{organization_event_type}/delete",
 *     "collection" = "/admin/structure/organization-event/types",
 *   },
 * )
 */
class OrganizationEventType extends ConfigEntityBundleBase implements OrganizationEventTypeInterface {

  /**
   * The bundle machine name.
   */
  protected string $id;

  /**
   * The human-readable bundle label.
   */
  protected string $label;

  /**
   * The bundle description.
   */
  protected string $description = '';

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function label(): string {
    return $this->label;
  }

}
