<?php

/**
 * @file
 * Skip dpl_go_deploy_0001 when next_site.go already exists.
 *
 * That hook always creates the entity and fails on sites (and imported DBs)
 * that already have GO configured. Do not change dpl_go.
 */

$hook = 'dpl_go_deploy_0001_create_next_go_site_configuration';

if (!\Drupal::entityTypeManager()->hasDefinition('next_site')) {
  return;
}

$existing_site = \Drupal::entityTypeManager()
  ->getStorage('next_site')
  ->load('go');
if (!$existing_site) {
  return;
}

$kv = \Drupal::keyValue('deploy_hook');
$existing = $kv->get('existing_updates', []);
if (in_array($hook, $existing, TRUE)) {
  return;
}

$existing[] = $hook;
$kv->set('existing_updates', $existing);
print "Skipped $hook: next_site.go already exists.\n";
