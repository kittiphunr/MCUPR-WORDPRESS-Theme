#!/usr/bin/env bash
# Checks an installable theme zip before it is uploaded (WordPress.org's automated review refuses hidden files such as .DS_Store):
#   scripts/check-zip.sh dist/mcupr-1.2.3.zip
# Fails (exit 1) and says what is wrong if the zip has: a hidden file or folder, __MACOSX, an AppleDouble ._ file, Thumbs.db / desktop.ini,
# anything outside the one top folder, or lacks style.css, functions.php, readme.txt, screenshot.jpg, index.php of the theme.
set -uo pipefail
zip_file="${1:-}"
[[ -f "$zip_file" ]] || { echo "check-zip: no such file: $zip_file" >&2; exit 2; }
list="$(unzip -Z1 "$zip_file")" || { echo "check-zip: not a zip: $zip_file" >&2; exit 2; }
bad=0
say() { echo "check-zip: $1" >&2; bad=1; }
junk="$(printf '%s\n' "$list" | grep -E '(^|/)\.[^/]|__MACOSX|(^|/)\._|(^|/)(Thumbs\.db|desktop\.ini|ehthumbs\.db)$' || true)"
[[ -z "$junk" ]] || say "hidden or system files in the zip (remove them and build again):
$(printf '%s\n' "$junk" | head -10 | sed 's/^/   /')"
tops="$(printf '%s\n' "$list" | cut -d/ -f1 | sort -u)"
[[ "$(printf '%s\n' "$tops" | wc -l | tr -d ' ')" == "1" ]] || say "more than one top-level entry: $(printf '%s' "$tops" | tr '\n' ' ')"
top="$(printf '%s\n' "$tops" | head -1)"
for f in style.css functions.php index.php readme.txt screenshot.jpg; do
  printf '%s\n' "$list" | grep -qx "$top/$f" || say "missing $top/$f"
done
n="$(printf '%s\n' "$list" | grep -vc '/$' || true)"
[[ $bad -eq 0 ]] && echo "check-zip: ok ($n files, folder $top/)" >&2
exit $bad
