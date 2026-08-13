<?php

declare(strict_types=1);

namespace Drupal\eonext_editorial_search\Fbi;

use Drupal\dpl_fbi\Fbi;
use Drupal\dpl_library_agency\Branch\BranchRepositoryInterface;
use Drupal\dpl_library_agency\BranchSettings;
use Drupal\dpl_library_token\LibraryTokenHandler;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Client for FBI complexSearch GraphQL queries.
 *
 * Lives inside this module (rather than dpl_fbi) so the editorial search
 * feature ships self-contained; it consumes the public dpl_fbi / library
 * services via dependency injection without modifying those modules.
 */
final class ComplexSearchClient {

  private const QUERY = <<<'GRAPHQL'
    query complexSearchWithPagination(
      $cql: String!
      $offset: Int!
      $limit: PaginationLimitScalar!
      $filters: ComplexSearchFiltersInput!
      $sort: [SortInput!]
    ) {
      complexSearch(cql: $cql, filters: $filters) {
        hitcount
        works(offset: $offset, limit: $limit, sort: $sort) {
          workId
        }
      }
    }
    GRAPHQL;

  /**
   * Maps advanced-search sort URL values to FBI sort inputs.
   *
   * @var array<string, array{index: string, order: string}>
   */
  private const SORT_MAP = [
    'sort.title.asc' => ['index' => 'sort.title', 'order' => 'ASC'],
    'sort.title.desc' => ['index' => 'sort.title', 'order' => 'DESC'],
    'sort.creator.asc' => ['index' => 'sort.creator', 'order' => 'ASC'],
    'sort.creator.desc' => ['index' => 'sort.creator', 'order' => 'DESC'],
    'sort.latestpublicationdate.asc' => ['index' => 'sort.latestpublicationdate', 'order' => 'ASC'],
    'sort.latestpublicationdate.desc' => ['index' => 'sort.latestpublicationdate', 'order' => 'DESC'],
  ];

  public function __construct(
    private readonly Fbi $fbi,
    private readonly LibraryTokenHandler $tokenHandler,
    private readonly BranchRepositoryInterface $branchRepository,
    private readonly BranchSettings $branchSettings,
    private readonly ClientInterface $httpClient,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Executes a complexSearch query and returns matching work IDs.
   *
   * @param string $cql
   *   The CQL search string.
   * @param int $limit
   *   Maximum number of works to return.
   * @param array<string, mixed> $cql_search
   *   Values from a dpl_fbi_cql_search field item.
   *
   * @return string[]
   *   Work IDs in result order.
   */
  public function getWorkIds(string $cql, int $limit, array $cql_search = []): array {
    if ($cql === '') {
      return [];
    }

    $variables = [
      'cql' => $cql,
      'offset' => 0,
      'limit' => max(1, $limit),
      'filters' => $this->buildFilters($cql_search),
    ];

    $sort = $this->buildSort($cql_search['sort'] ?? NULL);
    if ($sort !== NULL) {
      $variables['sort'] = $sort;
    }

    try {
      $response = $this->httpClient->request('POST', $this->fbi->getServiceUrl('local'), [
        'headers' => [
          'Content-Type' => 'application/json',
          'Authorization' => 'Bearer ' . ($this->tokenHandler->getToken()?->token ?? ''),
        ],
        'json' => [
          'query' => self::QUERY,
          'variables' => $variables,
        ],
        'timeout' => 30,
      ]);

      $payload = json_decode((string) $response->getBody(), TRUE, flags: JSON_THROW_ON_ERROR);
      if (!empty($payload['errors'])) {
        $message = $payload['errors'][0]['message'] ?? 'Unknown FBI error';
        throw new \RuntimeException((string) $message);
      }

      $works = $payload['data']['complexSearch']['works'] ?? [];
      $work_ids = [];
      foreach ($works as $work) {
        if (!empty($work['workId']) && is_string($work['workId'])) {
          $work_ids[] = $work['workId'];
        }
      }

      return $work_ids;
    }
    catch (\Throwable $exception) {
      $this->logger->error('FBI complexSearch failed: @message', [
        '@message' => $exception->getMessage(),
      ]);

      return [];
    }
  }

  /**
   * Builds FBI complexSearch filters from a CQL search field item.
   *
   * @param array<string, mixed> $cql_search
   *   Values from a dpl_fbi_cql_search field item.
   *
   * @return array<string, mixed>
   *   GraphQL filter variables.
   */
  public function buildFilters(array $cql_search): array {
    $filters = [
      'branchId' => $this->getSearchBranchIds(),
    ];

    foreach (['location', 'sublocation', 'branch', 'department'] as $key) {
      if (!empty($cql_search[$key]) && is_string($cql_search[$key])) {
        $filters[$key] = $this->commaSeparatedStringToArray($cql_search[$key]);
      }
    }

    if (!empty($cql_search['onshelf'])) {
      $filters['status'] = ['ONSHELF'];
    }

    if (!empty($cql_search['first_accession_date_operator']) && !empty($cql_search['first_accession_date_value'])) {
      $filters['firstAccessionDate'] = $cql_search['first_accession_date_operator'] . $cql_search['first_accession_date_value'];
    }

    return $filters;
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

  /**
   * @return string[]
   */
  private function commaSeparatedStringToArray(string $input): array {
    return array_values(array_filter(array_map('trim', explode(',', $input))));
  }

  /**
   * @return array<int, array{index: string, order: string}>|null
   */
  private function buildSort(?string $sort): ?array {
    if ($sort === NULL || $sort === '' || $sort === 'relevance') {
      return NULL;
    }

    return isset(self::SORT_MAP[$sort]) ? [self::SORT_MAP[$sort]] : NULL;
  }

}
