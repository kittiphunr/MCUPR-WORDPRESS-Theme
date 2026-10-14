#!/usr/bin/env bash
# Builds the installable theme: dist/mcupr-<version>.zip
# (Appearance → Themes → Add New → Upload Theme). Only the mcupr/ folder is zipped,
# so no dev files (node_modules, src, tests, package.json) end up on the site.
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$root"
version="$(sed -n 's/^Version: *//p' mcupr/style.css | head -1 | tr -d '[:space:]')"
[[ -n "$version" ]] || { echo "No Version: in mcupr/style.css"; exit 1; }
[[ -d node_modules ]] || npm install --silent
npm run build --silent
mkdir -p dist
out="$root/dist/mcupr-$version.zip"
rm -f "$out"
(cd "$root" && zip -qr "$out" mcupr -x '*.DS_Store' '*.map' '*/.git*' '*/node_modules/*')
echo "$out"
