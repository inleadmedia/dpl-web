<?php

declare(strict_types=1);

namespace Drupal\eonext_staff\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\eonext_staff\Entity\LibraryStaff;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\user\UserInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

/**
 * Theme and entity hooks for library staff.
 */
class StaffHooks {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Registers theme implementations.
   *
   * @return mixed[]
   *   Theme hook definitions.
   */
  #[Hook('theme')]
  public function theme(mixed $existing, string $type, string $theme, string $path): array {
    return [
      'eonext_library_staff_profile' => [
        'variables' => [
          'staff_name' => NULL,
          'interest_description' => FALSE,
          'content' => NULL,
        ],
      ],
      'paragraph__eonext_library_staff' => [
        'base hook' => 'paragraph',
        'template' => 'paragraph--eonext-library-staff',
      ],
    ];
  }

  /**
   * The staff paragraph is an empty shell, so set its contents here.
   *
   * @param mixed[] $variables
   *   Theme variables.
   */
  #[Hook('preprocess_paragraph__eonext_library_staff')]
  public function preprocessParagraphEonextLibraryStaff(array &$variables): void {
    $paragraph = $variables['paragraph'] ?? NULL;
    $view_mode = $variables['view_mode'] ?? NULL;
    if ($view_mode === 'preview' || !$paragraph instanceof ParagraphInterface) {
      return;
    }

    $view = Views::getView('staff');
    if (!($view instanceof ViewExecutable)) {
      return;
    }

    $view->setDisplay('block_1');
    $this->allowMultipleBranchArguments($view);
    $view->setArguments($this->getBranchFilterArgument($paragraph));
    $view->execute();
    $view_render = $view->render();
    $view_render['#attached']['library'][] = 'eonext_staff/general';

    $variables['header'] = $this->t('Library staff', [], ['context' => 'eonext']);
    $variables['staff'] = $view_render;
  }

  /**
   * Combines forename and surname into one profile link.
   *
   * @param mixed[] $variables
   *   Theme variables.
   */
  #[Hook('preprocess_views_view_field')]
  public function preprocessViewsViewField(array &$variables): void {
    $view = $variables['view'] ?? NULL;
    if (!$view instanceof ViewExecutable || $view->id() !== 'staff' || $view->current_display !== 'block_1') {
      return;
    }

    $field_id = $variables['field']->options['id'];
    if (!in_array($field_id, ['field_forename', 'field_surname'], TRUE)) {
      return;
    }

    $row = $variables['row'];
    if (empty($row->_entity) || !$row->_entity instanceof LibraryStaff) {
      return;
    }

    if ($field_id === 'field_surname') {
      $variables['output'] = '';
      return;
    }

    $full_name = $row->_entity->getFullName();
    if ($full_name === '') {
      return;
    }

    $variables['output'] = [
      '#type' => 'link',
      '#title' => $full_name,
      '#url' => $row->_entity->toUrl('canonical'),
      '#attributes' => [
        'class' => ['eonext-library-staff__name-link'],
      ],
    ];
  }

  /**
   * Deletes staff entities that belong to a user being deleted.
   */
  #[Hook('user_predelete')]
  public function userPredelete(UserInterface $account): void {
    $storage = $this->entityTypeManager->getStorage('eonext_library_staff');

    $staff_ids = $storage->getQuery()
      ->condition('uid', $account->id())
      ->accessCheck(FALSE)
      ->execute();

    if ($staff_ids === []) {
      return;
    }

    $storage->delete($storage->loadMultiple($staff_ids));
  }

  /**
   * Accepts several branch IDs in the staff view contextual filter.
   *
   * Stored Views config may still validate a single ID only. Without this,
   * arguments such as "12+34+56" fail validation and the list is empty.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view being executed.
   * @param string $display_id
   *   The current display ID.
   * @param mixed[] $args
   *   Views arguments.
   */
  #[Hook('views_pre_view')]
  public function viewsPreView(ViewExecutable $view, string $display_id, array &$args): void {
    if ($view->id() !== 'staff') {
      return;
    }

    $this->allowMultipleBranchArguments($view);
  }

  /**
   * Force the branch argument to treat + separated IDs as a list.
   */
  protected function allowMultipleBranchArguments(ViewExecutable $view): void {
    $view->initHandlers();
    if (!isset($view->argument['field_branch_target_id'])) {
      return;
    }

    $argument = $view->argument['field_branch_target_id'];
    $argument->options['break_phrase'] = TRUE;
    $argument->options['validate']['type'] = 'none';
    $argument->options['validate']['fail'] = 'ignore';
    $argument->options['validate_options']['multiple'] = 1;
  }

  /**
   * Builds the branch filter argument for the staff view.
   *
   * @return string[]
   *   Views contextual filter arguments.
   */
  protected function getBranchFilterArgument(ParagraphInterface $paragraph): array {
    if (!$paragraph->hasField('field_filter_branches') || $paragraph->get('field_filter_branches')->isEmpty()) {
      return [];
    }

    $branch_ids = array_column($paragraph->get('field_filter_branches')->getValue(), 'target_id');
    $branch_ids = array_values(array_unique(array_filter($branch_ids)));

    if ($branch_ids === []) {
      return [];
    }

    return [implode('+', $branch_ids)];
  }

}
