<?php

declare(strict_types=1);

namespace Drupal\eonext_event_engine;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a list builder for organization event entities.
 */
class OrganizationEventListBuilder extends EntityListBuilder {

  /**
   * The date formatter.
   */
  protected DateFormatterInterface $dateFormatter;

  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    DateFormatterInterface $date_formatter,
  ) {
    parent::__construct($entity_type, $storage);
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function buildHeader(): array {
    return [
      'title' => $this->t('Title', [], ['context' => 'eonext_event_engine']),
      'type' => $this->t('Type', [], ['context' => 'eonext_event_engine']),
      'author' => $this->t('Author', [], ['context' => 'eonext_event_engine']),
      'status' => $this->t('Status', [], ['context' => 'eonext_event_engine']),
      'changed' => $this->t('Updated', [], ['context' => 'eonext_event_engine']),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function buildRow(EntityInterface $entity): array {
    assert($entity instanceof OrganizationEventInterface);

    $owner = $entity->getOwner();

    return [
      'title' => $entity->toLink($entity->getTitle(), 'edit-form'),
      'type' => $entity->bundle(),
      'author' => $owner ? $owner->toLink() : $this->t('Anonymous', [], ['context' => 'eonext_event_engine']),
      'status' => $entity->isPublished()
        ? $this->t('Published', [], ['context' => 'eonext_event_engine'])
        : $this->t('Unpublished', [], ['context' => 'eonext_event_engine']),
      'changed' => $this->dateFormatter->format($entity->getChangedTime(), 'short'),
    ] + parent::buildRow($entity);
  }

}
