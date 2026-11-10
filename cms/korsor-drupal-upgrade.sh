#!/usr/bin/env bash
#
# Korsør — ONE file for drupal:upgrade after DB restore / merge (copy to staging).
#
# DDEV / Coder:
#   ddev exec bash -lc 'cd /var/www/html/cms && SKIP_REACT_BUILD=true ./korsor-drupal-upgrade.sh'
#
# Lagoon / shell on server (from cms docroot parent):
#   cd cms && SKIP_REACT_BUILD=true ./korsor-drupal-upgrade.sh
#
# Env:
#   SKIP_REACT_BUILD=true   (DDEV — skip React OOM)
#   SKIP_LOCALE=1           skip locale import at end
#
# Data safety: no SQL truncate of content; only legacy config/schema fixes.
#   Nodes, paragraphs, media, users (except orphan removed fields), branch/lokale stay.
#   eonext_kultur.settings: backup in state, restore after cim.
#
set -euo pipefail

CMS_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "${CMS_DIR}"

export EONEXT_MOBILESEARCH_SKIP="${EONEXT_MOBILESEARCH_SKIP:-1}"
export SKIP_REACT_BUILD="${SKIP_REACT_BUILD:-true}"

DRUSH="${DRUSH:-./vendor/bin/drush}"
TASK="task -t Taskfile.yaml"
_PHP_SNIPPET="$(mktemp /tmp/korsor-upgrade-XXXX.php)"
trap 'rm -f "$_PHP_SNIPPET"' EXIT

cat > "${_PHP_SNIPPET}" <<'KORSOR_PHP'
<?php

declare(strict_types=1);

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\Exception\UnknownExtensionException;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\locale\LocaleProjectRepository;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\Role;

$step = getenv('KORSOR_STEP') ?: '';

