<?php

declare(strict_types=1);

namespace Drupal\eonext_easysearch\Vector;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Client for the local HuggingFace Text Embeddings Inference (TEI) service.
 *
 * Serves intfloat/multilingual-e5-small. The e5 family expects inputs to be
 * prefixed with "query: " or "passage: ", which materially affects quality, so
 * this client applies the prefixes for the two call sites.
 */
final class EmbeddingClient {

  /**
   * Max inputs per request (stays under TEI's client batch limit).
   */
  private const CHUNK = 16;

  /**
   * Constructs the embedding client.
   */
  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly LoggerInterface $logger,
    private readonly VectorSearchSettings $settings,
  ) {}

  /**
   * Embeds a search query.
   *
   * @param string $text
   *   The user's query text.
   *
   * @return float[]
   *   The embedding vector, or an empty array on failure.
   */
  public function embedQuery(string $text): array {
    $vectors = $this->embed(['query: ' . $text]);

    return $vectors[0] ?? [];
  }

  /**
   * Embeds one or more documents (passages).
   *
   * @param string[] $texts
   *   The passage texts.
   *
   * @return float[][]
   *   One embedding vector per input, in order. Empty on failure.
   */
  public function embedPassages(array $texts): array {
    $prefixed = array_map(static fn (string $t): string => 'passage: ' . $t, array_values($texts));

    return $this->embed($prefixed);
  }

  /**
   * Calls the TEI /embed endpoint.
   *
   * @param string[] $inputs
   *   Already-prefixed input strings.
   *
   * @return float[][]
   *   Embedding vectors in input order.
   */
  private function embed(array $inputs): array {
    if ($inputs === []) {
      return [];
    }

    $vectors = [];

    try {
      foreach (array_chunk($inputs, self::CHUNK) as $chunk) {
        $response = $this->httpClient->request('POST', $this->baseUrl() . '/embed', [
          'headers' => ['Content-Type' => 'application/json'],
          'json' => ['inputs' => $chunk],
          'timeout' => 120,
        ]);

        $payload = json_decode((string) $response->getBody(), TRUE, flags: JSON_THROW_ON_ERROR);

        if (!is_array($payload)) {
          throw new \RuntimeException('Unexpected embedding response.');
        }

        foreach ($payload as $vector) {
          $vectors[] = array_map('floatval', (array) $vector);
        }
      }

      return $vectors;
    }
    catch (\Throwable $exception) {
      $this->logger->error('Embedding request failed: @message', [
        '@message' => $exception->getMessage(),
      ]);

      return [];
    }
  }

  /**
   * Returns the configured TEI base URL.
   */
  private function baseUrl(): string {
    return $this->settings->teiUrl();
  }

}
