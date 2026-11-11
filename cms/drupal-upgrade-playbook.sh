#!/usr/bin/env bash
#
# Slagbib-style drupal:upgrade wrapper (keeps Event Engine / org stack).
#
# Fixes ghost address_dawa on old DBs, then runs task drupal:upgrade.
#
# Korsør: eonext_event_engine must exist on disk (Composer VCS + auth.json) or
# drush will fail on the next ghost module after address_dawa is fixed.
#
# DDEV:
#   ddev exec bash -lc 'cd /var/www/html/cms && SKIP_REACT_BUILD=true ./drupal-upgrade-playbook.sh'
#
# Staging (from cms/):
#   SKIP_REACT_BUILD=true ./drupal-upgrade-playbook.sh
#
# Env:
#   SKIP_REACT_BUILD=true     skip React build (recommended in DDEV)
#   PLAYBOOK_PIN_DPL_UPDATE=0 do not pin dpl_update to 10081
#   SKIP_PLAYBOOK_PRE=1       skip config_ignore / eonext_staff pre-step
#   SKIP_EASYEMAIL_REINSTALL=1 skip destructive easyemail reinstall (pre-updb)
#
# If Drush dies on dpl_patron_redirect / dpl_go.go_site (prod DB, no full bootstrap):
#   php scripts/enable-dpl-go-core-extension.php
# Then re-run the playbook. After cim, protect-korsor-no-dpl-go keeps GO modules off.
#
set -euo pipefail

CMS_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "${CMS_DIR}"

export EONEXT_MOBILESEARCH_SKIP="${EONEXT_MOBILESEARCH_SKIP:-1}"
export SKIP_REACT_BUILD="${SKIP_REACT_BUILD:-true}"
export PLAYBOOK_PIN_DPL_UPDATE="${PLAYBOOK_PIN_DPL_UPDATE:-1}"

DRUSH="${DRUSH:-./vendor/bin/drush}"
TASK="task -t Taskfile.yaml"

drush_y() {
  echo ">> drush $*"
  "${DRUSH}" "$@"
}

echo "════════════════════════════════════════════════════════════"
echo " Playbook pre-deploy (config_ignore, eonext_staff, dpl_update)"
echo "════════════════════════════════════════════════════════════"
if [ "${SKIP_PLAYBOOK_PRE:-0}" != 1 ]; then
  export PLAYBOOK_PIN_DPL_UPDATE
  drush_y php:script scripts/playbook-pre-deploy.php
else
  echo "SKIP_PLAYBOOK_PRE=1 — skipping."
fi

echo "════════════════════════════════════════════════════════════"
echo " Old DB: address_dawa + missing extensions"
echo "════════════════════════════════════════════════════════════"
drush_y php:script scripts/remove-address-dawa-remnants.php
drush_y php:script scripts/prune-missing-core-extensions.php
drush_y php:script scripts/ensure-router-alias-column.php

echo "════════════════════════════════════════════════════════════"
echo " Enable deps for updb (geofield / Event Engine fields)"
echo "════════════════════════════════════════════════════════════"
export ENABLE_MODULES="${ENABLE_MODULES:-geofield}"
drush_y php:script scripts/enable-core-extension-modules.php

echo "════════════════════════════════════════════════════════════"
echo " drupal:update + updb + cim (config_ignore reset between updb and cim)"
echo "════════════════════════════════════════════════════════════"
${TASK} drupal:update
drush_y en varnish_purger -y
echo "════════════════════════════════════════════════════════════"
echo " easyemail reinstall (owned overlay + paragraph field schema)"
echo "════════════════════════════════════════════════════════════"
drush_y php:script scripts/reinstall-easyemail-for-upgrade.php
drush_y updb -y
# updb can leave patron_redirect on without dpl_go — sql escape, not full bootstrap.
php scripts/enable-dpl-go-core-extension.php
drush_y php:script scripts/reset-config-ignore-from-sync.php
drush_y php:script scripts/playbook-pre-cim.php
drush_y php:script scripts/protect-korsor-no-dpl-go.php
drush_y php:script scripts/protect-korsor-kultur-config.php
drush_y php:script scripts/protect-korsor-event-engine-config.php
drush_y php:script scripts/repair-korsor-workflow-config.php
drush_y cim -y
drush_y php:script scripts/protect-korsor-no-dpl-go.php
drush_y php:script scripts/ensure-service-message-config.php
drush_y php:script scripts/ensure-unilogin-patron-role.php
drush_y php:script scripts/skip-korsor-gsearch-deploy-hook.php
drush_y deploy:hook -y
drush_y pm-uninstall varnish_purger -y
${TASK} drupal:locale

echo "Done."
