<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_main;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;

/**
 * Builds render variables for article slider cards.
 */
final class ArticleSlideBuilder {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * Build article slide variables from a referenced entity.
   *
   * @return array<string, mixed>|null
   *   Slide variables or NULL when the entity cannot be rendered.
   */
  public function build(EntityInterface $entity): ?array {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'article') {
      return NULL;
    }

    return [
      'title' => $entity->label(),
      'teaser_text' => $this->getTeaserText($entity),
      'date_display' => $this->getPublicationDate($entity),
      'url' => $this->getNodeUrl($entity),
      'image' => $this->buildImage($entity),
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

  private function getTeaserText(NodeInterface $node): ?string {
    foreach (['field_teaser_text', 'field_subtitle'] as $fieldName) {
      if (!$node->hasField($fieldName) || $node->get($fieldName)->isEmpty()) {
        continue;
      }

      $value = $node->get($fieldName)->value ?? NULL;
      if (!is_string($value) || trim(strip_tags($value)) === '') {
        continue;
      }

      return trim(strip_tags($value));
    }

    return NULL;
  }

  private function getPublicationDate(NodeInterface $node): ?string {
    $timestamp = (int) $node->getCreatedTime();

    if ($node->hasField('field_publication_date') && !$node->get('field_publication_date')->isEmpty()) {
      $timestamp = (int) $node->get('field_publication_date')->value;
    }

    return $this->dateFormatter->format($timestamp, 'date_full_month');
  }

  /**
   * @return array<string, mixed>|null
   *   A render array for the slide image.
   */
  private function buildImage(NodeInterface $node): ?array {
    if (!$node->hasField('field_teaser_image') || $node->get('field_teaser_image')->isEmpty()) {
      return NULL;
    }

    $media = $node->get('field_teaser_image')->entity;
    if (!$media instanceof MediaInterface) {
      return NULL;
    }

    return $this->entityTypeManager->getViewBuilder('media')->view($media, 'list_teaser');
  }

}
