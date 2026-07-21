<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_locale;

use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\MediaInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Builds template variables for the lokaler listing paragraph.
 */
final class LokaleListingBuilder {

  /**
   * Builds hero image variables from a media reference field.
   *
   * @return array{src: string, alt: string}|null
   *   Hero image props, or NULL when no image is configured.
   */
  public static function buildHeroImage(ParagraphInterface $paragraph, string $heading = ''): ?array {
    if (!$paragraph->hasField('field_locale_hero_image') || $paragraph->get('field_locale_hero_image')->isEmpty()) {
      return NULL;
    }

    $media = $paragraph->get('field_locale_hero_image')->entity;
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
