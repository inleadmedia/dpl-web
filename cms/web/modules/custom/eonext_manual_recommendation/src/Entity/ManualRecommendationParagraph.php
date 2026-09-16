<?php

declare(strict_types=1);

namespace Drupal\eonext_manual_recommendation\Entity;

use Drupal\dpl_media\Entity\ImageMedia;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Bundle class for manual recommendation paragraphs.
 */
class ManualRecommendationParagraph extends Paragraph {

  /**
   * Determine if the image is positioned on the right.
   */
  public function isImagePositionRight(): bool {
    return (bool) $this->get('field_image_position_right')->value;
  }

  /**
   * Get the editorial heading.
   */
  public function getRecommendationTitle(): ?string {
    /** @var \Drupal\text\Plugin\Field\FieldType\TextItem $title */
    $title = $this->get('field_recommendation_title');
    $title = (string) $title->processed;

    return $title === '' ? NULL : $title;
  }

  /**
   * Get the editorial description.
   */
  public function getDescription(): ?string {
    $description = $this->get('field_recommendation_description')->value;

    return empty($description) ? NULL : $description;
  }

  /**
   * Get the manually entered material title.
   */
  public function getMaterialTitle(): ?string {
    $title = $this->get('field_mr_title')->value;

    return empty($title) ? NULL : $title;
  }

  /**
   * Get the manually entered author.
   */
  public function getAuthor(): ?string {
    $author = $this->get('field_mr_author')->value;

    return empty($author) ? NULL : $author;
  }

  /**
   * Get the manually entered publication year.
   */
  public function getPublicationYear(): ?int {
    if ($this->get('field_mr_publication_year')->isEmpty()) {
      return NULL;
    }

    return (int) $this->get('field_mr_publication_year')->value;
  }

  /**
   * Get the cover image media entity.
   */
  public function getCoverMedia(): ?ImageMedia {
    if ($this->get('field_mr_cover')->isEmpty()) {
      return NULL;
    }

    $media = $this->get('field_mr_cover')->entity;

    return $media instanceof ImageMedia ? $media : NULL;
  }

}
