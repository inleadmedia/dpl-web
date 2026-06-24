<?php

declare(strict_types=1);

namespace Drupal\eonext_easysearch\Vector;

/**
 * Semantic material search over the Qdrant vector index.
 *
 * Query path depends on DPL_FBI_EMBEDDING:
 * - tei: embed locally, then vector search in Qdrant
 * - qdrant_inference: Qdrant Cloud embeds the query and runs kNN
 */
final class SemanticSearch {

  /**
   * Constructs the semantic search service.
   */
  public function __construct(
    private readonly EmbeddingClient $embeddingClient,
    private readonly QdrantClient $qdrant,
    private readonly VectorSearchSettings $settings,
  ) {}

  /**
   * Runs a semantic search.
   *
   * @param string $query
   *   The natural-language query.
   * @param int $limit
   *   Maximum number of results.
   *
   * @return array[]
   *   Matching works ordered by descending similarity, including indexed
   *   payload fields such as material type and language.
   */
  public function search(string $query, int $limit = 10): array {
    if (trim($query) === '') {
      return [];
    }

    $vector = $this->settings->usesTei()
      ? $this->embeddingClient->embedQuery($query)
      : [];

    if ($this->settings->usesTei() && $vector === []) {
      return [];
    }

    $hits = $this->qdrant->searchWorks(
      MaterialVectorIndexer::COLLECTION,
      $query,
      $vector,
      $limit,
    );

    $results = [];
    foreach ($hits as $hit) {
      $payload = $hit['payload'];
      $results[] = [
        'workId' => $hit['workId'],
        'title' => (string) ($payload['title'] ?? ''),
        'creator' => (string) ($payload['creator'] ?? ''),
        'abstract' => (string) ($payload['abstract'] ?? ''),
        'materialTypeGeneral' => (string) ($payload['materialTypeGeneral'] ?? ''),
        'materialTypeGeneralCode' => (string) ($payload['materialTypeGeneralCode'] ?? ''),
        'languages' => is_array($payload['languages'] ?? NULL) ? $payload['languages'] : [],
        'languageCodes' => is_array($payload['languageCodes'] ?? NULL) ? $payload['languageCodes'] : [],
        'childrenOrAdults' => is_array($payload['childrenOrAdults'] ?? NULL) ? $payload['childrenOrAdults'] : [],
        'subjects' => is_array($payload['subjects'] ?? NULL) ? $payload['subjects'] : [],
        'score' => $hit['score'],
      ];
    }

    return $results;
  }

}
