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

zip -r dist.zip dist/
