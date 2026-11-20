<?php

/**
 * @file
 * Korsør: small fixes so `task drupal:upgrade` runs on a restored prod DB.
 *
 * Writes only active config/state; config/sync is not touched.
 *
 * Usage: drush php:script scripts/korsor-pre-upgrade.php
 */

declare(strict_types=1);

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Database\Database;
use Drupal\user\Entity\Role;
$config_factory = \Drupal::configFactory();
$state = \Drupal::state();

// Prod-only config (Event Engine, kultur, easyemail, lokale …) that cim would
// delete or overwrite. Listing config_ignore.settings itself makes cim use
// these active rules instead of the sync file.
$korsor_ignore = [
  'config_ignore.settings',
  'config_ignore_auto.settings',
  'field_inheritance.config',
  'administerusersbyrole.settings',
  'entity_clone.cloneable_entities',
  'filter.format.webform_default',
  'system.theme:default',
  'dk_address.settings',
  // Event Engine / workflow.
  'eonext_event_engine.*',
  'workflow.*',
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
  'taxonomy.vocabulary.organization_category',
  'taxonomy.vocabulary.gl_municipality',
  'user.role.employee',
  'user.role.organisation_*',
  'system.action.user_add_role_action.employee',
  'system.action.user_remove_role_action.employee',
  'system.action.user_add_role_action.organisation_*',
  'system.action.user_remove_role_action.organisation_*',
  'field.field.user.user.field_user_municipality',
  'field.field.user.user.field_user_organization',
  'field.field.user.user.field_user_phone',
  'field.storage.user.field_user_municipality',
  'field.storage.user.field_user_organization',
  'field.storage.user.field_user_phone',
  'core.entity_form_display.user.user.*',
  'core.entity_view_display.user.user.*',
  'block.block.ee_frontend_*',
  // GO config in sync depends on dpl_go, which Korsør never enabled.
  'core.entity_view_display.node.go_*',
  'user.role.external_graphql_client',
  'user.role.go_graphql_client',
  'user.role.mobile_graphql_client',
  // Sync version depends on bnf, which Korsør does not run.
  'user.role.mediator',
  // Not in Korsør sync; dpl_update_deploy_unilogin_patron_permissions needs it.
  'user.role.unilogin_patron',
  'field.field.node.branch.field_address_dawa',
  'field.storage.node.field_address_dawa',
  // Kultur / lokale / editorial search.
  'eonext_kultur.settings',
  'eonext_kultur_*',
  'eonext_editorial_search.*',
  'rest.resource.eonext_editorial_search.*',
  'views.view.editorial_search',
  'views.view.foreninger',
  'views.view.lokaler',
  'block.block.eonext_kultur_*',
  'css_editor.*',
  'node.type.lokale',
  'core.base_field_override.node.lokale.*',
  'field.field.node.lokale.*',
  'core.entity_form_display.node.lokale.*',
  'core.entity_view_display.node.lokale.*',
  'field.storage.node.field_booking_*',
  'field.storage.node.field_lokale_*',
  'field.storage.node.field_winkas_*',
  'field.storage.node.field_capacity',
  'field.storage.node.field_contact',
  'field.storage.node.field_description',
  'field.storage.node.field_images',
  'field.storage.node.field_size_m2',
  'field.storage.node.field_material',
  'field.field.node.article.field_material',
  'paragraphs.paragraphs_type.eonext_kultur_*',
  '~paragraphs.paragraphs_type.eonext_kultur_foreninger_listing',
  'core.entity_form_display.paragraph.eonext_kultur_*',
  '~core.entity_form_display.paragraph.eonext_kultur_foreninger_listing.default',
  'core.entity_view_display.paragraph.eonext_kultur_*',
  '~core.entity_view_display.paragraph.eonext_kultur_foreninger_listing.default',
  'field.field.paragraph.*.field_kultur_*',
  'field.storage.paragraph.field_kultur_*',
  'field.field.paragraph.*.field_locale_*',
  'field.storage.paragraph.field_locale_*',
  'field.field.paragraph.nav_grid_manual.field_nav_grid_overlay_color',
  'field.storage.paragraph.field_nav_grid_overlay_color',
  'core.entity_form_display.paragraph.*:content.field_kultur_*',
  'core.entity_view_display.paragraph.*:hidden.field_kultur_*',
  'core.entity_form_display.paragraph.nav_grid_manual.default:content.field_nav_grid_overlay_color',
  'core.entity_view_display.paragraph.nav_grid_manual.*:hidden.field_nav_grid_overlay_color',
  'field.field.node.*.field_paragraphs:settings.handler_settings.target_bundles.eonext_kultur_*',
  'field.field.node.*.field_paragraphs:settings.handler_settings.target_bundles.newsletter_*',
  'field.field.node.*.field_paragraphs:settings.handler_settings.target_bundles_drag_drop.eonext_kultur_*',
  'field.field.node.*.field_paragraphs:settings.handler_settings.target_bundles_drag_drop.newsletter_*',
  'linkit.linkit_profile.default:matchers.141d8756-2ca4-48ca-8408-56f76628f559',
  'core.entity_view_mode.node.box',
  'core.entity_view_mode.node.row',
  'core.entity_view_mode.node.hero_slide',
  'core.entity_view_mode.eventinstance.*',
  'core.entity_view_mode.eventseries.*',
  'core.entity_view_mode.paragraph.carousel',
  'core.entity_view_mode.paragraph.hero',
  'core.entity_view_display.node.*.box',
  'core.entity_view_display.node.*.hero_slide',
  'core.entity_view_display.eventinstance.default.box',
  'core.entity_view_display.eventinstance.default.hero_slide',
  'core.entity_view_display.eventseries.default.box',
  'core.entity_view_display.eventseries.default.hero_slide',
  'core.entity_view_display.paragraph.content_slider.carousel',
  'core.entity_view_display.paragraph.content_slider.hero',
  'field.field.eventseries.default.field_publication_date',
  'field.storage.eventseries.field_publication_date',
  'language.content_settings.eventseries.default',
  'core.entity_form_display.eventseries.default.default:content.field_publication_date',
  'core.entity_form_display.eventseries.default.default:third_party_settings.field_group.group_publication',
  'core.entity_view_display.eventseries.default.default:hidden.field_publication_date',
  'core.entity_form_display.node.article.default:hidden.field_material',
  'core.entity_view_display.node.article.*:hidden.field_material',
  'facets.facet.editorial_*',
  'facets.facet.event_is_free',
  'facets.facet.event_screen_names',
  'facets.facet.event_tags',
  'facets.facet_source.search_api__views_page__editorial_search__page',
  'taxonomy.vocabulary.audiences',
  // easyemail.
  'easyemail.*',
  'taxonomy.vocabulary.mailinglist',
  'paragraphs.paragraphs_type.newsletter_*',
  'field.field.paragraph.newsletter_*',
  'field.storage.paragraph.field_nl_*',
  'core.entity_form_display.paragraph.newsletter_*',
  'core.entity_view_display.paragraph.newsletter_*',
  'core.entity_form_display.easyemail_newsletter.*',
  'core.entity_view_display.easyemail_newsletter.*',
  // Dev/admin modules enabled on prod.
  'devel.*',
  'system.menu.devel',
  'dblog.settings',
  'views.view.watchdog',
  'field_ui.settings',
  'sparkpost.settings',
];

