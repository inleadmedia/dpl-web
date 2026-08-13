<?php

declare(strict_types=1);

namespace Drupal\eonext_event_engine;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for organization event bundle types.
 */
interface OrganizationEventTypeInterface extends ConfigEntityInterface {

  /**
   * Gets the bundle label.
   */
  public function label(): string;

}
