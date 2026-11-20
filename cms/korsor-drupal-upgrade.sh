#!/usr/bin/env bash
#
# Korsør: `task drupal:upgrade` on a restored prod DB, keeping Event Engine & co.
#
#   ddev exec bash -lc 'cd /var/www/html/cms && SKIP_REACT_BUILD=true ./korsor-drupal-upgrade.sh'
#
set -euo pipefail
cd "$(dirname "$0")"
DRUSH=./vendor/bin/drush

# dpl_patron_redirect now requires dpl_go (never enabled here) and breaks bootstrap.
php scripts/prune-dpl-go-modules-sql.php
"${DRUSH}" sql:query "TRUNCATE cache_container; TRUNCATE cache_bootstrap; TRUNCATE cache_discovery; TRUNCATE cache_config;"
"${DRUSH}" php:script scripts/korsor-pre-upgrade.php

task -t Taskfile.yaml drupal:upgrade

"${DRUSH}" php:script scripts/korsor-post-upgrade.php
