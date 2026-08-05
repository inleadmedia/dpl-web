<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_event_submissions\Form;

use Drupal\Core\Entity\EntityConstraintViolationListInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\eonext_kultur_event_submissions\SubmissionConstants;

/**
 * Public Kulturnat event registration form.
 */
final class KulturEventSubmissionPublicForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  protected function flagViolations(EntityConstraintViolationListInterface $violations, array $form, FormStateInterface $form_state): void {
    foreach ($violations->getEntityViolations() as $violation) {
      $form_state->setErrorByName(
        str_replace('.', '][', $violation->getPropertyPath()),
        $violation->getMessage()
      );
    }

    foreach ($violations->getFieldNames() as $field_name) {
      if (!isset($form[$field_name])) {
        continue;
      }

      foreach ($violations->getByField($field_name) as $violation) {
        if (isset($form[$field_name]['widget'][0]['value'])) {
          $form_state->setError($form[$field_name]['widget'][0]['value'], $violation->getMessage());
        }
        elseif (isset($form[$field_name]['widget'][0])) {
          $form_state->setError($form[$field_name]['widget'][0], $violation->getMessage());
        }
        else {
          $form_state->setErrorByName($field_name, $violation->getMessage());
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#method'] = 'post';
    $form['#action'] = Url::fromRoute('eonext_kultur_event_submissions.public_form')->toString();
    $form['#attributes']['enctype'] = 'multipart/form-data';
    $form['#attached']['library'][] = 'eonext_kultur_event_submissions/submission-form';
    $form['#attributes']['class'][] = 'dpl-form';

    if ($form_state->get('submission_success')) {
      $form['confirmation'] = $this->confirmationMessage();
      return $form;
    }

    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -100,
    ];

    /** @var \Drupal\eonext_kultur_event_submissions\Entity\KulturEventSubmission $submission */
    $submission = $this->entity;
    if ($submission->isNew()) {
      if ($submission->get('city')->isEmpty()) {
        $submission->set('city', SubmissionConstants::CITY_OTHER);
      }
      if ($submission->get('address')->isEmpty()) {
        $submission->set('address', ['country_code' => 'GL']);
      }
    }

    $form = parent::buildForm($form, $form_state);
    $form['#after_build'][] = 'eonext_kultur_event_submissions_public_form_after_build';

    if (isset($form['description_da'])) {
      $form['description_da']['widget'][0]['value']['#attributes']['class'][] = 'dpl-input--full-width';
      $form['description_da']['widget'][0]['value']['#states'] = [
        'required' => [
          ':input[name="city"]' => ['value' => SubmissionConstants::CITY_NUUK],
        ],
      ];
    }

    if (isset($form['description_gl']['widget'][0]['value'])) {
      $form['description_gl']['widget'][0]['value']['#attributes']['class'][] = 'dpl-input--full-width';
    }

    if (isset($form['image']['widget'][0])) {
      $form['image']['widget'][0]['#description'] = $this->t(
        'Accepted formats: PNG, JPG, JPEG, WEBP.',
        [],
        ['context' => 'eonext_kultur_event_submissions']
      );
      $form['image']['widget'][0]['#after_build'][] = 'eonext_kultur_event_submissions_image_widget_after_build';
    }

    $this->applySectionWrappers($form);

    if (isset($form['actions']['submit'])) {
      $form['actions']['submit']['#value'] = $this->t(
        'Submit event',
        [],
        ['context' => 'eonext_kultur_event_submissions']
      );
      $form['actions']['submit']['#attributes']['class'] = [
        'btn-primary',
        'btn-filled',
        'btn-medium',
        'dpl-button',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $city = $form_state->getValue('city');
    if (is_array($city)) {
      $city = $city[0]['value'] ?? NULL;
    }

    $descriptionDa = $form_state->getValue('description_da');
    if (is_array($descriptionDa)) {
      $descriptionDa = $descriptionDa[0]['value'] ?? '';
    }
    $descriptionDa = trim((string) $descriptionDa);

    if ($city === SubmissionConstants::CITY_NUUK && $descriptionDa === '') {
      $message = $this->t('Danish description is required when Nuuk is selected.', [], ['context' => 'eonext_kultur_event_submissions']);
      if (isset($form['description_da']['widget'][0]['value'])) {
        $form_state->setError($form['description_da']['widget'][0]['value'], $message);
      }
      else {
        $form_state->setErrorByName('description_da', $message);
      }
    }

    $start = $this->getDateTimeFromFormValue($form_state->getValue('event_start'));
    $end = $this->getDateTimeFromFormValue($form_state->getValue('event_end'));
    if ($start !== NULL && $end !== NULL && $end->getTimestamp() < $start->getTimestamp()) {
      $message = $this->t('Event end must be after event start.', [], ['context' => 'eonext_kultur_event_submissions']);
      if (isset($form['event_end']['widget'][0]['value'])) {
        $form_state->setError($form['event_end']['widget'][0]['value'], $message);
      }
      else {
        $form_state->setErrorByName('event_end', $message);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\eonext_kultur_event_submissions\Entity\KulturEventSubmission $submission */
    $submission = $this->entity;
    $submission->set('status', SubmissionConstants::STATUS_PENDING);

    $result = parent::save($form, $form_state);

    $this->messenger()->addStatus($this->t(
      'Thank you. Your event has been submitted for review.',
      [],
      ['context' => 'eonext_kultur_event_submissions']
    ));

    $destination = $this->getAdminDestinationUrl();
    if ($destination !== NULL) {
      $form_state->setRedirectUrl($destination);
      return $result;
    }

    $form_state->set('submission_success', TRUE);
    $form_state->setUserInput([]);
    $form_state->setRebuild(TRUE);

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);

    if ($this->getAdminDestinationUrl() !== NULL) {
      return;
    }

    $form_state->setRedirectUrl(Url::fromRoute('<current>'));
  }

  /**
   * Returns an admin redirect URL when the form was opened from the CMS.
   */
  private function getAdminDestinationUrl(): ?Url {
    $destination = \Drupal::request()->query->get('destination');
    if (!is_string($destination) || !str_starts_with($destination, '/admin')) {
      return NULL;
    }

    return Url::fromUserInput($destination);
  }

  /**
   * Builds an inline confirmation message shown after successful submit.
   */
  private function confirmationMessage(): array {
    $message = $this->t(
      'Thank you. Your event has been submitted for review.',
      [],
      ['context' => 'eonext_kultur_event_submissions']
    );

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['status-message', 'ek-submission-form__confirmation'],
        'role' => 'status',
      ],
      'icon' => [
        '#type' => 'markup',
        '#markup' => '<div class="status-message__icon"><img src="/themes/custom/novel/assets/dpl-design-system/icons/basic/icon-check.svg" alt="" /></div>',
      ],
      'description' => [
        '#type' => 'markup',
        '#markup' => '<div class="status-message__description">' . $message . '</div>',
      ],
    ];
  }

  /**
   * Extracts a datetime object from an entity form widget value.
   */
  private function getDateTimeFromFormValue(mixed $value): ?\Drupal\Core\Datetime\DrupalDateTime {
    if ($value instanceof \Drupal\Core\Datetime\DrupalDateTime) {
      return $value;
    }

    if (is_array($value) && isset($value[0]['value']) && $value[0]['value'] instanceof \Drupal\Core\Datetime\DrupalDateTime) {
      return $value[0]['value'];
    }

    return NULL;
  }

  /**
   * Adds section headings around field wrappers without moving form elements.
   */
  private function applySectionWrappers(array &$form): void {
    $this->wrapFieldSection($form, 'city', $this->t('City', [], ['context' => 'eonext_kultur_event_submissions']));
    $this->wrapFieldSection($form, 'organisation_name', $this->t('Organisation', [], ['context' => 'eonext_kultur_event_submissions']));
    $this->wrapFieldSection($form, 'address', $this->t('Location', [], ['context' => 'eonext_kultur_event_submissions']));

    if (isset($form['event_start'])) {
      $schedule_heading = '<h2 class="ek-submission-form__section-title text-header-h4">'
        . $this->t('Date and time', [], ['context' => 'eonext_kultur_event_submissions'])
        . '</h2>';
      $existing_prefix = $form['event_start']['#prefix'] ?? '';
      $form['event_start']['#prefix'] = $existing_prefix
        . '<div class="ek-submission-form__section ek-submission-form__datetime-row">'
        . $schedule_heading
        . '<div class="ek-submission-form__datetime-fields">';
    }

    if (isset($form['event_end'])) {
      $existing_suffix = $form['event_end']['#suffix'] ?? '';
      $form['event_end']['#suffix'] = $existing_suffix . '</div></div>';
    }

    if (isset($form['contact_name'])) {
      $contact_heading = '<h2 class="ek-submission-form__section-title text-header-h4">'
        . $this->t('Contact', [], ['context' => 'eonext_kultur_event_submissions'])
        . '</h2>';
      $existing_prefix = $form['contact_name']['#prefix'] ?? '';
      $form['contact_name']['#prefix'] = $existing_prefix
        . '<div class="ek-submission-form__section">'
        . $contact_heading;
    }

    if (isset($form['contact_phone'])) {
      $existing_suffix = $form['contact_phone']['#suffix'] ?? '';
      $form['contact_phone']['#suffix'] = $existing_suffix . '</div>';
    }

    $this->wrapFieldSection($form, 'image', $this->t('Image', [], ['context' => 'eonext_kultur_event_submissions']));

    if (isset($form['description_gl'])) {
      $description_heading = '<h2 class="ek-submission-form__section-title text-header-h4">'
        . $this->t('Event description', [], ['context' => 'eonext_kultur_event_submissions'])
        . '</h2>';
      $existing_prefix = $form['description_gl']['#prefix'] ?? '';
      $form['description_gl']['#prefix'] = $existing_prefix
        . '<div class="ek-submission-form__section">'
        . $description_heading;
    }

    if (isset($form['description_da'])) {
      $existing_suffix = $form['description_da']['#suffix'] ?? '';
      $form['description_da']['#suffix'] = $existing_suffix . '</div>';
    }
  }

  /**
   * Wraps a single field in a section container with a heading.
   */
  private function wrapFieldSection(array &$form, string $field_name, string|\Stringable $title): void {
    if (!isset($form[$field_name])) {
      return;
    }

    $heading = '<h2 class="ek-submission-form__section-title text-header-h4">' . $title . '</h2>';
    $existing_prefix = $form[$field_name]['#prefix'] ?? '';
    $form[$field_name]['#prefix'] = $existing_prefix
      . '<div class="ek-submission-form__section">'
      . $heading;
    $existing_suffix = $form[$field_name]['#suffix'] ?? '';
    $form[$field_name]['#suffix'] = $existing_suffix . '</div>';
  }

}
