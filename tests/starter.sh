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
failed_suites=(); suites_run=0
# suite <show> <command…>: runs one PHP suite and shows what it prints (show: all, nopass, or a grep pattern for the lines to show);
# a suite that exits with an error, or prints a FAIL / FAILED / Fatal error line, is remembered and listed at the end – and fails the run.
suite() {
	local show="$1" out rc; shift
	out=$("$@" 2>&1); rc=$?
	out=$(printf '%s\n' "$out" | grep -v Deprecated)
	case "$show" in
		all) printf '%s\n' "$out" ;;
		nopass) printf '%s\n' "$out" | grep -v '^PASS' ;;
		*) printf '%s\n' "$out" | grep "$show" ;;
	esac
	suites_run=$((suites_run + 1))
	if [[ $rc -ne 0 ]] || printf '%s\n' "$out" | grep -q '^FAIL\|FAILED\|Fatal error'; then failed_suites+=("$2"); fi
	return 0
}
summary() {
	echo
	if [[ ${#failed_suites[@]} -gt 0 ]]; then echo "FAILED PHP suites (${#failed_suites[@]} of $suites_run): ${failed_suites[*]}"; else echo "PHP suites: all $suites_run passed"; fi
}
suite all php php/starter-import.php "$SITE_DIR"
suite nopass php php/starter-addons.php "$SITE_DIR"
# the demo pack is checked on the site as the importer left it (the tests below add and remove pages, forums and files of their own)
suite '^FAIL\|FAILED' php php/demo-links.php "$SITE_DIR"
suite nopass php php/formbuilder.php "$SITE_DIR"
suite all php php/events.php "$SITE_DIR"
suite all php php/content-tools.php "$SITE_DIR"
suite all php php/suggestions.php "$SITE_DIR"
suite all php php/starter-fixtures.php "$SITE_DIR"
suite nopass php php/browser.php "$SITE_DIR"
suite nopass php php/print.php "$SITE_DIR"
suite nopass php php/staff.php "$SITE_DIR"
suite nopass php php/staff-import.php "$SITE_DIR"
suite nopass php php/forum.php "$SITE_DIR"
suite nopass php php/faq.php "$SITE_DIR"
suite nopass php php/albums.php "$SITE_DIR"
suite nopass php php/polls.php "$SITE_DIR"
suite nopass php php/videos.php "$SITE_DIR"
suite nopass php php/site-status.php "$SITE_DIR"
suite nopass php php/admin-menu.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/events-ics.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/blocks.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/speed.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/mode-badge.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/more-panel.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/offices.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/admin-overview.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/students.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/plain-permalinks.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/books.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/requests.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/student-central.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/student-xls.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/student-card.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/toolbar.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/personas.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/empty-note.php "$SITE_DIR"
suite '^FAIL\|FAILED\|Warning\|Fatal' php php/languages.php "$SITE_DIR"
suite '^FAIL\|FAILED\|Warning\|Fatal' php php/php-security.php "$SITE_DIR"
suite '^FAIL\|FAILED\|Warning\|Fatal' php php/elementor.php "$SITE_DIR"
suite '^FAIL\|FAILED\|Warning\|Fatal' php php/elementor-real.php "$SITE_DIR"
suite '^FAIL\|FAILED\|Warning\|Fatal' php php/cookie-center.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/no-emoji.php
suite '^FAIL\|FAILED' php php/security.php "$SITE_DIR"
suite '^FAIL\|FAILED\|Fatal' php php/data-hardening.php "$SITE_DIR"
suite '^FAIL\|FAILED\|Fatal' php php/roles.php "$SITE_DIR"
suite '^FAIL\|FAILED\|Fatal' php php/tab-title.php "$SITE_DIR"
suite '^FAIL\|FAILED\|Fatal' php php/status-lang-tabs.php "$SITE_DIR"
suite '^FAIL\|FAILED\|Fatal' php php/starter-data.php
suite '^FAIL\|FAILED\|Fatal' php php/starter-overwrite.php "$SITE_DIR"
suite '^FAIL\|FAILED\|Fatal' php php/starter-overwrite-mixed.php "$SITE_DIR"
suite '^FAIL\|FAILED\|Fatal' php php/seo-data.php "$SITE_DIR"
suite '^FAIL\|FAILED\|Fatal' php php/post-media.php "$SITE_DIR"
suite '^FAIL\|FAILED\|Fatal' php php/notices.php "$SITE_DIR"
suite '^FAIL\|FAILED\|Fatal' php php/notice-fields.php "$SITE_DIR"
suite '^FAIL\|FAILED\|Fatal' php php/google-embed.php "$SITE_DIR"
suite '^FAIL\|FAILED\|Fatal' php php/preloader.php "$SITE_DIR"
suite '^FAIL\|FAILED\|Fatal' php php/mobile-dock.php "$SITE_DIR"
suite '^FAIL\|FAILED\|Fatal' php php/search-page.php "$SITE_DIR"
suite '^FAIL\|FAILED' bash package-zip.sh
suite '^FAIL\|FAILED\|Fatal' php php/customizer-order.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/low-risk.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/groups-import.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/real-bugs.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/dedupe.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/speed-i18n.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/office-groups.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/student-sso.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/student-grades.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/person-empty.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/office-band.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/student-care.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/office-tabs.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/nav-capsule.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/plain-links.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/audience-scope.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/query-budget.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/menu-bar.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/theme-info.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/settings-io.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/images.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/images-bulk.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/visitor-counter.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/footer-layout.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/icon-picker.php "$SITE_DIR"
suite '^FAIL\|FAILED' bash versioning.sh
suite '^FAIL\|FAILED' php php/demo-home.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/numbers.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/insights.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/login.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/social-login.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/media-folders.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/file-manager.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/pager.php "$SITE_DIR"
suite '^FAIL\|FAILED' php php/office-news.php "$SITE_DIR"
PHP_CLI_SERVER_WORKERS=4 php -d upload_max_filesize=16M -d post_max_size=20M -S "localhost:${PORT}" -t "$SITE_DIR" >.server.log 2>&1 &
pid=$!; trap 'kill $pid 2>/dev/null' EXIT
for _ in $(seq 1 30); do curl -fs "$BASE_URL/" >/dev/null && break; sleep 0.5; done
suite '^FAIL\|FAILED' php php/student-mail.php "$SITE_DIR"
summary; [[ ${#failed_suites[@]} -eq 0 ]] || status=1
node e2e/starter.js || status=1
node e2e/audit.js || status=1
# Safari's engine (WebKitGTK through WebDriver) when it is installed: apt-get install webkit2gtk-driver xvfb && pip install selenium
if command -v WebKitWebDriver >/dev/null && command -v xvfb-run >/dev/null && python3 -c 'import selenium' 2>/dev/null; then xvfb-run -a python3 webkit/smoke.py "$BASE_URL" || status=1; else echo 'SKIP webkit smoke test (WebKitWebDriver, xvfb or selenium missing)'; fi
summary
exit $status
