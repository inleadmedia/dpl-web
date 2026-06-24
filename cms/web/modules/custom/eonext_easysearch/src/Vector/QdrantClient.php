<?php

declare(strict_types=1);

namespace Drupal\eonext_easysearch\Vector;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * REST client for Qdrant (local Docker or Qdrant Cloud).
 *
 * Supports two embedding modes via VectorSearchSettings:
 * - tei: upsert/search with pre-computed float vectors
 * - qdrant_inference: upsert/search with Document {text, model} objects
 */
final class QdrantClient {

  /**
   * Constructs the Qdrant client.
   */
  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly LoggerInterface $logger,
    private readonly VectorSearchSettings $settings,
  ) {}

  /**
   * Ensures a collection exists with the given vector size (cosine distance).
   *
   * @param string $collection
   *   The collection name.
   * @param int $size
   *   The embedding dimensionality (ignored when 0 — uses settings default).
   */
  public function ensureCollection(string $collection, int $size = 0): void {
    if ($size <= 0) {
      $size = $this->settings->vectorSize();
    }

    try {
      $response = $this->httpClient->request('GET', $this->url("/collections/$collection"), [
        'headers' => $this->headers(),
        'http_errors' => FALSE,
        'timeout' => 30,
      ]);

      if ($response->getStatusCode() === 200) {
        return;
      }

      $this->httpClient->request('PUT', $this->url("/collections/$collection"), [
        'headers' => $this->headers(),
        'json' => [
          'vectors' => ['size' => $size, 'distance' => 'Cosine'],
        ],
        'timeout' => 30,
      ]);
    }
    catch (\Throwable $exception) {
      $this->logger->error('Qdrant ensureCollection failed: @message', [
        '@message' => $exception->getMessage(),
      ]);
    }
  }

  /**
   * Upserts works into Qdrant (vectors or inference documents).
   *
   * @param string $collection
   *   The collection name.
   * @param array[] $points
   *   Each item has keys workId, payload, and either vector (float[]) or text
   *   (string) when using Qdrant inference.
   *
   * @return bool
   *   TRUE on success.
   */
  public function upsert(string $collection, array $points): bool {
    if ($points === []) {
      return TRUE;
    }

    $body = ['points' => []];
    foreach ($points as $point) {
      $entry = [
        'id' => $this->workIdToUuid($point['workId']),
        'payload' => ['workId' => $point['workId']] + $point['payload'],
      ];

      if ($this->settings->usesQdrantInference()) {
        $entry['vector'] = [
          'text' => $this->passageText((string) ($point['text'] ?? '')),
          'model' => $this->settings->inferenceModel(),
        ];
      }
      else {
        $entry['vector'] = $point['vector'];
      }

      $body['points'][] = $entry;
    }

    try {
      $this->httpClient->request('PUT', $this->url("/collections/$collection/points?wait=true"), [
        'headers' => $this->headers(),
        'json' => $body,
        'timeout' => $this->settings->usesQdrantInference() ? 180 : 60,
      ]);

      return TRUE;
    }
    catch (\Throwable $exception) {
      $this->logger->error('Qdrant upsert failed: @message', [
        '@message' => $exception->getMessage(),
      ]);

      return FALSE;
    }
  }

  /**
   * Semantic search: by query text (inference) or pre-computed vector (TEI).
   *
   * @param string $collection
   *   The collection name.
   * @param string $query
   *   Natural-language query (inference mode).
   * @param float[] $vector
   *   Query embedding (TEI mode).
   * @param int $limit
   *   Maximum number of results.
   *
   * @return array[]
   *   Matches with workId, score and payload.
   */
  public function searchWorks(
    string $collection,
    string $query,
    array $vector,
    int $limit,
  ): array {
    if ($this->settings->usesQdrantInference()) {
      return $this->searchByQueryText($collection, $query, $limit);
    }

    if ($vector === []) {
      return [];
    }

    return $this->searchByVector($collection, $vector, $limit);
  }

  /**
   * Returns the number of points stored in a collection.
   */
  public function count(string $collection): int {
    try {
      $response = $this->httpClient->request('POST', $this->url("/collections/$collection/points/count"), [
        'headers' => $this->headers(),
        'json' => ['exact' => TRUE],
        'http_errors' => FALSE,
        'timeout' => 30,
      ]);

      $payload = json_decode((string) $response->getBody(), TRUE, flags: JSON_THROW_ON_ERROR);

      return (int) ($payload['result']['count'] ?? 0);
    }
    catch (\Throwable $exception) {
      return 0;
    }
  }

  /**
   * Vector search via POST /points/search (TEI mode).
   *
   * @param float[] $vector
   *   Query embedding.
   */
  private function searchByVector(string $collection, array $vector, int $limit): array {
    try {
      $response = $this->httpClient->request('POST', $this->url("/collections/$collection/points/search"), [
        'headers' => $this->headers(),
        'json' => [
          'vector' => $vector,
          'limit' => max(1, $limit),
          'with_payload' => TRUE,
        ],
        'timeout' => 30,
      ]);

      $payload = json_decode((string) $response->getBody(), TRUE, flags: JSON_THROW_ON_ERROR);

      return $this->parseHits($payload);
    }
    catch (\Throwable $exception) {
      $this->logger->error('Qdrant vector search failed: @message', [
        '@message' => $exception->getMessage(),
      ]);

      return [];
    }
  }

  /**
   * Inference search via POST /points/query (Qdrant Cloud Inference).
   */
  private function searchByQueryText(string $collection, string $query, int $limit): array {
    if (trim($query) === '') {
      return [];
    }

    try {
      $response = $this->httpClient->request('POST', $this->url("/collections/$collection/points/query"), [
        'headers' => $this->headers(),
        'json' => [
          'query' => [
            'text' => $this->queryText($query),
            'model' => $this->settings->inferenceModel(),
          ],
          'limit' => max(1, $limit),
          'with_payload' => TRUE,
        ],
        'timeout' => 60,
      ]);

      $payload = json_decode((string) $response->getBody(), TRUE, flags: JSON_THROW_ON_ERROR);

      return $this->parseHits($payload);
    }
    catch (\Throwable $exception) {
      $this->logger->error('Qdrant inference search failed: @message', [
        '@message' => $exception->getMessage(),
      ]);

      return [];
    }
  }

  /**
   * Normalises Qdrant search/query responses into hit arrays.
   *
   * @param array<string, mixed> $payload
   *   Decoded Qdrant JSON response.
   *
   * @return array[]
   *   Parsed hits.
   */
  private function parseHits(array $payload): array {
    $raw = $payload['result'] ?? [];
    if (isset($raw['points']) && is_array($raw['points'])) {
      $raw = $raw['points'];
    }

    if (!is_array($raw)) {
      return [];
    }

    $results = [];
    foreach ($raw as $hit) {
      if (!is_array($hit)) {
        continue;
      }

      $work_id = $hit['payload']['workId'] ?? NULL;
      if (!is_string($work_id) || $work_id === '') {
        continue;
      }

      $results[] = [
        'workId' => $work_id,
        'score' => (float) ($hit['score'] ?? 0),
        'payload' => is_array($hit['payload'] ?? NULL) ? $hit['payload'] : [],
      ];
    }

    return $results;
  }

  /**
   * Prefixes passage text for e5 models.
   */
  private function passageText(string $text): string {
    $text = trim($text);
    if ($text === '') {
      return '';
    }

    return str_starts_with($text, 'passage:') ? $text : 'passage: ' . $text;
  }

  /**
   * Prefixes query text for e5 models.
   */
  private function queryText(string $text): string {
    $text = trim($text);
    if ($text === '') {
      return '';
    }

    return str_starts_with($text, 'query:') ? $text : 'query: ' . $text;
  }

  /**
   * Maps a work ID string to a deterministic UUID (v5-style).
   */
  private function workIdToUuid(string $workId): string {
    $hash = md5($workId);

    return sprintf(
      '%s-%s-%s-%s-%s',
      substr($hash, 0, 8),
      substr($hash, 8, 4),
      substr($hash, 12, 4),
      substr($hash, 16, 4),
      substr($hash, 20, 12),
    );
  }

  /**
   * Builds request headers including Qdrant Cloud authentication.
   *
   * @return array<string, string>
   *   HTTP headers.
   */
  private function headers(): array {
    $headers = ['Content-Type' => 'application/json'];
    $api_key = $this->settings->qdrantApiKey();

    if ($api_key !== NULL) {
      $headers['api-key'] = $api_key;
    }

    return $headers;
  }

  /**
   * Builds a full Qdrant URL for the given path.
   */
  private function url(string $path): string {
    return $this->settings->qdrantUrl() . $path;
  }

}