switch ($step) {

  case 'import_config_ignore':
    $sync = new FileStorage(DRUPAL_ROOT . '/../config/sync');
    $data = $sync->read('config_ignore.settings');
    if (!$data) {
      throw new RuntimeException('config/sync/config_ignore.settings.yml missing.');
    }
    \Drupal::configFactory()->getEditable('config_ignore.settings')->setData($data)->save(TRUE);
    echo "Imported config_ignore.settings from config/sync.\n";
    break;

  case 'protect_kultur_config':
    $patterns = [
      'node.type.lokale',
      'language.content_settings.node.lokale',
      'paragraphs.paragraphs_type.eonext_kultur_*',
      'field.field.node.lokale.*',
      'core.entity_form_display.node.lokale.*',
      'core.entity_view_display.node.lokale.*',
      'field.field.paragraph.content_slider.field_kultur_*',
      'field.field.paragraph.eonext_kultur_*',
      'core.entity_view_display.paragraph.content_slider.carousel',
      'core.entity_view_display.paragraph.content_slider.hero',
      'core.entity_view_mode.paragraph.carousel',
      'core.entity_view_mode.paragraph.hero',
      'core.entity_view_mode.node.hero_slide',
      'core.entity_view_mode.eventinstance.hero_slide',
      'core.entity_view_mode.eventseries.hero_slide',
      'core.entity_view_display.node.*.hero_slide',
      'core.entity_view_display.eventinstance.default.hero_slide',
      'core.entity_view_display.eventseries.default.hero_slide',
      'core.entity_form_display.paragraph.eonext_kultur_*',
      'core.entity_view_display.paragraph.eonext_kultur_*',
      'field.storage.paragraph.field_kultur_*',
      'field.field.paragraph.*.field_kultur_*',
      'field.storage.paragraph.field_nav_grid_overlay_color',
      'field.field.paragraph.nav_grid_manual.field_nav_grid_overlay_color',
      'field.storage.paragraph.field_kultur_banner_bg_color',
      'field.field.paragraph.banner.field_kultur_banner_bg_color',
      'field.storage.paragraph.field_kultur_center_text',
      'field.field.paragraph.text_body.field_kultur_center_text',
      'field.storage.paragraph.field_locale_*',
      'field.storage.node.field_booking_*',
      'field.storage.node.field_lokale_*',
      'field.storage.node.field_winkas_*',
      'field.storage.node.field_capacity',
      'field.storage.node.field_size_m2',
      'field.storage.node.field_contact',
      'field.storage.node.field_description',
      'field.storage.node.field_images',
      'eonext_kultur_branches.settings',
      'eonext_kultur_events.settings',
      'eonext_kultur_locale.settings',
      'eonext_kultur_main.settings',
      'eonext_kultur_event_submissions.*',
      'views.view.foreninger',
      'views.view.lokaler',
      'field.storage.*.field_address_gsearch',
      'field.storage.*.field_event_address_gsearch',
      'field.field.*.field_address_gsearch',
      'field.field.*.field_event_address_gsearch',
      'gsearch.settings',
      'field_inheritance.field_inheritance.eventinstance_default_event_address',
      'views.view.entity_reference_content',
      'core.entity_form_display.node.branch.default',
      'core.entity_view_display.node.branch.*',
    ];
    $remove = [
      'paragraphs.paragraphs_type.eonext_kultur',
      'field.storage.node.field_*',
      'field.storage.paragraph.field_*',
      'core.entity_form_display.eventinstance.default.*',
      'core.entity_form_display.eventseries.default.*',
      'core.entity_view_display.eventinstance.default.*',
      'core.entity_view_display.eventseries.default.*',
    ];
    $auto = \Drupal::configFactory()->getEditable('config_ignore_auto.settings');
    $list = $auto->get('ignored_config_entities') ?: [];
    $list = array_values(array_filter($list, static fn (string $item): bool => !in_array($item, $remove, TRUE)));
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
    if ($mode !== 'intermediate') {
      $entities = ['import' => is_array($entities) ? $entities : [], 'export' => []];
    }
    foreach (['import', 'export'] as $direction) {
      $entities[$direction] = array_values(array_filter(
        $entities[$direction] ?? [],
        static fn (string $item): bool => !in_array($item, $remove, TRUE),
      ));
      foreach ($patterns as $pattern) {
        if (!in_array($pattern, $entities[$direction], TRUE)) {
          $entities[$direction][] = $pattern;
        }
      }
      sort($entities[$direction]);
    }
    $ignore->set('mode', 'intermediate')->set('ignored_config_entities', $entities)->save();
    echo 'Updated config_ignore patterns (' . count($patterns) . ").\n";
    break;

  case 'delete_stale_organization':
    $storage = \Drupal::service('config.storage');
    $names = array_unique(array_merge(
      $storage->listAll('core.entity_form_display.organization.'),
      $storage->listAll('core.entity_view_display.organization.'),
      $storage->listAll('field.field.organization.'),
      $storage->listAll('field.storage.organization.'),
      $storage->listAll('organization.'),
      $storage->listAll('field.field.user.user.field_user_organization'),
      $storage->listAll('field.storage.user.field_user_organization'),
    ));
    sort($names);
    foreach ($names as $name) {
      \Drupal::configFactory()->getEditable($name)->delete();
    }
    echo 'Removed ' . count($names) . " organization config item(s).\n";
    break;

  case 'purge_cim_legacy_blockers':
    // Config-only cleanup for cim (no SQL truncate — nodes/paragraphs/users stay intact).
    // Legacy: eonext_event_engine, org approval workflows, ee_frontend — not in branch sync.
    $deleted = [];
    $etm = \Drupal::entityTypeManager();
    foreach (['organization_category', 'organization_categories'] as $vid) {
      if (!$etm->getStorage('taxonomy_vocabulary')->load($vid)) {
        continue;
      }
      try {
        $tids = $etm->getStorage('taxonomy_term')->getQuery()
          ->accessCheck(FALSE)
          ->condition('vid', $vid)
          ->execute();
        if ($tids !== []) {
          $etm->getStorage('taxonomy_term')->delete(
            $etm->getStorage('taxonomy_term')->loadMultiple($tids),
          );
          $deleted[] = 'taxonomy_terms:' . $vid . ':' . count($tids);
        }
      }
      catch (Throwable $e) {
        echo "taxonomy {$vid}: {$e->getMessage()} (config cleanup may still allow cim).\n";
      }
    }
    if ($etm->hasDefinition('workflow')) {
      foreach (['organization_approval', 'events_approval'] as $wid) {
        try {
          $etm->getStorage('workflow')->load($wid)?->delete();
          $deleted[] = 'workflow:' . $wid;
        }
        catch (Throwable) {
        }
      }
    }
    $config_storage = \Drupal::service('config.storage');
    $config_prefixes = [
      'workflow.',
      'field.field.municipality.',
      'field.storage.municipality.',
      'field.field.organization_event.',
      'field.storage.organization_event.',
      'core.entity_form_display.municipality.',
      'core.entity_view_display.municipality.',
      'core.entity_form_display.organization_event.',
      'core.entity_view_display.organization_event.',
      'eonext_event_engine.',
      'municipality.',
      'organization_event.',
      'taxonomy.vocabulary.organization_category',
      'taxonomy.vocabulary.organization_categories',
    ];
    foreach ($config_prefixes as $prefix) {
      foreach ($config_storage->listAll($prefix) as $name) {
        \Drupal::configFactory()->getEditable($name)->delete();
        $deleted[] = $name;
      }
    }
    foreach ($config_storage->listAll('block.block.') as $block_name) {
      $theme = \Drupal::configFactory()->get($block_name)->get('theme');
      if ($theme === 'ee_frontend') {
        \Drupal::configFactory()->getEditable($block_name)->delete();
        $deleted[] = $block_name;
      }
    }
    $extension = \Drupal::configFactory()->getEditable('core.extension');
    $modules = $extension->get('module') ?? [];
    $themes = $extension->get('theme') ?? [];
    $ext_changed = FALSE;
    foreach (['eonext_event_engine', 'address_dawa'] as $module) {
      if (isset($modules[$module])) {
        unset($modules[$module]);
        $ext_changed = TRUE;
      }
    }
    foreach (['ee_frontend'] as $theme) {
      if (isset($themes[$theme])) {
        unset($themes[$theme]);
        $ext_changed = TRUE;
      }
    }
    if ($ext_changed) {
      asort($modules);
      asort($themes);
      $extension->set('module', $modules)->set('theme', $themes)->save();
      $deleted[] = 'core.extension';
    }
    $theme_cfg = \Drupal::configFactory()->getEditable('system.theme');
    foreach (['default', 'admin'] as $theme_key) {
      if ($theme_cfg->get($theme_key) === 'ee_frontend') {
        $theme_cfg->set($theme_key, $theme_key === 'default' ? 'novel' : 'gin')->save();
        $deleted[] = 'system.theme.' . $theme_key;
      }
    }
    foreach ($config_storage->listAll('user.role.') as $role_name) {
      $role = \Drupal::configFactory()->getEditable($role_name);
      $data = $role->getRawData();
      if (empty($data['dependencies']['config'])) {
        continue;
      }
      $before = count($data['dependencies']['config']);
      $data['dependencies']['config'] = array_values(array_filter(
        $data['dependencies']['config'],
        static fn (string $dep): bool => !preg_match(
          '/^workflow\.workflow\.|^taxonomy\.vocabulary\.organization_categor/',
          $dep,
        ),
      ));
      if (count($data['dependencies']['config']) !== $before) {
        $role->setData($data)->save();
        $deleted[] = $role_name;
      }
    }
    $count = count($deleted);
    echo $count === 0 ? "No CIM legacy blockers.\n" : "Purged {$count} CIM blocker item(s).\n";
    break;

  case 'prepare_user_config_for_cim':
    // Legacy event-engine roles/fields on user — not in branch sync (cim deletes them).
    $cf = \Drupal::configFactory();
    $sync = new FileStorage(DRUPAL_ROOT . '/../config/sync');
    foreach (['employee', 'organisation_member', 'organisation_owner'] as $rid) {
      $name = 'user.role.' . $rid;
      if (!$cf->get($name)->isNew()) {
        $cf->getEditable($name)->delete();
        echo "Deleted legacy role config {$name}.\n";
      }
    }
    $legacy_fields = ['field_user_organization', 'field_user_phone'];
    foreach ($cf->listAll('core.entity_') as $display_name) {
      if (!str_contains($display_name, '.user.user.')) {
        continue;
      }
      $display = $cf->getEditable($display_name);
      $data = $display->getRawData();
      $changed = FALSE;
      foreach ($legacy_fields as $field_name) {
        $dep = 'field.field.user.user.' . $field_name;
        foreach (['content', 'hidden'] as $section) {
          if (isset($data[$section][$field_name])) {
            unset($data[$section][$field_name]);
            $changed = TRUE;
          }
        }
        if (isset($data['dependencies']['config'])) {
          $before = count($data['dependencies']['config']);
          $data['dependencies']['config'] = array_values(array_filter(
            $data['dependencies']['config'],
            static fn (string $item): bool => $item !== $dep,
          ));
          if (count($data['dependencies']['config']) !== $before) {
            $changed = TRUE;
          }
        }
      }
      if ($changed) {
        $display->setData($data)->save();
        echo "Cleaned {$display_name}.\n";
      }
    }
    foreach (['core.entity_form_display.user.user.default', 'core.entity_view_display.user.user.default'] as $name) {
      $data = $sync->read($name);
      if ($data) {
        $cf->getEditable($name)->setData($data)->save(TRUE);
        echo "Pre-applied {$name} from sync.\n";
      }
    }
    $register = $sync->read('core.entity_form_display.user.user.register');
    if ($register) {
      $cf->getEditable('core.entity_form_display.user.user.register')->setData($register)->save(TRUE);
    }
    elseif (!$cf->get('core.entity_form_display.user.user.register')->isNew()) {
      $cf->getEditable('core.entity_form_display.user.user.register')->delete();
    }
    foreach ($legacy_fields as $field_name) {
      foreach (["field.field.user.user.{$field_name}", "field.storage.user.{$field_name}"] as $name) {
        if (!$cf->get($name)->isNew()) {
          $cf->getEditable($name)->delete();
          echo "Deleted {$name}.\n";
        }
      }
    }
    break;

  case 'delete_kultur_theme_settings_before_cim':
    // Theme settings only (placeholder colors etc.) — NOT nodes/paragraphs/slider module settings.
    $name = 'eonext_kultur.settings';
    $config = \Drupal::configFactory()->get($name);
    if (!$config->isNew()) {
      $raw = $config->getRawData();
      if ($raw !== []) {
        \Drupal::state()->set('korsor_backup.eonext_kultur.settings', $raw);
        echo "Backed up {$name} to state (korsor_backup).\n";
      }
      \Drupal::configFactory()->getEditable($name)->delete();
      echo "Removed {$name} temporarily so cim can run; restored after theme:enable.\n";
    }
    break;

  case 'restore_kultur_theme_settings':
    $backup = \Drupal::state()->get('korsor_backup.eonext_kultur.settings');
    if (!is_array($backup) || $backup === []) {
      echo "No kultur theme settings backup to restore.\n";
      break;
    }
    \Drupal::configFactory()->getEditable('eonext_kultur.settings')->setData($backup)->save(TRUE);
    \Drupal::state()->delete('korsor_backup.eonext_kultur.settings');
    echo "Restored eonext_kultur.settings from backup.\n";
    break;

  case 'preapply_sync_roles_and_theme':
    // Active DB roles still reference removed workflows — branch sync does not.
    $sync = new FileStorage(DRUPAL_ROOT . '/../config/sync');
    foreach (['administrator', 'editor', 'local_administrator'] as $rid) {
      $name = 'user.role.' . $rid;
      $data = $sync->read($name);
      if (!$data) {
        continue;
      }
      \Drupal::configFactory()->getEditable($name)->setData($data)->save(TRUE);
      echo "Pre-applied {$name} from sync.\n";
    }
    foreach (['system.theme'] as $name) {
      $data = $sync->read($name);
      if ($data) {
        \Drupal::configFactory()->getEditable($name)->setData($data)->save(TRUE);
        echo "Pre-applied {$name} from sync.\n";
      }
    }
    $sync_ext = $sync->read('core.extension');
    if (is_array($sync_ext)) {
      $ext = \Drupal::configFactory()->getEditable('core.extension');
      $modules = $ext->get('module') ?? [];
      $themes = $sync_ext['theme'] ?? [];
      foreach (['eonext_event_engine', 'address_dawa', 'ee_frontend'] as $ghost) {
        unset($modules[$ghost]);
      }
      unset($themes['ee_frontend']);
      // Korsør theme is not listed in branch sync core.extension but blocks depend on it at cim.
      $themes['eonext_kultur'] = $themes['eonext_kultur'] ?? 0;
      asort($modules);
      asort($themes);
      $ext->set('module', $modules)->set('theme', $themes)->save();
      echo "Aligned core.extension with sync (keeps eonext_kultur, no ee_frontend).\n";
    }
    break;

  case 'prune_stale_event_config':
    // Old DB: box displays + eventseries publication field absent from branch sync.
    $delete = [
      'core.entity_view_display.eventinstance.default.box',
      'core.entity_view_display.eventseries.default.box',
      'core.entity_form_display.eventinstance.default.box',
      'core.entity_form_display.eventseries.default.box',
      'field.field.eventseries.default.field_publication_date',
      'field.storage.eventseries.field_publication_date',
      'language.content_settings.eventseries.default',
    ];
    $removed = [];
    foreach ($delete as $name) {
      $config = \Drupal::configFactory()->getEditable($name);
      if (!$config->isNew()) {
        $config->delete();
        $removed[] = $name;
      }
    }
    echo 'Pruned ' . count($removed) . " stale event config item(s).\n";
    if ($removed !== []) {
      echo implode(', ', $removed) . "\n";
    }
    break;

  case 'prune_missing_extensions':
    $list = \Drupal::service('extension.list.module');
    $extension = \Drupal::configFactory()->getEditable('core.extension');
    $modules = $extension->get('module') ?? [];
    $removed = [];
    foreach (array_keys($modules) as $name) {
      if ($name === 'default') {
        continue;
      }
      $ok = FALSE;
      try {
        $pathname = $list->getPathname($name);
        $ok = $pathname !== '' && is_file(DRUPAL_ROOT . '/' . $pathname);
      }
      catch (UnknownExtensionException) {
      }
      if ($ok) {
        continue;
      }
      unset($modules[$name]);
      $removed[] = $name;
    }
    $theme_list = \Drupal::service('extension.list.theme');
    $themes = $extension->get('theme') ?? [];
    $removed_themes = [];
    foreach (array_keys($themes) as $name) {
      if ($name === 'default') {
        continue;
      }
      $ok = FALSE;
      try {
        $pathname = $theme_list->getPathname($name);
        $ok = $pathname !== '' && is_file(DRUPAL_ROOT . '/' . $pathname);
      }
      catch (UnknownExtensionException) {
      }
      if ($ok) {
        continue;
      }
      unset($themes[$name]);
      $removed_themes[] = $name;
    }
    $sys_theme = \Drupal::configFactory()->getEditable('system.theme');
    foreach (['default', 'admin'] as $theme_key) {
      if (in_array($sys_theme->get($theme_key), $removed_themes, TRUE)) {
        $sys_theme->set($theme_key, $theme_key === 'default' ? 'novel' : 'gin')->save();
      }
    }
    if ($removed === [] && $removed_themes === []) {
      echo "No missing extensions in core.extension.\n";
      break;
    }
    asort($modules);
    asort($themes);
    $extension->set('module', $modules)->set('theme', $themes)->save();
    $msg = [];
    if ($removed !== []) {
      $msg[] = 'modules: ' . implode(', ', $removed);
    }
    if ($removed_themes !== []) {
      $msg[] = 'themes: ' . implode(', ', $removed_themes);
    }
    echo 'Removed from core.extension — ' . implode('; ', $msg) . "\n";
    break;

  case 'remove_address_dawa':
    $config_factory = \Drupal::configFactory();
    $deleted = [];
    $field_names = $config_factory->listAll('field.field.');
    $field_names[] = 'field.storage.node.field_address_dawa';
    foreach ($field_names as $config_name) {
      if (!str_ends_with($config_name, '.field_address_dawa')) {
        continue;
      }
      $config = $config_factory->getEditable($config_name);
      if (!$config->isNew()) {
        $config->delete();
        $deleted[] = $config_name;
      }
    }
    $schema = Database::getConnection()->schema();
    foreach (['node__field_address_dawa', 'node_revision__field_address_dawa'] as $table) {
      if ($schema->tableExists($table)) {
        $schema->dropTable($table);
        $deleted[] = $table;
      }
    }
    foreach ($config_factory->listAll('core.entity_') as $config_name) {
      if (!str_contains($config_name, '.branch.')) {
        continue;
      }
      $config = $config_factory->getEditable($config_name);
      if ($config->isNew()) {
        continue;
      }
      $data = $config->getRawData();
      $changed = FALSE;
      if (isset($data['dependencies']['config'])) {
        $before = count($data['dependencies']['config']);
        $data['dependencies']['config'] = array_values(array_filter(
          $data['dependencies']['config'],
          static fn (string $dep): bool => !str_contains($dep, 'field_address_dawa'),
        ));
        if (count($data['dependencies']['config']) !== $before) {
          $changed = TRUE;
        }
      }
      foreach (['content', 'hidden'] as $section) {
        if (isset($data[$section]['field_address_dawa'])) {
          unset($data[$section]['field_address_dawa']);
          $changed = TRUE;
        }
      }
      if ($changed) {
        $config->setData($data)->save();
        $deleted[] = $config_name;
      }
    }
    $extension = \Drupal::configFactory()->getEditable('core.extension');
    $modules = $extension->get('module') ?? [];
    if (isset($modules['address_dawa'])) {
      unset($modules['address_dawa']);
      asort($modules);
      $extension->set('module', $modules)->save();
      $deleted[] = 'core.extension.module.address_dawa';
    }
    foreach (['field.storage.node.field_address_dawa'] as $storage_name) {
      $storage = \Drupal::configFactory()->getEditable($storage_name);
      if (!$storage->isNew()) {
        $storage->delete();
        $deleted[] = $storage_name;
      }
    }
    echo $deleted === [] ? "No field_address_dawa remnants.\n" : 'Removed: ' . implode(', ', $deleted) . "\n";
    break;

  case 'easyemail_trusted_updates':
    $install = DRUPAL_ROOT . '/modules/inlead/easyemail/easyemail.install';
    if (!is_readable($install)) {
      $install = DRUPAL_ROOT . '/modules/contrib/easyemail/easyemail.install';
    }
    if (is_readable($install)) {
      require_once $install;
    }
    $sync_path = \Drupal::service('extension.path.resolver')->getPath('module', 'easyemail') . '/config/sync';
    $source = new FileStorage($sync_path);
    $config_factory = \Drupal::configFactory();
    $entity_type_manager = \Drupal::entityTypeManager();
    $imported = 0;
    foreach ($source->listAll() as $name) {
      if (!preg_match('/^(field\.storage\.|field\.field\.|core\.entity_(form|view)_display\.)/', $name)) {
        continue;
      }
      $data = $source->read($name);
      if (!$data) {
        continue;
      }
      $existing = $config_factory->get($name);
      if (!$existing->isNew() && $name !== 'core.entity_form_display.paragraph.newsletter_article.default') {
        continue;
      }
      if ($name === 'core.entity_form_display.paragraph.newsletter_article.default') {
        $config_factory->getEditable($name)->setData($data)->save(TRUE);
        $imported++;
        continue;
      }
      $entity_id = $data['id'] ?? NULL;
      if (str_starts_with($name, 'field.storage.') && $entity_id && $entity_type_manager->getStorage('field_storage_config')->load($entity_id)) {
        continue;
      }
      if (str_starts_with($name, 'field.field.') && $entity_id && $entity_type_manager->getStorage('field_config')->load($entity_id)) {
        continue;
      }
      if (empty($data['uuid'])) {
        $data['uuid'] = \Drupal::service('uuid')->generate();
      }
      $config_factory->getEditable($name)->setData($data)->save(TRUE);
      $imported++;
    }
    $schema = (int) \Drupal::keyValue('system.schema')->get('easyemail');
    foreach ([
      10022 => 'easyemail_make_button_align_field_optional',
      10024 => 'easyemail_update_10024',
      10025 => 'easyemail_remove_multi_column_newsletter_paragraphs',
      10026 => 'easyemail_update_10026',
      10027 => 'easyemail_update_10027',
      10028 => 'easyemail_update_10028',
      10029 => 'easyemail_update_10029',
    ] as $version => $callable) {
      if ($schema >= $version) {
        continue;
      }
      if (function_exists($callable)) {
        try {
          echo $callable() . "\n";
        }
        catch (Throwable $e) {
          echo "{$callable} error: " . $e->getMessage() . "\n";
        }
      }
      \Drupal::keyValue('system.schema')->set('easyemail', $version);
      $schema = $version;
    }
    $target_schema = 10029;
    if ($schema < $target_schema) {
      \Drupal::keyValue('system.schema')->set('easyemail', $target_schema);
    }
    echo "easyemail: imported {$imported} config; schema=" . \Drupal::keyValue('system.schema')->get('easyemail') . "\n";
    break;

  case 'remove_orphan_user_entity_reference_fields':
    // Legacy DB user ER fields (municipality, organization, …) block dpl_app / dpl_media install.
    $etm = \Drupal::entityTypeManager();
    $config_factory = \Drupal::configFactory();
    $deleted = [];
    $orphan_fields = [];
    foreach ($config_factory->listAll('field.storage.user.') as $storage_name) {
      $data = $config_factory->get($storage_name)->getRawData();
      if (($data['type'] ?? '') !== 'entity_reference') {
        continue;
      }
      $target = $data['settings']['target_type'] ?? '';
      if ($target === '' || $etm->hasDefinition($target)) {
        continue;
      }
      $orphan_fields[] = $data['field_name'] ?? '';
    }
    $orphan_fields = array_values(array_filter(array_unique($orphan_fields)));
    foreach ($orphan_fields as $field_name) {
      $field_config_name = "field.field.user.user.{$field_name}";
      $storage_name = "field.storage.user.{$field_name}";
      foreach ([$field_config_name, $storage_name] as $name) {
        $config = $config_factory->getEditable($name);
        if (!$config->isNew()) {
          $config->delete();
          $deleted[] = $name;
        }
      }
      try {
        FieldConfig::loadByName('user', 'user', $field_name)?->delete();
      }
      catch (Throwable) {
      }
      try {
        FieldStorageConfig::loadByName('user', $field_name)?->delete();
      }
      catch (Throwable) {
      }
      // Keep user field SQL tables — data preserved; only config removed so bootstrap/module install works.
    }
    foreach ($config_factory->listAll('core.entity_') as $display_name) {
      if (!str_contains($display_name, '.user.user.')) {
        continue;
      }
      $display = $config_factory->getEditable($display_name);
      if ($display->isNew()) {
        continue;
      }
      $data = $display->getRawData();
      $changed = FALSE;
      foreach ($orphan_fields as $field_name) {
        foreach (['content', 'hidden'] as $section) {
          if (isset($data[$section][$field_name])) {
            unset($data[$section][$field_name]);
            $changed = TRUE;
          }
        }
        if (isset($data['dependencies']['config'])) {
          $dep = "field.field.user.user.{$field_name}";
          $before = count($data['dependencies']['config']);
          $data['dependencies']['config'] = array_values(array_filter(
            $data['dependencies']['config'],
            static fn (string $item): bool => $item !== $dep,
          ));
          if (count($data['dependencies']['config']) !== $before) {
            $changed = TRUE;
          }
        }
      }
      if ($changed) {
        $display->setData($data)->save();
        $deleted[] = $display_name;
      }
    }
    echo $deleted === [] ? "No orphan user entity-reference fields.\n" : 'Removed: ' . implode(', ', $deleted) . "\n";
    break;

  case 'complete_pending_post_updates':
    // Restored DB + new core/contrib: post-updates mutate legacy config and often
    // abort the whole `drush updb`. Korsør fixes config via cim afterward.
    $post_update_registry = \Drupal::service('update.post_update_registry');
    $pending = $post_update_registry->getPendingUpdateFunctions();
    if ($pending === []) {
      echo "No pending post-updates.\n";
      break;
    }
    $ran = 0;
    $failed = 0;
    foreach ($pending as $function) {
      if (is_callable($function)) {
        $sandbox = [];
        try {
          do {
            $function($sandbox);
          } while (isset($sandbox['#finished']) && (float) $sandbox['#finished'] < 1);
          $ran++;
        }
        catch (Throwable $e) {
          $failed++;
          echo "post-update {$function}: {$e->getMessage()}\n";
        }
      }
      $post_update_registry->registerInvokedUpdates([$function]);
    }
    echo count($pending) . " post-update(s): ran {$ran}, failed {$failed}, all marked done.\n";
    break;

  case 'skip_dpl_update_dk_address':
    // Korsør branch sync keeps gsearch; dk_address is not shipped on this branch.
    $list = \Drupal::service('extension.list.module');
    $missing = FALSE;
    foreach (['dk_address', 'dk_address_gsearch_migrate'] as $module) {
      try {
        $list->getPathname($module);
      }
      catch (UnknownExtensionException) {
        $missing = TRUE;
        break;
      }
    }
    if (!$missing) {
      echo "dk_address present — run dpl_update_update_10083 normally.\n";
      break;
    }
    $kv = \Drupal::keyValue('system.schema');
    $schema = (int) $kv->get('dpl_update');
    if ($schema < 10083) {
      $kv->set('dpl_update', 10083);
      echo "Marked dpl_update schema 10083 (skip dk_address install).\n";
    }
    else {
      echo "dpl_update schema already >= 10083.\n";
    }
    break;

  case 'prune_missing_module_schema':
    $kv = \Drupal::keyValue('system.schema');
    $list = \Drupal::service('extension.list.module');
    $removed = [];
    foreach (array_keys($kv->getAll()) as $module) {
      try {
        $list->getPathname($module);
        continue;
      }
      catch (UnknownExtensionException) {
      }
      $kv->delete($module);
      $removed[] = $module;
    }
    echo $removed === [] ? "No orphan system.schema modules.\n" : 'Removed schema keys: ' . implode(', ', $removed) . "\n";
    break;

  case 'restore_kultur_sliders':
    $module_path = \Drupal::service('extension.list.module')->getPath('eonext_kultur_main');
    $storage = new FileStorage($module_path . '/config/install');
    $config_names = [
      'core.entity_view_mode.paragraph.carousel',
      'core.entity_view_mode.paragraph.hero',
      'core.entity_view_mode.node.hero_slide',
      'core.entity_view_mode.eventinstance.hero_slide',
      'core.entity_view_mode.eventseries.hero_slide',
      'field.storage.paragraph.field_kultur_slider_view_mode',
      'field.storage.paragraph.field_kultur_slider_logo',
      'field.field.paragraph.content_slider.field_kultur_slider_view_mode',
      'field.field.paragraph.content_slider.field_kultur_slider_logo',
      'core.entity_view_display.paragraph.content_slider.carousel',
      'core.entity_view_display.paragraph.content_slider.hero',
      'core.entity_view_display.node.page.hero_slide',
      'core.entity_view_display.node.article.hero_slide',
      'core.entity_view_display.eventinstance.default.hero_slide',
      'core.entity_view_display.eventseries.default.hero_slide',
    ];
    $messages = [];
    foreach ($config_names as $config_name) {
      $data = $storage->read($config_name);
      if (!$data) {
        continue;
      }
      if (str_contains($config_name, 'entity_view_mode.')
        && empty($data['mode'])
        && !empty($data['id'])) {
        $parts = explode('.', $data['id'], 2);
        $data['mode'] = $parts[1] ?? '';
      }
      $editable = \Drupal::configFactory()->getEditable($config_name);
      $raw = $editable->getRawData();
      $import = $editable->isNew();
      if (str_contains($config_name, 'entity_view_display.')) {
        $import = $import
          || empty($raw['targetEntityType'])
          || empty($raw['bundle'])
          || empty($raw['mode']);
      }
      elseif (str_contains($config_name, 'entity_view_mode.')) {
        $import = $import
          || empty($raw['targetEntityType'])
          || empty($raw['mode']);
      }
      elseif (str_starts_with($config_name, 'field.')) {
        $import = $import || empty($raw['field_name']);
      }
      if (!$import) {
        continue;
      }
      $editable->setData($data)->save(TRUE);
      $messages[] = $config_name;
    }
    if (function_exists('_eonext_kultur_main_configure_slider_logo_form_display')) {
      _eonext_kultur_main_configure_slider_logo_form_display();
    }
    if (function_exists('_eonext_kultur_main_backfill_content_slider_view_mode')) {
      $count = _eonext_kultur_main_backfill_content_slider_view_mode();
      if ($count > 0) {
        $messages[] = "backfilled {$count}";
      }
    }
    echo $messages === [] ? "Kultur slider config OK.\n" : "Restored: " . implode(', ', $messages) . "\n";
    break;

  case 'ensure_router_alias_column':
    // D11 core expects router.alias; old DB dumps lack it until system_update_11201.
    $schema = Database::getConnection()->schema();
    if ($schema->tableExists('router') && !$schema->fieldExists('router', 'alias')) {
      $spec = [
        'fields' => [
          'alias' => [
            'description' => 'The alias of the route, if applicable.',
            'type' => 'varchar_ascii',
            'length' => 255,
          ],
        ],
      ];
      $schema->addField('router', 'alias', $spec['fields']['alias']);
      $schema->addIndex('router', 'alias', ['alias'], $spec);
      echo "Added router.alias column (system_update_11201).\n";
    }
    else {
      echo "router.alias column OK.\n";
    }
    break;

  case 'ensure_service_message_config':
    // updb enables dpl_service_message; Korsør sync has no service_message YAML yet.
    if (!\Drupal::moduleHandler()->moduleExists('dpl_service_message')) {
      echo "Skip: dpl_service_message not enabled.\n";
      break;
    }
    if (FieldStorageConfig::loadByName('node', 'field_svcmsg_placement')) {
      echo "Service message bundle/fields OK.\n";
      break;
    }
    if (!NodeType::load('service_message')) {
      NodeType::create([
        'type' => 'service_message',
        'name' => 'Service message',
        'description' => 'Short-lived notices about library operation.',
        'new_revision' => TRUE,
      ])->save();
      echo "Created node type service_message.\n";
    }
    $ensure_field = static function (string $field_name, string $type, array $storage_settings = [], array $field_settings = []): void {
      if (!FieldStorageConfig::loadByName('node', $field_name)) {
        FieldStorageConfig::create([
          'field_name' => $field_name,
          'entity_type' => 'node',
          'type' => $type,
          'settings' => $storage_settings,
          'cardinality' => $field_name === 'field_svcmsg_branches' ? -1 : 1,
        ])->save();
      }
      if (!FieldConfig::loadByName('node', 'service_message', $field_name)) {
        FieldConfig::create([
          'field_name' => $field_name,
          'entity_type' => 'node',
          'bundle' => 'service_message',
          'label' => $field_name,
          'required' => $field_name === 'field_svcmsg_placement',
          'settings' => $field_settings,
        ])->save();
      }
    };
    $ensure_field('field_svcmsg_heading', 'string');
    $ensure_field('field_svcmsg_body', 'string', ['max_length' => 255]);
    $ensure_field('field_svcmsg_placement', 'list_string', [
      'allowed_values' => [
        'global' => 'Global',
        'in_page' => 'In page',
      ],
    ]);
    $ensure_field('field_svcmsg_link', 'link');
    $ensure_field('field_svcmsg_frontpage', 'boolean');
    $ensure_field('field_svcmsg_branches', 'entity_reference', ['target_type' => 'node'], [
      'handler' => 'default:node',
      'handler_settings' => ['target_bundles' => ['branch' => 'branch']],
    ]);
    echo "Ensured service_message field storage/config.\n";
    break;

  case 'ensure_audiences_vocab':
    // DPL deploy hook installs default audience terms before vocabulary exists in Korsør sync.
    $sync = new FileStorage(DRUPAL_ROOT . '/../config/sync');
    $config_factory = \Drupal::configFactory();
    $vocab = $config_factory->get('taxonomy.vocabulary.audiences');
    if (!$vocab->get('uuid')) {
      $data = $sync->read('taxonomy.vocabulary.audiences');
      if (!$data) {
        $data = [
          'uuid' => \Drupal::service('uuid')->generate(),
          'langcode' => 'da',
          'status' => TRUE,
          'dependencies' => ['module' => ['flat_taxonomy']],
          'third_party_settings' => [
            'flat_taxonomy' => ['flat' => 1],
          ],
          'name' => 'Målgrupper',
          'vid' => 'audiences',
          'description' => 'Målgrupper, f.eks "børn", "unge", "voksne"',
          'weight' => 0,
          'new_revision' => FALSE,
        ];
        echo "Bootstrapped taxonomy.vocabulary.audiences.\n";
      }
      else {
        echo "Imported taxonomy.vocabulary.audiences from sync.\n";
      }
      $config_factory->getEditable('taxonomy.vocabulary.audiences')->setData($data)->save();
    }
    $lcs = 'language.content_settings.taxonomy_term.audiences';
    if (!$config_factory->get($lcs)->get('uuid')) {
      $data = $sync->read($lcs);
      if (!$data) {
        $data = [
          'uuid' => \Drupal::service('uuid')->generate(),
          'langcode' => 'en',
          'status' => TRUE,
          'dependencies' => [
            'config' => ['taxonomy.vocabulary.audiences'],
          ],
          'id' => 'taxonomy_term.audiences',
          'target_entity_type_id' => 'taxonomy_term',
          'target_bundle' => 'audiences',
          'default_langcode' => 'da',
          'language_alterable' => FALSE,
        ];
        echo "Bootstrapped {$lcs}.\n";
      }
      else {
        echo "Imported {$lcs} from sync.\n";
      }
      $config_factory->getEditable($lcs)->setData($data)->save();
    }
    $count = (int) \Drupal::entityQuery('taxonomy_term')
      ->accessCheck(FALSE)
      ->condition('vid', 'audiences')
      ->count()
      ->execute();
    if ($count === 0) {
      foreach (['Børn', 'Unge', 'Voksne'] as $label) {
        Term::create(['vid' => 'audiences', 'name' => $label])->save();
      }
      echo "Created default audience terms (Børn, Unge, Voksne).\n";
    }
    else {
      echo "Audience terms already present ({$count}).\n";
    }
    break;

  case 'ensure_gsearch_enabled':
    if (!\Drupal::moduleHandler()->moduleExists('gsearch')) {
      \Drupal::service('module_installer')->install(['gsearch']);
      echo "Enabled gsearch (core.extension is config-ignored).\n";
    }
    else {
      echo "gsearch already enabled.\n";
    }
    break;

  case 'ensure_eonext_kultur_theme_enabled':
    $theme_handler = \Drupal::service('theme_handler');
    if (!$theme_handler->themeExists('eonext_kultur')) {
      echo "eonext_kultur not in codebase — skip.\n";
      break;
    }
    $extension = \Drupal::configFactory()->getEditable('core.extension');
    $themes = $extension->get('theme') ?? [];
    if (!isset($themes['eonext_kultur'])) {
      $themes['eonext_kultur'] = 0;
      asort($themes);
      $extension->set('theme', $themes)->save();
      echo "Registered eonext_kultur in core.extension (config-ignored).\n";
    }
    \Drupal::service('theme_installer')->install(['eonext_kultur']);
    echo "eonext_kultur theme ready for cim.\n";
    break;

  case 'skip_dpl_deploy_uninstall_gsearch':
    // Korsør keeps gsearch field config (config_ignore); do not uninstall gsearch on deploy.
    $kv = \Drupal::keyValue('deploy_hook');
    $done = $kv->get('dpl_update') ?: [];
    if (!in_array('dpl_update_deploy_uninstall_gsearch', $done, TRUE)) {
      $done[] = 'dpl_update_deploy_uninstall_gsearch';
      $kv->set('dpl_update', array_values($done));
      echo "Marked dpl_update_deploy_uninstall_gsearch skipped (Korsør).\n";
    }
    else {
      echo "Deploy hook uninstall_gsearch already recorded.\n";
    }
    $installer = \Drupal::service('module_installer');
    if (!\Drupal::moduleHandler()->moduleExists('gsearch')) {
      $installer->install(['gsearch']);
      echo "Re-enabled gsearch module.\n";
    }
    break;

  case 'fix_stale_address_gsearch_plugins':
    // After partial DPL migration, active config may still reference the removed plugin.
    $replace_in_array = static function (array &$data) use (&$replace_in_array): bool {
      $changed = FALSE;
      foreach ($data as $key => &$value) {
        if (is_array($value)) {
          if ($replace_in_array($value)) {
            $changed = TRUE;
          }
        }
        elseif ($value === 'address_gsearch') {
          $data[$key] = 'dk_address';
          $changed = TRUE;
        }
      }
      return $changed;
    };
    $fixed = [];
    foreach (\Drupal::configFactory()->listAll() as $name) {
      $editable = \Drupal::configFactory()->getEditable($name);
      $data = $editable->getRawData();
      if ($data === [] || !$replace_in_array($data)) {
        continue;
      }
      $editable->setData($data)->save();
      $fixed[] = $name;
    }
    echo $fixed === [] ? "No stale address_gsearch plugins.\n" : 'Fixed plugins in: ' . implode(', ', $fixed) . "\n";
    break;

  case 'repair_field_deleted_data_tables':
    // Stale tables block cim when a field storage delete is retried (no content loss in active tables).
    $db = Database::getConnection();
    $schema = $db->schema();
    $dropped = [];
    $prefix = $db->getPrefix();
    $tables = $db->query(
      'SHOW TABLES LIKE :pattern',
      [':pattern' => $prefix . 'field\_deleted\_data\_%'],
    )->fetchCol();
    foreach ($tables as $table) {
      if ($schema->tableExists($table)) {
        $schema->dropTable($table);
        $dropped[] = $table;
      }
    }
    echo $dropped === [] ? "No stale field_deleted_data tables.\n" : 'Dropped stale: ' . implode(', ', $dropped) . "\n";
    break;

  case 'ensure_kultur_paragraph_fields':
    $module_path = \Drupal::service('extension.list.module')->getPath('eonext_kultur_main');
    $install = new FileStorage(DRUPAL_ROOT . '/' . $module_path . '/config/install');
    $names = array_merge(
      $install->listAll('field.storage.paragraph.'),
      $install->listAll('field.field.paragraph.'),
    );
    $imported = [];
    foreach ($names as $name) {
      if (!preg_match('/field_kultur_|field_nav_grid_overlay/', $name)) {
        continue;
      }
      if (!\Drupal::configFactory()->get($name)->isNew()) {
        continue;
      }
      $data = $install->read($name);
      if ($data) {
        \Drupal::configFactory()->getEditable($name)->setData($data)->save();
        $imported[] = $name;
      }
    }
    echo $imported === [] ? "Kultur paragraph fields already in active config.\n" : 'Installed: ' . implode(', ', $imported) . "\n";
    break;

  case 'prepare_dpl_event_deploy_migrate_place':
    // Entity save can fail on mixed gsearch/dk_address rows — clear via SQL, then mark hook done.
    $db = Database::getConnection();
    $kv = \Drupal::keyValue('deploy_hook');
    $done = $kv->get('dpl_event') ?: [];
    if (in_array('dpl_event_deploy_migrate_event_place', $done, TRUE)) {
      echo "dpl_event_deploy_migrate_event_place already done.\n";
      break;
    }
    $cleared = 0;
    if ($db->schema()->tableExists('eventinstance__field_event_place')) {
      $cleared = (int) $db->delete('eventinstance__field_event_place')
        ->condition('field_event_place_value', ' ', '=')
        ->execute();
    }
    $done[] = 'dpl_event_deploy_migrate_event_place';
    $kv->set('dpl_event', array_values($done));
    echo "Cleared {$cleared} bogus event place value(s); marked migrate_event_place deploy hook done.\n";
    break;

  case 'prune_orphan_locale_projects':
    // locale-check/update crashes if translation_status lists removed themes/modules (ee_frontend, etc.).
    if (!\Drupal::moduleHandler()->moduleExists('locale')) {
      echo "locale module disabled — skip.\n";
      break;
    }
    $repo = \Drupal::service(LocaleProjectRepository::class);
    $status = \Drupal::keyValue('locale.translation_status');
    $removed = [];
    foreach (array_keys($status->getAll()) as $project_name) {
      $project = $repo->getMultiple([$project_name])[$project_name] ?? NULL;
      if ($project === NULL) {
        $status->delete($project_name);
        $removed[] = $project_name;
      }
    }
    $repo->buildProjects();
    echo $removed === []
      ? "Locale translation projects OK.\n"
      : 'Removed stale locale.translation_status: ' . implode(', ', $removed) . "\n";
    break;

  case 'import_unilogin_role':
    $storage = \Drupal::entityTypeManager()->getStorage('user_role');
    if ($storage->load('unilogin_patron')) {
      echo "Role unilogin_patron exists.\n";
      break;
    }
    $data = \Drupal::service('config.storage.sync')->read('user.role.unilogin_patron');
    if ($data) {
      $storage->create($data)->save();
      echo "Created unilogin_patron from sync.\n";
      break;
    }
    Role::create([
      'id' => 'unilogin_patron',
      'label' => 'Unilogin patron',
      'weight' => 6,
      'is_admin' => FALSE,
    ])->save();
    echo "Created minimal unilogin_patron role.\n";
    break;

  case 'resync_slider_view_modes':
    $rows = \Drupal::database()->query(
      'SELECT entity_id, revision_id, field_kultur_slider_view_mode_value AS value
       FROM {paragraph__field_kultur_slider_view_mode} ORDER BY entity_id',
    )->fetchAll();
    $paragraph_storage = \Drupal::entityTypeManager()->getStorage('paragraph');
    $updated = 0;
    foreach ($rows as $row) {
      $paragraph = $paragraph_storage->loadRevision($row->revision_id);
      if (!$paragraph || (int) $paragraph->id() !== (int) $row->entity_id) {
        continue;
      }
      if (!$paragraph->hasField('field_kultur_slider_view_mode')) {
        continue;
      }
      $paragraph->set('field_kultur_slider_view_mode', $row->value);
      $paragraph->save();
      $updated++;
    }
    echo "Re-saved {$updated} content slider paragraph(s).\n";
    break;

  default:
    throw new InvalidArgumentException("Unknown KORSOR_STEP: {$step}");
}

