#!/usr/bin/env bash
# Builds the installable theme: dist/mcupr-<version>.zip
# (Appearance → Themes → Add New → Upload Theme, and the WordPress.org theme upload). Only the mcupr/ folder is zipped, so no
# dev files (node_modules, src, tests, package.json) end up on the site, and none of the junk a Mac or a Windows machine leaves in a
# folder (.DS_Store, __MACOSX, ._*, Thumbs.db): the WordPress.org review refuses a theme that has such a file.
# Do not zip the folder with Finder ("Compress"): it adds .DS_Store and __MACOSX itself. Use this script.
#
# Environment (for the tests, not for everyday use): PKG_ROOT = the folder that holds mcupr/ (default: the repository),
# PKG_SKIP_BUILD=1 = do not run npm / the CSS build, PKG_OUT = the folder for the zip (default: <root>/dist).
set -euo pipefail
root="${PKG_ROOT:-$(cd "$(dirname "$0")/.." && pwd)}"
here="$(cd "$(dirname "$0")" && pwd)"
cd "$root"
version="$(sed -n 's/^Version: *//p' mcupr/style.css | head -1 | tr -d '[:space:]')"
[[ -n "$version" ]] || { echo "No Version: in mcupr/style.css"; exit 1; }
if [[ -z "${PKG_SKIP_BUILD:-}" ]]; then
  [[ -d node_modules ]] || npm install --silent
  npm run build --silent
fi
outdir="${PKG_OUT:-$root/dist}"
mkdir -p "$outdir"
out="$outdir/mcupr-$version.zip"
rm -f "$out"
# '*/.*' = any hidden file or folder (.DS_Store, .git, .idea, .env ...): the WordPress.org review refuses every one of them
zip -qr "$out" mcupr \
  -x '*/.*' '*__MACOSX*' '*Thumbs.db' '*/desktop.ini' '*/ehthumbs.db' \
     '*.map' '*.bak' '*.orig' '*.rej' '*.swp' '*~' '*.log' '*/node_modules/*'
bash "$here/check-zip.sh" "$out"
echo "$out"
