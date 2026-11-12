<?php

/**
 * @file
 * Keep Korsør EO Kultur / Lokale / branch views during drush cim (not in DPL sync).
 *
 * Writes patterns to config_ignore_auto and config_ignore (import + export).
 * Run after reset-config-ignore-from-sync.php and before cim.
 *
 * Usage: drush php:script scripts/protect-korsor-kultur-config.php
 */

declare(strict_types=1);

$patterns = [
  'node.type.lokale',
  'language.content_settings.node.lokale',
  'core.base_field_override.node.lokale.*',
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
  'core.entity_view_mode.node.box',
  'core.entity_view_mode.node.row',
  'core.entity_view_mode.eventinstance.hero_slide',
  'core.entity_view_mode.eventseries.hero_slide',
  'core.entity_view_display.node.*.hero_slide',
  'core.entity_view_display.node.page.box',
  'core.entity_view_display.node.page.hero_slide',
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
  'field.field.paragraph.*.field_locale_*',
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
  'block.block.eonext_kultur_*',
  'system.theme',
  'field.storage.*.field_address_gsearch',
  'field.storage.*.field_event_address_gsearch',
  'field.field.*.field_address_gsearch',
  'field.field.*.field_event_address_gsearch',
  'gsearch.settings',
  // Instance fields inherited from series (taglines, tags, categories, etc.).
  'field_inheritance.field_inheritance.eventinstance_default_*',
  'field_inheritance.config',
  // Kultur calendar facets (not in DPL sync except event_categories).
  'facets.facet.event_tags',
  'facets.facet.event_screen_names',
  'facets.facet.event_is_free',
  'eonext_translation.settings',
  'eonext_translation.footer',
  'views.view.entity_reference_content',
  'core.entity_form_display.node.branch.default',
  'core.entity_view_display.node.branch.*',
];

// Broad patterns break DPL cim; do not add field.storage.node.field_* etc.
$remove_from_auto = [
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
$list = array_values(array_filter($list, static fn ($item): bool => is_string($item) && !in_array($item, $remove_from_auto, TRUE)));
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
  $entities[$direction] = array_values(array_filter(
    $entities[$direction] ?? [],
    static fn ($item): bool => is_string($item) && !in_array($item, $remove_from_auto, TRUE),
  ));
  foreach ($patterns as $pattern) {
    if (!in_array($pattern, $entities[$direction], TRUE)) {
      $entities[$direction][] = $pattern;
    }
  }
  sort($entities[$direction]);
}
$ignore->set('mode', 'intermediate')->set('ignored_config_entities', $entities)->save();

echo 'Korsør kultur/lokale config ignore patterns applied (' . count($patterns) . ").\n";
