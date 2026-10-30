#!/usr/bin/env bash
# The version and the build number: the files agree, and scripts/bump-version.sh steps them as VERSIONING.md says.
#   bash versioning.sh
set -uo pipefail
cd "$(dirname "$0")/.."
fails=0
t() { if [[ "$1" == ok ]]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails + 1)); fi; }
ver() { sed -n "s/^define( 'MCUPR_VERSION', '\([^']*\)' );.*/\1/p" "$1/mcupr/functions.php"; }
bld() { sed -n "s/^define( 'MCUPR_BUILD', \([0-9]*\) );.*/\1/p" "$1/mcupr/functions.php"; }
v="$(ver .)"; b="$(bld .)"
[[ "$v" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] && t ok "the version is x.y.z ($v)" || t no "the version is x.y.z ($v)"
[[ "$b" =~ ^[0-9]+$ && "$b" -gt 0 ]] && t ok "there is a build number ($b)" || t no "there is a build number ($b)"
stable() { sed -n 's/^Stable tag: //p' "$1" | head -1; }
[[ "$(sed -n 's/^Version: //p' mcupr/style.css)" == "$v" && "$(sed -n 's/^  "version": "\(.*\)",*/\1/p' package.json | head -1)" == "$v" && ( -z "$(stable mcupr/readme.txt)" || "$(stable mcupr/readme.txt)" == "$v" ) ]] && t ok "style.css, package.json and readme.txt (its Stable tag, if it has one) say the same version" || t no "style.css, package.json and readme.txt say the same version"
grep -q "^= $v =" mcupr/readme.txt && grep -q "(build $b)" mcupr/readme.txt && t ok "readme.txt has the changelog of this version with its build" || t no "readme.txt has the changelog of this version with its build"
grep -q "^## v$v (build $b)" README.md && t ok "README.md has the section of this version with its build" || t no "README.md has the section of this version with its build ('## v$v (build $b)')"
# the script, on a copy
tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT
mkdir -p "$tmp/mcupr" "$tmp/scripts"
cp mcupr/functions.php mcupr/style.css mcupr/readme.txt "$tmp/mcupr/"; cp package.json "$tmp/"; cp scripts/bump-version.sh "$tmp/scripts/"
IFS=. read -r X Y Z <<<"$v"
bash "$tmp/scripts/bump-version.sh" patch "test patch" >/dev/null 2>&1
[[ "$(ver "$tmp")" == "$X.$Y.$((Z + 1))" && "$(bld "$tmp")" == "$((b + 1))" ]] && t ok "patch: $v → $X.$Y.$((Z + 1)), build $((b + 1))" || t no "patch: got $(ver "$tmp") build $(bld "$tmp")"
bash "$tmp/scripts/bump-version.sh" minor "test minor" >/dev/null 2>&1
[[ "$(ver "$tmp")" == "$X.$((Y + 1)).0" && "$(bld "$tmp")" == "$((b + 2))" ]] && t ok "minor: the second number goes up and the last returns to 0 ($(ver "$tmp")), build $((b + 2))" || t no "minor: got $(ver "$tmp") build $(bld "$tmp")"
bash "$tmp/scripts/bump-version.sh" 3.0.0 "test said" >/dev/null 2>&1
[[ "$(ver "$tmp")" == "3.0.0" && "$(bld "$tmp")" == "$((b + 3))" ]] && t ok "a version said out loud is taken, the build still goes up" || t no "said: got $(ver "$tmp") build $(bld "$tmp")"
[[ "$(sed -n 's/^Version: //p' "$tmp/mcupr/style.css")" == "3.0.0" && ( -z "$(stable "$tmp/mcupr/readme.txt")" || "$(stable "$tmp/mcupr/readme.txt")" == "3.0.0" ) && "$(sed -n 's/^  "version": "\(.*\)",*/\1/p' "$tmp/package.json" | head -1)" == "3.0.0" ]] && t ok "the script sets every file" || t no "the script sets every file"
grep -q "^= 3.0.0 =" "$tmp/mcupr/readme.txt" && grep -q "test said (build $((b + 3)))" "$tmp/mcupr/readme.txt" && t ok "the changelog line carries the build" || t no "the changelog line carries the build"
bash "$tmp/scripts/bump-version.sh" nonsense "x" >/dev/null 2>&1 && t no "a wrong word is refused" || t ok "a wrong word is refused"
echo; [[ $fails -eq 0 ]] && echo "all passed" || echo "$fails FAILED"
exit $((fails > 0))
