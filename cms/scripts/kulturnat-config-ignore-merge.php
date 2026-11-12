<?php

/**
 * @file
 * Merge Kulturnat patterns into config_ignore_auto and config_ignore.settings.
 */

declare(strict_types=1);

/**
 * Adds config ignore patterns (import + export + auto).
 *
 * @param string[] $patterns
 *   Config entity names or wildcards.
 * @param string[] $remove_from_auto
 *   Patterns to drop from config_ignore_auto if present.
 */
function kulturnat_config_ignore_merge_patterns(array $patterns, array $remove_from_auto = []): void {
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
}
