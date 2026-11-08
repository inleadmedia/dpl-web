<?php

declare(strict_types=1);

namespace Drupal\eonext_staff\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Url;
use Drupal\user\UserInterface;

/**
 * Provides a confirmation form for deleting library staff entities.
 *
 * Deletes only the staff profile; the linked user account is kept.
 */
final class LibraryStaffDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $user = $this->getLinkedUser();
    if ($user instanceof UserInterface) {
      return $this->t('Are you sure you want to delete the staff profile for %label?', [
        '%label' => $user->label(),
      ]);
    }

    return parent::getQuestion();
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    $uid = $this->getLinkedUserId();
    if ($uid !== NULL) {
      return Url::fromRoute('entity.eonext_library_staff.edit_form', [
        'user' => $uid,
      ]);
    }

    return parent::getCancelUrl();
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectUrl() {
    $uid = $this->getLinkedUserId();
    if ($uid !== NULL) {
      return Url::fromRoute('entity.user.edit_form', ['user' => $uid]);
    }

    return Url::fromRoute('<front>');
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeletionMessage() {
    $user = $this->getLinkedUser();
    if ($user instanceof UserInterface) {
      return $this->t('The staff profile for %label has been deleted. The user account was not deleted.', [
        '%label' => $user->label(),
      ]);
    }

    return parent::getDeletionMessage();
  }

  /**
   * {@inheritdoc}
   */
  protected function logDeletionMessage() {
    $user = $this->getLinkedUser();
    if ($user instanceof UserInterface) {
      $this->logger('eonext_staff')->notice('Staff profile for user %label has been deleted.', [
        '%label' => $user->label(),
      ]);
      return;
    }

    parent::logDeletionMessage();
  }

  /**
   * Returns the user linked to the staff entity being deleted.
   */
  private function getLinkedUser(): ?UserInterface {
    $user = $this->getEntity()->get('uid')->entity;
    return $user instanceof UserInterface ? $user : NULL;
  }

  /**
   * Returns the linked user ID, usable after the staff entity is deleted.
   */
  private function getLinkedUserId(): ?int {
    $uid = $this->getEntity()->get('uid')->target_id;
    return $uid !== NULL && $uid !== '' ? (int) $uid : NULL;
  }

}
