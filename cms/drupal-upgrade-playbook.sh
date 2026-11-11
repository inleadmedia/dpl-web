#!/usr/bin/env bash
#
# Pre-upgrade fixes for Kulturnat DBs merged from D10 → D11 stack.
#
# Removes ghost address_dawa (and other missing modules) from active config,
# then runs task drupal:upgrade.
#
# DDEV:
#   ddev exec bash -lc 'cd /var/www/html/cms && PUPPETEER_SKIP_DOWNLOAD=true SKIP_REACT_BUILD=true ./drupal-upgrade-playbook.sh'
#
set -euo pipefail

CMS_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "${CMS_DIR}"

export EONEXT_MOBILESEARCH_SKIP="${EONEXT_MOBILESEARCH_SKIP:-1}"
export SKIP_REACT_BUILD="${SKIP_REACT_BUILD:-true}"
export PUPPETEER_SKIP_DOWNLOAD="${PUPPETEER_SKIP_DOWNLOAD:-true}"
export PLAYBOOK_PIN_DPL_UPDATE="${PLAYBOOK_PIN_DPL_UPDATE:-1}"

DRUSH="${DRUSH:-./vendor/bin/drush}"
TASK="task -t Taskfile.yaml"

drush_y() {
  echo ">> drush $*"
  "${DRUSH}" "$@"
}

echo "════════════════════════════════════════════════════════════"
echo " Playbook pre-deploy (pin dpl_update schema)"
echo "════════════════════════════════════════════════════════════"
if [ "${SKIP_PLAYBOOK_PRE:-0}" != 1 ]; then
  export PLAYBOOK_PIN_DPL_UPDATE
  drush_y php:script scripts/playbook-pre-deploy.php
else
  echo "SKIP_PLAYBOOK_PRE=1 - skipping."
fi

echo "════════════════════════════════════════════════════════════"
echo " Old DB: address_dawa + missing extensions"
echo "════════════════════════════════════════════════════════════"
drush_y php:script scripts/remove-address-dawa-remnants.php
drush_y php:script scripts/prune-missing-core-extensions.php
drush_y php:script scripts/ensure-router-alias-column.php

echo "════════════════════════════════════════════════════════════"
echo " Composer + easyemail reinstall (before updb)"
echo "════════════════════════════════════════════════════════════"
composer install --no-cache
drush_y php:script scripts/repair-field-deleted-data-tables.php
drush_y php:script scripts/reinstall-easyemail-for-upgrade.php

echo "════════════════════════════════════════════════════════════"
echo " drupal:update + updb"
echo "════════════════════════════════════════════════════════════"
${TASK} drupal:update
drush_y sql:query "TRUNCATE cache_container; TRUNCATE cache_bootstrap; TRUNCATE cache_discovery;"
drush_y php:script scripts/detach-varnish-purgers-for-enable.php
drush_y en varnish_purger -y
drush_y updb -y

echo "════════════════════════════════════════════════════════════"
echo " cim (protect Kulturnat config - not in DPL sync)"
echo "════════════════════════════════════════════════════════════"
drush_y php:script scripts/remove-address-dawa-remnants.php
drush_y php:script scripts/reset-config-ignore-from-sync.php
export ENABLE_MODULES="${ENABLE_MODULES:-dpl_biblio,dk_address,dpl_service_message}"
drush_y php:script scripts/enable-core-extension-modules.php
drush_y php:script scripts/protect-kulturnat-kultur-config.php
drush_y php:script scripts/protect-kulturnat-cim-dependencies.php
drush_y cim -y
drush_y 'deploy:hook' -y
drush_y pm-uninstall varnish_purger -y
${TASK} drupal:locale

echo "Done."
