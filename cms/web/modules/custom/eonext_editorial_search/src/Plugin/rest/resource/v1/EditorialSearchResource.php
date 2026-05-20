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
   *   material  - Work ID to find related editorial content.
   *   page      - Zero-based page number (default: 0).
   *   page_size - Items per page (default: 10, max: 100).
   */
  public function get(Request $request): Response {
    $search = $request->query->get('q');
    $material = $request->query->get('material');

    $material_editorials = [];
    $editorial_nids = [];

    if (!empty($material)) {
      $editorial_nids = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->getQuery()
        ->accessCheck(TRUE)
        ->condition('field_material', $material)
        ->range(0, self::DEFAULT_PAGE_SIZE)
        ->execute();

      $material_editorials = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadMultiple($editorial_nids);
    }

    // Return the editorials directly.
    if (!empty($material) && (empty($search) || !is_string($search))) {
      $results = [];
      foreach ($material_editorials as $editorial) {
        $results[] = $this->mapEntity($editorial);
      }

      $response = $this->createJsonResponse($results);
      $response->addCacheableDependency(
        $this->buildCacheMetadata($material_editorials)
      );

      return $response;
    }

    if (empty($search) || !is_string($search)) {
      throw new HttpException(400, 'Missing required query parameter "q".');
    }

    $page = max(0, (int) $request->query->get('page', 0));

    $page_size = min(
      self::MAX_PAGE_SIZE,
      max(
        1,
        (int) $request->query->get('page_size', self::DEFAULT_PAGE_SIZE)
      )
    );

    $view = Views::getView(DplSearchSettings::EDITORIAL_VIEW_ID);

    if (!($view instanceof ViewExecutable)) {
      throw new HttpException(500, 'Editorial search view could not be loaded.');
    }

    $view->setDisplay('page');
    $view->setItemsPerPage($page_size);
    $view->setCurrentPage($page);
    $view->setExposedInput([DplSearchSettings::EDITORIAL_QUERY_KEY => $search]);
    $view->execute();

    $results = [];

    foreach ($view->result as $row) {
      $entity = $row->_entity ?? NULL;
      if ($entity instanceof ContentEntityInterface) {

        if (!empty($material) && !in_array($entity->id(), $editorial_nids)) {
          continue;
        }
        $results[] = $this->mapEntity($entity);
      }
    }

    $data = [
      'total' => !empty($material) ? count($results) : (int) $view->total_rows,
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
    return [
      'url.query_args:q',
      'url.query_args:material',
      'url.query_args:page',
      'url.query_args:page_size',
    ];
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
    $data = [
      'uuid' => $entity->uuid(),
      'id' => (int) $entity->id(),
      'type' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
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

    $data['image'] = $this->extractTeaserImage($entity);

    $categories_field = $entity->hasField('field_categories') ? $entity->get('field_categories') : NULL;
    if ($categories_field instanceof EntityReferenceFieldItemListInterface) {
      foreach ($categories_field->referencedEntities() as $term) {
        $data['categories'][] = $term->label();
      }
    }

    $tags_field = $entity->hasField('field_tags') ? $entity->get('field_tags') : NULL;
    if ($tags_field instanceof EntityReferenceFieldItemListInterface) {
      foreach ($tags_field->referencedEntities() as $term) {
        $data['tags'][] = $term->label();
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
