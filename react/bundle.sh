#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

if [ -d "$SCRIPT_DIR/../design-system" ]; then
  (cd "$SCRIPT_DIR/../design-system" && VERSION="${VERSION:-}" ./bundle.sh)
fi

cd "$SCRIPT_DIR"
yarn install

DESIGN_SYSTEM_PKG="$SCRIPT_DIR/node_modules/@danskernesdigitalebibliotek/dpl-design-system"
if [ -d "$SCRIPT_DIR/../design-system/build" ] && [ -d "$DESIGN_SYSTEM_PKG" ]; then
  rm -rf "$DESIGN_SYSTEM_PKG/build"
  cp -a "$SCRIPT_DIR/../design-system/build" "$DESIGN_SYSTEM_PKG/build"
fi

yarn clean
yarn build:js:prod
yarn build:css:prod

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
