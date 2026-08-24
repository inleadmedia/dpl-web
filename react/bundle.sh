#!/bin/bash
set -e

# pnpm install walks the whole workspace, including cms pa11y → puppeteer@9
# which has no Chromium binary for linux/arm64. Skip browser downloads;
# they are not needed to bundle React/CSS. Same flags as cms/lagoon/cli.dockerfile.
export PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD="${PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD:-1}"
export CYPRESS_INSTALL_BINARY="${CYPRESS_INSTALL_BINARY:-0}"
export PUPPETEER_SKIP_DOWNLOAD="${PUPPETEER_SKIP_DOWNLOAD:-true}"
export PUPPETEER_SKIP_CHROMIUM_DOWNLOAD="${PUPPETEER_SKIP_CHROMIUM_DOWNLOAD:-true}"

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

if [ -d "$SCRIPT_DIR/../design-system" ]; then
  (cd "$SCRIPT_DIR/../design-system" && VERSION="${VERSION:-}" ./bundle.sh)
fi

cd "$SCRIPT_DIR"
corepack pnpm install

# Yarn/file installs put a separate copy under node_modules. pnpm workspace:*
# links the package at the design-system repo itself — copying then would
# rm -rf the just-built build/ via the symlink.
DESIGN_SYSTEM_PKG="$SCRIPT_DIR/node_modules/@danskernesdigitalebibliotek/dpl-design-system"
DESIGN_SYSTEM_SRC="$SCRIPT_DIR/../design-system"
if [ -d "$DESIGN_SYSTEM_SRC/build" ] && [ -d "$DESIGN_SYSTEM_PKG" ]; then
  pkg_root="$(cd "$DESIGN_SYSTEM_PKG" && pwd -P)"
  src_root="$(cd "$DESIGN_SYSTEM_SRC" && pwd -P)"
  if [ "$pkg_root" != "$src_root" ]; then
    rm -rf "$DESIGN_SYSTEM_PKG/build"
    cp -a "$DESIGN_SYSTEM_SRC/build" "$DESIGN_SYSTEM_PKG/build"
  fi
fi

corepack pnpm run clean
corepack pnpm run build:js:prod
corepack pnpm run build:css:prod

if [ -n "${VERSION:-}" ]; then
  echo "$VERSION" > ./dist/version.txt
fi

cp composer.json dist/composer.json

# dist.zip is for GitHub releases only; Composer path install uses dist/ directly.
if command -v zip >/dev/null 2>&1; then
  zip -r dist.zip dist/
else
  echo "zip not found; skipping dist.zip (not required for drupal:update)" >&2
fi

# Used by cms/Taskfile.yaml to skip rebuilds when sources are unchanged.
touch "$SCRIPT_DIR/dist/.bundle-stamp"
