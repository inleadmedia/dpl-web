<?php

declare(strict_types=1);

namespace Drupal\eonext_event_engine\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eonext_event_engine\OrganizationEventInterface;

/**
 * Form controller for organization event add/edit forms.
 */
class OrganizationEventForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function save(array $form, FormStateInterface $form_state): int {
    assert($this->entity instanceof OrganizationEventInterface);

    $status = parent::save($form, $form_state);

    $message = $status === SAVED_NEW
      ? $this->t('Created event %title.', ['%title' => $this->entity->getTitle()], ['context' => 'eonext_event_engine'])
      : $this->t('Updated event %title.', ['%title' => $this->entity->getTitle()], ['context' => 'eonext_event_engine']);
    $this->messenger()->addStatus($message);

    $form_state->setRedirect('entity.organization_event.collection');

    return $status;
  }

}
