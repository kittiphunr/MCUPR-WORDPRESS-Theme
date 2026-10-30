#!/usr/bin/env bash
# Sets the theme version and build number everywhere (style.css, functions.php, readme.txt changelog, package.json).
#   bash scripts/bump-version.sh patch "What changed"   – the usual update: 1.80.0 → 1.80.1
#   bash scripts/bump-version.sh minor "What changed"   – a big change:     1.80.1 → 1.81.0
#   bash scripts/bump-version.sh 2.0.0 "What changed"   – a version said out loud (only when asked)
# The version goes up by one step each time (see VERSIONING.md). Every run also raises the build number by one (MCUPR_BUILD in
# functions.php): it is shown after the version in the footer, the About dialog and the control center, and is written in the
# README.md heading of the release ("## v1.80.1 (build 1056) – …"), so a zip and a page can always be traced to one build.
# WordPress will not upload a theme zip with the version of an installed theme as a new one – always bump.
set -euo pipefail
cd "$(dirname "$0")/.."
how="${1:?usage: bump-version.sh patch|minor|X.Y.Z [changelog line]}"
note="${2:-Update.}"
cur="$(sed -n "s/^define( 'MCUPR_VERSION', '\([^']*\)' );.*/\1/p" mcupr/functions.php)"
build="$(sed -n "s/^define( 'MCUPR_BUILD', \([0-9]*\) );.*/\1/p" mcupr/functions.php)"
[[ "$cur" =~ ^([0-9]+)\.([0-9]+)\.([0-9]+)$ ]] || { echo "cannot read the current version from mcupr/functions.php"; exit 1; }
maj="${BASH_REMATCH[1]}"; min="${BASH_REMATCH[2]}"; pat="${BASH_REMATCH[3]}"
case "$how" in
  patch) v="$maj.$min.$((pat + 1))" ;;
  minor) v="$maj.$((min + 1)).0" ;;
  *) [[ "$how" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "say patch, minor or a version like 1.2.3"; exit 1; }; v="$how" ;;
esac
b=$(( ${build:-0} + 1 ))
sed -i "s/^Version: .*/Version: $v/" mcupr/style.css
if grep -q "^define( 'MCUPR_BUILD'" mcupr/functions.php; then
  sed -i "s/^define( 'MCUPR_BUILD', [0-9]* );.*/define( 'MCUPR_BUILD', $b );/" mcupr/functions.php
else
  sed -i "s/^\(define( 'MCUPR_VERSION', '[^']*' );.*\)$/\1\ndefine( 'MCUPR_BUILD', $b );/" mcupr/functions.php
fi
sed -i "s/define( 'MCUPR_VERSION', '[^']*' )/define( 'MCUPR_VERSION', '$v' )/" mcupr/functions.php
sed -i "s/^  \"version\": \"[^\"]*\"/  \"version\": \"$v\"/" package.json
sed -i "s/^Stable tag: .*/Stable tag: $v/" mcupr/readme.txt
sed -i "/^== Changelog ==/{n;a\\
\\
= $v =\\
* $note (build $b)
}" mcupr/readme.txt
echo "version -> $v (build $b)"
echo "Now write the README.md section:  ## v$v (build $b) – …"
