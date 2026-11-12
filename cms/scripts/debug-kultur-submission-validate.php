<?php

/**
 * @file
 * Simulate public submission validate (CLI) — prints form errors with keys.
 *
 * Usage:
 *   drush php:script scripts/debug-kultur-submission-validate.php
 * Env:
 *   DEBUG_SUBMISSION_GIVEN_NAME=1  — include autofill-like hidden address key
 *   DEBUG_EVENT_CATEGORY_TID=39    — taxonomy term id for event_categories
 *   DEBUG_SHOW_ALL_ERRORS=1        — include non-address errors (default: address only)
 */

declare(strict_types=1);

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormState;
use Drupal\eonext_kultur_event_submissions\Entity\KulturEventSubmission;
use Drupal\eonext_kultur_event_submissions\SubmissionConstants;

/**
 * Datetime widget value after valueCallback (datetime-local).
 */
function debug_kultur_submission_datetime_widget_value(string $iso_local): array {
  $timezone = date_default_timezone_get();
  $object = DrupalDateTime::createFromFormat('Y-m-d\TH:i', $iso_local, $timezone);
  if (!$object instanceof DrupalDateTime || $object->hasErrors()) {
    $object = NULL;
  }

  return [
    'date' => $iso_local,
    'time' => '',
    'object' => $object,
  ];
}

$category_tid = (int) (getenv('DEBUG_EVENT_CATEGORY_TID') ?: 39);

$entity = KulturEventSubmission::create(['city' => SubmissionConstants::CITY_OTHER]);
$form_object = \Drupal::entityTypeManager()->getFormObject('kultur_event_submission', 'public');
$form_object->setEntity($entity);
$form_state = new FormState();

$address = [
  'langcode' => 'da',
  'country_code' => 'GL',
  'organization' => 'test org',
  'address_line1' => 'test street',
  'address_line2' => '',
  'postal_code' => '3900',
  'locality' => 'test city',
];
if (getenv('DEBUG_SUBMISSION_GIVEN_NAME')) {
  $address['given_name'] = 'autofill';
}

$user_input = [
  'city' => SubmissionConstants::CITY_OTHER,
  'organisation_name' => [0 => ['value' => 'GL title']],
  'title_da' => [0 => ['value' => 'DA title']],
  'event_categories' => [(string) $category_tid => (string) $category_tid],
  'address' => [0 => ['address' => $address]],
  'event_start' => [0 => ['value' => ['date' => '2026-11-12T19:49']]],
  'event_end' => [0 => ['value' => ['date' => '2026-11-12T22:52']]],
  'contact_name' => [0 => ['value' => 'Contact']],
  'contact_email' => [0 => ['value' => 'test@example.com']],
  'contact_phone' => [0 => ['value' => '12345678']],
  'description_gl' => [0 => ['value' => 'GL body', 'format' => 'basic']],
  'description_da' => [0 => ['value' => 'DA body', 'format' => 'basic']],
];

$form_state->setUserInput($user_input);
$form_state->setSubmitted(TRUE);

$form = \Drupal::formBuilder()->buildForm($form_object, $form_state);

$event_start_value = debug_kultur_submission_datetime_widget_value('2026-11-12T19:49');
$event_end_value = debug_kultur_submission_datetime_widget_value('2026-11-12T22:52');

$form_state->setValue('city', [['value' => SubmissionConstants::CITY_OTHER]]);
$form_state->setValue('organisation_name', [['value' => 'GL title']]);
$form_state->setValue('title_da', [['value' => 'DA title']]);
$form_state->setValue('event_categories', [['target_id' => $category_tid]]);
$form_state->setValue('address', [['address' => $address]]);
$form_state->setValue('event_start', [['value' => $event_start_value]]);
$form_state->setValue('event_end', [['value' => $event_end_value]]);
$form_state->setValue('contact_name', [['value' => 'Contact']]);
$form_state->setValue('contact_email', [['value' => 'test@example.com']]);
$form_state->setValue('contact_phone', [['value' => '12345678']]);
$form_state->setValue('description_gl', [['value' => 'GL body', 'format' => 'basic']]);
$form_state->setValue('description_da', [['value' => 'DA body', 'format' => 'basic']]);
$form_state->setUserInput($user_input);

$file_ids = \Drupal::entityQuery('file')
  ->accessCheck(FALSE)
  ->range(0, 1)
  ->execute();
if ($file_ids !== []) {
  $entity->set('image', ['target_id' => (int) reset($file_ids)]);
}

// Address element validators (custom sanitize + Address module rules).
if (isset($form['address']['widget'][0]['address'])) {
  $address_element = &$form['address']['widget'][0]['address'];
  foreach ($address_element['#element_validate'] ?? [] as $callback) {
    call_user_func_array($callback, [&$address_element, $form_state, &$form]);
  }
  unset($address_element);
}

$form_object->validateForm($form, $form_state);

$errors = $form_state->getErrors();
$show_all = (bool) getenv('DEBUG_SHOW_ALL_ERRORS');

if ($errors === []) {
  echo "No validation errors.\n";
  return;
}

foreach ($errors as $name => $message) {
  if (!$show_all && !str_contains((string) $name, 'address')) {
    continue;
  }
  echo $name . ': ' . strip_tags((string) $message) . "\n";
}

if (!$show_all) {
  echo "\n(Hidden non-address errors — set DEBUG_SHOW_ALL_ERRORS=1 to list all.)\n";
}
