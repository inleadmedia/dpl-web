<?php

namespace Drupal\eonext_editorial_search;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Taxonomy term context for editorial search term landing pages.
 */
final class EonextEditorialSearchTaxonomyContext {

  /**
   * Constructs a taxonomy context value object.
   */
  public function __construct(
    public readonly string $field,
    public readonly int $tid,
  ) {}

  /**
   * Reads taxonomy term context from the current route, if applicable.
   */
  public static function fromRoute(?RouteMatchInterface $route_match = NULL): ?self {
    $route_match ??= \Drupal::routeMatch();
    if ($route_match->getRouteName() !== 'entity.taxonomy_term.canonical') {
      return NULL;
    }

    $term = $route_match->getParameter('taxonomy_term');
    if (!$term instanceof TermInterface) {
      return NULL;
    }

    $field = match ($term->bundle()) {
      'tags' => 'tags',
      'categories' => 'categories',
      default => NULL,
    };

    if ($field === NULL) {
      return NULL;
    }

    return new self($field, (int) $term->id());
  }

}
