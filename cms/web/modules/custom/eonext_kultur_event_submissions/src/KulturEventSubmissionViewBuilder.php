<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_event_submissions;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Link;
use Drupal\eonext_kultur_event_submissions\Entity\KulturEventSubmission;
use Drupal\eonext_kultur_event_submissions\SubmissionConstants;
use Drupal\file\FileInterface;

/**
 * View builder for Kulturnat event submission admin pages.
 */
final class KulturEventSubmissionViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $entity, $view_mode = 'full', $langcode = NULL): array {
    assert($entity instanceof KulturEventSubmission);

    $status = (string) $entity->get('status')->value;
    $dateFormatter = $this->getDateFormatter();

    $eventStart = $entity->get('event_start')->value;
    $eventEnd = $entity->get('event_end')->value;

    $image = NULL;
    if (!$entity->get('image')->isEmpty()) {
      $file = $entity->get('image')->entity;
      if ($file instanceof FileInterface) {
        $image = [
          'url' => $file->createFileUrl(),
          'alt' => $entity->label(),
        ];
      }
    }

    $eventseries = NULL;
    if (!$entity->get('eventseries')->isEmpty()) {
      $series = $entity->get('eventseries')->entity;
      if ($series !== NULL) {
        $eventseries = Link::fromTextAndUrl(
          $series->label(),
          $series->toUrl()
        );
      }
    }

    return [
      '#theme' => 'kultur_event_submission',
      '#organisation_name' => $entity->label(),
      '#status' => $status,
      '#status_label' => SubmissionConstants::statusOptions()[$status] ?? $status,
      '#city_label' => SubmissionConstants::cityOptions()[(string) $entity->get('city')->value] ?? '',
      '#address' => $entity->get('address')->view(['label' => 'hidden']),
      '#event_start' => $eventStart
        ? $dateFormatter->format(strtotime($eventStart), 'custom', 'd/m/Y H:i')
        : '',
      '#event_end' => $eventEnd
        ? $dateFormatter->format(strtotime($eventEnd), 'custom', 'd/m/Y H:i')
        : '',
      '#contact_name' => (string) $entity->get('contact_name')->value,
      '#contact_email' => (string) $entity->get('contact_email')->value,
      '#contact_phone' => (string) $entity->get('contact_phone')->value,
      '#description_gl' => (string) $entity->get('description_gl')->value,
      '#description_da' => (string) $entity->get('description_da')->value,
      '#image' => $image,
      '#eventseries' => $eventseries,
      '#submitted' => $dateFormatter->format((int) $entity->get('created')->value, 'medium'),
      '#updated' => $dateFormatter->format((int) $entity->get('changed')->value, 'medium'),
      '#actions' => $this->buildActions($entity),
      '#attached' => [
        'library' => ['eonext_kultur_event_submissions/admin-detail'],
      ],
      '#cache' => [
        'tags' => $entity->getCacheTags(),
        'contexts' => ['user.permissions'],
      ],
    ];
  }

  /**
   * Builds approve/reject action links for pending submissions.
   *
   * @return array<string, array<string, mixed>>
   *   Action link render arrays keyed by action id.
   */
  private function buildActions(KulturEventSubmission $entity): array {
    if (!$entity->isPending()) {
      return [];
    }

    return [
      'approve' => Link::createFromRoute(
        $this->t('Approve', [], ['context' => 'eonext_kultur_event_submissions']),
        'entity.kultur_event_submission.approve_form',
        ['kultur_event_submission' => $entity->id()],
        ['attributes' => ['class' => ['button', 'button--primary']]]
      )->toRenderable(),
      'reject' => Link::createFromRoute(
        $this->t('Reject', [], ['context' => 'eonext_kultur_event_submissions']),
        'entity.kultur_event_submission.reject_form',
        ['kultur_event_submission' => $entity->id()],
        ['attributes' => ['class' => ['button']]]
      )->toRenderable(),
    ];
  }

  /**
   * Returns the date formatter service.
   */
  private function getDateFormatter(): DateFormatterInterface {
    return \Drupal::service('date.formatter');
  }

}
