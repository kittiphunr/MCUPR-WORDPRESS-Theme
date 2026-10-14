#!/usr/bin/env bash
# Sets the theme version everywhere (style.css, functions.php, readme.txt changelog, package.json).
#   bash scripts/bump-version.sh 1.1.1 "What changed"
# WordPress will not upload a theme zip with the version of an installed theme as a new one – always bump.
set -euo pipefail
cd "$(dirname "$0")/.."
v="${1:?usage: bump-version.sh X.Y.Z [changelog line]}"
note="${2:-Update.}"
[[ "$v" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "version must look like 1.2.3"; exit 1; }
sed -i "s/^Version: .*/Version: $v/" mcupr/style.css
sed -i "s/define( 'MCUPR_VERSION', '[^']*' )/define( 'MCUPR_VERSION', '$v' )/" mcupr/functions.php
sed -i "s/^  \"version\": \"[^\"]*\"/  \"version\": \"$v\"/" package.json
sed -i "s/^Stable tag: .*/Stable tag: $v/" mcupr/readme.txt
sed -i "/^== Changelog ==/{n;a\\
\\
= $v =\\
* $note
}" mcupr/readme.txt
echo "version -> $v"
