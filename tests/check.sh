#!/usr/bin/env bash
# Fast static checks (no WordPress needed): PHP lint, JS syntax, Tailwind build up to date.
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$root/mcupr"
echo "== PHP lint"; find . -name '*.php' -print0 | xargs -0 -n1 php -l | grep -v '^No syntax errors' || true
bad=$(find . -name '*.php' -print0 | xargs -0 -n1 php -l 2>&1 | grep -c '^PHP Parse error\|^Parse error' || true)
[[ "$bad" == "0" ]] || { echo "PHP syntax errors found"; exit 1; }
echo "== JS syntax"; node --check assets/js/main.js; node --check assets/js/listen.js
echo "== Tailwind build up to date"
cd "$root"
[[ -d node_modules ]] || npm install --silent
cp mcupr/assets/css/tailwind.css /tmp/tailwind.before.css
npm run build --silent
if ! cmp -s mcupr/assets/css/tailwind.css /tmp/tailwind.before.css; then echo "mcupr/assets/css/tailwind.css is stale: run 'npm run build' and commit"; exit 1; fi
echo "== Minimum PHP / WordPress versions agree (style.css, readme.txt, functions.php)"
php_style=$(sed -n 's/^Requires PHP: *//p' mcupr/style.css | tr -d '[:space:]')
php_readme=$(sed -n 's/^Requires PHP: *//p' mcupr/readme.txt | tr -d '[:space:]')
php_code=$(sed -n "s/.*MCUPR_MIN_PHP', '\([0-9.]*\)'.*/\1/p" mcupr/functions.php)
wp_style=$(sed -n 's/^Requires at least: *//p' mcupr/style.css | tr -d '[:space:]')
wp_code=$(sed -n "s/.*MCUPR_MIN_WP', '\([0-9.]*\)'.*/\1/p" mcupr/functions.php)
[[ "$php_style" == "$php_readme" && "$php_style" == "$php_code" && "$wp_style" == "$wp_code" ]] || { echo "version requirements differ: PHP $php_style/$php_readme/$php_code, WP $wp_style/$wp_code"; exit 1; }
echo "   PHP >= $php_style, WordPress >= $wp_style"
echo "== Theme version agrees (style.css, functions.php, package.json, readme.txt)"
v_style=$(sed -n 's/^Version: *//p' mcupr/style.css | tr -d '[:space:]')
v_code=$(sed -n "s/.*MCUPR_VERSION', '\([0-9.]*\)'.*/\1/p" mcupr/functions.php)
v_pkg=$(sed -n 's/^  "version": "\([0-9.]*\)".*/\1/p' package.json)
v_log=$(sed -n 's/^= \([0-9.]*\) =$/\1/p' mcupr/readme.txt | head -1)
[[ "$v_style" == "$v_code" && "$v_style" == "$v_pkg" && "$v_style" == "$v_log" ]] || { echo "versions differ: style $v_style, code $v_code, package.json $v_pkg, changelog $v_log (use scripts/bump-version.sh)"; exit 1; }
echo "   $v_style"
echo "== PHP-version compatibility (optional: needs phpcs + PHPCompatibility on PATH)"
if command -v phpcs >/dev/null 2>&1 && phpcs -i | grep -q PHPCompatibility; then
  phpcs --standard=PHPCompatibility --runtime-set testVersion "${php_style}-" --extensions=php --report=summary mcupr
else echo "   skipped"; fi
echo "== Every admin / Customizer string has a Thai translation (scripts/admin_th.py -> inc/admin-th-strings.php)"
cp mcupr/inc/admin-th-strings.php /tmp/admin-th.before.php
python3 scripts/make-admin-th.py >/dev/null
cmp -s mcupr/inc/admin-th-strings.php /tmp/admin-th.before.php || { echo "admin-th-strings.php is stale: run 'python3 scripts/make-admin-th.py' and commit"; exit 1; }
missing=$(php -r 'define("ABSPATH",1); $d = include "mcupr/inc/admin-th-strings.php"; $m = array(); foreach ( explode( "\n", trim( shell_exec( "php scripts/extract-strings.php" ) ) ) as $l ) { $k = json_decode( $l ); if ( $k !== null && ! isset( $d[ $k ] ) ) { $m[] = $k; } } echo implode( "\n", $m );')
[[ -z "$missing" ]] || { echo "No Thai translation for:"; echo "$missing"; exit 1; }
echo "== Theme package is clean"
bash scripts/package.sh >/dev/null
unzip -l dist/mcupr-*.zip | grep -E "node_modules|src/|package.*json|\.map$|tests/" && { echo "dev files leaked into the zip"; exit 1; } || true
echo "OK"
