<?php

/**
 * @file
 * Drop broken workflow transitions/states before cim (WorkflowConfigTransition crash).
 *
 * When org workflows exist only on Korsør but sync updates other workflow YAML,
 * orphaned transition rows can make getFromState() null during import.
 *
 * Usage: drush php:script scripts/repair-korsor-workflow-config.php
 */

declare(strict_types=1);

$config_factory = \Drupal::configFactory();
$state_ids = [];
foreach ($config_factory->listAll('workflow.state.') as $name) {
  $data = $config_factory->get($name)->getRawData();
  if (!empty($data['id'])) {
    $state_ids[$data['id']] = TRUE;
  }
}

$removed = [];
foreach ($config_factory->listAll('workflow.transition.') as $name) {
  $config = $config_factory->get($name);
  $data = $config->getRawData();
  if ($data === []) {
    continue;
  }
  $from = $data['from_sid'] ?? '';
  $to = $data['to_sid'] ?? '';
  $valid = isset($state_ids[$from]) && isset($state_ids[$to]);
  if (!$valid) {
    $config_factory->getEditable($name)->delete();
    $removed[] = $name;
  }
}

if ($removed !== []) {
  echo 'Removed broken workflow transitions: ' . implode(', ', $removed) . "\n";
}
else {
  echo "No broken workflow transitions found.\n";
}

// Drop transition configs whose parent workflow config is missing.
$workflow_ids = [];
foreach ($config_factory->listAll('workflow.workflow.') as $name) {
  $data = $config_factory->get($name)->getRawData();
  if (!empty($data['id'])) {
    $workflow_ids[$data['id']] = TRUE;
  }
}

foreach ($config_factory->listAll('workflow.state.') as $name) {
  $data = $config_factory->get($name)->getRawData();
  $wid = $data['workflow'] ?? $data['wid'] ?? '';
  if ($wid !== '' && !isset($workflow_ids[$wid])) {
    $config_factory->getEditable($name)->delete();
    $removed[] = $name;
    echo "Removed orphaned state {$name}.\n";
  }
}
