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

# Task loads .task.env for task cmds; sourcing here covers direct script runs and CI shells.
if [[ -f "$CMS_ROOT/.task.env" ]]; then
  set -a
  # shellcheck disable=SC1091
  source "$CMS_ROOT/.task.env"
  set +a
fi

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
  for composer_auth in "${COMPOSER_HOME:-$HOME/.composer}/auth.json" "$HOME/.config/composer/auth.json"; do
    if [[ -f "$composer_auth" ]]; then
      TOKEN="$(php -r '
        $data = json_decode(file_get_contents($argv[1]), true);
        echo $data["github-oauth"]["github.com"] ?? "";
      ' "$composer_auth")"
      if ! is_placeholder "$TOKEN" && [[ -n "$TOKEN" ]]; then
        break
      fi
    fi
  done
fi

if is_placeholder "$TOKEN" || [[ -z "$TOKEN" ]]; then
  echo "composer-github-auth: No GitHub token. On deploy hosts create cms/.task.env (see .task.env.example) with COMPOSER_GITHUB_TOKEN, or export it in ci_dplcms5.sh before task drupal:update." >&2
  exit 1
fi

HTTPS_PREFIX="https://${TOKEN}@github.com/"

# Idempotent: repeated deploys used --add and plain set, leaving multi-valued insteadOf keys.
clear_github_url_rewrites() {
  local scope="$1" # --global | --local
  local git_cmd=(git config "$scope")
  if [[ "$scope" == "--local" ]]; then
    git_cmd=(git -C "$REPO_ROOT" config --local)
  fi
  while IFS= read -r key; do
    [[ -n "$key" ]] || continue
    "${git_cmd[@]}" --unset-all "$key" 2>/dev/null || true
  done < <("${git_cmd[@]}" --get-regexp '^url\..*@github\.com/' 2>/dev/null | awk '{print $1}' | sort -u)
}

apply_github_url_rewrites() {
  local scope="$1"
  local git_cmd=(git config "$scope")
  if [[ "$scope" == "--local" ]]; then
    git_cmd=(git -C "$REPO_ROOT" config --local)
  fi
  clear_github_url_rewrites "$scope"
  "${git_cmd[@]}" --add url."${HTTPS_PREFIX}".insteadOf git@github.com:
  "${git_cmd[@]}" --add url."${HTTPS_PREFIX}".insteadOf ssh://git@github.com/
}

apply_github_url_rewrites --global

if git -C "$REPO_ROOT" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  apply_github_url_rewrites --local
fi

php -r '
  file_put_contents(
    $argv[1],
    json_encode(["github-oauth" => ["github.com" => $argv[2]]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
  );
' "$CMS_ROOT/auth.json" "$TOKEN"

echo "composer-github-auth: GitHub HTTPS auth configured for Composer VCS clones."
