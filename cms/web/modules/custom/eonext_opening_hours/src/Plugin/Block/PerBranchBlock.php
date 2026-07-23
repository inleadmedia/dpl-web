<?php

declare(strict_types=1);

namespace Drupal\eonext_opening_hours\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\dpl_opening_hours\Model\OpeningHoursRepository;
use Drupal\dpl_opening_hours\Plugin\rest\resource\v1\OpeningHoursResourceBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an opening hours per branch block.
 *
 * @Block(
 *   id = "eonext_opening_hours_per_branch",
 *   admin_label = @Translation("Opening hours per branch"),
 *   category = @Translation("Custom"),
 * )
 */
final class PerBranchBlock extends BlockBase implements ContainerFactoryPluginInterface {


  /**
   * Defines the cache tag for midnight.
   */
  const MIDNIGHT_CACHE_TAG = 'midnight';

  /**
   * Constructs a PerBranchBlock block object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\dpl_opening_hours\Model\OpeningHoursRepository $openingHoursRepository
   *   The opening hours repository.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private EntityTypeManagerInterface $entityTypeManager,
    private OpeningHoursRepository $openingHoursRepository,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('dpl_opening_hours.repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'available_branches' => $this->t('Available branches'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {

    $branches = $this
      ->entityTypeManager
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'branch',
        'status' => 1,
      ]);

    $form['available_branches'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Available branches'),
      '#options' => array_map(function ($branch) {
        return $branch->label();
      }, $branches),
      '#default_value' => $this->configuration['available_branches'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['available_branches'] = array_filter($form_state->getValue('available_branches'));
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {

    // The available branches from the configuration.
    $availableBranches = array_unique(
      $this->configuration['available_branches']
    );

    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $branchesQuery = $nodeStorage
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'branch')
      ->condition('status', 1);

    if (!empty($availableBranches)) {
      $branchesQuery->condition('nid', $availableBranches, 'IN');
    }
    $branchIds = $branchesQuery->execute();

    $branches = $nodeStorage
      ->loadMultiple($branchIds);

    // Ordering branches by availableBranches.
    if (!empty($availableBranches)) {
      $branches = array_map(function ($branchId) use ($branches) {
        return $branches[$branchId];
      }, $availableBranches);
    }

    foreach ($branches as $branch) {
      $items[] = $this->formatItem($branch);
    }

    return [
      '#theme' => 'eonext_opening_hours',
      '#header_items' => [
        $this->t('Today: @date', [
          '@date' => (new DrupalDateTime('today'))->format('l, F d'),
        ]),
        $this->t('Opening hours'),
      ],
      '#items' => $items,
      '#cache' => [
        'tags' => [OpeningHoursResourceBase::CACHE_TAG_LIST, self::MIDNIGHT_CACHE_TAG],
        'max-age' => 86400,
        'contexts' => ['day'],
      ],
      '#attached' => [
        'library' => [
          'eonext_opening_hours/general',
        ],
      ],
    ];
  }

  /**
   * Format the opening hours item.
   *
   * @param \Drupal\node\NodeInterface $branch
   *   The branch node.
   *
   * @return array|bool
   *   The formatted item.
   */
  private function formatItem(NodeInterface $branch): array|bool {

    $openingHours = $this
      ->openingHoursRepository
      ->loadMultiple([$branch->id()], new \DateTime(), new \DateTime());

    // Order openingHours by startTime.
    usort($openingHours, function ($a, $b) {
      return $a->startTime->getTimestamp() - $b->startTime->getTimestamp();
    });

    return [
      'id' => $branch->id(),
      'branch' => $branch->label(),
      'opening_hours' => $openingHours,
    ];
  }

}
