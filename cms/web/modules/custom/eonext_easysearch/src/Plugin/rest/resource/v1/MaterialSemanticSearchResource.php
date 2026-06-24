<?php

declare(strict_types=1);

namespace Drupal\eonext_easysearch\Plugin\rest\resource\v1;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\eonext_easysearch\MaterialSemanticSearch;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * REST resource for semantic material search.
 *
 * @RestResource(
 *   id = "eonext_easysearch:material_semantic_search",
 *   label = @Translation("Material semantic search"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/material-semantic-search",
 *   }
 * )
 */
final class MaterialSemanticSearchResource extends ResourceBase {

  private const DEFAULT_LIMIT = 10;

  private const MAX_LIMIT = 50;

  /**
   * Constructs the material semantic search REST resource.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    $logger,
    private readonly MaterialSemanticSearch $materialSemanticSearch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get(MaterialSemanticSearch::class),
    );
  }

  /**
   * Semantic material search.
   *
   * Query parameters:
   *   q              - Natural-language search query (required).
   *   limit          - Max results (default 10, max 50).
   *   material_type  - Filter by FBI GeneralMaterialTypeCodeEnum (e.g. BOOKS).
   *   language       - Filter by main language ISO code (e.g. dan, eng).
   *   children       - If 1/true, prefer works tagged for children in the index.
   */
  public function get(Request $request): Response {
    $query = $request->query->get('q');
    if (!is_string($query) || trim($query) === '') {
      throw new BadRequestHttpException('Query parameter "q" is required.');
    }

    $limit = (int) $request->query->get('limit', self::DEFAULT_LIMIT);
    $limit = max(1, min(self::MAX_LIMIT, $limit));

    $filters = [
      'material_type' => $this->stringQueryParam($request, 'material_type'),
      'language' => $this->stringQueryParam($request, 'language'),
      'children' => in_array(
        strtolower((string) $request->query->get('children', '')),
        ['1', 'true', 'yes'],
        TRUE,
      ),
    ];

    $data = $this->materialSemanticSearch->search($query, $limit, $filters);

    $response = new CacheableResponse(
      json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
      200,
      ['Content-Type' => 'application/json'],
    );

    $cache = (new CacheableMetadata())
      ->setCacheMaxAge(0);
    $response->addCacheableDependency($cache);

    return $response;
  }

  /**
   * Returns a trimmed query string parameter or empty string.
   */
  private function stringQueryParam(Request $request, string $name): string {
    $value = $request->query->get($name);

    return is_string($value) ? trim($value) : '';
  }

}