$data = (new FileStorage(DRUPAL_ROOT . '/../config/sync'))->read('config_ignore.settings');
$data['mode'] = 'intermediate';
foreach (['import', 'export'] as $direction) {
  $list = array_values(array_unique(array_merge($data['ignored_config_entities'][$direction] ?? [], $korsor_ignore)));
  sort($list);
  $data['ignored_config_entities'][$direction] = $list;
}
$config_factory->getEditable('config_ignore.settings')->setData($data)->save(TRUE);
echo 'config_ignore.settings (active): ' . count($data['ignored_config_entities']['import']) . " import patterns.\n";

// Extensions whose code is gone (address_dawa, easyemail_inlead_default).
$extension = $config_factory->getEditable('core.extension');
$modules = $extension->get('module') ?? [];
$module_list = \Drupal::service('extension.list.module');
$removed = [];
$module_list->reset();
foreach (array_keys($modules) as $name) {
  if (!$module_list->exists($name)) {
    unset($modules[$name]);
    \Drupal::keyValue('system.schema')->delete($name);
    $removed[] = $name;
  }
}
if ($removed) {
  $extension->set('module', $modules)->save();
  echo 'Removed missing modules from core.extension: ' . implode(', ', $removed) . "\n";
}
// `drush en varnish_purger` in drupal:upgrade runs before dpl_update_update_10076
// can remove these, and dies on the missing address_dawa field type.
foreach (['field.field.node.branch.field_address_dawa', 'field.storage.node.field_address_dawa'] as $name) {
  $config_factory->getEditable($name)->delete();
}
foreach ($config_factory->listAll('core.entity_') as $name) {
  if (!str_contains($name, '.node.branch.')) {
    continue;
  }
  $display = $config_factory->getEditable($name);
  $raw = $display->getRawData();
  unset($raw['content']['field_address_dawa'], $raw['hidden']['field_address_dawa']);
  if (isset($raw['dependencies']['config'])) {
    $raw['dependencies']['config'] = array_values(array_diff($raw['dependencies']['config'], ['field.field.node.branch.field_address_dawa']));
  }
  if (isset($raw['dependencies']['module'])) {
    $raw['dependencies']['module'] = array_values(array_diff($raw['dependencies']['module'], ['address_dawa']));
  }
  $display->setData($raw)->save();
}

