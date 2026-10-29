#!/usr/bin/env bash
# Run only what you changed, on the site the last full run left behind (.site-starter): PHP suites and/or blocks of the browser test.
#   bash only.sh php visitor-counter images-bulk        – php/visitor-counter.php, php/images-bulk.php
#   bash only.sh e2e footer-stats pictures-bulk         – blocks of e2e/starter.js marked "// @only name"
#   bash only.sh php customizer-order e2e fm-scroll     – both
#   bash only.sh --list                                 – the blocks that can be run alone
# Run `bash starter.sh` once first (it builds the site). Before a merge the whole of starter.sh still has to be green.
set -uo pipefail
cd "$(dirname "$0")"
PORT="${PORT:-8090}"; SITE_DIR=".site-starter"
export BASE_URL="http://localhost:${PORT}"
if [[ "${1:-}" == "--list" || $# -eq 0 ]]; then node e2e/only.js --list; exit 0; fi
[[ -f "$SITE_DIR/wp-config.php" ]] || { echo "No test site yet: run 'bash starter.sh' once."; exit 2; }
[[ -z "${CHROMIUM_PATH:-}" && ! -d "$HOME/.cache/ms-playwright" && -x /opt/pw-browsers/chromium ]] && export CHROMIUM_PATH=/opt/pw-browsers/chromium
mode=""; php_list=(); e2e_list=()
for a in "$@"; do
  case "$a" in php|e2e) mode="$a" ;; *) [[ "$mode" == php ]] && php_list+=("$a"); [[ "$mode" == e2e ]] && e2e_list+=("$a"); [[ -z "$mode" ]] && { echo "Start with php or e2e."; exit 2; } ;; esac
done
status=0
for t in "${php_list[@]+"${php_list[@]}"}"; do
  [[ -f "php/$t.php" ]] || { echo "No php/$t.php"; status=1; continue; }
  echo "== php/$t.php"; php "php/$t.php" "$SITE_DIR" 2>&1 | grep -v Deprecated | grep -v "^PASS" || true
  php "php/$t.php" "$SITE_DIR" >/dev/null 2>&1 || status=1
done
if [[ ${#e2e_list[@]} -gt 0 ]]; then
  started=""
  if ! curl -s -o /dev/null "$BASE_URL/"; then
    PHP_CLI_SERVER_WORKERS=4 php -d upload_max_filesize=16M -d post_max_size=20M -S "localhost:${PORT}" -t "$SITE_DIR" >.server.log 2>&1 & started=$!
    for _ in $(seq 1 30); do curl -s -o /dev/null "$BASE_URL/" && break; sleep 0.5; done
  fi
  node e2e/only.js "${e2e_list[@]}" || status=1
  [[ -n "$started" ]] && kill "$started" 2>/dev/null
fi
exit $status
