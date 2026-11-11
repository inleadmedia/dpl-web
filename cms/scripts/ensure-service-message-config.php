<?php

/**
 * @file
 * Bootstrap service_message bundle/fields when missing from Korsør config sync.
 *
 * updb enables dpl_service_message; sync may not yet ship the bundle YAML.
 *
 * Usage: drush php:script scripts/ensure-service-message-config.php
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;

if (!\Drupal::moduleHandler()->moduleExists('dpl_service_message')) {
  echo "Skip: dpl_service_message not enabled.\n";
  return;
}

if (FieldStorageConfig::loadByName('node', 'field_svcmsg_placement')) {
  echo "Service message bundle/fields OK.\n";
  return;
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

$ensure_field = static function (
  string $field_name,
  string $type,
  array $storage_settings = [],
  array $field_settings = [],
): void {
  if (!FieldStorageConfig::loadByName('node', $field_name)) {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => $type,
      'settings' => $storage_settings,
      'cardinality' => $field_name === 'field_svcmsg_branches' ? -1 : 1,
    ])->save();
    echo "Created field storage {$field_name}.\n";
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
    echo "Created field {$field_name} on service_message.\n";
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
$ensure_field(
  'field_svcmsg_branches',
  'entity_reference',
  ['target_type' => 'node'],
  [
    'handler' => 'default:node',
    'handler_settings' => ['target_bundles' => ['branch' => 'branch']],
  ],
);

echo "Ensured service_message field storage/config.\n";
