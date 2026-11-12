<?php

/**
 * @file
 * Restore anonymous/authenticated submit access after config import.
 *
 * drush cim imports DPL user.role.anonymous without Kulturnat permissions, so
 * the main-menu link to /kultur/register-event disappears (route access check).
 *
 * Usage: drush php:script scripts/grant-kulturnat-submission-access.php
 */

declare(strict_types=1);

if (!\Drupal::moduleHandler()->moduleExists('eonext_kultur_event_submissions')) {
  echo "eonext_kultur_event_submissions is not enabled — skip.\n";
  return;
}

\Drupal::moduleHandler()->loadInclude('eonext_kultur_event_submissions', 'install');
if (!function_exists('eonext_kultur_event_submissions_reapply_eonext_437_fixes')) {
  echo "reapply helper missing — skip.\n";
  return;
}

echo eonext_kultur_event_submissions_reapply_eonext_437_fixes() . "\n";
