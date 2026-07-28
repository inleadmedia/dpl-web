<?php

namespace Drupal\eonext_branch_opening_hours\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\dpl_react_apps\Controller\DplReactAppsController;
use Drupal\node\NodeInterface;

/**
 * Defines OpeningHoursEditorController class.
 */
class BareOpeningHoursEditorController extends ControllerBase {

  /**
   * Display the opening hours app.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node ID.
   *
   * @return mixed[]
   *   The app render array.
   */
  public function content(NodeInterface $node): array {
    if ($node->getType() !== 'branch') {
      return [];
    }

    return [
      '#theme' => 'dpl_react_app',
      '#name' => 'opening-hours',
      '#data' => [
        'branch-id' => $node->id(),
        'branch-title' => $node->getTitle(),
        'opening-hours-bare' => 1,
        'class' => ['opening-hours--bare'],
        'opening-hours-heading-text' => t('Opening Hours', [], ['context' => 'Opening Hours']),
        'show-opening-hours-for-week-text' => t('Show opening hours for week', [], ['context' => 'Opening Hours']),
        'week-text' => t('Week', [], ['context' => 'Opening Hours']),
        'library-is-closed-text' => t('The library is closed this day', [], ['context' => 'Opening Hours']),
      ] + DplReactAppsController::externalApiBaseUrls(),
      '#attributes' => [
        'class' => ['opening-hours--bare'],
      ],
    ];
  }

  /**
   * Check access for a specific library node.
   *
   * @param int $node
   *   The node ID.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(int $node): AccessResult {
    $nodeStorage = $this->entityTypeManager()->getStorage('node');
    $nodeEntity = $nodeStorage->load($node);

    if ($nodeEntity instanceof NodeInterface && $nodeEntity->getType() === 'branch') {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}
