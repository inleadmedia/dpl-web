<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_event_submissions;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\eonext_kultur_event_submissions\Entity\KulturEventSubmission;

/**
 * List builder for Kulturnat event submissions.
 */
final class KulturEventSubmissionListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    return [
      'organisation_name' => $this->t('Organisation', [], ['context' => 'eonext_kultur_event_submissions']),
      'city' => $this->t('City', [], ['context' => 'eonext_kultur_event_submissions']),
      'event_start' => $this->t('Event start', [], ['context' => 'eonext_kultur_event_submissions']),
      'status' => $this->t('Status', [], ['context' => 'eonext_kultur_event_submissions']),
      'created' => $this->t('Submitted', [], ['context' => 'eonext_kultur_event_submissions']),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    if (!$entity instanceof KulturEventSubmission) {
      return [];
    }

    $city = SubmissionConstants::cityOptions()[$entity->get('city')->value] ?? $entity->get('city')->value;
    $status = SubmissionConstants::statusOptions()[$entity->get('status')->value] ?? $entity->get('status')->value;
    $event_start = $entity->get('event_start')->value;

    $row = [
      'organisation_name' => Link::fromTextAndUrl(
        $entity->get('organisation_name')->value,
        $entity->toUrl('canonical')
      ),
      'city' => $city,
      'event_start' => $event_start ?: $this->t('N/A'),
      'status' => $status,
      'created' => $entity->get('created')->value
        ? \Drupal::service('date.formatter')->format((int) $entity->get('created')->value, 'short')
        : '',
    ];

    $operations = [];

    if ($entity->isPending()) {
      $operations['approve'] = [
        'title' => $this->t('Approve', [], ['context' => 'eonext_kultur_event_submissions']),
        'weight' => 0,
        'url' => Url::fromRoute('entity.kultur_event_submission.approve_form', [
          'kultur_event_submission' => $entity->id(),
        ]),
      ];
      $operations['reject'] = [
        'title' => $this->t('Reject', [], ['context' => 'eonext_kultur_event_submissions']),
        'weight' => 1,
        'url' => Url::fromRoute('entity.kultur_event_submission.reject_form', [
          'kultur_event_submission' => $entity->id(),
        ]),
      ];
    }

    if (!$entity->get('eventseries')->isEmpty()) {
      $series = $entity->get('eventseries')->entity;
      if ($series !== NULL) {
        $operations['eventseries'] = [
          'title' => $this->t('View event series', [], ['context' => 'eonext_kultur_event_submissions']),
          'weight' => 5,
          'url' => $series->toUrl(),
        ];
      }
    }

    $row['operations'] = [
      'data' => [
        '#type' => 'operations',
        '#links' => $operations,
      ],
    ];

    return $row;
  }

}
