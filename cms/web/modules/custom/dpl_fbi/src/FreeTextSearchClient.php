<?php

declare(strict_types=1);

namespace Drupal\dpl_fbi;

use Drupal\dpl_library_agency\Branch\BranchRepositoryInterface;
use Drupal\dpl_library_agency\BranchSettings;
use Drupal\dpl_library_token\LibraryTokenHandler;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Prototype client for FBI free-text and semantic (mood) search.
 *
 * Used to evaluate whether FBI's own search is good enough before investing
 * in a local vector/AI index. Results are scoped to library holdings via the
 * branchId filter (free-text search only; moodSearch has no filter support).
 */
final class FreeTextSearchClient {

  /**
   * FBI simple free-text search, scoped to holdings via branchId.
   */
  private const SEARCH_QUERY = <<<'GRAPHQL'
    query simpleSearch(
      $q: SearchQueryInput!
      $filters: SearchFiltersInput
      $offset: Int!
      $limit: PaginationLimitScalar!
    ) {
      search(q: $q, filters: $filters) {
        hitcount
        works(offset: $offset, limit: $limit) {
          workId
          titles { full }
          creators { display }
        }
      }
    }
    GRAPHQL;

  /**
   * FBI semantic "mood" search (no holdings filtering available).
   */
  private const MOOD_QUERY = <<<'GRAPHQL'
    query moodNaturalSearch(
      $q: String!
      $offset: Int!
      $limit: PaginationLimitScalar!
    ) {
      mood {
        moodSearch(q: $q, offset: $offset, limit: $limit) {
          works(offset: $offset, limit: $limit) {
            workId
            titles { full }
            creators { display }
          }
        }
      }
    }
    GRAPHQL;

  /**
   * Constructs the free-text search client.
   */
  public function __construct(
    private readonly Fbi $fbi,
    private readonly LibraryTokenHandler $tokenHandler,
    private readonly BranchRepositoryInterface $branchRepository,
    private readonly BranchSettings $branchSettings,
    private readonly ClientInterface $httpClient,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Runs FBI free-text search scoped to holdings.
   *
   * @param string $query
   *   Natural-language / keyword query typed by a user.
   * @param int $limit
   *   Maximum number of works to return.
   *
   * @return array<int,array{workId:string,title:string,creator:string}>
   *   Matching works in relevance order.
   */
  public function search(string $query, int $limit = 10): array {
    if (trim($query) === '') {
      return [];
    }

    $variables = [
      'q' => ['all' => $query],
      'offset' => 0,
      'limit' => max(1, $limit),
    ];

    // Only scope to holdings when branches are configured. FBI treats an empty
    // branchId list as "match nothing", which would wipe out all results.
    $branch_ids = $this->getSearchBranchIds();
    if ($branch_ids !== []) {
      $variables['filters'] = ['branchId' => $branch_ids];
    }

    return $this->extractWorks(
      $this->execute(self::SEARCH_QUERY, $variables),
      ['data', 'search', 'works'],
    );
  }

  /**
   * Runs FBI semantic "mood" search.
   *
   * Note: moodSearch covers fiction with mood tags and does not support
   * branchId filtering, so results are not limited to holdings.
   *
   * @param string $query
   *   Natural-language query describing a mood/theme.
   * @param int $limit
   *   Maximum number of works to return.
   *
   * @return array<int,array{workId:string,title:string,creator:string}>
   *   Matching works in relevance order.
   */
  public function moodSearch(string $query, int $limit = 10): array {
    if (trim($query) === '') {
      return [];
    }

    $variables = [
      'q' => $query,
      'offset' => 0,
      'limit' => max(1, $limit),
    ];

    return $this->extractWorks(
      $this->execute(self::MOOD_QUERY, $variables),
      ['data', 'mood', 'moodSearch', 'works'],
    );
  }

  /**
   * Executes a GraphQL query against the local FBI profile.
   *
   * @param string $query
   *   The GraphQL query string.
   * @param array<string,mixed> $variables
   *   GraphQL variables.
   *
   * @return array<string,mixed>
   *   The decoded GraphQL payload, or an empty array on failure.
   */
  private function execute(string $query, array $variables): array {
    try {
      $response = $this->httpClient->request('POST', $this->fbi->getServiceUrl('local'), [
        'headers' => [
          'Content-Type' => 'application/json',
          'Authorization' => 'Bearer ' . ($this->tokenHandler->getToken()?->token ?? ''),
        ],
        'json' => [
          'query' => $query,
          'variables' => $variables,
        ],
        'timeout' => 30,
      ]);

      $payload = json_decode((string) $response->getBody(), TRUE, flags: JSON_THROW_ON_ERROR);

      if (!empty($payload['errors'])) {
        $message = $payload['errors'][0]['message'] ?? 'Unknown FBI error';
        throw new \RuntimeException((string) $message);
      }

      return is_array($payload) ? $payload : [];
    }
    catch (\Throwable $exception) {
      $this->logger->error('FBI free-text search failed: @message', [
        '@message' => $exception->getMessage(),
      ]);

      return [];
    }
  }

  /**
   * Extracts a normalised work list from a GraphQL payload.
   *
   * @param array<string,mixed> $payload
   *   The decoded GraphQL payload.
   * @param string[] $path
   *   Path of keys leading to the works array.
   *
   * @return array<int,array{workId:string,title:string,creator:string}>
   *   Normalised works.
   */
  private function extractWorks(array $payload, array $path): array {
    $works = $payload;
    foreach ($path as $key) {
      $works = $works[$key] ?? NULL;
      if (!is_array($works)) {
        return [];
      }
    }

    $results = [];
    foreach ($works as $work) {
      if (empty($work['workId']) || !is_string($work['workId'])) {
        continue;
      }

      $results[] = [
        'workId' => $work['workId'],
        'title' => (string) ($work['titles']['full'][0] ?? ''),
        'creator' => (string) ($work['creators'][0]['display'] ?? ''),
      ];
    }

    return $results;
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
