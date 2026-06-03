<?php

declare(strict_types=1);

namespace Drupal\eonext_editorial_search\Plugin\search_api\processor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eonext_editorial_search\EditorialContentExtractor;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Indexes full editorial body content and referenced material metadata.
 */
#[SearchApiProcessor(
  id: 'editorial_content',
  label: new TranslatableMarkup('Editorial content'),
  description: new TranslatableMarkup('Indexes paragraph body text, node body fields, and material titles/abstracts for editorial search.'),
  stages: [
    'preprocess_index' => 0,
  ],
)]
final class EditorialContentProcessor extends ProcessorPluginBase {

  /**
   * Search API field ID for aggregated editorial content.
   */
  public const FIELD_ID = 'editorial_content';

  /**
   * The editorial content extractor.
   */
  protected EditorialContentExtractor $contentExtractor;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->contentExtractor = $container->get(EditorialContentExtractor::class);
    return $processor;
  }

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index): bool {
    foreach ($index->getDatasources() as $datasource) {
      if (self::isSupportedDatasource($datasource)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array $items): void {
    foreach ($items as $item) {
      $this->setEditorialContent($item);
    }
  }

  /**
   * Sets editorial_content on an indexed item.
   */
  private function setEditorialContent(ItemInterface $item): void {
    $entity = $item->getOriginalObject()->getValue();
    if (!($entity instanceof ContentEntityInterface)) {
      return;
    }

    $content = $this->contentExtractor->extract($entity);
    if ($content === '') {
      return;
    }

    $content_field = $item->getField(self::FIELD_ID, FALSE);
    if ($content_field instanceof FieldInterface) {
      $content_field->setValues([]);
      $content_field->addValue($content);
    }
  }

  /**
   * Whether a datasource can provide editorial content for indexing.
   */
  private static function isSupportedDatasource(DatasourceInterface $datasource): bool {
    return in_array($datasource->getEntityTypeId(), ['node', 'eventseries'], TRUE);
  }

}
