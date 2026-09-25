<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_events;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\MediaInterface;

/**
 * Builds hero variables for the events listing page.
 */
final class EventsListingHeroBuilder {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Builds hero template variables when configured.
   *
   * @return array{hero_image: array{src: string, alt: string}|null, heading: string}|null
   *   Hero props or NULL when no hero is configured.
   */
  public function build(?string $default_heading = NULL): ?array {
    $config = $this->configFactory->get('eonext_kultur_events.settings');
    $media_id = $config->get('hero_media');
    $heading = trim((string) $config->get('hero_heading'));
    if ($heading === '' && $default_heading !== NULL) {
      $heading = $default_heading;
    }

    $hero_image = NULL;
    if (is_numeric($media_id) && (int) $media_id > 0) {
      $hero_image = $this->buildHeroImage((int) $media_id, $heading);
    }

    if ($hero_image === NULL && $heading === '') {
      return NULL;
    }

    return [
      'hero_image' => $hero_image,
      'heading' => $heading,
    ];
  }

  /**
   * @return array{src: string, alt: string}|null
   *   Image props for the page hero.
   */
  private function buildHeroImage(int $media_id, string $heading): ?array {
    $media = $this->entityTypeManager->getStorage('media')->load($media_id);
    if (!$media instanceof MediaInterface) {
      return NULL;
    }

    if (!$media->hasField('field_media_image') || $media->get('field_media_image')->isEmpty()) {
      return NULL;
    }

    $image_item = $media->get('field_media_image')->first();
    $file = $image_item?->entity;
    if (!$file instanceof FileInterface) {
      return NULL;
    }

    $alt = $image_item->alt ?: $media->label() ?: $heading;
    $style = ImageStyle::load('banner');
    $src = $style !== NULL
      ? $style->buildUrl($file->getFileUri())
      : \Drupal::service('file_url_generator')->generateString($file->getFileUri());

    return [
      'src' => $src,
      'alt' => $alt,
    ];
  }

}
