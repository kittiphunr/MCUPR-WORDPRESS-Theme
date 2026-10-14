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
echo "== Theme package is clean"
bash scripts/package.sh >/dev/null
unzip -l dist/mcupr-*.zip | grep -E "node_modules|src/|package.*json|\.map$|tests/" && { echo "dev files leaked into the zip"; exit 1; } || true
echo "OK"
