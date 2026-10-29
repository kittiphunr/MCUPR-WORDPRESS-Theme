#!/usr/bin/env bash
# The installable zip: nothing a Mac or a Windows machine leaves in a folder gets in (WordPress.org's review refuses a .DS_Store), nothing
# the theme needs is left out, and the check refuses a zip that Finder made.   bash package-zip.sh
set -uo pipefail
here="$(cd "$(dirname "$0")" && pwd)"
repo="$(cd "$here/.." && pwd)"
fails=0
t() { if [[ "$1" == "0" ]]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT

# 1. a folder with junk in it: the script leaves the junk out
mkdir -p "$tmp/root"; cp -R "$repo/mcupr" "$tmp/root/mcupr"
junk=( ".DS_Store" "assets/.DS_Store" "inc/.DS_Store" "__MACOSX/mcupr/x" "._style.css" "inc/._functions.php" "Thumbs.db" "assets/Thumbs.db" "desktop.ini" "inc/old.php.bak" "inc/x.orig" "assets/js/main.js.map" ".hidden-file" ".git/config" "debug.log" "inc/notes.php~" ".idea/workspace.xml" )
for j in "${junk[@]}"; do mkdir -p "$tmp/root/mcupr/$(dirname "$j")"; echo x > "$tmp/root/mcupr/$j"; done
out="$(PKG_ROOT="$tmp/root" PKG_SKIP_BUILD=1 PKG_OUT="$tmp/out" bash "$repo/scripts/package.sh" 2>&1)"; rc=$?
t "$rc" "package: the script builds a zip from a folder full of junk and its own check passes"
zip_file="$(ls "$tmp/out"/mcupr-*.zip 2>/dev/null | head -1)"
[[ -f "$zip_file" ]]; t "$?" "package: the zip is named after the version in style.css ($(basename "${zip_file:-none}"))"
list="$(unzip -Z1 "$zip_file" 2>/dev/null)"
left=""; for j in "${junk[@]}"; do printf '%s\n' "$list" | grep -qx "mcupr/$j" && left="$left $j"; done
[[ -z "$left" ]]; t "$?" "package: none of the ${#junk[@]} junk files is in the zip${left:+ (left:$left)}"
printf '%s\n' "$list" | grep -qE '(^|/)\.[^/]|__MACOSX|(^|/)\._'; [[ $? -ne 0 ]]; t "$?" "package: no hidden file or folder at all in the zip"
tracked="$(git -C "$repo" ls-files mcupr | wc -l | tr -d ' ')"
files="$(printf '%s\n' "$list" | grep -vc '/$')"
[[ "$tracked" == "$files" ]]; t "$?" "package: every file the theme has (and no other) is in the zip: $files of $tracked"
printf '%s\n' "$list" | cut -d/ -f1 | sort -u | grep -qx mcupr && [[ "$(printf '%s\n' "$list" | cut -d/ -f1 | sort -u | wc -l | tr -d ' ')" == "1" ]]; t "$?" "package: one top folder, mcupr/ (the folder name the theme keeps on every site)"

# 2. the check refuses what Finder makes, and says which file
mkdir -p "$tmp/finder"; cp -R "$repo/mcupr" "$tmp/finder/mcupr"; echo x > "$tmp/finder/mcupr/.DS_Store"; mkdir -p "$tmp/finder/__MACOSX"; echo x > "$tmp/finder/__MACOSX/._style.css"
( cd "$tmp/finder" && zip -qr "$tmp/finder.zip" mcupr __MACOSX )
msg="$(bash "$repo/scripts/check-zip.sh" "$tmp/finder.zip" 2>&1)"; rc=$?
[[ "$rc" == "1" ]]; t "$?" "check: a zip made the way Finder makes it is refused (exit $rc)"
printf '%s' "$msg" | grep -q '\.DS_Store'; t "$?" "check: the message names the .DS_Store"
printf '%s' "$msg" | grep -q '__MACOSX'; t "$?" "check: ... and the __MACOSX folder"
( cd "$tmp/root" && zip -qr "$tmp/nostyle.zip" mcupr -x 'mcupr/style.css' '*/.*' '*__MACOSX*' )
bash "$repo/scripts/check-zip.sh" "$tmp/nostyle.zip" >/dev/null 2>&1; [[ $? -eq 1 ]]; t "$?" "check: a zip without style.css is refused"
bash "$repo/scripts/check-zip.sh" "$tmp/does-not-exist.zip" >/dev/null 2>&1; [[ $? -eq 2 ]]; t "$?" "check: a file that is not there is an error, not a pass"
bash "$repo/scripts/check-zip.sh" "$zip_file" >/dev/null 2>&1; t "$?" "check: the clean zip passes"

# 3. the instructions no longer tell anybody to zip the folder by hand
grep -q 'Compress' "$repo/scripts/package.sh"; t "$?" "package.sh warns against zipping with Finder"
grep -q 'npm run package' "$repo/README.md"; t "$?" "README: tells how to build the zip"
! grep -n 'zip โฟลเดอร์นี้' "$repo/README.md" >/dev/null; t "$?" "README: no longer says to zip the folder by hand"
echo "$([[ $fails -eq 0 ]] && echo 'all passed' || echo "$fails FAILED")"
exit $fails