// D11 core reads router.alias before system_update_11201 has run.
$schema = Database::getConnection()->schema();
if (!$schema->fieldExists('router', 'alias')) {
  $spec = ['type' => 'varchar_ascii', 'length' => 255];
  $schema->addField('router', 'alias', $spec);
  $schema->addIndex('router', 'alias', ['alias'], ['fields' => ['alias' => $spec]]);
  echo "Added router.alias.\n";
}

// Keep gsearch (Event Engine + Korsør sync): no dk_address migration, no uninstall.
// dpl_update_update_10083 installs dk_address_gsearch_migrate, whose hook_install
// converts the gsearch fields; registering it without its install hook makes
// 10083 a no-op. korsor-post-upgrade.php uninstalls it again.
if (!\Drupal::moduleHandler()->moduleExists('dk_address_gsearch_migrate')) {
  \Drupal::service('module_installer')->install(['dk_address']);
  $extension = \Drupal::configFactory()->getEditable('core.extension');
  $extension->set('module.dk_address_gsearch_migrate', 0)->save();
  \Drupal::service('update.update_hook_registry')->setInstalledVersion('dk_address_gsearch_migrate', \Drupal::CORE_MINIMUM_SCHEMA_VERSION);
  echo "Installed dk_address; registered dk_address_gsearch_migrate without migrating.\n";
}
$deploy = \Drupal::keyValue('deploy_hook');
$done = $deploy->get('existing_updates', []);
if (!in_array('dpl_update_deploy_uninstall_gsearch', $done, TRUE)) {
  $done[] = 'dpl_update_deploy_uninstall_gsearch';
  $deploy->set('existing_updates', $done);
}

// updb's locale batch crashes on projects uninstalled mid-run (facets_date_range).
// cim restores locale.settings from sync; drupal:locale imports afterwards.
$config_factory->getEditable('locale.settings')->set('translation.import_enabled', FALSE)->save();

// dpl_patron_redirect is disabled (needs dpl_go); its simple config fails cim validation.
$patron = $config_factory->getEditable('dpl_patron_redirect.settings');
if (!$patron->isNew()) {
  $state->set('korsor_upgrade.backup.dpl_patron_redirect.settings', $patron->getRawData());
  $patron->delete();
  echo "Backed up + removed dpl_patron_redirect.settings.\n";
}

if (!Role::load('unilogin_patron')) {
  Role::create(['id' => 'unilogin_patron', 'label' => 'Unilogin patron', 'weight' => 6])->save();
  echo "Created role unilogin_patron.\n";
}

// cim replaces shared roles; korsor-post-upgrade.php re-grants prod permissions.
if (!$state->get('korsor_upgrade.role_permissions')) {
  $roles = [];
  foreach ($config_factory->listAll('user.role.') as $name) {
    $roles[$name] = $config_factory->get($name)->get('permissions') ?: [];
  }
  $state->set('korsor_upgrade.role_permissions', $roles);
}

echo "Korsør pre-upgrade done.\n";
