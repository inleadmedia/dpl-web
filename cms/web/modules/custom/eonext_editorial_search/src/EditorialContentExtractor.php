<?php

declare(strict_types=1);

namespace Drupal\eonext_editorial_search;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Extracts searchable editorial content from nodes and event series.
 */
final class EditorialContentExtractor {

  /**
   * Paragraph reference fields that may contain editorial body content.
   *
   * @var string[]
   */
  private const PARAGRAPH_FIELDS = [
    'field_paragraphs',
    'field_related_materials',
    'field_e_resource_materials',
    'field_event_paragraphs',
  ];

  /**
   * Text-based field types whose values should be included in fulltext search.
   *
   * @var string[]
   */
  private const TEXT_FIELD_TYPES = [
    'string',
    'string_long',
    'text',
    'text_long',
    'text_with_summary',
  ];

  /**
   * Extracts searchable text from an editorial entity.
   */
  public function extract(ContentEntityInterface $entity): string {
    $parts = [];

    if ($entity->hasField('body') && !$entity->get('body')->isEmpty()) {
      $parts = [...$parts, ...$this->extractTextFieldValues($entity->get('body'))];
    }

    if ($entity->hasField('field_e_resource_lead') && !$entity->get('field_e_resource_lead')->isEmpty()) {
      $parts = [...$parts, ...$this->extractTextFieldValues($entity->get('field_e_resource_lead'))];
    }

    foreach (self::PARAGRAPH_FIELDS as $field_name) {
      if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
        continue;
      }

      foreach ($entity->get($field_name)->referencedEntities() as $paragraph) {
        if ($paragraph instanceof ParagraphInterface) {
          $parts = [...$parts, ...$this->extractFromParagraph($paragraph)];
        }
      }
    }

    $normalized = [];
    foreach ($parts as $part) {
      $text = $this->normalizeText($part);
      if ($text !== '') {
        $normalized[] = $text;
      }
    }

    return implode(' ', array_values(array_unique($normalized)));
  }

  /**
   * @return string[]
   */
  private function extractFromParagraph(ParagraphInterface $paragraph): array {
    $parts = [];

    foreach ($paragraph->getFieldDefinitions() as $field_name => $definition) {
      if (!$paragraph->hasField($field_name) || $paragraph->get($field_name)->isEmpty()) {
        continue;
      }

      $field = $paragraph->get($field_name);
      $field_type = $definition->getType();

      if ($field_type === 'entity_reference_revisions') {
        foreach ($field->referencedEntities() as $nested_paragraph) {
          if ($nested_paragraph instanceof ParagraphInterface) {
            $parts = [...$parts, ...$this->extractFromParagraph($nested_paragraph)];
          }
        }
        continue;
      }

      if ($field_type === 'entity_reference') {
        $target_type = $definition->getSetting('target_type');
        if ($target_type === 'taxonomy_term') {
          foreach ($field->referencedEntities() as $term) {
            if ($term instanceof TermInterface) {
              $parts[] = $term->label();
            }
          }
        }
        continue;
      }

      if (in_array($field_type, self::TEXT_FIELD_TYPES, TRUE)) {
        $parts = [...$parts, ...$this->extractTextFieldValues($field)];
        continue;
      }

      if ($field_type === 'link') {
        $parts = [...$parts, ...$this->extractLinkFieldValues($field)];
      }
    }

    return $parts;
  }

  /**
   * @return string[]
   */
  private function extractTextFieldValues(FieldItemListInterface $field): array {
    $parts = [];

    foreach ($field as $item) {
      if (isset($item->summary)) {
        $summary = trim((string) $item->summary);
        if ($summary !== '') {
          $parts[] = $summary;
        }
      }

      $value = trim((string) $item->getString());
      if ($value !== '') {
        $parts[] = $value;
      }
    }

    return $parts;
  }

  /**
   * @return string[]
   */
  private function extractLinkFieldValues(FieldItemListInterface $field): array {
    $parts = [];

    foreach ($field as $item) {
      $title = trim((string) ($item->title ?? ''));
      if ($title !== '') {
        $parts[] = $title;
      }
    }

    return $parts;
  }

  /**
   * Normalizes extracted text for indexing.
   */
  private function normalizeText(string $text): string {
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? '';
    $text = mb_strtolower(trim($text));

    return $text;
  }

}
