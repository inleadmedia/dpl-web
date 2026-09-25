<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_branches;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Curated branch order and visibility for the foreninger overview.
 */
final class ForeningerListingRegistry {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * @return list<array{nid: int, enabled: bool}>
   */
  public function getListingItems(): array {
    $items = $this->configFactory->get('eonext_kultur_branches.settings')->get('listing_items');
    if (!is_array($items) || $items === []) {
      return $this->buildLegacyListingItems();
    }

    $normalized = [];
    foreach ($items as $item) {
      if (!is_array($item) || !isset($item['nid'])) {
        continue;
      }
      $nid = (int) $item['nid'];
      if ($nid <= 0) {
        continue;
      }
      $normalized[] = [
        'nid' => $nid,
        'enabled' => !empty($item['enabled']),
      ];
    }

    return $this->mergeWithAllBranches($normalized);
  }

  /**
   * @return list<int>
   *   Enabled branch node IDs in display order.
   */
  public function getEnabledNids(): array {
    $nids = [];
    foreach ($this->getListingItems() as $item) {
      if (!$item['enabled']) {
        continue;
      }
      if ($this->isPublishedBranch($item['nid'])) {
        $nids[] = $item['nid'];
      }
    }

    return $nids;
  }

  /**
   * @return list<array{nid: int, enabled: bool}>
   */
  public function getAdminRows(): array {
    $saved = $this->getSavedListingItems();
    if ($saved === []) {
      return $this->buildLegacyListingItems();
    }

    return $this->mergeWithAllBranches($saved);
  }

  /**
   * Persists branch order and enabled flags from the admin form.
   *
   * @param list<array{nid: int, enabled: bool}> $items
   */
  public function saveListingItems(array $items): void {
    $payload = [];
    foreach ($items as $item) {
      $nid = (int) ($item['nid'] ?? 0);
      if ($nid <= 0) {
        continue;
      }
      $payload[] = [
        'nid' => $nid,
        'enabled' => !empty($item['enabled']),
      ];
    }

    $this->configFactory->getEditable('eonext_kultur_branches.settings')
      ->set('listing_items', $payload)
      ->save();
  }

  /**
   * Seeds listing_items from promoted branches (minus legacy exclusions).
   */
  public function initializeListingItems(): void {
    if ($this->getSavedListingItems() !== []) {
      return;
    }

    $this->saveListingItems($this->buildLegacyListingItems());
  }

  /**
   * @return list<array{nid: int, enabled: bool}>
   */
  private function getSavedListingItems(): array {
    $items = $this->configFactory->get('eonext_kultur_branches.settings')->get('listing_items');
    if (!is_array($items)) {
      return [];
    }

    $normalized = [];
    foreach ($items as $item) {
      if (!is_array($item) || !isset($item['nid'])) {
        continue;
      }
      $nid = (int) $item['nid'];
      if ($nid <= 0) {
        continue;
      }
      $normalized[] = [
        'nid' => $nid,
        'enabled' => !empty($item['enabled']),
      ];
    }

    return $normalized;
  }

  /**
   * @return list<array{nid: int, enabled: bool}>
   */
  private function buildLegacyListingItems(): array {
    $excluded = $this->configFactory->get('eonext_kultur_branches.settings')->get('excluded_branch_ids') ?? [];
    $excluded = is_array($excluded) ? array_map('intval', $excluded) : [];

    $storage = $this->entityTypeManager->getStorage('node');
    $promoted_nids = array_map('intval', array_values($storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'branch')
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('field_promoted_on_lists', 1)
      ->sort('title', 'ASC')
      ->execute()));

    $all_nids = array_map('intval', array_values($storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'branch')
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('title', 'ASC')
      ->execute()));

    $items = [];
    foreach ($promoted_nids as $nid) {
      $items[] = [
        'nid' => $nid,
        'enabled' => !in_array($nid, $excluded, TRUE),
      ];
    }

    $listed = array_column($items, 'nid');
    foreach ($all_nids as $nid) {
      if (in_array($nid, $listed, TRUE)) {
        continue;
      }
      $items[] = [
        'nid' => $nid,
        'enabled' => FALSE,
      ];
    }

    return $items;
  }

  /**
   * @param list<array{nid: int, enabled: bool}> $items
   *
   * @return list<array{nid: int, enabled: bool}>
   */
  private function mergeWithAllBranches(array $items): array {
    $all_nids = array_map('intval', array_values($this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'branch')
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('title', 'ASC')
      ->execute()));

    if ($all_nids === []) {
      return [];
    }

    $published = array_fill_keys($all_nids, TRUE);

    $by_nid = [];
    foreach ($items as $item) {
      $by_nid[$item['nid']] = $item;
    }

    $merged = [];
    foreach ($items as $item) {
      if (isset($published[$item['nid']])) {
        $merged[] = $item;
      }
    }

    foreach ($all_nids as $nid) {
      if (isset($by_nid[$nid])) {
        continue;
      }
      $merged[] = [
        'nid' => $nid,
        'enabled' => FALSE,
      ];
    }

    return $merged;
  }

  private function isPublishedBranch(int $nid): bool {
    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    return $node instanceof NodeInterface
      && $node->bundle() === 'branch'
      && $node->isPublished();
  }

}
