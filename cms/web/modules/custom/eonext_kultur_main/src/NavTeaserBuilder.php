<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_main;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\recurring_events\Entity\EventInstance;
use Drupal\recurring_events\Entity\EventSeries;

/**
 * Builds nav teaser variables from referenced content entities.
 */
final class NavTeaserBuilder {

  /**
   * Builds nav teaser template variables for an entity.
   *
   * @return array<string, string>
   *   Nav teaser variables for Twig.
   */
  public function build(EntityInterface $entity): array {
    return match ($entity->getEntityTypeId()) {
      'eventseries' => $entity instanceof EventSeries ? $this->fromEventSeries($entity) : [],
      'eventinstance' => $entity instanceof EventInstance ? $this->fromEventInstance($entity) : [],
      'node' => $entity instanceof NodeInterface ? $this->fromNode($entity) : [],
      default => [],
    };
  }

  /**
   * @return array<string, string>
   */
  private function fromNode(NodeInterface $node): array {
    return [
      'title' => $node->label(),
      'url' => $this->getNodeUrl($node),
      'subtitle' => $this->getPlainTextField($node, 'field_subtitle'),
      'teaser_text' => $this->getTeaserText($node, ['field_teaser_text']),
      'background_image_url' => $this->getBackgroundImageUrl($node, [
        'field_teaser_image',
        'field_hero_image',
        'field_images',
      ]),
    ];
  }

  /**
   * @return array<string, string>
   */
  private function fromEventSeries(EventSeries $eventSeries): array {
    return [
      'title' => $eventSeries->label(),
      'url' => $eventSeries->toUrl()->toString(),
      'subtitle' => '',
      'teaser_text' => $this->getTeaserText($eventSeries, ['field_teaser_text', 'event_teaser_text']),
      'background_image_url' => $this->getBackgroundImageUrl($eventSeries, [
        'field_teaser_image',
        'field_event_image',
        'event_teaser_image',
        'event_image',
      ]),
    ];
  }

  /**
   * @return array<string, string>
   */
  private function fromEventInstance(EventInstance $eventInstance): array {
    $eventSeries = $eventInstance->getEventSeries();
    $url = $eventSeries instanceof EventSeries
      ? $eventSeries->toUrl()->toString()
      : Url::fromRoute('entity.eventinstance.canonical', [
        'eventinstance' => $eventInstance->id(),
      ])->toString();

    return [
      'title' => $eventInstance->label(),
      'url' => $url,
      'subtitle' => '',
      'teaser_text' => $this->getTeaserText($eventInstance, [
        'field_teaser_text',
        'event_teaser_text',
      ]),
      'background_image_url' => $this->getBackgroundImageUrl($eventInstance, [
        'field_teaser_image',
        'field_event_image',
        'event_teaser_image',
        'event_image',
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
   * @param string[] $fieldNames
   */
  private function getTeaserText(EntityInterface $entity, array $fieldNames): string {
    foreach ($fieldNames as $fieldName) {
      if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
        continue;
      }

      $value = $entity->get($fieldName)->value ?? $entity->get($fieldName)->getString();
      if (!is_string($value)) {
        continue;
      }

      $text = trim(strip_tags($value));
      if ($text !== '') {
        return $text;
      }
    }

    return '';
  }

  private function getPlainTextField(EntityInterface $entity, string $fieldName): string {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return '';
    }

    return trim((string) $entity->get($fieldName)->value);
  }

  /**
   * @param string[] $fieldNames
   */
  private function getBackgroundImageUrl(EntityInterface $entity, array $fieldNames): string {
    foreach ($fieldNames as $fieldName) {
      if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
        continue;
      }

      $target = $entity->get($fieldName)->entity;
      if ($target instanceof MediaInterface) {
        $url = $this->getMediaImageUrl($target);
        if ($url !== '') {
          return $url;
        }
        continue;
      }

      if ($target instanceof FileInterface) {
        return $this->buildStyledFileUrl($target->getFileUri());
      }
    }

    return '';
  }

  private function getMediaImageUrl(MediaInterface $media): string {
    if (!$media->hasField('field_media_image') || $media->get('field_media_image')->isEmpty()) {
      return '';
    }

    $file = $media->get('field_media_image')->entity;
    if (!$file instanceof FileInterface) {
      return '';
    }

    return $this->buildStyledFileUrl($file->getFileUri());
  }

  private function buildStyledFileUrl(string $uri): string {
    $style = ImageStyle::load('banner');
    if ($style === NULL) {
      return '';
    }

    return $style->buildUrl($uri);
  }

}
