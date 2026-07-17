<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_events\Plugin\facets\processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Drupal\facets\Result\Result;

/**
 * Maps the free-event boolean facet value to a readable label.
 *
 * @FacetsProcessor(
 *   id = "free_event_label",
 *   label = @Translation("Free event label"),
 *   description = @Translation("Shows only free events with a readable label."),
 *   stages = {
 *     "build" = 20
 *   }
 * )
 */
final class FreeEventLabelProcessor extends ProcessorPluginBase implements BuildProcessorInterface {

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

      $result->setDisplayValue(new TranslatableMarkup('Free events', [], ['context' => 'eonext_kultur_events']));
      $filtered[] = $result;
    }

    return $filtered;
  }

}
