<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_main;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\recurring_events\Entity\EventInstance;
use Drupal\recurring_events\Entity\EventSeries;

/**
 * Builds render variables for Nav Grid box items.
 */
final class BoxBuilder {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Build box variables from a referenced entity.
   *
   * @return array<string, mixed>|null
   *   Box variables or NULL when the entity cannot be rendered.
   */
  public function build(EntityInterface $entity): ?array {
    return match ($entity->getEntityTypeId()) {
      'eventseries' => $entity instanceof EventSeries ? $this->fromEventSeries($entity) : NULL,
      'eventinstance' => $entity instanceof EventInstance ? $this->fromEventInstance($entity) : NULL,
      'node' => $entity instanceof NodeInterface ? $this->fromNode($entity) : NULL,
      default => NULL,
    };
  }

  /**
   * @return array<string, mixed>
   */
  private function fromNode(NodeInterface $node): array {
    return [
      'title' => $node->label(),
      'url' => $this->getNodeUrl($node),
      'image' => $this->buildNodeImage($node),
    ];
  }

  /**
   * @return array<string, mixed>
   */
  private function fromEventSeries(EventSeries $eventSeries): array {
    return [
      'title' => $eventSeries->label(),
      'url' => $eventSeries->toUrl()->toString(),
      'image' => $this->buildMediaImage($eventSeries, ['field_event_image', 'field_teaser_image']),
    ];
  }

  /**
   * @return array<string, mixed>
   */
  private function fromEventInstance(EventInstance $eventInstance): array {
    return [
      'title' => $eventInstance->label(),
      'url' => Url::fromRoute('entity.eventinstance.canonical', [
        'eventinstance' => $eventInstance->id(),
      ])->toString(),
      'image' => $this->buildMediaImage($eventInstance, [
        'event_image',
        'field_event_image',
        'event_teaser_image',
        'field_teaser_image',
      ]),
    ];
  }

  private function getNodeUrl(NodeInterface $node): string {
    if ($node->hasField('field_canonical_url') && !$node->get('field_canonical_url')->isEmpty()) {
      $uri = $node->get('field_canonical_url')->first()->uri ?? NULL;
      if (is_string($uri) && $uri !== '') {
        return Url::fromUri($uri)->toString();
      }
    }

    return $node->toUrl()->toString();
  }

  /**
   * @return array<string, mixed>|null
   *   A render array for the box background image.
   */
  private function buildNodeImage(NodeInterface $node): ?array {
    if ($node->hasField('field_teaser_image') && !$node->get('field_teaser_image')->isEmpty()) {
      return $this->buildMediaImage($node, ['field_teaser_image']);
    }

    if ($node->hasField('field_images') && !$node->get('field_images')->isEmpty()) {
      return $this->buildFileImage($node, 'field_images');
    }

    return NULL;
  }

  /**
   * @param string[] $fieldNames
   *
   * @return array<string, mixed>|null
   */
  private function buildMediaImage(EntityInterface $entity, array $fieldNames): ?array {
    foreach ($fieldNames as $fieldName) {
      if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
        continue;
      }

      $media = $entity->get($fieldName)->entity;
      if ($media instanceof MediaInterface) {
        return $this->entityTypeManager->getViewBuilder('media')->view($media, 'banner');
      }
    }

    return NULL;
  }

  /**
   * @return array<string, mixed>|null
   */
  private function buildFileImage(EntityInterface $entity, string $fieldName): ?array {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return NULL;
    }

    $file = $entity->get($fieldName)->entity;
    if (!$file instanceof FileInterface) {
      return NULL;
    }

    return [
      '#theme' => 'image_style',
      '#style_name' => 'banner',
      '#uri' => $file->getFileUri(),
      '#alt' => $entity->label() ?? '',
    ];
  }

}
