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

require_once __DIR__ . '/kulturnat-config-ignore-merge.php';
/** @var array{patterns: string[], remove_from_auto: string[]} $data */
$data = require __DIR__ . '/kulturnat-config-ignore-patterns.php';
$patterns = $data['patterns'];
$remove_from_auto = $data['remove_from_auto'];

kulturnat_config_ignore_merge_patterns($patterns, $remove_from_auto);

echo 'Kulturnat kultur/lokale/editorial config ignore patterns applied (' . count($patterns) . ").\n";
