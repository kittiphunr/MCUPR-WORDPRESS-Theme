#!/usr/bin/env bash
# Fast static checks (no WordPress needed): PHP lint, JS syntax, Tailwind build up to date.
set -euo pipefail
cd "$(dirname "$0")/../mcupr"
echo "== PHP lint"; find . -path ./node_modules -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l | grep -v '^No syntax errors' || true
bad=$(find . -path ./node_modules -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l 2>&1 | grep -c '^PHP Parse error\|^Parse error' || true)
[[ "$bad" == "0" ]] || { echo "PHP syntax errors found"; exit 1; }
echo "== JS syntax"; node --check assets/js/main.js
echo "== Tailwind build up to date"
[[ -d node_modules ]] || npm install --silent
cp assets/css/tailwind.css /tmp/tailwind.before.css
npm run build --silent
if ! cmp -s assets/css/tailwind.css /tmp/tailwind.before.css; then echo "assets/css/tailwind.css is stale: run 'npm run build' in mcupr/ and commit"; exit 1; fi
echo "OK"
