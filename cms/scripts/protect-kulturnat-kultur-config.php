<?php

/**
 * @file
 * Keep Kulturnat EO Kultur / Lokale / submissions config during drush cim.
 *
 * Same approach as Korsør protect-korsor-kultur-config.php — patterns on
 * config_ignore_auto + config_ignore (import + export). Not in DPL 430 sync.
 *
 * Usage: drush php:script scripts/protect-kulturnat-kultur-config.php
 */

declare(strict_types=1);

$patterns = [
  'node.type.lokale',
  'language.content_settings.node.lokale',
  'core.base_field_override.node.lokale.*',
  'paragraphs.paragraphs_type.eonext_kultur_*',
  'language.content_settings.paragraph.eonext_kultur_*',
  'core.base_field_override.paragraph.eonext_kultur_*',
  'field.field.node.lokale.*',
  'core.entity_form_display.node.lokale.*',
  'core.entity_view_display.node.lokale.*',
  'field.field.paragraph.content_slider.field_kultur_*',
  'field.field.paragraph.eonext_kultur_*',
  'field.field.paragraph.banner.field_kultur_banner_bg_color',
  'field.field.paragraph.nav_grid_manual.field_nav_grid_overlay_color',
  'core.entity_view_display.paragraph.content_slider.carousel',
  'core.entity_view_display.paragraph.content_slider.hero',
  'core.entity_view_mode.paragraph.carousel',
  'core.entity_view_mode.paragraph.hero',
  'core.entity_view_mode.node.hero_slide',
  'core.entity_view_mode.node.box',
  'core.entity_view_mode.node.row',
  'core.entity_view_mode.eventinstance.hero_slide',
  'core.entity_view_mode.eventinstance.box',
  'core.entity_view_mode.eventseries.hero_slide',
  'core.entity_view_mode.eventseries.box',
  'core.entity_view_display.node.*.hero_slide',
  'core.entity_view_display.node.page.box',
  'core.entity_view_display.node.page.hero_slide',
  'core.entity_view_display.node.article.hero_slide',
  'core.entity_view_display.eventinstance.default.hero_slide',
  'core.entity_view_display.eventinstance.default.box',
  'core.entity_view_display.eventseries.default.hero_slide',
  'core.entity_view_display.eventseries.default.box',
  'core.entity_form_display.paragraph.eonext_kultur_*',
  'core.entity_view_display.paragraph.eonext_kultur_*',
  'field.storage.paragraph.field_kultur_*',
  'field.field.paragraph.*.field_kultur_*',
  'field.storage.paragraph.field_nav_grid_overlay_color',
  'field.storage.paragraph.field_locale_*',
  'field.field.paragraph.*.field_locale_*',
  'field.storage.node.field_booking_*',
  'field.storage.node.field_lokale_*',
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
  'eonext_kultur.settings',
  'eonext_editorial_search.*',
  'facets.facet.editorial_*',
  'facets.facet_source.search_api__views_page__editorial_search__page',
  'rest.resource.eonext_editorial_search.*',
  'core.entity_form_display.kultur_event_submission.*',
  'core.entity_view_display.kultur_event_submission.*',
  'core.entity_form_mode.kultur_event_submission.*',
  'core.entity_view_mode.kultur_event_submission.*',
  'views.view.lokaler',
  'views.view.foreninger',
  'block.block.eonext_kultur_*',
  'system.theme',
  'field.storage.*.field_address_gsearch',
  'field.storage.*.field_event_address_gsearch',
  'field.field.*.field_address_gsearch',
  'field.field.*.field_event_address_gsearch',
  'field_inheritance.field_inheritance.eventinstance_default_event_address',
  'views.view.entity_reference_content',
  'core.entity_form_display.node.branch.default',
  'core.entity_view_display.node.branch.*',
  'field.storage.paragraph.field_kultur_banner_bg_color',
  'field.field.paragraph.banner.field_kultur_banner_bg_color',
  'gsearch.settings',
  'dk_address.settings',
  'dpl_biblio.settings',
  'field.field.node.page.field_paragraphs',
  'user.role.administrator',
  'user.role.editor',
  'user.role.local_administrator',
  'field.storage.eventseries.field_publication_date',
  'field.field.eventseries.default.field_publication_date',
  'language.content_settings.eventseries.default',
  'language.content_settings.eventinstance.default',
  'filter.format.webform_default',
];

$remove_from_auto = [
  'paragraphs.paragraphs_type.eonext_kultur',
  'field.storage.node.field_*',
  'field.storage.paragraph.field_*',
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
    'export' => is_array($entities['export'] ?? []) ? ($entities['export'] ?? []) : [],
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

echo 'Kulturnat kultur/lokale/editorial config ignore patterns applied (' . count($patterns) . ").\n";
