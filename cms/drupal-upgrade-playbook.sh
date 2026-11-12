#!/usr/bin/env bash
#
# Pre-upgrade fixes for Kulturnat DBs merged from D10 → D11 stack.
#
# Config preservation follows Korsør drupal-upgrade-playbook.sh: reset
# config_ignore from sync, merge EO kultur patterns (import + export), scrub
# GO/ghost modules, cim, then ensure kultur runtime + second updb.
#
# DDEV:
#   ddev exec bash -lc 'cd /var/www/html/cms && PUPPETEER_SKIP_DOWNLOAD=true SKIP_REACT_BUILD=true ./drupal-upgrade-playbook.sh'
#
# Staging (multisite / non-default docroot):
#   export DRUSH_URI=https://kulturnat-eonext.stg.inlead.dev
#   ./drupal-upgrade-playbook.sh
# Do not set DRUSH to "drush --uri=..." — use DRUSH_BIN + DRUSH_URI only.
#
# If Drush dies on dpl_go.go_site (eonext_translation), run:
#   php scripts/enable-dpl-go-core-extension.php
#
# Env:
#   SKIP_REACT_BUILD=true       skip React build (recommended in DDEV)
#   PLAYBOOK_PIN_DPL_UPDATE=0   do not pin dpl_update to 10081
#   SKIP_PLAYBOOK_PRE=1         skip config_ignore / dpl_update pre-step
#   SKIP_EASYEMAIL_REINSTALL=1  skip easyemail reinstall (pre-updb)
#
set -euo pipefail

CMS_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "${CMS_DIR}"

export EONEXT_MOBILESEARCH_SKIP="${EONEXT_MOBILESEARCH_SKIP:-1}"
export SKIP_REACT_BUILD="${SKIP_REACT_BUILD:-true}"
export PUPPETEER_SKIP_DOWNLOAD="${PUPPETEER_SKIP_DOWNLOAD:-true}"
export PLAYBOOK_PIN_DPL_UPDATE="${PLAYBOOK_PIN_DPL_UPDATE:-1}"

DRUSH_BIN="${DRUSH_BIN:-./vendor/bin/drush}"
TASK="task -t Taskfile.yaml"

drush_y() {
  echo ">> drush $*"
  if [[ -n "${DRUSH_URI:-}" ]]; then
    "${DRUSH_BIN}" --uri="${DRUSH_URI}" "$@"
  else
    "${DRUSH_BIN}" "$@"
  fi
}

# Prod DB: broken stacks break full bootstrap (Drush sql:* only).
php scripts/enable-dpl-go-core-extension.php
php scripts/enable-bnf-for-bnf-client-core-extension.php

echo "════════════════════════════════════════════════════════════"
echo " Playbook pre-deploy (config_ignore, pin dpl_update)"
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
echo " Enable deps for updb"
echo "════════════════════════════════════════════════════════════"
export ENABLE_MODULES="${ENABLE_MODULES:-dk_address,dpl_service_message,bnf}"
php scripts/enable-bnf-for-bnf-client-core-extension.php
drush_y php:script scripts/enable-core-extension-modules.php

echo "════════════════════════════════════════════════════════════"
echo " drupal:update + updb"
echo "════════════════════════════════════════════════════════════"
${TASK} drupal:update
# composer scaffold deletes sites/*/files/php — rebuild needs dpl_go in core.extension again.
php scripts/enable-dpl-go-core-extension.php
drush_y php:script scripts/detach-varnish-purgers-for-enable.php
drush_y en varnish_purger -y
if [ "${SKIP_EASYEMAIL_REINSTALL:-0}" != 1 ]; then
  composer install --no-cache
  drush_y php:script scripts/repair-field-deleted-data-tables.php
  drush_y php:script scripts/reinstall-easyemail-for-upgrade.php
fi
php scripts/enable-bnf-for-bnf-client-core-extension.php
drush_y updb -y
# updb can leave GO off while eonext_translation stays on — sql escape, not full bootstrap.
php scripts/enable-dpl-go-core-extension.php

echo "════════════════════════════════════════════════════════════"
echo " cim (protect Kulturnat config — same order as Korsør)"
echo "════════════════════════════════════════════════════════════"
drush_y php:script scripts/remove-address-dawa-remnants.php
drush_y php:script scripts/reset-config-ignore-from-sync.php
drush_y php:script scripts/playbook-pre-cim.php
drush_y php:script scripts/protect-kulturnat-no-dpl-go.php
drush_y php:script scripts/protect-kulturnat-cim-dependencies.php
drush_y php:script scripts/protect-kulturnat-kultur-config.php
drush_y php:script scripts/playbook-import-field-inheritance-pre-cim.php
export ENABLE_MODULES="${ENABLE_MODULES:-dpl_biblio,dk_address,dpl_service_message}"
drush_y php:script scripts/enable-core-extension-modules.php
# protect-* turns dpl_go off; eonext_translation needs it until cim finishes.
php scripts/enable-dpl-go-core-extension.php
drush_y cim -y
drush_y php:script scripts/grant-kulturnat-submission-access.php
drush_y php:script scripts/protect-kulturnat-no-dpl-go.php
drush_y php:script scripts/ensure-kulturnat-kultur-runtime.php
drush_y updb -y
drush_y cr
drush_y deploy:hook -y
drush_y pm-uninstall varnish_purger -y
${TASK} drupal:locale

echo "Done."
