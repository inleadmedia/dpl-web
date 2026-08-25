#!/bin/bash
set -e

corepack pnpm run build

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

if [ -f composer.json ]; then
  cp composer.json build/composer.json
else
  # Fallback when composer.json is not in the repo checkout (prefer committing it).
  cat > build/composer.json <<'EOF'
{
  "name": "inleadmedia/dpl-design-system",
  "type": "drupal-library"
}
EOF
fi

# dist.zip is for GitHub releases only; Composer path install uses build/ directly.
if command -v zip >/dev/null 2>&1; then
  rm -f dist.zip
  (cd build && zip -r ../dist.zip .)
else
  echo "zip not found; skipping dist.zip (not required for drupal:update)" >&2
fi
