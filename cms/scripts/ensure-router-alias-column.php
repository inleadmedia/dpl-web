<?php

/**
 * @file
 * Add router.alias when upgrading D10 DB dumps on D11 core (before drush en / cr).
 *
 * Usage: drush php:script scripts/ensure-router-alias-column.php
 */

declare(strict_types=1);

use Drupal\Core\Database\Database;

$schema = Database::getConnection()->schema();
if ($schema->tableExists('router') && !$schema->fieldExists('router', 'alias')) {
  $field = [
    'description' => 'The alias of the route, if applicable.',
    'type' => 'varchar_ascii',
    'length' => 255,
  ];
  $schema->addField('router', 'alias', $field);
  $schema->addIndex('router', 'alias', ['alias'], [
    'fields' => ['alias' => $field],
  ]);
  echo "Added router.alias column (system_update_11201).\n";
}
else {
  echo "router.alias column OK.\n";
}
