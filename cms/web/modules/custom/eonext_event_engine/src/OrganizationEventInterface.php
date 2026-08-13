<?php

declare(strict_types=1);

namespace Drupal\eonext_event_engine;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for organization event entities.
 */
interface OrganizationEventInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface, EntityPublishedInterface {

  /**
   * Gets the event title.
   */
  public function getTitle(): string;

  /**
   * Sets the event title.
   */
  public function setTitle(string $title): self;

  /**
   * Gets the creation timestamp.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the creation timestamp.
   */
  public function setCreatedTime(int $timestamp): self;

}
