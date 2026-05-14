<?php

namespace Drupal\eonext_advanced_search\Plugin\rest\resource\v1;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Url;
use Drupal\dpl_search\DplSearchSettings;
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
 *   id = "eonext_advanced_search:editorial_search",
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
   *   q         - Fulltext search string (required).
   *   page      - Zero-based page number (default: 0).
   *   page_size - Items per page (default: 10, max: 100).
   */
  public function get(Request $request): Response {
    $search = $request->query->get('q');

    if (empty($search) || !is_string($search)) {
      throw new HttpException(400, 'Missing required query parameter "q".');
    }

    $page = max(0, (int) $request->query->get('page', 0));
    $page_size = min(self::MAX_PAGE_SIZE, max(1, (int) $request->query->get('page_size', self::DEFAULT_PAGE_SIZE)));

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
        $results[] = $this->mapEntity($entity);
      }
    }

    $data = [
      'total' => (int) $view->total_rows,
      'page' => $page,
      'page_size' => $page_size,
      'results' => $results,
    ];

    $response = new CacheableResponse(
      json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
      200,
      ['Content-Type' => 'application/json']
    );

    $cache = new CacheableMetadata();
    $cache->setCacheContexts([
      'url.query_args:q',
      'url.query_args:page',
      'url.query_args:page_size',
    ]);
    $cache->setCacheTags(['search_api_list:content_events']);
    $response->addCacheableDependency($cache);

    return $response;
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

}
