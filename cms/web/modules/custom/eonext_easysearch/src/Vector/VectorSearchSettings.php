<?php

declare(strict_types=1);

namespace Drupal\eonext_easysearch\Vector;

/**
 * Environment-driven configuration for vector search backends.
 *
 * Switch setups by setting env vars (e.g. in .ddev/config.local.yaml +
 * .ddev/.env), then `ddev restart`.
 *
 * Qdrant target (local Docker vs Cloud):
 * - QDRANT_URL — http://qdrant:6333 (ddev default) or https://….cloud.qdrant.io
 * - QDRANT_API_KEY — required for Qdrant Cloud; omit for local Qdrant
 *
 * Embedding backend (how text becomes vectors):
 * - EONEXT_EASYSEARCH_EMBEDDING=tei — local HuggingFace TEI (default); needs TEI_URL
 * - EONEXT_EASYSEARCH_EMBEDDING=qdrant_inference — Qdrant Cloud embeds on upsert/query;
 *   requires Cloud cluster with Inference enabled + QDRANT_INFERENCE_MODEL
 *
 * Common combinations:
 * | EONEXT_EASYSEARCH_EMBEDDING | QDRANT_URL        | TEI | Notes                    |
 * | tei               | http://qdrant:6333| yes | Local dev (ddev default) |
 * | tei               | https://…cloud…   | yes | Cloud storage, local TEI |
 * | qdrant_inference  | https://…cloud…   | no  | Full Qdrant Cloud        |
 */
final class VectorSearchSettings {

  public const EMBEDDING_TEI = 'tei';

  public const EMBEDDING_QDRANT_INFERENCE = 'qdrant_inference';

  private const DEFAULT_QDRANT_URL = 'http://qdrant:6333';

  private const DEFAULT_TEI_URL = 'http://tei:80';

  private const DEFAULT_INFERENCE_MODEL = 'intfloat/multilingual-e5-small';

  /**
   * Vector size for intfloat/multilingual-e5-small.
   */
  private const DEFAULT_VECTOR_SIZE = 384;

  /**
   * Returns the Qdrant REST base URL.
   */
  public function qdrantUrl(): string {
    $url = getenv('QDRANT_URL');

    return is_string($url) && $url !== ''
      ? rtrim($url, '/')
      : self::DEFAULT_QDRANT_URL;
  }

  /**
   * Returns the Qdrant Cloud API key, or NULL when not configured.
   */
  public function qdrantApiKey(): ?string {
    $key = getenv('QDRANT_API_KEY');

    return is_string($key) && $key !== '' ? $key : NULL;
  }

  /**
   * Whether Qdrant Cloud auth is configured.
   */
  public function usesQdrantCloud(): bool {
    return $this->qdrantApiKey() !== NULL;
  }

  /**
   * Returns the TEI base URL.
   */
  public function teiUrl(): string {
    $url = getenv('TEI_URL');

    return is_string($url) && $url !== ''
      ? rtrim($url, '/')
      : self::DEFAULT_TEI_URL;
  }

  /**
   * Active embedding backend: tei or qdrant_inference.
   */
  public function embeddingBackend(): string {
    $value = getenv('EONEXT_EASYSEARCH_EMBEDDING');
    if (!is_string($value) || $value === '') {
      $value = getenv('DPL_FBI_EMBEDDING');
    }
    if (!is_string($value) || $value === '') {
      return self::EMBEDDING_TEI;
    }

    $normalized = strtolower(trim($value));

    return match ($normalized) {
      self::EMBEDDING_QDRANT_INFERENCE, 'inference', 'qdrant' => self::EMBEDDING_QDRANT_INFERENCE,
      default => self::EMBEDDING_TEI,
    };
  }

  /**
   * Whether embeddings are produced by Qdrant Cloud Inference.
   */
  public function usesQdrantInference(): bool {
    return $this->embeddingBackend() === self::EMBEDDING_QDRANT_INFERENCE;
  }

  /**
   * Whether embeddings are produced by local TEI.
   */
  public function usesTei(): bool {
    return !$this->usesQdrantInference();
  }

  /**
   * Cloud inference model name (Qdrant console → Inference tab).
   */
  public function inferenceModel(): string {
    $model = getenv('QDRANT_INFERENCE_MODEL');

    return is_string($model) && $model !== ''
      ? $model
      : self::DEFAULT_INFERENCE_MODEL;
  }

  /**
   * Vector dimensionality for collection creation (e5-small = 384).
   */
  public function vectorSize(): int {
    $size = getenv('QDRANT_VECTOR_SIZE');

    return is_string($size) && ctype_digit($size) && (int) $size > 0
      ? (int) $size
      : self::DEFAULT_VECTOR_SIZE;
  }

  /**
   * Human-readable summary for logging / Drush status.
   *
   * @return array<string, string|bool|int>
   *   Current configuration values.
   */
  public function summary(): array {
    return [
      'qdrant_url' => $this->qdrantUrl(),
      'qdrant_cloud' => $this->usesQdrantCloud(),
      'embedding' => $this->embeddingBackend(),
      'tei_url' => $this->teiUrl(),
      'inference_model' => $this->inferenceModel(),
      'vector_size' => $this->vectorSize(),
    ];
  }

}
