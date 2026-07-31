<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_event_submissions\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\eonext_kultur_event_submissions\Entity\KulturEventSubmission;
use Drupal\eonext_kultur_event_submissions\SubmissionConstants;

/**
 * Confirmation form for rejecting a Kulturnat event submission.
 */
final class KulturEventSubmissionRejectForm extends ContentEntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'kultur_event_submission_reject_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Reject submission from @organisation?', [
      '@organisation' => $this->entity->get('organisation_name')->value,
    ], ['context' => 'eonext_kultur_event_submissions']);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('The submission will be marked as rejected and will not create an event series.', [], ['context' => 'eonext_kultur_event_submissions']);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Reject submission', [], ['context' => 'eonext_kultur_event_submissions']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('entity.kultur_event_submission.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    if (!$this->entity instanceof KulturEventSubmission || !$this->entity->isPending()) {
      $this->messenger()->addError($this->t('Only pending submissions can be rejected.', [], ['context' => 'eonext_kultur_event_submissions']));
      $form['actions']['submit']['#access'] = FALSE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->entity->set('status', SubmissionConstants::STATUS_REJECTED);
    $this->entity->save();

    $this->messenger()->addStatus($this->t('Submission rejected.', [], ['context' => 'eonext_kultur_event_submissions']));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
