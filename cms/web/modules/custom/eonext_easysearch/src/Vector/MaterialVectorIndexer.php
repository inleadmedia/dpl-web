<?php

declare(strict_types=1);

namespace Drupal\eonext_easysearch\Vector;

use Drupal\dpl_fbi\Fbi;
use Drupal\dpl_library_agency\Branch\BranchRepositoryInterface;
use Drupal\dpl_library_agency\BranchSettings;
use Drupal\dpl_library_token\LibraryTokenHandler;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Builds the local Qdrant vector index from FBI works.
 *
 * Pulls holdings-scoped works via complexSearch, embeds rich metadata with the
 * local model, and upserts structured payload fields for display and filtering.
 */
final class MaterialVectorIndexer {

  /**
   * The Qdrant collection name.
   */
  public const COLLECTION = 'fbi_materials';

  /**
   * TEI embedding batch limit (see EmbeddingClient).
   */
  private const EMBED_BATCH = 16;

  /**
   * Fetches works with metadata used for embedding and payload storage.
   */
  private const QUERY = <<<'GRAPHQL'
    query indexWorks(
      $cql: String!
      $offset: Int!
      $limit: PaginationLimitScalar!
      $filters: ComplexSearchFiltersInput!
    ) {
      complexSearch(cql: $cql, filters: $filters) {
        hitcount
        works(offset: $offset, limit: $limit) {
          workId
          titles { full }
          abstract
          creators { display }
          subjects { dbcVerified { display } }
          genreAndForm
          fictionNonfiction { display code }
          materialTypes {
            materialTypeGeneral { code display }
            materialTypeSpecific { display }
          }
          mainLanguages { display isoCode }
          manifestations {
            bestRepresentation {
              cover { large { url } }
              audience {
                generalAudience
                ages { display }
                childrenOrAdults { display code }
              }
            }
          }
        }
      }
    }
    GRAPHQL;

