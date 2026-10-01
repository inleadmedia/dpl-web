<?php

declare(strict_types=1);

namespace Drupal\eonext_manual_recommendation\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\eonext_manual_recommendation\Entity\ManualRecommendationParagraph;

/**
 * Theme and entity hooks for manual recommendation paragraphs.
 */
class ManualRecommendationHooks {

  use StringTranslationTrait;

  public function __construct(
    protected FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * Registers the manual recommendation paragraph bundle class.
   *
   * @param mixed[] $bundles
   *   Bundle info for altering.
   */
  #[Hook('entity_bundle_info_alter')]
  public function entityBundleInfoAlter(array &$bundles): void {
    if (isset($bundles['paragraph']['manual_recommendation'])) {
      $bundles['paragraph']['manual_recommendation']['class'] = ManualRecommendationParagraph::class;
    }
  }

  /**
   * Registers theme implementations.
   *
   * @return mixed[]
   *   Theme hook definitions.
   */
  #[Hook('theme')]
  public function theme(mixed $existing, string $type, string $theme, string $path): array {
    return [
      'paragraph__manual_recommendation' => [
        'base hook' => 'paragraph',
        'template' => 'paragraph--manual-recommendation',
      ],
    ];
  }

  /**
   * Prepares variables for the manual recommendation paragraph template.
   *
   * @param mixed[] $variables
   *   Theme variables.
   */
  #[Hook('preprocess_paragraph__manual_recommendation')]
  public function preprocessParagraphManualRecommendation(array &$variables): void {
    $paragraph = $variables['paragraph'] ?? NULL;
    $view_mode = $variables['view_mode'] ?? NULL;

    if ($view_mode === 'preview' || !($paragraph instanceof ManualRecommendationParagraph)) {
      return;
    }

    $variables['position_image_right'] = $paragraph->isImagePositionRight();
    $variables['heading'] = $paragraph->getRecommendationTitle();
    $variables['description'] = $paragraph->getDescription();
    $variables['material_title'] = $paragraph->getMaterialTitle();

    $author = $paragraph->getAuthor();
    $year = $paragraph->getPublicationYear();
    if ($author && $year) {
      $variables['material_author'] = $this->t('@author (@year)', [
        '@author' => $author,
        '@year' => $year,
      ], ['context' => 'eonext']);
    }
    else {
      $variables['material_author'] = $author ?? ($year ? (string) $year : NULL);
    }

    $cover_media = $paragraph->getCoverMedia();
    $variables['cover_url'] = NULL;
    $variables['cover_alt'] = $variables['material_title'] ?? '';

    if ($cover_media === NULL) {
      return;
    }

    $variables['#cache']['tags'] = Cache::mergeTags(
      $variables['#cache']['tags'] ?? [],
      $cover_media->getCacheTags()
    );

    if ($cover_media->hasField('field_media_image') && !$cover_media->get('field_media_image')->isEmpty()) {
      $alt = (string) ($cover_media->get('field_media_image')->alt ?? '');
      if ($alt !== '') {
        $variables['cover_alt'] = $alt;
      }
    }

    $file = $cover_media->getImageFile();
    $uri = $file?->getFileUri();
    if ($file === NULL || $uri === NULL) {
      return;
    }

    $variables['cover_url'] = $this->fileUrlGenerator->generateString($uri);
    $variables['#cache']['tags'] = Cache::mergeTags(
      $variables['#cache']['tags'] ?? [],
      $file->getCacheTags()
    );
  }

}
