<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_branches;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Resolves public organisation details for a kultur branch (forening) page.
 */
final class BranchLinkedOrganizationResolver {

  private const ORGANIZATION_BRANCH_FIELD = 'field_organization_branch';

  private const ORGANIZATION_URL_FIELD = 'field_organization_url';

  private const ORGANIZATION_CATEGORY_FIELD = 'field_organization_category';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Website link render array and category label from the linked organisation.
   *
   * @return array{website: ?array<string, mixed>, category: ?string, cache_tags: list<string>}
   */
  public function getDisplayValuesForBranch(int $branch_nid): array {
    $empty = [
      'website' => NULL,
      'category' => NULL,
      'cache_tags' => [],
    ];

    if ($branch_nid <= 0 || !$this->entityTypeManager->hasDefinition('organization')) {
      return $empty;
    }

    $organization = $this->loadOrganizationForBranch($branch_nid);
    if (!$organization) {
      return $empty;
    }

    $cache_tags = ['organization:' . $organization->id()];

    $website = NULL;
    if ($organization->hasField(self::ORGANIZATION_URL_FIELD) && !$organization->get(self::ORGANIZATION_URL_FIELD)->isEmpty()) {
      $link_item = $organization->get(self::ORGANIZATION_URL_FIELD)->first();
      if ($link_item !== NULL) {
        $url = $link_item->getUrl();
        $title = trim((string) ($link_item->title ?? ''));
        if ($title === '') {
          $title = $url->isExternal() ? ($url->getUri() ?? $url->toString()) : $url->toString();
        }
        $website = [
          '#type' => 'link',
          '#title' => $title,
          '#url' => $url,
          '#attributes' => ['rel' => 'noopener noreferrer'],
        ];
        if ($url->isExternal()) {
          $website['#attributes']['target'] = '_blank';
        }
      }
    }

    $category = NULL;
    if ($organization->hasField(self::ORGANIZATION_CATEGORY_FIELD) && !$organization->get(self::ORGANIZATION_CATEGORY_FIELD)->isEmpty()) {
      $term = $organization->get(self::ORGANIZATION_CATEGORY_FIELD)->entity;
      if ($term !== NULL) {
        $category = $term->label();
        $cache_tags[] = $term->getEntityTypeId() . ':' . $term->id();
      }
    }

    return [
      'website' => $website,
      'category' => $category,
      'cache_tags' => $cache_tags,
    ];
  }

  private function loadOrganizationForBranch(int $branch_nid): ?ContentEntityInterface {
    $storage = $this->entityTypeManager->getStorage('organization');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition(self::ORGANIZATION_BRANCH_FIELD, $branch_nid)
      ->range(0, 1)
      ->execute();

    if ($ids === []) {
      return NULL;
    }

    $organization = $storage->load(reset($ids));
    return $organization instanceof ContentEntityInterface ? $organization : NULL;
  }

}