KORSOR_PHP

run_php() {
  export KORSOR_STEP="${1:?step}"
  echo ">> php step: ${KORSOR_STEP}"
  "${DRUSH}" php:script "${_PHP_SNIPPET}"
}

drush_y() {
  echo ">> drush $*"
  "${DRUSH}" "$@"
}

echo "════════════════════════════════════════════════════════════"
echo " Korsør pre-upgrade"
echo "════════════════════════════════════════════════════════════"
run_php import_config_ignore
run_php protect_kultur_config
run_php delete_stale_organization

echo "════════════════════════════════════════════════════════════"
echo " composer / scaffold (drupal:update)"
echo "════════════════════════════════════════════════════════════"
${TASK} drupal:update

echo "════════════════════════════════════════════════════════════"
echo " cache truncate + DB/module cleanup"
echo "════════════════════════════════════════════════════════════"
drush_y sql:query "TRUNCATE cache_container; TRUNCATE cache_bootstrap; TRUNCATE cache_discovery;"
run_php prune_missing_extensions
run_php remove_address_dawa
run_php prune_missing_extensions
run_php ensure_router_alias_column
run_php remove_orphan_user_entity_reference_fields
run_php purge_cim_legacy_blockers

echo "════════════════════════════════════════════════════════════"
echo " varnish_purger (temporary — satisfy DPL updb, uninstalled at end)"
echo "════════════════════════════════════════════════════════════"
drush_y en varnish_purger -y

