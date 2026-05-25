<?php

namespace Drupal\eonext_editorial_search\Plugin\rest\resource\v1;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Url;
use Drupal\dpl_search\DplSearchSettings;
use Drupal\file\FileInterface;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\media\MediaInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\views\Views;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * REST resource for querying the editorial search.
 *
 * @RestResource(
 *   id = "eonext_editorial_search:editorial_search",
 *   label = @Translation("Editorial search"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/editorial-search",
 *   }
 * )
 */
final class EditorialSearchResource extends ResourceBase {

  const DEFAULT_PAGE_SIZE = 10;
  const MAX_PAGE_SIZE = 100;

  /**
   * Query parameter used by Drupal facets (query_string processor).
   */
  private const FACET_FILTER_KEY = 'f';

  /**
   * Request attribute for entity-type filters (node, eventseries).
   */
  private const ENTITY_TYPE_FILTER_ATTRIBUTE = 'editorial_search_entity_types';

  /**
   * Values for "type" that filter by Drupal entity type, not content_type facet.
   */
  private const ENTITY_TYPE_FILTER_VALUES = [
    'node',
    'eventseries',
  ];

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
    );
  }

  /**
   * Editorial search resource.
   *
   * Supported query parameters:
   *   q         - Fulltext search string (required unless material is set).
   *   material  - Work ID; finds articles referencing the work in field_material.
   *   page      - Zero-based page number (default: 0).
   *   page_size - Items per page (default: 10, max: 100).
   *   f[]         - Facet filters (same as /search/web), e.g. f[]=content_type:article.
   *   entity_type - Entity filter (node, eventseries).
   */
  public function get(Request $request): Response {
    $search = $request->query->get('q');
    $material = $request->query->get('material');

    $material_editorials = [];
    $editorial_nids = [];

    $page = max(0, (int) $request->query->get('page', 0));
    $page_size = min(
      self::MAX_PAGE_SIZE,
      max(
        1,
        (int) $request->query->get('page_size', self::DEFAULT_PAGE_SIZE)
      )
    );

    if (!empty($material) && is_string($material)) {
      $editorial_nids = $this->findArticleNidsByMaterial($material);

      // Return the editorials directly when no fulltext query is provided.
      if (empty($search) || !is_string($search)) {
        $paged_nids = array_slice($editorial_nids, $page * $page_size, $page_size);
        $material_editorials = \Drupal::entityTypeManager()
          ->getStorage('node')
          ->loadMultiple($paged_nids);

        $results = [];
        foreach ($material_editorials as $editorial) {
          $results[] = $this->mapEntity($editorial);
        }

        $data = [
          'total' => count($editorial_nids),
          'page' => $page,
          'page_size' => $page_size,
          'results' => $results,
        ];

        $response = $this->createJsonResponse($data);
        $response->addCacheableDependency(
          $this->buildCacheMetadata($material_editorials)
        );

        return $response;
      }
    }

    if (empty($search) || !is_string($search)) {
      throw new HttpException(400, 'Missing required query parameter "q".');
    }

    $view = Views::getView(DplSearchSettings::EDITORIAL_VIEW_ID);

    if (!($view instanceof ViewExecutable)) {
      throw new HttpException(500, 'Editorial search view could not be loaded.');
    }

    $view->setDisplay('page');
    $view->setItemsPerPage($page_size);
    $view->setCurrentPage($page);
    $view->setExposedInput([DplSearchSettings::EDITORIAL_QUERY_KEY => $search]);
    $this->applyFacetFiltersToRequest($request);
    $view->execute();

    $results = [];

    $entity_type_filters = $request->attributes->get(self::ENTITY_TYPE_FILTER_ATTRIBUTE, []);

    foreach ($view->result as $row) {
      $entity = $row->_entity ?? NULL;
      if ($entity instanceof ContentEntityInterface) {

        if (!empty($material) && is_string($material)) {
          if ($entity->getEntityTypeId() !== 'node' || !in_array((int) $entity->id(), $editorial_nids, TRUE)) {
            continue;
          }
        }

        if ($entity_type_filters !== [] && !in_array($entity->getEntityTypeId(), $entity_type_filters, TRUE)) {
          continue;
        }

        $results[] = $this->mapEntity($entity);
      }
    }

    $use_result_count = !empty($material) || $entity_type_filters !== [];

    $data = [
      'total' => $use_result_count ? count($results) : (int) $view->total_rows,
      'page' => $page,
      'page_size' => $page_size,
      'results' => $results,
    ];

    $response = $this->createJsonResponse($data);
    $response->addCacheableDependency(
      $this->buildCacheMetadata($material_editorials)
    );

    return $response;
  }

  /**
   * Builds cache metadata for editorial search responses.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface[] $entities
   *   Optional entities to add as cacheable dependencies.
   */
  private function buildCacheMetadata(array $entities = []): CacheableMetadata {
    $cache = new CacheableMetadata();
    $cache->setCacheContexts($this->getQueryArgCacheContexts());
    $cache->setCacheTags(['search_api_list:content_events']);

    foreach ($entities as $entity) {
      if ($entity instanceof ContentEntityInterface) {
        $cache->addCacheableDependency($entity);
      }
    }

    return $cache;
  }

  /**
   * Returns cache contexts for supported query parameters.
   *
   * All supported query args are always included so that responses for the
   * same "q" value cannot collide when "material" (or pagination args) differ.
   *
   * @return string[]
   *   Cache context IDs.
   */
  private function getQueryArgCacheContexts(): array {
    $contexts = [
      'url.query_args:q',
      'url.query_args:material',
      'url.query_args:page',
      'url.query_args:page_size',
      'url.query_args:' . self::FACET_FILTER_KEY,
    ];

    $contexts[] = 'url.query_args:entity_type';

    return $contexts;
  }

  /**
   * Applies editorial search facet filters to the request (for Views + Facets).
   *
   * Mirrors /search/web facet query strings, e.g. f[]=content_type:article.
   */
  private function applyFacetFiltersToRequest(Request $request): void {
    $facet_filters = array_values(array_unique($this->collectFacetFilterValues($request)));
    [$facet_filters, $entity_type_filters] = $this->partitionFacetFilters($facet_filters);

    if ($request->query->has('entity_type')) {
      $raw = $request->query->all()['entity_type'] ?? [];
      $values = is_array($raw) ? $raw : [$raw];
      foreach ($values as $value) {
        if (is_string($value) && in_array($value, self::ENTITY_TYPE_FILTER_VALUES, TRUE)) {
          $entity_type_filters[] = $value;
        }
      }
    }

    $entity_type_filters = array_values(array_unique($entity_type_filters));

    if ($entity_type_filters !== []) {
      $request->attributes->set(self::ENTITY_TYPE_FILTER_ATTRIBUTE, $entity_type_filters);
    }

    if ($facet_filters === []) {
      return;
    }

    $request->query->set(self::FACET_FILTER_KEY, $facet_filters);
  }

  /**
   * Splits facet tokens into Search API facet filters and entity-type filters.
   *
   * The content_type facet uses bundle machine names (article, default). Values
   * "node" and "eventseries" filter by Drupal entity type instead.
   *
   * @return array{0: string[], 1: string[]}
   *   [facet filter tokens, entity type IDs].
   */
  private function partitionFacetFilters(array $tokens): array {
    $facet_filters = [];
    $entity_type_filters = [];

    foreach ($tokens as $token) {
      if (preg_match('/^content_type:([^:]+)$/', $token, $matches)) {
        $value = $matches[1];
        if (in_array($value, self::ENTITY_TYPE_FILTER_VALUES, TRUE)) {
          $entity_type_filters[] = $value;
          continue;
        }
      }

      $facet_filters[] = $token;
    }

    return [
      $facet_filters,
      array_values(array_unique($entity_type_filters)),
    ];
  }

  /**
   * Collects facet filter tokens from the "f" query parameter.
   *
   * @return string[]
   *   Filter tokens, e.g. ["content_type:article", "categories:42"].
   */
  private function collectFacetFilterValues(Request $request): array {
    $query = $request->query->all();
    $values = [];

    if (isset($query[self::FACET_FILTER_KEY])) {
      $filters = $query[self::FACET_FILTER_KEY];
      if (is_array($filters)) {
        foreach ($filters as $filter) {
          if (is_string($filter) && $filter !== '') {
            $values[] = $filter;
          }
        }
      }
      elseif (is_string($filters) && $filters !== '') {
        $values[] = $filters;
      }
    }

    foreach ($query as $key => $value) {
      if (!is_string($key) || !preg_match('/^f(\[\d*\])?$/', $key)) {
        continue;
      }
      if (is_string($value) && $value !== '') {
        $values[] = $value;
      }
    }

    return $values;
  }

  /**
   * Finds article node IDs that reference a material in field_material.
   *
   * @return int[]
   *   Article node IDs, sorted ascending.
   */
  private function findArticleNidsByMaterial(string $material): array {
    $article_nids = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('field_material.value', $material)
      ->execute();

    $article_nids = array_map('intval', array_values($article_nids));
    sort($article_nids, SORT_NUMERIC);

    return $article_nids;
  }

  /**
   * Creates a JSON cacheable response.
   *
   * @param mixed $data
   *   The response data to encode as JSON.
   */
  private function createJsonResponse(mixed $data): CacheableResponse {
    return new CacheableResponse(
      json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
      200,
      ['Content-Type' => 'application/json']
    );
  }

  /**
   * Map an entity to a response array.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to map.
   *
   * @return mixed[]
   *   The mapped entity data.
   */
  private function mapEntity(ContentEntityInterface $entity): array {
    $bundle = $entity->bundle();
    $entity_type = $entity->getEntityTypeId();

    $data = [
      'uuid' => $entity->uuid(),
      'id' => (int) $entity->id(),
      'type' => $entity_type,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'content_type' => $bundle,
      'facets' => [
        'content_type' => $bundle,
        'categories' => [],
        'tags' => [],
      ],
      'title' => $entity->label(),
      'url' => Url::fromRoute(
        'entity.' . $entity->getEntityTypeId() . '.canonical',
        [$entity->getEntityTypeId() => $entity->id()],
        ['absolute' => TRUE]
      )->toString(),
      'created_at' => $entity->hasField('created') && !$entity->get('created')->isEmpty()
        ? date('c', (int) $entity->get('created')->getString())
        : NULL,
      'updated_at' => $entity->hasField('changed') && !$entity->get('changed')->isEmpty()
        ? date('c', (int) $entity->get('changed')->getString())
        : NULL,
      'categories' => [],
      'tags' => [],
      'branches' => [],
    ];

    if ($entity->hasField('field_teaser_text') && !$entity->get('field_teaser_text')->isEmpty()) {
      $data['teaser_text'] = $entity->get('field_teaser_text')->getString();
    }

    $data['image'] = $this->extractTeaserImage($entity) ?? [];

    $categories_field = $entity->hasField('field_categories') ? $entity->get('field_categories') : NULL;
    if ($categories_field instanceof EntityReferenceFieldItemListInterface) {
      foreach ($categories_field->referencedEntities() as $term) {
        $data['categories'][] = $term->label();
        $data['facets']['categories'][] = (string) $term->id();
      }
    }

    $tags_field = $entity->hasField('field_tags') ? $entity->get('field_tags') : NULL;
    if ($tags_field instanceof EntityReferenceFieldItemListInterface) {
      foreach ($tags_field->referencedEntities() as $term) {
        $data['tags'][] = $term->label();
        $data['facets']['tags'][] = (string) $term->id();
      }
    }

    $branch_field = $entity->hasField('field_branch') ? $entity->get('field_branch') : NULL;
    if ($branch_field instanceof EntityReferenceFieldItemListInterface) {
      foreach ($branch_field->referencedEntities() as $branch) {
        $data['branches'][] = [
          'nid' => (int) $branch->id(),
          'title' => $branch->label(),
        ];
      }
    }

    return $data;
  }

  /**
   * Extracts the teaser image URL and alt text from an entity.
   *
   * Checks the following fields in order, using the first non-empty one:
   *   - field_teaser_image        (nodes, eventseries)
   *   - field_e_resource_list_image (e_resource nodes)
   *
   * Resolves: entity → <image field> (media) → field_media_image (file).
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to extract the image from.
   *
   * @return array|null
   *   An array with 'url' and 'alt' keys, or NULL if no image is set.
   */
  private function extractTeaserImage(ContentEntityInterface $entity): ?array {
    $candidate_fields = ['field_teaser_image', 'field_e_resource_list_image'];

    $image_media_field = NULL;
    foreach ($candidate_fields as $field_name) {
      $field = $entity->hasField($field_name) ? $entity->get($field_name) : NULL;
      if ($field instanceof EntityReferenceFieldItemListInterface && !$field->isEmpty()) {
        $image_media_field = $field;
        break;
      }
    }

    if ($image_media_field === NULL) {
      return NULL;
    }

    $media = $image_media_field->referencedEntities()[0] ?? NULL;
    if (!($media instanceof MediaInterface) || !$media->hasField('field_media_image') || $media->get('field_media_image')->isEmpty()) {
      return NULL;
    }

    $image_field = $media->get('field_media_image');
    $file = ($image_field instanceof EntityReferenceFieldItemListInterface)
      ? $image_field->referencedEntities()[0] ?? NULL
      : NULL;

    if (!($file instanceof FileInterface)) {
      return NULL;
    }

    /** @var \Drupal\Core\File\FileUrlGeneratorInterface $url_generator */
    $url_generator = \Drupal::service('file_url_generator');

    $image_item = $image_field->first();

    return [
      'url' => $url_generator->generateAbsoluteString($file->getFileUri()),
      'alt' => $image_item instanceof ImageItem
        ? (string) ($image_item->alt ?? '')
        : '',
    ];
  }

}
