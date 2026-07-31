<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_event_submissions\Form;

use CommerceGuys\Addressing\AddressFormat\AddressField;
use CommerceGuys\Addressing\AddressFormat\FieldOverride;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Url;
use Drupal\eonext_kultur_event_submissions\Entity\KulturEventSubmission;
use Drupal\eonext_kultur_event_submissions\SubmissionConstants;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Public Kulturnat event registration form.
 */
final class KulturEventSubmissionPublicForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'eonext_kultur_event_submission_public_form';
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
    $form['#after_build'][] = 'eonext_kultur_event_submissions_public_form_after_build';

    if ($form_state->get('submission_success')) {
      $form['confirmation'] = $this->confirmationMessage();
    }
    else {
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -100,
      ];
    }

    $form['section_city'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ek-submission-form__section']],
      'heading' => $this->sectionTitle($this->t('City', [], ['context' => 'eonext_kultur_event_submissions'])),
      'city' => [
        '#type' => 'radios',
        '#title' => $this->t('Select city', [], ['context' => 'eonext_kultur_event_submissions']),
        '#title_display' => 'invisible',
        '#required' => TRUE,
        '#options' => SubmissionConstants::cityOptions(),
        '#default_value' => SubmissionConstants::CITY_OTHER,
      ],
    ];

    $form['section_organisation'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ek-submission-form__section']],
      'heading' => $this->sectionTitle($this->t('Organisation', [], ['context' => 'eonext_kultur_event_submissions'])),
      'organisation_name' => [
        '#type' => 'textfield',
        '#title' => $this->t('Organisation name', [], ['context' => 'eonext_kultur_event_submissions']),
        '#required' => TRUE,
        '#maxlength' => 255,
      ],
    ];

    $form['section_location'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ek-submission-form__section']],
      'heading' => $this->sectionTitle($this->t('Location', [], ['context' => 'eonext_kultur_event_submissions'])),
      'address' => [
        '#type' => 'address',
        '#title' => $this->t('Address', [], ['context' => 'eonext_kultur_event_submissions']),
        '#title_display' => 'invisible',
        '#required' => TRUE,
        '#default_value' => ['country_code' => 'GL'],
        '#available_countries' => ['GL', 'DK'],
        '#field_overrides' => [
          AddressField::GIVEN_NAME => FieldOverride::HIDDEN,
          AddressField::ADDITIONAL_NAME => FieldOverride::HIDDEN,
          AddressField::FAMILY_NAME => FieldOverride::HIDDEN,
          AddressField::ORGANIZATION => FieldOverride::HIDDEN,
          AddressField::ADDRESS_LINE2 => FieldOverride::HIDDEN,
          AddressField::ADDRESS_LINE3 => FieldOverride::HIDDEN,
          AddressField::SORTING_CODE => FieldOverride::HIDDEN,
          AddressField::DEPENDENT_LOCALITY => FieldOverride::HIDDEN,
          AddressField::ADMINISTRATIVE_AREA => FieldOverride::HIDDEN,
        ],
      ],
    ];

    $form['section_schedule'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ek-submission-form__section']],
      'heading' => $this->sectionTitle($this->t('Date and time', [], ['context' => 'eonext_kultur_event_submissions'])),
      'schedule' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['ek-submission-form__datetime-row']],
        'event_start_group' => [
          '#type' => 'container',
          '#tree' => FALSE,
          '#attributes' => ['class' => ['ek-submission-form__datetime-group']],
          'event_start' => [
            '#type' => 'datetime',
            '#title' => $this->t('Event start', [], ['context' => 'eonext_kultur_event_submissions']),
            '#required' => TRUE,
            '#date_date_element' => 'date',
            '#date_time_element' => 'time',
          ],
        ],
        'event_end_group' => [
          '#type' => 'container',
          '#tree' => FALSE,
          '#attributes' => ['class' => ['ek-submission-form__datetime-group']],
          'event_end' => [
            '#type' => 'datetime',
            '#title' => $this->t('Event end', [], ['context' => 'eonext_kultur_event_submissions']),
            '#required' => TRUE,
            '#date_date_element' => 'date',
            '#date_time_element' => 'time',
          ],
        ],
      ],
    ];

    $form['section_contact'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ek-submission-form__section']],
      'heading' => $this->sectionTitle($this->t('Contact', [], ['context' => 'eonext_kultur_event_submissions'])),
      'contact_name' => [
        '#type' => 'textfield',
        '#title' => $this->t('Contact name', [], ['context' => 'eonext_kultur_event_submissions']),
        '#required' => TRUE,
        '#maxlength' => 255,
      ],
      'contact_email' => [
        '#type' => 'email',
        '#title' => $this->t('Contact email', [], ['context' => 'eonext_kultur_event_submissions']),
        '#required' => TRUE,
      ],
      'contact_phone' => [
        '#type' => 'tel',
        '#title' => $this->t('Contact phone', [], ['context' => 'eonext_kultur_event_submissions']),
        '#required' => FALSE,
        '#maxlength' => 64,
      ],
    ];

    $form['section_image'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ek-submission-form__section']],
      'heading' => $this->sectionTitle($this->t('Image', [], ['context' => 'eonext_kultur_event_submissions'])),
      'image' => [
        '#type' => 'file',
        '#title' => $this->t('Event image', [], ['context' => 'eonext_kultur_event_submissions']),
        '#required' => TRUE,
        '#upload_validators' => [
          'file_validate_extensions' => ['png jpg jpeg webp'],
        ],
        '#description' => $this->t('Accepted formats: PNG, JPG, JPEG, WEBP.', [], ['context' => 'eonext_kultur_event_submissions']),
      ],
    ];

    $form['section_descriptions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ek-submission-form__section']],
      'heading' => $this->sectionTitle($this->t('Event description', [], ['context' => 'eonext_kultur_event_submissions'])),
      'description_gl' => [
        '#type' => 'textarea',
        '#title' => $this->t('Info om arrangement på grønlandsk', [], ['context' => 'eonext_kultur_event_submissions']),
        '#required' => TRUE,
        '#rows' => 6,
        '#attributes' => ['class' => ['dpl-input--full-width']],
      ],
      'description_da' => [
        '#type' => 'textarea',
        '#title' => $this->t('Info om arrangement på dansk', [], ['context' => 'eonext_kultur_event_submissions']),
        '#required' => FALSE,
        '#rows' => 6,
        '#attributes' => ['class' => ['dpl-input--full-width']],
        '#states' => [
          'required' => [
            ':input[name="city"]' => ['value' => SubmissionConstants::CITY_NUUK],
          ],
        ],
      ],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit event', [], ['context' => 'eonext_kultur_event_submissions']),
      '#attributes' => ['class' => ['btn-primary', 'btn-filled', 'btn-medium', 'dpl-button']],
    ];

    return $form;
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
   * Builds a section heading markup element.
   */
  private function sectionTitle(string|\Stringable $title): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $title,
      '#attributes' => ['class' => ['ek-submission-form__section-title', 'text-header-h4']],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $city = $form_state->getValue('city');
    $descriptionDa = trim((string) $form_state->getValue('description_da'));

    if ($city === SubmissionConstants::CITY_NUUK && $descriptionDa === '') {
      $form_state->setErrorByName(
        'description_da',
        $this->t('Danish description is required when Nuuk is selected.', [], ['context' => 'eonext_kultur_event_submissions'])
      );
    }

    $start = $form_state->getValue('event_start');
    $end = $form_state->getValue('event_end');
    if ($start instanceof DrupalDateTime
      && $end instanceof DrupalDateTime
      && $end->getTimestamp() < $start->getTimestamp()) {
      $form_state->setErrorByName(
        'event_end',
        $this->t('Event end must be after event start.', [], ['context' => 'eonext_kultur_event_submissions'])
      );
    }

    if ($form_state->hasAnyErrors()) {
      return;
    }

    $validators = [
      'file_validate_extensions' => ['png jpg jpeg webp'],
    ];
    /** @var \Drupal\file\FileInterface|null $file */
    $file = file_save_upload(
      'image',
      $validators,
      'public://kultur-event-submissions',
      FileSystemInterface::EXISTS_RENAME
    );

    if ($file === NULL) {
      $form_state->setErrorByName(
        'image',
        $this->t('The image could not be uploaded. Check the file format and try again.', [], ['context' => 'eonext_kultur_event_submissions'])
      );
      return;
    }

    $file->setPermanent();
    $file->save();
    $form_state->set('uploaded_image_fid', (int) $file->id());
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $fileId = (int) $form_state->get('uploaded_image_fid');

    /** @var \Drupal\Core\Datetime\DrupalDateTime $eventStart */
    $eventStart = $form_state->getValue('event_start');
    /** @var \Drupal\Core\Datetime\DrupalDateTime $eventEnd */
    $eventEnd = $form_state->getValue('event_end');

    /** @var \Drupal\eonext_kultur_event_submissions\Entity\KulturEventSubmission $submission */
    $submission = $this->entityTypeManager->getStorage('kultur_event_submission')->create([
      'organisation_name' => $form_state->getValue('organisation_name'),
      'city' => $form_state->getValue('city'),
      'address' => $form_state->getValue('address'),
      'event_start' => $eventStart->format('Y-m-d\TH:i:s'),
      'event_end' => $eventEnd->format('Y-m-d\TH:i:s'),
      'contact_name' => $form_state->getValue('contact_name'),
      'contact_email' => $form_state->getValue('contact_email'),
      'contact_phone' => $form_state->getValue('contact_phone'),
      'image' => $fileId > 0 ? ['target_id' => $fileId] : [],
      'description_gl' => $form_state->getValue('description_gl'),
      'description_da' => $form_state->getValue('description_da'),
      'status' => SubmissionConstants::STATUS_PENDING,
    ]);
    $submission->save();

    $this->messenger()->addStatus($this->t('Thank you. Your event has been submitted for review.', [], ['context' => 'eonext_kultur_event_submissions']));
    $form_state->set('submission_success', TRUE);
    $form_state->setUserInput([]);
    $form_state->setRebuild(TRUE);
  }

}