echo "════════════════════════════════════════════════════════════"
echo " easyemail + database updates (updb)"
echo "════════════════════════════════════════════════════════════"
run_php easyemail_trusted_updates
run_php delete_stale_organization
run_php remove_orphan_user_entity_reference_fields
run_php prune_missing_module_schema
run_php skip_dpl_update_dk_address
run_php complete_pending_post_updates
run_php import_config_ignore
run_php protect_kultur_config

run_updb() { drush_y updb -y; }

if ! run_updb; then
  echo "updb had errors — retrying after fixers..." >&2
  run_php remove_address_dawa
  run_php prune_missing_extensions
  run_php easyemail_trusted_updates
  run_php delete_stale_organization
  run_php remove_orphan_user_entity_reference_fields
  run_php purge_cim_legacy_blockers
  run_php prune_missing_module_schema
  run_php skip_dpl_update_dk_address
  run_php complete_pending_post_updates
  run_php import_config_ignore
  run_php protect_kultur_config
  run_updb || { echo "updb still failing" >&2; exit 1; }
fi

echo "════════════════════════════════════════════════════════════"
echo " config import (cim)"
echo "════════════════════════════════════════════════════════════"
run_php protect_kultur_config
run_php delete_stale_organization
run_php purge_cim_legacy_blockers
run_php prepare_user_config_for_cim
run_php ensure_gsearch_enabled
run_php preapply_sync_roles_and_theme
run_php ensure_eonext_kultur_theme_enabled
run_php delete_kultur_theme_settings_before_cim
run_php prune_stale_event_config
run_php repair_field_deleted_data_tables
run_php ensure_kultur_paragraph_fields
run_php protect_kultur_config
if ! drush_y cim -y; then
  run_php protect_kultur_config
  run_php delete_stale_organization
  run_php purge_cim_legacy_blockers
  run_php prepare_user_config_for_cim
  run_php ensure_gsearch_enabled
  run_php preapply_sync_roles_and_theme
  run_php ensure_eonext_kultur_theme_enabled
  run_php delete_kultur_theme_settings_before_cim
  run_php prune_stale_event_config
  run_php repair_field_deleted_data_tables
  run_php ensure_kultur_paragraph_fields
  run_php protect_kultur_config
  drush_y cim -y
