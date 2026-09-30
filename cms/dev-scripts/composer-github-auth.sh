#!/usr/bin/env bash
# Configure Git and Composer auth for private inleadmedia/* VCS packages.
#
# Token resolution (first match wins):
#   1. COMPOSER_GITHUB_TOKEN
#   2. GITHUB_TOKEN
#   3. cms/auth.json github-oauth.github.com (if not a placeholder)
#
# Deploy targets often have no .git directory — global git config is applied too.

set -euo pipefail

CMS_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
REPO_ROOT="$(git -C "$CMS_ROOT" rev-parse --show-toplevel 2>/dev/null || dirname "$CMS_ROOT")"

is_placeholder() {
  case "$1" in
    ''|'YOUR_'*|'replace-with-'*|'ghp_REPLACE'*) return 0 ;;
    *) return 1 ;;
  esac
}

TOKEN="${COMPOSER_GITHUB_TOKEN:-${GITHUB_TOKEN:-}}"

if is_placeholder "$TOKEN" || [[ -z "$TOKEN" ]]; then
  if [[ -f "$CMS_ROOT/auth.json" ]]; then
    TOKEN="$(php -r '
      $data = json_decode(file_get_contents($argv[1]), true);
      echo $data["github-oauth"]["github.com"] ?? "";
    ' "$CMS_ROOT/auth.json")"
  fi
fi

if is_placeholder "$TOKEN" || [[ -z "$TOKEN" ]]; then
  echo "composer-github-auth: No GitHub token. Set COMPOSER_GITHUB_TOKEN in cms/.task.env, or cms/auth.json." >&2
  exit 1
fi

HTTPS_PREFIX="https://${TOKEN}@github.com/"

apply_git_rewrite() {
  # Composer lock uses git@github.com:org/repo.git (not only ssh:// URLs).
  git config "$@" url."${HTTPS_PREFIX}".insteadOf git@github.com:
  git config "$@" --add url."${HTTPS_PREFIX}".insteadOf ssh://git@github.com/
}

if git -C "$REPO_ROOT" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  apply_git_rewrite -C "$REPO_ROOT" --local
fi
apply_git_rewrite --global

php -r '
  file_put_contents(
    $argv[1],
    json_encode(["github-oauth" => ["github.com" => $argv[2]]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
  );
' "$CMS_ROOT/auth.json" "$TOKEN"

echo "composer-github-auth: GitHub HTTPS auth configured for Composer VCS clones."
