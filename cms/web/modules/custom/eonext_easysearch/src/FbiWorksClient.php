<?php

declare(strict_types=1);

namespace Drupal\eonext_easysearch;

use Drupal\dpl_fbi\Fbi;
use Drupal\dpl_library_token\LibraryTokenHandler;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Fetches FBI works in bulk for hydrating semantic search results.
 *
 * Uses the FBI works(id: [...]) query with fields aligned to the React
 * WorkSmall GraphQL fragment so results can feed SearchResultList /
 * CardListItem.
 */
final class FbiWorksClient {

  /**
   * Work fields compatible with the frontend WorkSmall fragment.
   */
  private const QUERY = <<<'GRAPHQL'
    query worksByIds($ids: [String!]!) {
      works(id: $ids) {
        workId
        titles {
          full
          original
        }
        abstract
        creators {
          display
          __typename
        }
        series {
          title
          isPopular
          members {
            numberInSeries
            work {
              workId
              titles {
                main
              }
            }
          }
          readThisFirst
          readThisWhenever
        }
        workYear {
          year
        }
        genreAndForm
        manifestations {
          all {
            ...ManifestationWorkSmall
          }
          latest {
            ...ManifestationWorkSmall
          }
          bestRepresentation {
            ...ManifestationWorkSmall
          }
        }
      }
    }

    fragment ManifestationWorkSmall on Manifestation {
      pid
      genreAndForm
      source
      titles {
        main
        original
      }
      fictionNonfiction {
        display
        code
      }
      materialTypes {
        materialTypeSpecific {
          display
        }
      }
      creators {
        display
        __typename
      }
      identifiers {
        type
        value
      }
      accessTypes {
        code
      }
      access {
        __typename
        ... on AccessUrl {
          origin
          url
          loginRequired
          status
        }
        ... on InfomediaService {
          id
        }
        ... on InterLibraryLoan {
          loanIsPossible
        }
        ... on Ereol {
          origin
          url
          canAlwaysBeLoaned
        }
        ... on DigitalArticleService {
          issn
        }
      }
      languages {
        main {
          display
          iso639Set1
        }
      }
      audience {
        generalAudience
        ages {
          display
        }
        childrenOrAdults {
          display
          code
        }
      }
      cover {
        large {
          url
          width
          height
        }
        medium {
          url
          width
          height
        }
        small {
          url
          width
          height
        }
        thumbnail
      }
    }
    GRAPHQL;

  /**
   * Constructs the FBI works client.
   */
  public function __construct(
    private readonly Fbi $fbi,
    private readonly LibraryTokenHandler $tokenHandler,
    private readonly ClientInterface $httpClient,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Loads works by ID from FBI.
   *
   * @param string[] $work_ids
   *   Work IDs to load.
   *
   * @return array[]
   *   Works keyed by workId. Order is not preserved.
   */
  public function getByIds(array $work_ids): array {
    $work_ids = array_values(array_filter(array_unique($work_ids)));
    if ($work_ids === []) {
      return [];
    }

    try {
      $response = $this->httpClient->request('POST', $this->fbi->getServiceUrl('local'), [
        'headers' => [
          'Content-Type' => 'application/json',
          'Authorization' => 'Bearer ' . ($this->tokenHandler->getToken()?->token ?? ''),
        ],
        'json' => [
          'query' => self::QUERY,
          'variables' => ['ids' => $work_ids],
        ],
        'timeout' => 60,
      ]);

      $payload = json_decode((string) $response->getBody(), TRUE, flags: JSON_THROW_ON_ERROR);
      if (!empty($payload['errors'])) {
        throw new \RuntimeException((string) ($payload['errors'][0]['message'] ?? 'Unknown FBI error'));
      }

      $indexed = [];
      foreach ($payload['data']['works'] ?? [] as $work) {
        if (!empty($work['workId']) && is_string($work['workId'])) {
          $indexed[$work['workId']] = $work;
        }
      }

      return $indexed;
    }
    catch (\Throwable $exception) {
      $this->logger->error('FBI works bulk fetch failed: @message', [
        '@message' => $exception->getMessage(),
      ]);

      return [];
    }
  }

}
