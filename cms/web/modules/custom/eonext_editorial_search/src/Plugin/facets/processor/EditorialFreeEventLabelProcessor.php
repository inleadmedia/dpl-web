<?php

declare(strict_types=1);

namespace Drupal\eonext_editorial_search\Plugin\facets\processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Drupal\facets\Result\Result;

/**
 * Maps the editorial free-event boolean facet value to a readable label.
 *
 * @FacetsProcessor(
 *   id = "editorial_free_event_label",
 *   label = @Translation("Editorial free event label"),
 *   description = @Translation("Shows only free events with a readable label."),
 *   stages = {
 *     "build" = 20
 *   }
 * )
 */
final class EditorialFreeEventLabelProcessor extends ProcessorPluginBase implements BuildProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results): array {
    $filtered = [];

    foreach ($results as $result) {
      if (!$result instanceof Result) {
        continue;
      }

      if ((string) $result->getRawValue() !== '1') {
        continue;
      }

      $result->setDisplayValue(new TranslatableMarkup('Free events', [], ['context' => 'eonext_editorial_search']));
      $filtered[] = $result;
    }

    return $filtered;
  }

}
