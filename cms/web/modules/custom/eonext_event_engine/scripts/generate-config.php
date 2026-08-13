<?php

declare(strict_types=1);

$sourceDir = dirname(__DIR__, 5) . '/config/sync';
$targetDir = dirname(__DIR__) . '/config/sync';

if (!is_dir($targetDir) && !mkdir($targetDir, 0755, TRUE)) {
  throw new RuntimeException("Could not create $targetDir");
}

$fields = [
  'field_description',
  'field_event_location_type',
  'field_branch',
  'field_event_non_branch_location',
  'field_event_location',
  'field_event_address_gsearch',
  'field_event_place',
  'field_event_all_day',
  'field_event_image',
  'field_event_state',
  'field_audiences',
  'field_categories',
  'field_tags',
  'field_event_paragraphs',
  'field_teaser_text',
  'field_teaser_image',
  'field_relevant_ticket_manager',
  'field_event_link',
  'field_ticket_capacity',
  'field_ticket_categories',
  'field_event_partners',
  'field_screen_names',
  'field_automatic_list_hide',
];

$generateUuid = static function (): string {
  $data = random_bytes(16);
  $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
  $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
};

$transform = static function (string $content) use ($generateUuid): string {
  $content = str_replace('eventseries.default.', 'organization_event.organization_event.', $content);
  $content = str_replace('eventseries', 'organization_event', $content);
  $content = str_replace(
    'recurring_events.organization_event_type.default',
    'eonext_event_engine.organization_event_type.organization_event',
    $content,
  );
  $content = str_replace(
    'recurring_events.eventseries_type.default',
    'eonext_event_engine.organization_event_type.organization_event',
    $content,
  );
  $content = preg_replace("/\n  module:\n    - recurring_events\n/", "\n", $content) ?? $content;
  $content = str_replace("    - recurring_events\n", '', $content);
  $content = str_replace('bundle: default', 'bundle: organization_event', $content);
  // Field storages must use unique UUIDs; copied eventseries UUIDs collide.
  $content = preg_replace(
    '/^uuid: .*$/m',
    'uuid: ' . $generateUuid(),
    $content,
  ) ?? $content;
  return $content;
};

foreach ($fields as $field) {
  foreach ([
    "field.storage.eventseries.$field.yml",
    "field.field.eventseries.default.$field.yml",
  ] as $file) {
    $path = "$sourceDir/$file";
    if (!file_exists($path)) {
      fwrite(STDERR, "Missing: $path\n");
      continue;
    }
    $content = $transform(file_get_contents($path));
    $newName = str_replace('eventseries', 'organization_event', basename($file));
    $newName = str_replace('.default.', '.organization_event.', $newName);
    file_put_contents("$targetDir/$newName", $content);
  }
}

file_put_contents("$targetDir/field.storage.organization_event.field_event_date.yml", <<<'YAML'
uuid: 00000000-0000-4000-8000-000000000001
langcode: en
status: true
dependencies:
  module:
    - datetime_range
    - eonext_event_engine
id: organization_event.field_event_date
field_name: field_event_date
entity_type: organization_event
type: daterange
settings:
  datetime_type: datetime
module: datetime_range
locked: false
cardinality: 1
translatable: true
indexes: {  }
persist_with_no_fields: false
custom_storage: false

YAML);

file_put_contents("$targetDir/field.field.organization_event.organization_event.field_event_date.yml", <<<'YAML'
uuid: 00000000-0000-4000-8000-000000000002
langcode: en
status: true
dependencies:
  config:
    - field.storage.organization_event.field_event_date
    - eonext_event_engine.organization_event_type.organization_event
  module:
    - datetime_range
id: organization_event.organization_event.field_event_date
field_name: field_event_date
entity_type: organization_event
bundle: organization_event
label: Dates
description: 'The start and end date/time for the event.'
required: false
translatable: false
default_value: {  }
default_value_callback: ''
settings: {  }
field_type: daterange

YAML);

// Bundle type config uses a module-prefixed filename; see
// eonext_event_engine.organization_event_type.organization_event.yml
if (file_exists("$targetDir/organization_event_type.organization_event.yml")) {
  unlink("$targetDir/organization_event_type.organization_event.yml");
}

echo 'Generated ' . count(glob("$targetDir/*.yml")) . " config files\n";
