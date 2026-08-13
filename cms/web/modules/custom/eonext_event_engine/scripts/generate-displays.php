<?php

declare(strict_types=1);

$targetDir = dirname(__DIR__) . '/config/sync';

$fieldConfigs = glob("$targetDir/field.field.organization_event.organization_event.*.yml");
$fieldNames = array_map(static function (string $path): string {
  return basename($path, '.yml');
}, $fieldConfigs);
$fieldNames = array_map(static function (string $name): string {
  return str_replace('field.field.organization_event.organization_event.', '', $name);
}, $fieldNames);

$dependencies = [
  'config' => array_map(static fn (string $field): string => "field.field.organization_event.organization_event.$field", $fieldNames),
  'module' => [
    'datetime_range',
    'eonext_event_engine',
    'gsearch',
    'link',
    'media_library',
    'options',
    'paragraphs',
    'select2',
  ],
];
$dependencies['config'][] = 'eonext_event_engine.organization_event_type.organization_event';
sort($dependencies['config']);

$formWidgets = [
  'title' => ['type' => 'string_textfield', 'weight' => 0],
  'field_description' => ['type' => 'string_textarea', 'weight' => 1],
  'field_automatic_list_hide' => ['type' => 'boolean_checkbox', 'weight' => 2],
  'field_event_all_day' => ['type' => 'boolean_checkbox', 'weight' => 3],
  'field_event_date' => ['type' => 'daterange_default', 'weight' => 4],
  'field_event_location_type' => ['type' => 'options_buttons', 'weight' => 5],
  'field_branch' => ['type' => 'select2_entity_reference', 'weight' => 6],
  'field_event_non_branch_location' => ['type' => 'boolean_checkbox', 'weight' => 7],
  'field_event_location' => ['type' => 'string_textfield', 'weight' => 8],
  'field_event_address_gsearch' => ['type' => 'address_gsearch', 'weight' => 9],
  'field_event_place' => ['type' => 'string_textfield', 'weight' => 10],
  'field_event_paragraphs' => ['type' => 'paragraphs', 'weight' => 11],
  'field_relevant_ticket_manager' => ['type' => 'boolean_checkbox', 'weight' => 12],
  'field_event_link' => ['type' => 'link_default', 'weight' => 13],
  'field_ticket_capacity' => ['type' => 'number', 'weight' => 14],
  'field_ticket_categories' => ['type' => 'paragraphs', 'weight' => 15],
  'field_event_partners' => ['type' => 'string_textfield', 'weight' => 16],
  'field_event_image' => ['type' => 'media_library_widget', 'weight' => 17],
  'field_event_state' => ['type' => 'select2', 'weight' => 18],
  'field_audiences' => ['type' => 'select2_entity_reference', 'weight' => 19],
  'field_teaser_text' => ['type' => 'string_textfield', 'weight' => 20],
  'field_teaser_image' => ['type' => 'media_library_widget', 'weight' => 21],
  'field_categories' => ['type' => 'select2_entity_reference', 'weight' => 22],
  'field_tags' => ['type' => 'select2_entity_reference', 'weight' => 23],
  'field_screen_names' => ['type' => 'select2_entity_reference', 'weight' => 24],
  'status' => ['type' => 'boolean_checkbox', 'weight' => 50],
  'uid' => ['type' => 'entity_reference_autocomplete', 'weight' => 51],
];

$content = [];
foreach ($formWidgets as $fieldName => $widget) {
  $content[$fieldName] = [
    'type' => $widget['type'],
    'weight' => $widget['weight'],
    'region' => 'content',
    'settings' => new stdClass(),
    'third_party_settings' => new stdClass(),
  ];
}

$formDisplay = [
  'uuid' => '00000000-0000-4000-8000-000000000020',
  'langcode' => 'en',
  'status' => TRUE,
  'dependencies' => $dependencies,
  'id' => 'organization_event.organization_event.default',
  'targetEntityType' => 'organization_event',
  'bundle' => 'organization_event',
  'mode' => 'default',
  'content' => $content,
  'hidden' => new stdClass(),
];

$viewDisplay = [
  'uuid' => '00000000-0000-4000-8000-000000000021',
  'langcode' => 'en',
  'status' => TRUE,
  'dependencies' => $dependencies,
  'id' => 'organization_event.organization_event.default',
  'targetEntityType' => 'organization_event',
  'bundle' => 'organization_event',
  'mode' => 'default',
  'content' => [],
  'hidden' => new stdClass(),
];

$weight = 0;
foreach (array_keys($formWidgets) as $fieldName) {
  if (in_array($fieldName, ['status', 'uid'], TRUE)) {
    continue;
  }
  $viewDisplay['content'][$fieldName] = [
    'type' => 'string',
    'label' => 'above',
    'weight' => $weight++,
    'region' => 'content',
    'settings' => new stdClass(),
    'third_party_settings' => new stdClass(),
  ];
}

$dump = static function (mixed $value, int $indent = 0) use (&$dump): string {
  $pad = str_repeat('  ', $indent);
  if (is_array($value)) {
    if ($value === []) {
      return "{  }\n";
    }
    $out = "\n";
    foreach ($value as $key => $item) {
      if (is_int($key)) {
        $out .= $pad . '  - ' . ltrim($dump($item, $indent + 1));
      }
      else {
        $scalar = $dump($item, $indent + 1);
        if (str_starts_with($scalar, "\n")) {
          $out .= $pad . "  $key:" . $scalar;
        }
        else {
          $out .= $pad . "  $key: " . $scalar;
        }
      }
    }
    return $out;
  }
  if ($value === TRUE) {
    return "true\n";
  }
  if ($value === FALSE) {
    return "false\n";
  }
  if ($value instanceof stdClass) {
    return "{  }\n";
  }
  if (is_string($value)) {
    return (str_contains($value, ' ') ? "'$value'" : $value) . "\n";
  }
  return (string) $value . "\n";
};

foreach ([
  'core.entity_form_display.organization_event.organization_event.default.yml' => $formDisplay,
  'core.entity_view_display.organization_event.organization_event.default.yml' => $viewDisplay,
] as $filename => $display) {
  $yaml = $dump($display);
  file_put_contents("$targetDir/$filename", $yaml);
}

echo "Generated form and view displays\n";
