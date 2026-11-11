<?php

/**
 * @file
 * Keep Korsør Event Engine / org / workflow config during drush cim.
 *
 * Not in DPL branch sync; without ignore, cim deletes workflows, roles, etc.
 *
 * Usage: drush php:script scripts/protect-korsor-event-engine-config.php
 */

declare(strict_types=1);

$patterns = [
  'workflow.workflow.*',
  'workflow.state.*',
  'workflow.transition.*',
  'workflow.type.*',
  'workflow.settings',
  'views.view.workflow_entity_history',
  'field.field.organization.*',
  'field.storage.organization.*',
  'field.field.organization_event.*',
  'field.storage.organization_event.*',
  'field.field.municipality.*',
  'field.storage.municipality.*',
  'core.entity_form_display.organization.*',
  'core.entity_view_display.organization.*',
  'core.entity_form_display.organization_event.*',
  'core.entity_view_display.organization_event.*',
  'core.entity_form_display.municipality.*',
  'core.entity_view_display.municipality.*',
  'core.entity_form_mode.organization.*',
  'eonext_event_engine.*',
  'municipality.*',
  'organization_event.*',
  'organization.*',
  'taxonomy.vocabulary.organization_category',
  'taxonomy.vocabulary.organization_categories',
  'system.action.user_add_role_action.employee',
  'system.action.user_remove_role_action.employee',
  'system.action.user_add_role_action.organisation_member',
  'system.action.user_remove_role_action.organisation_member',
  'system.action.user_add_role_action.organisation_owner',
  'system.action.user_remove_role_action.organisation_owner',
  'user.role.employee',
  'user.role.organisation_member',
  'user.role.organisation_owner',
  'block.block.ee_frontend_*',
  'facets.facet.editorial_screen_names',
  'facets.facet.event_screen_names',
  'field.storage.eventseries.field_publication_date',
  'field.field.eventseries.default.field_publication_date',
  'language.content_settings.eventseries.default',
  'core.entity_view_display.eventseries.default.box',
  'core.entity_view_display.eventinstance.default.box',
  'core.entity_view_mode.eventseries.box',
  'core.entity_view_mode.eventinstance.box',
  'field.storage.user.field_user_phone',
  'field.storage.user.field_user_organization',
  'field.storage.user.field_user_municipality',
  'field.field.user.user.field_user_phone',
  'field.field.user.user.field_user_organization',
  'field.field.user.user.field_user_municipality',
  'filter.format.webform_default',
];

$auto = \Drupal::configFactory()->getEditable('config_ignore_auto.settings');
$list = $auto->get('ignored_config_entities') ?: [];
foreach ($patterns as $pattern) {
  if (!in_array($pattern, $list, TRUE)) {
    $list[] = $pattern;
  }
}
sort($list);
$auto->set('ignored_config_entities', $list)->save();

$ignore = \Drupal::configFactory()->getEditable('config_ignore.settings');
$mode = $ignore->get('mode') ?: 'intermediate';
$entities = $ignore->get('ignored_config_entities') ?: [];
if ($mode !== 'intermediate' || !isset($entities['import'])) {
  $entities = [
    'import' => is_array($entities['import'] ?? $entities) ? ($entities['import'] ?? $entities) : [],
    'export' => is_array($entities['export'] ?? []) ? $entities['export'] : [],
  ];
}
foreach (['import', 'export'] as $direction) {
  foreach ($patterns as $pattern) {
    if (!in_array($pattern, $entities[$direction], TRUE)) {
      $entities[$direction][] = $pattern;
    }
  }
  sort($entities[$direction]);
}
$ignore->set('mode', 'intermediate')->set('ignored_config_entities', $entities)->save();

echo 'Korsør Event Engine / org config ignore patterns applied (' . count($patterns) . ").\n";
