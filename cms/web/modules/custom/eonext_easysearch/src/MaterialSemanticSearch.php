<?php

declare(strict_types=1);

namespace Drupal\eonext_easysearch;

use Drupal\eonext_easysearch\Vector\SemanticSearch;

/**
 * Orchestrates semantic vector search with FBI work hydration.
 *
 * Ranks works via the local Qdrant index, optionally filters on indexed
 * payload fields, then hydrates full Work objects from FBI for UI display.
 */
final class MaterialSemanticSearch {

  private const MAX_LIMIT = 50;

  /**
   * Over-fetch factor when payload filters may discard vector hits.
   */
  private const FILTER_OVERFETCH = 3;

  /**
   * Constructs the material semantic search service.
   */
  public function __construct(
    private readonly SemanticSearch $semanticSearch,
    private readonly FbiWorksClient $fbiWorksClient,
  ) {}

  /**
   * Runs semantic search and returns FBI-hydrated works in vector rank order.
   *
   * @param string $query
   *   The natural-language query.
   * @param int $limit
   *   Maximum number of results.
   * @param array $filters
   *   Optional filters keyed by material_type (GeneralMaterialTypeCodeEnum
   *   value, e.g. BOOKS), language (iso code, e.g. dan), or children (bool).
   *
   * @return array
   *   Response with keys hitcount (int) and results (array[]). Each result has
   *   workId, score and work (FBI WorkSmall-shaped array).
   */
  public function search(string $query, int $limit = 10, array $filters = []): array {
    $query = trim($query);
    if ($query === '') {
      return ['hitcount' => 0, 'results' => []];
    }

    $limit = max(1, min(self::MAX_LIMIT, $limit));
    $vector_limit = $limit;
    if ($this->hasActiveFilters($filters)) {
      $vector_limit = min(self::MAX_LIMIT, $limit * self::FILTER_OVERFETCH);
    }

    $hits = $this->semanticSearch->search($query, $vector_limit);
    $hits = $this->applyPayloadFilters($hits, $filters);
    $hits = array_slice($hits, 0, $limit);

    if ($hits === []) {
      return ['hitcount' => 0, 'results' => []];
    }

    $works_by_id = $this->fbiWorksClient->getByIds(array_column($hits, 'workId'));

    $results = [];
    foreach ($hits as $hit) {
      $work = $works_by_id[$hit['workId']] ?? NULL;
      if ($work === NULL) {
        continue;
      }

      $results[] = [
        'workId' => $hit['workId'],
        'score' => $hit['score'],
        'work' => $work,
      ];
    }

    return [
      'hitcount' => count($results),
      'results' => $results,
    ];
  }

  /**
   * Whether any supported payload filter is active.
   */
  private function hasActiveFilters(array $filters): bool {
    return ($filters['material_type'] ?? '') !== ''
      || ($filters['language'] ?? '') !== ''
      || !empty($filters['children']);
  }

  /**
   * Filters vector hits using Qdrant payload metadata.
   *
   * @param array[] $hits
   *   Semantic search hits.
   * @param array $filters
   *   Filter values.
   *
   * @return array[]
   *   Filtered hits preserving vector order.
   */
  private function applyPayloadFilters(array $hits, array $filters): array {
    if (!$this->hasActiveFilters($filters)) {
      return $hits;
    }

    $material_type = strtoupper((string) ($filters['material_type'] ?? ''));
    $language = strtolower((string) ($filters['language'] ?? ''));
    $children_only = !empty($filters['children']);

    return array_values(array_filter($hits, static function (array $hit) use ($material_type, $language, $children_only): bool {
      if ($material_type !== '') {
        $code = strtoupper((string) ($hit['materialTypeGeneralCode'] ?? ''));
        if ($code !== $material_type) {
          return FALSE;
        }
      }

      if ($language !== '') {
        $codes = array_map('strtolower', $hit['languageCodes'] ?? []);
        if ($codes !== [] && !in_array($language, $codes, TRUE)) {
          return FALSE;
        }
      }

      if ($children_only) {
        $audience = array_map('strtolower', $hit['childrenOrAdults'] ?? []);
        $has_children = array_filter($audience, static fn (string $value): bool => str_contains($value, 'børn') || str_contains($value, 'child'));
        if ($has_children === []) {
          return FALSE;
        }
      }

      return TRUE;
    }));
  }

}
