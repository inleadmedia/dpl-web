#!/bin/bash
set -e

yarn install
yarn build

rm -rf ./build
mkdir ./build
mkdir ./build/js
cp -r ./public/icons ./build/icons
cp -r ./src/styles/css ./build/css
cp -r ./src/styles/fonts ./build/fonts
find ./src -name "*.js" -exec cp {} ./build/js \;

if [ -n "${VERSION:-}" ]; then
  echo "$VERSION" > ./build/version.txt
fi

cp composer.json build/composer.json

# dist.zip is for GitHub releases only; Composer path install uses build/ directly.
if command -v zip >/dev/null 2>&1; then
  rm -f dist.zip
  (cd build && zip -r ../dist.zip .)
else
  echo "zip not found; skipping dist.zip (not required for drupal:update)" >&2
fi
