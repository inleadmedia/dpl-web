<?php

/**
 * @file
 * Drop stale field_deleted_data_* tables (prod / failed uninstall leftovers).
 *
 * Blocks field module uninstall when rename target already exists.
 *
 * Usage: drush php:script scripts/repair-field-deleted-data-tables.php
 */

declare(strict_types=1);

use Drupal\Core\Database\Database;

$db = Database::getConnection();
$schema = $db->schema();
$dropped = [];
$prefix = $db->getPrefix();
$tables = $db->query(
  'SHOW TABLES LIKE :pattern',
  [':pattern' => $prefix . 'field\_deleted\_data\_%'],
)->fetchCol();

foreach ($tables as $table) {
  if ($schema->tableExists($table)) {
    $schema->dropTable($table);
    $dropped[] = $table;
  }
}

if ($dropped === []) {
  echo "No stale field_deleted_data tables.\n";
}
else {
  echo 'Dropped stale field_deleted_data tables: ' . implode(', ', $dropped) . "\n";
}
