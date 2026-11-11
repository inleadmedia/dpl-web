<?php

/**
 * @file
 * Korsør: make drush cim succeed without DPL Go / patron_redirect.
 *
 * Prod DB + DPL sync still contain GO and patron config; protect-korsor-no-dpl-go
 * disables those modules. This script deletes dependent active config, skips GO
 * sync on import (config_ignore), and enables bnf when missing (mediator role).
 *
 * Usage: drush php:script scripts/protect-korsor-cim-dependencies.php
 */

declare(strict_types=1);

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Extension\Exception\UnknownExtensionException;

$modules_off = ['dpl_go', 'dpl_patron_redirect'];
$roles_off = [
  'user.role.external_graphql_client',
  'user.role.go_graphql_client',
  'user.role.mobile_graphql_client',
];

$config_factory = \Drupal::configFactory();
$deleted = [];

// BNF is in sync core.extension but import is ignored — enable for mediator role.
$extension = $config_factory->getEditable('core.extension');
$modules = $extension->get('module') ?? [];
$bnf_enabled = FALSE;
if (!isset($modules['bnf'])) {
  try {
    $list = \Drupal::service('extension.list.module');
    $pathname = $list->getPathname('bnf');
    if ($pathname !== '' && is_file(DRUPAL_ROOT . '/' . $pathname)) {
      $modules['bnf'] = 0;
      asort($modules);
      $extension->set('module', $modules)->save();
      $bnf_enabled = TRUE;
    }
  }
  catch (UnknownExtensionException) {
    // No BNF on disk — mediator import may still fail; sync must be trimmed.
  }
}

foreach ($config_factory->listAll() as $name) {
  $config = $config_factory->get($name);
  $data = $config->getRawData();
  if (!is_array($data)) {
    continue;
  }
  $mod_deps = $data['dependencies']['module'] ?? [];
  if (array_intersect($modules_off, $mod_deps) === []) {
    continue;
  }
  if (!$config_factory->getEditable($name)->isNew()) {
    $config_factory->getEditable($name)->delete();
    $deleted[] = $name;
  }
}

$always_delete = ['dpl_patron_redirect.settings'];
foreach ($always_delete as $name) {
  if (!$config_factory->get($name)->isNew()) {
    $config_factory->getEditable($name)->delete();
    $deleted[] = $name;
  }
}

$sync = new FileStorage(DRUPAL_ROOT . '/../config/sync');
$ignore_add = [];
foreach ($sync->listAll() as $name) {
  $data = $sync->read($name);
  if (!is_array($data)) {
    continue;
  }
  $mod_deps = $data['dependencies']['module'] ?? [];
  $config_deps = $data['dependencies']['config'] ?? [];
  $skip = array_intersect($modules_off, $mod_deps) !== []
    || array_intersect($roles_off, $config_deps) !== [];
  if (!$skip) {
    continue;
  }
  $ignore_add[] = $name;
  if (!$config_factory->get($name)->isNew()) {
    $config_factory->getEditable($name)->delete();
    $deleted[] = $name;
  }
}

foreach ($config_factory->listAll() as $name) {
  $data = $config_factory->get($name)->getRawData();
  if (!is_array($data)) {
    continue;
  }
  $config_deps = $data['dependencies']['config'] ?? [];
  if (array_intersect($roles_off, $config_deps) === []) {
    continue;
  }
  if (!$config_factory->get($name)->isNew()) {
    $config_factory->getEditable($name)->delete();
    $deleted[] = $name;
  }
}

$ignore_patterns = [
  'next.next_entity_type_config.node.go_*',
  'next.next_entity_type_config.paragraph.go_*',
  'core.entity_view_display.node.go_*',
  'core.entity_form_display.node.go_*',
  'node.type.go_*',
  'language.content_settings.node.go_*',
  'pathauto.pattern.go_*',
  'user.role.go_graphql_client',
  'user.role.external_graphql_client',
  'user.role.mobile_graphql_client',
  'system.action.user_add_role_action.external_graphql_client',
  'system.action.user_add_role_action.go_graphql_client',
  'system.action.user_add_role_action.mobile_graphql_client',
  'system.action.user_remove_role_action.external_graphql_client',
  'system.action.user_remove_role_action.go_graphql_client',
  'system.action.user_remove_role_action.mobile_graphql_client',
  'simple_oauth.oauth2_scope.go_graphql_client',
  'simple_oauth.oauth2_scope.mobile_graphql_client',
  'dpl_patron_redirect.settings',
  'next.next_site.go',
];

$ignore_add = array_values(array_unique(array_merge($ignore_add, $ignore_patterns)));

$ignore = $config_factory->getEditable('config_ignore.settings');
$mode = $ignore->get('mode') ?: 'intermediate';
$entities = $ignore->get('ignored_config_entities') ?: [];
if ($mode !== 'intermediate' || !isset($entities['import'])) {
  $entities = [
    'import' => is_array($entities['import'] ?? $entities) ? ($entities['import'] ?? $entities) : [],
    'export' => is_array($entities['export'] ?? []) ? $entities['export'] : [],
  ];
}
foreach ($ignore_add as $pattern) {
  if (!in_array($pattern, $entities['import'], TRUE)) {
    $entities['import'][] = $pattern;
  }
}
sort($entities['import']);
$ignore->set('mode', 'intermediate')->set('ignored_config_entities', $entities)->save();

$deleted = array_values(array_unique($deleted));
sort($deleted);

$summary = 'protect-korsor-cim-dependencies:';
if ($bnf_enabled) {
  $summary .= ' enabled bnf;';
}
$summary .= ' import ignore patterns=' . count($ignore_add) . ';';
if ($deleted === []) {
  $summary .= ' no active config deleted.';
}
else {
  $preview = implode(', ', array_slice($deleted, 0, 15));
  if (count($deleted) > 15) {
    $preview .= ' (+' . (count($deleted) - 15) . ' more)';
  }
  $summary .= ' deleted active: ' . $preview . '.';
}

echo $summary . "\n";
