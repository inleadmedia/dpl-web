<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_locale\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\eonext_kultur_locale\LokaleConstants;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drag-and-drop ordering for lokale nodes on the Book lokale page.
 */
final class LokaleListOrderForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Access check for the room order admin page.
   */
  public static function access(AccountInterface $account): AccessResult {
    $allowed = $account->hasPermission('administer lokale list order')
      || $account->hasPermission('edit any lokale content')
      || $account->hasPermission('administer nodes');
    return AccessResult::allowedIf($allowed)->cachePerPermissions();
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'eonext_kultur_locale_lokale_list_order';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['help'] = [
      '#type' => 'item',
      '#markup' => $this->t(
        'Drag rows to change the order of rooms on the <a href=":url">Book lokale</a> page.',
        [':url' => Url::fromUserInput('/book-lokale')->toString()],
      ),
    ];

    $nodes = $this->loadOrderedNodes();

    $form['rooms'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Room'),
        $this->t('Weight'),
      ],
      '#empty' => $this->t('No published rooms found.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'lokale-list-order-weight',
        ],
      ],
    ];

    foreach ($nodes as $index => $node) {
      assert($node instanceof NodeInterface);
      $form['rooms'][$node->id()] = [
        '#attributes' => ['class' => ['draggable']],
        '#weight' => $index,
        'title' => $node->toLink($node->label(), 'edit-form')->toRenderable(),
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @title', ['@title' => $node->label()]),
          '#title_display' => 'invisible',
          '#default_value' => $index,
          '#attributes' => ['class' => ['lokale-list-order-weight']],
        ],
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save order'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $rows = $form_state->getValue('rooms') ?? [];
    if (!is_array($rows)) {
      return;
    }

    uasort($rows, static function (array $a, array $b): int {
      return ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0);
    });

    $order = 0;
    $storage = $this->entityTypeManager->getStorage('node');
    foreach (array_keys($rows) as $nid) {
      $node = $storage->load($nid);
      if (!$node instanceof NodeInterface || $node->bundle() !== LokaleConstants::NODE_BUNDLE) {
        continue;
      }
      if (!$node->hasField('field_lokale_list_order')) {
        continue;
      }
      $node->set('field_lokale_list_order', $order);
      $node->save();
      $order++;
    }

    $this->messenger()->addStatus($this->t('Room order saved.'));
  }

  /**
   * Loads published lokale nodes in current list order.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Nodes keyed by nid.
   */
  private function loadOrderedNodes(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', LokaleConstants::NODE_BUNDLE)
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('field_lokale_list_order', 'ASC')
      ->sort('title', 'ASC');

    $nids = $query->execute();
    if ($nids === []) {
      return [];
    }

    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $storage->loadMultiple($nids);
    uasort($nodes, static function (NodeInterface $a, NodeInterface $b): int {
      $weight_a = $a->hasField('field_lokale_list_order') && !$a->get('field_lokale_list_order')->isEmpty()
        ? (int) $a->get('field_lokale_list_order')->value : 0;
      $weight_b = $b->hasField('field_lokale_list_order') && !$b->get('field_lokale_list_order')->isEmpty()
        ? (int) $b->get('field_lokale_list_order')->value : 0;
      if ($weight_a !== $weight_b) {
        return $weight_a <=> $weight_b;
      }
      return strnatcasecmp($a->label(), $b->label());
    });

    return $nodes;
  }

}
