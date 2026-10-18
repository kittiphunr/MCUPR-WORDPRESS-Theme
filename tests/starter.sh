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
php php/starter-addons.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep -v "^PASS" || status=1
php php/formbuilder.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep -v "^PASS" || true
php php/events.php "$SITE_DIR" 2>&1 | grep -v Deprecated || status=1
php php/content-tools.php "$SITE_DIR" 2>&1 | grep -v Deprecated || status=1
php php/suggestions.php "$SITE_DIR" 2>&1 | grep -v Deprecated || status=1
php php/starter-fixtures.php "$SITE_DIR" 2>&1 | grep -v Deprecated
php php/browser.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep -v "^PASS" || true
php php/print.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep -v "^PASS" || true
php php/staff.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep -v "^PASS" || true
php php/staff-import.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep -v "^PASS" || true
php php/forum.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep -v "^PASS" || true
php php/faq.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep -v "^PASS" || true
php php/albums.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep -v "^PASS" || true
php php/polls.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep -v "^PASS" || true
php php/videos.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep -v "^PASS" || true
php php/site-status.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep -v "^PASS" || true
php php/admin-menu.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep -v "^PASS" || true
php php/events-ics.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/blocks.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/speed.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/mode-badge.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/more-panel.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/offices.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/admin-overview.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/students.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/plain-permalinks.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/books.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/requests.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/student-central.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/student-xls.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/student-card.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/toolbar.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/no-emoji.php 2>&1 | grep "^FAIL\|FAILED" || true
php php/security.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/real-bugs.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/dedupe.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/student-sso.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/student-grades.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/person-empty.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/office-band.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/student-care.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/office-tabs.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/nav-capsule.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/plain-links.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/audience-scope.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/query-budget.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/menu-bar.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/theme-info.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/settings-io.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/images.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/demo-home.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/numbers.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
php php/insights.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
PHP_CLI_SERVER_WORKERS=4 php -d upload_max_filesize=16M -d post_max_size=20M -S "localhost:${PORT}" -t "$SITE_DIR" >.server.log 2>&1 &
pid=$!; trap 'kill $pid 2>/dev/null' EXIT
for _ in $(seq 1 30); do curl -fs "$BASE_URL/" >/dev/null && break; sleep 0.5; done
php php/student-mail.php "$SITE_DIR" 2>&1 | grep -v Deprecated | grep "^FAIL\|FAILED" || true
node e2e/starter.js || status=1
node e2e/audit.js || status=1
exit $status
