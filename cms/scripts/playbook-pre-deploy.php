<?php

/**
 * @file
 * Slagbib-style pre-deploy: config_ignore from sync, config_ignore_auto, eonext_staff.
 *
 * Usage: drush php:script scripts/playbook-pre-deploy.php
 */

declare(strict_types=1);

use Drupal\Core\Config\FileStorage;
use Drupal\user\Entity\Role;

$sync = new FileStorage(DRUPAL_ROOT . '/../config/sync');
$data = $sync->read('config_ignore.settings');
if ($data) {
  \Drupal::configFactory()->getEditable('config_ignore.settings')
    ->setData($data)
    ->save(TRUE);
  echo "Imported config_ignore.settings from config/sync.\n";
}

$names = [
  'core.entity_view_mode.eonext_library_staff.profile',
  'filter.format.eonext_staff_interest',
  'editor.editor.eonext_staff_interest',
  'field.storage.eonext_library_staff.field_interest_description',
  'field.field.eonext_library_staff.eonext_library_staff.field_interest_description',
  'core.entity_form_display.eonext_library_staff.eonext_library_staff.default',
  'core.entity_view_display.eonext_library_staff.eonext_library_staff.default',
  'core.entity_view_display.eonext_library_staff.eonext_library_staff.profile',
  'paragraphs.paragraphs_type.eonext_library_staff',
  'field.field.paragraph.eonext_library_staff.field_filter_branches',
  'core.entity_form_display.paragraph.eonext_library_staff.default',
  'core.entity_view_display.paragraph.eonext_library_staff.default',
  'paragraphs.paragraphs_type.manual_recommendation',
  'field.storage.paragraph.field_mr_author',
  'field.storage.paragraph.field_mr_cover',
  'field.storage.paragraph.field_mr_publication_year',
  'field.storage.paragraph.field_mr_title',
  'field.field.paragraph.manual_recommendation.field_image_position_right',
  'field.field.paragraph.manual_recommendation.field_mr_author',
  'field.field.paragraph.manual_recommendation.field_mr_cover',
  'field.field.paragraph.manual_recommendation.field_mr_publication_year',
  'field.field.paragraph.manual_recommendation.field_mr_title',
  'field.field.paragraph.manual_recommendation.field_recommendation_description',
  'field.field.paragraph.manual_recommendation.field_recommendation_title',
  'core.entity_form_display.paragraph.manual_recommendation.default',
  'core.entity_view_display.paragraph.manual_recommendation.default',
  'core.entity_view_display.paragraph.manual_recommendation.preview',
  'field.storage.paragraph.field_nl_button_align',
  'field.storage.paragraph.field_nl_image_position',
  'field.storage.paragraph.field_nl_picture_first_width',
  'field.storage.paragraph.field_nl_picture_second_width',
];

$config = \Drupal::configFactory()->getEditable('config_ignore_auto.settings');
$ignored = $config->get('ignored_config_entities') ?: [];
$ignored = array_values(array_unique(array_filter($ignored, 'is_string')));
foreach ($names as $name) {
  if (!in_array($name, $ignored, TRUE)) {
    $ignored[] = $name;
  }
}
$config->set('ignored_config_entities', $ignored)->save();

if (\Drupal::moduleHandler()->moduleExists('eonext_staff')) {
  \Drupal::moduleHandler()->loadInclude('eonext_staff', 'install');
  $staff_install = \Drupal::service('extension.path.resolver')->getPath('module', 'eonext_staff') . '/config/install';
  $staff_source = new FileStorage($staff_install);
  foreach ([
    'filter.format.eonext_staff_interest',
    'editor.editor.eonext_staff_interest',
    'core.entity_view_mode.eonext_library_staff.profile',
  ] as $cfg) {
    $cfg_data = $staff_source->read($cfg);
    if ($cfg_data && \Drupal::configFactory()->get($cfg)->isNew()) {
      \Drupal::configFactory()->getEditable($cfg)->setData($cfg_data)->save(TRUE);
    }
  }
  if (function_exists('eonext_staff_install_extension_config')) {
    eonext_staff_install_extension_config();
  }
  if (function_exists('_eonext_staff_grant_interest_text_format_permission')) {
    _eonext_staff_grant_interest_text_format_permission();
  }
  else {
    $permission = 'use text format eonext_staff_interest';
    foreach (['administrator', 'local_administrator', 'editor'] as $role_id) {
      $role = Role::load($role_id);
      if ($role && !$role->hasPermission($permission)) {
        $role->grantPermission($permission)->save();
      }
    }
  }
}

echo "config_ignore_auto updated + eonext_staff extension config repaired.\n";

if (getenv('PLAYBOOK_PIN_DPL_UPDATE') !== '0') {
  \Drupal::keyValue('system.schema')->set('dpl_update', 10081);
  echo "Pinned system.schema dpl_update to 10081 (skips 10076–10077; address_dawa cleanup is separate).\n";
}
