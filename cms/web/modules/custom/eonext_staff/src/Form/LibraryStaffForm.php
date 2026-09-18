<?php

declare(strict_types=1);

namespace Drupal\eonext_staff\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eonext_staff\LibraryStaffInterface;
use Drupal\user\UserInterface;

/**
 * Form controller for the library staff entity edit forms.
 */
final class LibraryStaffForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   *
   * The routing system cannot pass the user parameter into `_entity_form`, so
   * load the user from the route and attach or reuse their staff entity.
   */
  public function setEntity(EntityInterface $entity): static {
    $user = $this->getEntityFromRouteMatch($this->getRouteMatch(), 'user');
    if (!$user instanceof UserInterface) {
      return parent::setEntity($entity);
    }

    $existing = $this->entityTypeManager
      ->getStorage('eonext_library_staff')
      ->loadByProperties(['uid' => $user->id()]);

    if ($existing !== []) {
      $entity = reset($existing);
    }
    elseif ($entity instanceof LibraryStaffInterface) {
      $entity->setUserId((int) $user->id());
    }

    return parent::setEntity($entity);
  }

  /**
   * {@inheritdoc}
   *
   * @param mixed[] $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $user = $this->getEntityFromRouteMatch($this->getRouteMatch(), 'user');
    if (!$user instanceof UserInterface) {
      throw new \LogicException('Staff form requires a user route parameter.');
    }

    $message_args = ['%label' => $user->label()];
    $this->messenger()->addStatus($this->t('Staff information for user %label has been updated.', $message_args, ['context' => 'eonext']));
    $this->logger('eonext_staff')->notice('Staff information for user %label has been updated.', $message_args);
    $form_state->setRedirectUrl($user->toUrl('edit-form'));

    return $result;
  }

}
