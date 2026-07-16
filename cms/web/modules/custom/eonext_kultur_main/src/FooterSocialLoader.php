<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_main;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\path_alias\AliasManagerInterface;

/**
 * Loads the footer social band paragraph from the front page.
 */
final class FooterSocialLoader {

  public const PARAGRAPH_BUNDLE = 'eonext_kultur_footer_social';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly AliasManagerInterface $aliasManager,
  ) {}

  /**
   * Build a render array for the footer social band.
   *
   * @return array<string, mixed>|null
   *   Render array or NULL when no paragraph is configured.
   */
  public function buildRenderArray(): ?array {
    $paragraph = $this->loadParagraph();
    if (!$paragraph instanceof ParagraphInterface) {
      return NULL;
    }

    $data = $this->extractData($paragraph);
    if ($data['facebook'] === NULL && $data['instagram'] === NULL && $data['title'] === '') {
      return NULL;
    }

    return [
      '#theme' => 'eonext_kultur_footer_social',
      '#title' => $data['title'],
      '#text' => $data['text'],
      '#cta' => $data['cta'],
      '#hashtag' => $data['hashtag'],
      '#facebook' => $data['facebook'],
      '#instagram' => $data['instagram'],
      '#cache' => [
        'tags' => $paragraph->getCacheTags(),
        'contexts' => ['languages:language_interface'],
      ],
    ];
  }

  /**
   * Extract footer social URLs for the teal footer contact icons.
   *
   * @return array{facebook: ?string, instagram: ?string}
   *   Social URLs.
   */
  public function getSocialUrls(): array {
    $paragraph = $this->loadParagraph();
    if (!$paragraph instanceof ParagraphInterface) {
      return ['facebook' => NULL, 'instagram' => NULL];
    }

    $data = $this->extractData($paragraph);
    return [
      'facebook' => $data['facebook'],
      'instagram' => $data['instagram'],
    ];
  }

  /**
   * Load the footer social paragraph from the front page.
   */
  private function loadParagraph(): ?ParagraphInterface {
    $node = $this->loadFrontPageNode();
    if (!$node instanceof NodeInterface || !$node->hasField('field_paragraphs')) {
      return NULL;
    }

    foreach ($node->get('field_paragraphs')->referencedEntities() as $paragraph) {
      if ($paragraph instanceof ParagraphInterface && $paragraph->bundle() === self::PARAGRAPH_BUNDLE) {
        return $paragraph;
      }
    }

    return NULL;
  }

  /**
   * Load the configured front page node.
   */
  private function loadFrontPageNode(): ?NodeInterface {
    $front = $this->configFactory->get('system.site')->get('page.front');
    if (!is_string($front) || $front === '') {
      return NULL;
    }

    $internalPath = $this->aliasManager->getPathByAlias($front);
    if (!preg_match('/^\/node\/(\d+)$/', $internalPath, $matches)) {
      return NULL;
    }

    $node = $this->entityTypeManager->getStorage('node')->load($matches[1]);
    return $node instanceof NodeInterface ? $node : NULL;
  }

  /**
   * @return array{
   *   title: string,
   *   text: string,
   *   cta: string,
   *   hashtag: string,
   *   facebook: ?string,
   *   instagram: ?string
   * }
   *   Normalized paragraph values.
   */
  private function extractData(ParagraphInterface $paragraph): array {
    return [
      'title' => $paragraph->get('field_kultur_footer_title')->value ?? '',
      'text' => $paragraph->get('field_kultur_footer_text')->value ?? '',
      'cta' => $paragraph->get('field_kultur_footer_cta')->value ?? '',
      'hashtag' => $paragraph->get('field_kultur_footer_hashtag')->value ?? '',
      'facebook' => $this->linkValue($paragraph, 'field_kultur_footer_facebook'),
      'instagram' => $this->linkValue($paragraph, 'field_kultur_footer_instagram'),
    ];
  }

  /**
   * Get the URI from a link field.
   */
  private function linkValue(ParagraphInterface $paragraph, string $fieldName): ?string {
    if (!$paragraph->hasField($fieldName) || $paragraph->get($fieldName)->isEmpty()) {
      return NULL;
    }

    $uri = $paragraph->get($fieldName)->first()?->getUrl()->toString();
    return is_string($uri) && $uri !== '' ? $uri : NULL;
  }

}
