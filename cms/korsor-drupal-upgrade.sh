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
    ));
    sort($names);
    foreach ($names as $name) {
      \Drupal::configFactory()->getEditable($name)->delete();
    }
    echo 'Removed ' . count($names) . " organization config item(s).\n";
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
    if ($removed === []) {
      echo "No missing modules in core.extension.\n";
      break;
    }
    asort($modules);
    $extension->set('module', $modules)->save();
    echo 'Removed from core.extension: ' . implode(', ', $removed) . "\n";
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
    if ($schema < 10019) {
      \Drupal::keyValue('system.schema')->set('easyemail', 10019);
    }
    elseif ($schema >= 10019 && $schema < 10021) {
      \Drupal::keyValue('system.schema')->set('easyemail', 10021);
    }
    echo "easyemail: imported {$imported} config; schema=" . \Drupal::keyValue('system.schema')->get('easyemail') . "\n";
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

  case 'import_audiences_vocab':
    $sync = \Drupal::service('config.storage.sync');
    foreach (['taxonomy.vocabulary.audiences', 'language.content_settings.taxonomy_term.audiences'] as $name) {
      $data = $sync->read($name);
      if (!$data) {
        echo "Skip (not in sync): {$name}\n";
        continue;
      }
      \Drupal::configFactory()->getEditable($name)->setData($data)->save();
      echo "Imported: {$name}\n";
    }
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

echo "════════════════════════════════════════════════════════════"
echo " varnish_purger (temporary)"
echo "════════════════════════════════════════════════════════════"
drush_y en varnish_purger -y

echo "════════════════════════════════════════════════════════════"
echo " easyemail + database updates (updb)"
echo "════════════════════════════════════════════════════════════"
run_php easyemail_trusted_updates
run_php import_config_ignore
run_php protect_kultur_config

run_updb() { drush_y updb -y; }

if ! run_updb; then
  echo "updb had errors — retrying after fixers..." >&2
  run_php remove_address_dawa
  run_php prune_missing_extensions
  run_php easyemail_trusted_updates
  run_php import_config_ignore
  run_php protect_kultur_config
  run_updb || { echo "updb still failing" >&2; exit 1; }
fi

echo "════════════════════════════════════════════════════════════"
echo " config import (cim)"
echo "════════════════════════════════════════════════════════════"
run_php protect_kultur_config
run_php delete_stale_organization
run_php prune_stale_event_config
if ! drush_y cim -y; then
  run_php protect_kultur_config
  run_php delete_stale_organization
  run_php prune_stale_event_config
  drush_y cim -y
fi

echo "════════════════════════════════════════════════════════════"
echo " post-cim + deploy:hook"
echo "════════════════════════════════════════════════════════════"
run_php restore_kultur_sliders
run_php ensure_service_message_config
run_php import_audiences_vocab
run_php import_unilogin_role
if ! drush_y deploy:hook -y; then
  run_php import_audiences_vocab
  run_php import_unilogin_role
  drush_y deploy:hook -y
fi

echo "════════════════════════════════════════════════════════════"
echo " theme + sliders + cleanup"
echo "════════════════════════════════════════════════════════════"
drush_y config:set system.theme default eonext_kultur -y
run_php resync_slider_view_modes
drush_y pm-uninstall varnish_purger -y

if [[ "${SKIP_LOCALE:-}" != "1" ]]; then
  ${TASK} drupal:locale
fi

run_php ensure_service_message_config
drush_y cr

echo "════════════════════════════════════════════════════════════"
echo " Done — check frontpage sliders"
echo "════════════════════════════════════════════════════════════"
