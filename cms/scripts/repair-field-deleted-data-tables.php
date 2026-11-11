<?php

/**
 * @file
 * Drop stale field_deleted_data_* / field_deleted_revision_* tables.
 *
 * Prod DB and failed uninstalls leave tombstones that block easyemail / field purge.
 *
 * Usage: drush php:script scripts/repair-field-deleted-data-tables.php
 */

declare(strict_types=1);

use Drupal\Core\Database\Database;

$db = Database::getConnection();
$schema = $db->schema();
$dropped = [];
$prefix = $db->getPrefix();

$patterns = [
  $prefix . 'field\_deleted\_data\_%',
  $prefix . 'field\_deleted\_revision\_%',
];

foreach ($patterns as $pattern) {
  $tables = $db->query(
    'SHOW TABLES LIKE :pattern',
    [':pattern' => $pattern],
  )->fetchCol();
  foreach ($tables as $table) {
    if ($schema->tableExists($table)) {
      $schema->dropTable($table);
      $dropped[] = $table;
    }
  }
}

if ($dropped === []) {
  echo "No stale field_deleted_data / field_deleted_revision tables.\n";
}
else {
  echo 'Dropped stale field tombstone tables: ' . implode(', ', $dropped) . "\n";
}
