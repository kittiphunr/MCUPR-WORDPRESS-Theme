#!/usr/bin/env bash
# Fresh clean WordPress + the real WordPress Importer, imports mcupr/starter/mcupr-starter.xml, then checks the
# result in PHP (php/starter-import.php) and in the browser (e2e/starter.js).
#   bash starter.sh
set -uo pipefail
cd "$(dirname "$0")"
PORT="${PORT:-8090}"; SITE_DIR=".site-starter"
export BASE_URL="http://localhost:${PORT}"
[[ -d node_modules ]] || npm install --silent
rm -rf "$SITE_DIR"
MCUPR_NO_FIXTURES=1 PORT="$PORT" SITE_DIR="$SITE_DIR" bash bootstrap.sh >/dev/null || exit 1
git clone -q --depth 1 https://github.com/WordPress/wordpress-importer.git "$SITE_DIR/wp-content/plugins/wordpress-importer" || exit 1
status=0
php php/starter-import.php "$SITE_DIR" 2>&1 | grep -v Deprecated || status=1
php php/events.php "$SITE_DIR" 2>&1 | grep -v Deprecated || status=1
php php/content-tools.php "$SITE_DIR" 2>&1 | grep -v Deprecated || status=1
php php/suggestions.php "$SITE_DIR" 2>&1 | grep -v Deprecated || status=1
php php/starter-fixtures.php "$SITE_DIR" 2>&1 | grep -v Deprecated
php php/browser.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep -v "^PASS" || true
php php/print.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep -v "^PASS" || true
php php/staff.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep -v "^PASS" || true
php -S "localhost:${PORT}" -t "$SITE_DIR" >.server.log 2>&1 &
pid=$!; trap 'kill $pid 2>/dev/null' EXIT
for _ in $(seq 1 30); do curl -fs "$BASE_URL/" >/dev/null && break; sleep 0.5; done
node e2e/starter.js || status=1
exit $status
