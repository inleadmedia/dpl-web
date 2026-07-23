<?php

namespace Drupal\eonext_editorial_search\Plugin\facets\processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Replaces indexed taxonomy term IDs with their labels.
 *
 * @FacetsProcessor(
 *   id = "taxonomy_term_label",
 *   label = @Translation("Taxonomy term label"),
 *   description = @Translation("Replaces indexed taxonomy term IDs with their labels. Works with aggregated taxonomy fields."),
 *   stages = {
 *     "build" = 5
 *   }
 * )
 */
class TaxonomyTermLabelProcessor extends ProcessorPluginBase implements BuildProcessorInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a TaxonomyTermLabelProcessor object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results) {
    $ids = [];
    foreach ($results as $result) {
      $raw = $result->getRawValue();
      if ($raw !== '' && $raw !== NULL) {
        $ids[] = $raw;
      }
    }

    if (!$ids) {
      return $results;
    }

    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($ids);

    foreach ($results as $result) {
      $id = $result->getRawValue();
      if (isset($terms[$id]) && $terms[$id] instanceof TermInterface) {
        $result->setDisplayValue($terms[$id]->label());
      }
    }

    return $results;
  }

}
