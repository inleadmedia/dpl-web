<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_branches\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\eonext_kultur_branches\ForeningerListingRegistry;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drag-and-drop order and enable/disable for /foreninger branches.
 */
final class ForeningerBranchListingForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ForeningerListingRegistry $listingRegistry,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('eonext_kultur_branches.listing_registry'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'eonext_kultur_branches_branch_listing';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['help'] = [
      '#type' => 'item',
      '#markup' => $this->t(
        'Choose which branches appear on <a href=":url">/foreninger</a> and drag rows to set the order.',
        [':url' => Url::fromUserInput('/foreninger')->toString()],
      ),
    ];

    $rows = $this->listingRegistry->getAdminRows();
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple(array_column($rows, 'nid'));

    $form['branches'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Show on overview'),
        $this->t('Branch'),
        $this->t('Weight'),
      ],
      '#empty' => $this->t('No published branches found.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'foreninger-branch-listing-weight',
        ],
      ],
    ];

    foreach ($rows as $index => $row) {
      $nid = (int) $row['nid'];
      $node = $nodes[$nid] ?? NULL;
      if (!$node instanceof NodeInterface) {
        continue;
      }

      $form['branches'][$nid] = [
        '#attributes' => ['class' => ['draggable']],
        '#weight' => $index,
        'enabled' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Show @title on foreninger overview', ['@title' => $node->label()]),
          '#title_display' => 'invisible',
          '#default_value' => $row['enabled'] ? 1 : 0,
        ],
        'title' => $node->toLink($node->label(), 'edit-form')->toRenderable(),
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @title', ['@title' => $node->label()]),
          '#title_display' => 'invisible',
          '#default_value' => $index,
          '#attributes' => ['class' => ['foreninger-branch-listing-weight']],
        ],
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save branches'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $rows = $form_state->getValue('branches') ?? [];
    if (!is_array($rows)) {
      return;
    }

    uasort($rows, static function (array $a, array $b): int {
      return ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0);
    });

    $items = [];
    foreach ($rows as $nid => $row) {
      $items[] = [
        'nid' => (int) $nid,
        'enabled' => !empty($row['enabled']),
      ];
    }

    $this->listingRegistry->saveListingItems($items);
    $this->messenger()->addStatus($this->t('Foreninger branch list saved.'));
  }

}
