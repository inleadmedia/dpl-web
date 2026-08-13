<?php

declare(strict_types=1);

namespace Drupal\eonext_event_engine;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines a list builder for organization event bundle types.
 */
class OrganizationEventTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function buildHeader(): array {
    return [
      'label' => $this->t('Label', [], ['context' => 'eonext_event_engine']),
      'id' => $this->t('Machine name', [], ['context' => 'eonext_event_engine']),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function buildRow(EntityInterface $entity): array {
    assert($entity instanceof OrganizationEventTypeInterface);

    return [
      'label' => $entity->label(),
      'id' => $entity->id(),
    ] + parent::buildRow($entity);
  }

}