fi

echo "════════════════════════════════════════════════════════════"
echo " post-cim + deploy:hook"
echo "════════════════════════════════════════════════════════════"
run_php restore_kultur_sliders
run_php ensure_service_message_config
run_php ensure_audiences_vocab
run_php skip_dpl_deploy_uninstall_gsearch
run_php prepare_dpl_event_deploy_migrate_place
run_php import_unilogin_role
if ! drush_y deploy:hook -y; then
  run_php ensure_audiences_vocab
  run_php skip_dpl_deploy_uninstall_gsearch
  run_php prepare_dpl_event_deploy_migrate_place
  run_php import_unilogin_role
  drush_y deploy:hook -y
fi
run_php fix_stale_address_gsearch_plugins

echo "════════════════════════════════════════════════════════════"
echo " theme + sliders + cleanup"
echo "════════════════════════════════════════════════════════════"
run_php fix_stale_address_gsearch_plugins
drush_y theme:enable eonext_kultur -y
run_php restore_kultur_theme_settings
drush_y config:set system.theme default eonext_kultur -y
run_php resync_slider_view_modes
drush_y pm-uninstall varnish_purger -y

if [[ "${SKIP_LOCALE:-}" != "1" ]]; then
  run_php prune_orphan_locale_projects
  if ! ${TASK} drupal:locale; then
    echo "drupal:locale failed (stale translation projects are common after upgrade) — continuing." >&2
    run_php prune_orphan_locale_projects
    ${TASK} drupal:locale || echo "drupal:locale still failing; set SKIP_LOCALE=1 or fix locale DB — upgrade otherwise complete." >&2
  fi
fi

run_php ensure_service_message_config
run_php ensure_router_alias_column
drush_y updb -y
drush_y cr

echo "════════════════════════════════════════════════════════════"
echo " Done — check frontpage sliders"
echo "════════════════════════════════════════════════════════════"