  /**
   * Constructs the material vector indexer.
   */
  public function __construct(
    private readonly Fbi $fbi,
    private readonly LibraryTokenHandler $tokenHandler,
    private readonly BranchRepositoryInterface $branchRepository,
    private readonly BranchSettings $branchSettings,
    private readonly ClientInterface $httpClient,
    private readonly EmbeddingClient $embeddingClient,
    private readonly QdrantClient $qdrant,
    private readonly VectorSearchSettings $settings,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Indexes works matching a CQL query into Qdrant.
   *
   * @param string $cql
   *   The CQL query selecting works to index.
   * @param int $max
   *   Maximum number of works to index.
   * @param int $batch
   *   Page/batch size for fetching and embedding.
   *
   * @return array
   *   Keyed by 'indexed' (int) and 'hitcount' (int).
   */
  public function index(string $cql, int $max = 200, int $batch = 50): array {
    $filters = $this->buildFilters();

    $indexed = 0;
    $hitcount = 0;
    $offset = 0;
    $collection_ready = FALSE;

    while ($indexed < $max) {
      $page = min($batch, $max - $indexed);
      $works = $this->fetchWorks($cql, $filters, $offset, $page, $hitcount);

      if ($works === []) {
        break;
      }

      $upserted = $this->upsertWorks($works, $collection_ready);
      if ($upserted === 0) {
        break;
      }

      $indexed += $upserted;
      $offset += count($works);

      if ($offset >= $hitcount) {
        break;
      }
    }

    return ['indexed' => $indexed, 'hitcount' => $hitcount];
  }

  /**
   * Fetches works from FBI and writes a JSON snapshot (no embedding).
   *
   * @param string $cql
   *   The CQL query selecting works.
   * @param int $max
   *   Maximum number of works to export.
   * @param int $batch
   *   FBI page size.
   * @param string $file_path
   *   Absolute path for the JSON snapshot.
   *
   * @return array
   *   Keys: exported (int), hitcount (int), file (string).
   */
  public function exportToFile(
    string $cql,
    int $max,
    int $batch,
    string $file_path,
  ): array {
    $filters = $this->buildFilters();
    $exported = 0;
    $hitcount = 0;
    $offset = 0;
    $all_works = [];

    while ($exported < $max) {
      $page = min($batch, $max - $exported);
      $works = $this->fetchWorks($cql, $filters, $offset, $page, $hitcount);

      if ($works === []) {
        break;
      }

      array_push($all_works, ...$works);
      $exported += count($works);
      $offset += count($works);

      MaterialVectorSnapshot::write($file_path, [
        'cql' => $cql,
        'fbi_hitcount' => $hitcount,
        'branch_ids' => $filters['branchId'] ?? [],
        'export_batch_size' => $batch,
      ], $all_works);

      if ($offset >= $hitcount) {
        break;
      }
    }

    return [
      'exported' => $exported,
      'hitcount' => $hitcount,
      'file' => $file_path,
    ];
  }

  /**
   * Embeds and upserts works from a JSON snapshot into Qdrant.
   *
   * @param string $file_path
   *   Absolute path to a snapshot written by exportToFile().
   * @param int $batch
   *   Embedding batch size (capped at 16 for TEI).
   *
   * @return array
   *   Keys: indexed (int), hitcount (int), file (string).
   */
  public function indexFromFile(string $file_path, int $batch = self::EMBED_BATCH): array {
    $snapshot = MaterialVectorSnapshot::read($file_path);
    $works = $snapshot['works'];
    $max_batch = $this->settings->usesQdrantInference() ? 50 : self::EMBED_BATCH;
    $batch = max(1, min($max_batch, $batch));
    $collection_ready = FALSE;
    $indexed = 0;

    foreach (array_chunk($works, $batch) as $chunk) {
      $indexed += $this->upsertWorks($chunk, $collection_ready);
    }

    return [
      'indexed' => $indexed,
      'hitcount' => (int) ($snapshot['fbi_hitcount'] ?? count($works)),
      'file' => $file_path,
    ];
  }

  /**
   * Embeds normalised works and upserts them into Qdrant.
   *
   * @param array[] $works
   *   Normalised works from fetchWorks().
   * @param bool $collection_ready
   *   Whether the Qdrant collection has been ensured (by reference).
   *
   * @return int
   *   Number of works upserted.
   */
  private function upsertWorks(array $works, bool &$collection_ready): int {
    if ($works === []) {
      return 0;
    }

    if ($this->settings->usesQdrantInference()) {
      return $this->upsertWorksViaInference($works, $collection_ready);
    }

    return $this->upsertWorksViaTei($works, $collection_ready);
  }

  /**
   * Upserts works using Qdrant Cloud Inference (Document vectors).
   *
   * @param array[] $works
   *   Normalised works from fetchWorks().
   * @param bool $collection_ready
   *   Whether the Qdrant collection has been ensured (by reference).
   *
   * @return int
   *   Number of works upserted.
   */
  private function upsertWorksViaInference(array $works, bool &$collection_ready): int {
    if (!$collection_ready) {
      $this->qdrant->ensureCollection(self::COLLECTION);
      $collection_ready = TRUE;
    }

    $points = [];
    foreach ($works as $work) {
      $points[] = [
        'workId' => $work['workId'],
        'text' => $work['text'],
        'payload' => $work['payload'],
      ];
    }

    if (!$this->qdrant->upsert(self::COLLECTION, $points)) {
      return 0;
    }

    return count($works);
  }

  /**
   * Upserts works using local TEI embeddings (float vectors).
   *
   * @param array[] $works
   *   Normalised works from fetchWorks().
   * @param bool $collection_ready
   *   Whether the Qdrant collection has been ensured (by reference).
   *
   * @return int
   *   Number of works upserted.
   */
  private function upsertWorksViaTei(array $works, bool &$collection_ready): int {
    $texts = array_column($works, 'text');
    $vectors = $this->embeddingClient->embedPassages($texts);
    if (count($vectors) !== count($works)) {
      $this->logger->error('Embedding count mismatch; aborting batch.');
      return 0;
    }

    if (!$collection_ready) {
      $size = isset($vectors[0]) ? count($vectors[0]) : 0;
      if ($size === 0) {
        $this->logger->error('Empty embedding vector; is the TEI service running?');
        return 0;
      }
      $this->qdrant->ensureCollection(self::COLLECTION, $size);
      $collection_ready = TRUE;
    }

    $points = [];
    foreach ($works as $i => $work) {
      $points[] = [
        'workId' => $work['workId'],
        'vector' => $vectors[$i],
        'payload' => $work['payload'],
      ];
    }

    if (!$this->qdrant->upsert(self::COLLECTION, $points)) {
      return 0;
    }

    return count($works);
  }

  /**
   * Builds complexSearch filters for holdings scope.
   *
   * @return array<string, mixed>
   *   Filters for FBI complexSearch.
   */
  private function buildFilters(): array {
    $filters = [];
    $branch_ids = $this->getSearchBranchIds();
    if ($branch_ids !== []) {
      $filters['branchId'] = $branch_ids;
    }

    return $filters;
  }

  /**
   * Fetches a page of works with normalised embedding text and payload.
   *
   * @param string $cql
   *   The CQL query.
   * @param array $filters
   *   complexSearch filters.
   * @param int $offset
   *   Pagination offset.
   * @param int $limit
   *   Page size.
   * @param int $hitcount
   *   Populated with the total hitcount (by reference).
   *
   * @return array[]
   *   Each item has keys 'workId', 'text' and 'payload'.
   */
  private function fetchWorks(string $cql, array $filters, int $offset, int $limit, int &$hitcount): array {
    try {
      $response = $this->httpClient->request('POST', $this->fbi->getServiceUrl('local'), [
        'headers' => [
          'Content-Type' => 'application/json',
          'Authorization' => 'Bearer ' . ($this->tokenHandler->getToken()?->token ?? ''),
        ],
        'json' => [
          'query' => self::QUERY,
          'variables' => [
            'cql' => $cql,
            'offset' => $offset,
            'limit' => max(1, $limit),
            'filters' => (object) $filters,
          ],
        ],
        'timeout' => 90,
      ]);

      $payload = json_decode((string) $response->getBody(), TRUE, flags: JSON_THROW_ON_ERROR);
      if (!empty($payload['errors'])) {
        throw new \RuntimeException((string) ($payload['errors'][0]['message'] ?? 'Unknown FBI error'));
      }

      $hitcount = (int) ($payload['data']['complexSearch']['hitcount'] ?? 0);
      $works = $payload['data']['complexSearch']['works'] ?? [];

      $result = [];
      foreach ($works as $work) {
        $normalized = $this->normalizeWork($work);
        if ($normalized !== NULL) {
          $result[] = $normalized;
        }
      }

      return $result;
    }
    catch (\Throwable $exception) {
      $this->logger->error('FBI work fetch for indexing failed: @message', [
        '@message' => $exception->getMessage(),
      ]);

      return [];
    }
  }

  /**
   * Normalises a raw FBI work into embedding text and Qdrant payload.
   *
   * @param array $work
   *   A work array from the GraphQL response.
   *
   * @return array|null
   *   Normalised work, or NULL when there is nothing to embed.
   */
  private function normalizeWork(array $work): ?array {
    if (empty($work['workId']) || !is_string($work['workId'])) {
      return NULL;
    }

    $title = (string) ($work['titles']['full'][0] ?? '');
    $abstract = (string) ($work['abstract'][0] ?? '');
    $creator = (string) ($work['creators'][0]['display'] ?? '');

    $subjects = $this->extractStringList($work['subjects']['dbcVerified'] ?? [], 'display');
    $genre_and_form = $this->extractStringList($work['genreAndForm'] ?? []);

    $fiction_nonfiction = (string) ($work['fictionNonfiction']['display'] ?? '');
    $fiction_nonfiction_code = (string) ($work['fictionNonfiction']['code'] ?? '');

    $material_type_general = '';
    $material_type_general_code = '';
    $material_types_specific = [];
    foreach ($work['materialTypes'] ?? [] as $material_type) {
      if ($material_type_general === '' && !empty($material_type['materialTypeGeneral']['display'])) {
        $material_type_general = (string) $material_type['materialTypeGeneral']['display'];
        $material_type_general_code = (string) ($material_type['materialTypeGeneral']['code'] ?? '');
      }
      if (!empty($material_type['materialTypeSpecific']['display'])) {
        $material_types_specific[] = (string) $material_type['materialTypeSpecific']['display'];
      }
    }
    $material_types_specific = array_values(array_unique($material_types_specific));

    $languages = $this->extractStringList($work['mainLanguages'] ?? [], 'display');
    $language_codes = $this->extractStringList($work['mainLanguages'] ?? [], 'isoCode');

    $best_representation = $work['manifestations']['bestRepresentation'] ?? [];
    $cover_url = (string) ($best_representation['cover']['large']['url'] ?? '');

    $audience = $best_representation['audience'] ?? [];
    $general_audience = $this->extractStringList($audience['generalAudience'] ?? []);
    $children_or_adults = $this->extractStringList($audience['childrenOrAdults'] ?? [], 'display');
    $age_ranges = $this->extractStringList($audience['ages'] ?? [], 'display');

    $text = $this->buildEmbeddingText(
      $title,
      $creator,
      $abstract,
      $subjects,
      $genre_and_form,
      $fiction_nonfiction,
      $material_type_general,
      $general_audience,
      $children_or_adults,
      $age_ranges,
      $languages,
    );

    if ($text === '') {
      return NULL;
    }

    return [
      'workId' => $work['workId'],
      'text' => $text,
      'payload' => array_filter([
        'title' => $title,
        'creator' => $creator,
        'abstract' => $abstract,
        'subjects' => $subjects,
        'genreAndForm' => $genre_and_form,
        'fictionNonfiction' => $fiction_nonfiction,
        'fictionNonfictionCode' => $fiction_nonfiction_code,
        'materialTypeGeneral' => $material_type_general,
        'materialTypeGeneralCode' => $material_type_general_code,
        'materialTypesSpecific' => $material_types_specific,
        'languages' => $languages,
        'languageCodes' => $language_codes,
        'generalAudience' => $general_audience,
        'childrenOrAdults' => $children_or_adults,
        'ageRanges' => $age_ranges,
        'coverUrl' => $cover_url,
      ], static fn ($value): bool => $value !== '' && $value !== []),
    ];
  }

  /**
   * Builds the passage text sent to the embedding model.
   */
  private function buildEmbeddingText(
    string $title,
    string $creator,
    string $abstract,
    array $subjects,
    array $genre_and_form,
    string $fiction_nonfiction,
    string $material_type_general,
    array $general_audience,
    array $children_or_adults,
    array $age_ranges,
    array $languages,
  ): string {
    $parts = array_filter([$title, $creator, $abstract]);

    if ($subjects !== []) {
      $parts[] = 'Subjects: ' . implode(', ', array_slice($subjects, 0, 15));
    }

    if ($genre_and_form !== []) {
      $parts[] = 'Genre: ' . implode(', ', array_slice($genre_and_form, 0, 10));
    }

    if ($fiction_nonfiction !== '') {
      $parts[] = $fiction_nonfiction;
    }

    if ($material_type_general !== '') {
      $parts[] = $material_type_general;
    }

    $audience_parts = array_merge($general_audience, $children_or_adults, $age_ranges);
    if ($audience_parts !== []) {
      $parts[] = 'Audience: ' . implode(', ', $audience_parts);
    }

    if ($languages !== []) {
      $parts[] = 'Language: ' . implode(', ', $languages);
    }

    return trim(implode('. ', $parts));
  }

  /**
   * Extracts string values from a list of arrays or scalar strings.
   *
   * @param array $items
   *   The source list.
   * @param string|null $key
   *   Optional array key to read from each item.
   *
   * @return string[]
   *   Non-empty string values.
   */
  private function extractStringList(array $items, ?string $key = NULL): array {
    $values = [];

    foreach ($items as $item) {
      if ($key === NULL) {
        $value = is_string($item) ? $item : '';
      }
      elseif (is_array($item) && isset($item[$key]) && is_string($item[$key])) {
        $value = $item[$key];
      }
      else {
        $value = '';
      }

      $value = trim($value);
      if ($value !== '') {
        $values[] = $value;
      }
    }

    return array_values(array_unique($values));
  }

  /**
   * Returns branch agency numbers used for FBI search filtering.
   *
   * @return string[]
   *   Numeric agency IDs extracted from branch ISILs.
   */
  private function getSearchBranchIds(): array {
    $branch_ids = [];

    foreach ($this->branchRepository->getBranches() as $branch) {
      if (in_array($branch->id, $this->branchSettings->getExcludedSearchBranches(), TRUE)) {
        continue;
      }

      if (preg_match('/-(\d+)/', $branch->id, $matches)) {
        $branch_ids[] = $matches[1];
      }
    }

    return $branch_ids;
  }

}
