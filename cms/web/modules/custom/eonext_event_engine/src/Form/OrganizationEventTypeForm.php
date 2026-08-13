<?php

declare(strict_types=1);

namespace Drupal\eonext_event_engine\Form;

use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eonext_event_engine\Entity\OrganizationEventType;

/**
 * Form handler for organization event bundle types.
 */
class OrganizationEventTypeForm extends BundleEntityFormBase {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    assert($this->entity instanceof OrganizationEventType);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label', [], ['context' => 'eonext_event_engine']),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => [OrganizationEventType::class, 'load'],
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    return $this->protectBundleIdElement($form);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function save(array $form, FormStateInterface $form_state): int {
    $status = parent::save($form, $form_state);

    $message = $status === SAVED_NEW
      ? $this->t('Created event type %label.', ['%label' => $this->entity->label()], ['context' => 'eonext_event_engine'])
      : $this->t('Updated event type %label.', ['%label' => $this->entity->label()], ['context' => 'eonext_event_engine']);
    $this->messenger()->addStatus($message);

    $form_state->setRedirect('entity.organization_event_type.collection');

    return $status;
  }

}
