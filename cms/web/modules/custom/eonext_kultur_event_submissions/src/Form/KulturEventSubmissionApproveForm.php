<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_event_submissions\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\eonext_kultur_event_submissions\Entity\KulturEventSubmission;
use Drupal\eonext_kultur_event_submissions\KulturEventSubmissionInterface;
use Drupal\eonext_kultur_event_submissions\Service\EventSeriesPublisher;
use Drupal\eonext_kultur_event_submissions\SubmissionConstants;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for approving a Kulturnat event submission.
 */
final class KulturEventSubmissionApproveForm extends ContentEntityConfirmFormBase {

  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    private readonly EventSeriesPublisher $eventSeriesPublisher,
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('eonext_kultur_event_submissions.event_series_publisher'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'kultur_event_submission_approve_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Approve submission from @organisation?', [
      '@organisation' => $this->entity->get('organisation_name')->value,
    ], ['context' => 'eonext_kultur_event_submissions']);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This will create a published event series with Greenlandic and Danish language versions based on the submitted content.', [], ['context' => 'eonext_kultur_event_submissions']);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Approve and publish', [], ['context' => 'eonext_kultur_event_submissions']);
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
      $this->messenger()->addError($this->t('Only pending submissions can be approved.', [], ['context' => 'eonext_kultur_event_submissions']));
      $form['actions']['submit']['#access'] = FALSE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->entity instanceof KulturEventSubmissionInterface) {
      return;
    }

    $series = $this->eventSeriesPublisher->publish($this->entity);

    $this->entity->set('status', SubmissionConstants::STATUS_APPROVED);
    $this->entity->set('eventseries', ['target_id' => $series->id()]);
    $this->entity->save();

    $this->messenger()->addStatus($this->t('Submission approved. Event series %title was created.', [
      '%title' => $series->label(),
    ], ['context' => 'eonext_kultur_event_submissions']));

    $form_state->setRedirectUrl($series->toUrl());
  }

}
