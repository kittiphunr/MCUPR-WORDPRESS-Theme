#!/usr/bin/env bash
# Lighthouse (mobile, simulated slow 4G) against the test site with the animations OFF by default.
#   bash perf/lighthouse.sh [path ...]      default paths: / and a news article
# Needs: npm i -g lighthouse (or LIGHTHOUSE=/path/to/lighthouse), a Chromium (CHROMIUM_PATH).
set -uo pipefail
cd "$(dirname "$0")/.."
SITE_DIR="${SITE_DIR:-.site}"; PHP_PORT="${PHP_PORT:-8282}"; FRONT_PORT="${FRONT_PORT:-8686}"
LH="${LIGHTHOUSE:-lighthouse}"
CHROME="${CHROMIUM_PATH:-$(ls /opt/pw-browsers/chromium-*/chrome-linux/chrome 2>/dev/null | head -1)}"
php -S "localhost:${PHP_PORT}" -t "$SITE_DIR" >/dev/null 2>&1 & PHP_PID=$!
node perf/serve.js "$FRONT_PORT" "$PHP_PORT" >/dev/null 2>&1 & FRONT_PID=$!
trap 'kill $PHP_PID $FRONT_PID 2>/dev/null' EXIT
sleep 2
paths=("$@"); [[ ${#paths[@]} -eq 0 ]] && paths=("/" "/%E0%B8%82%E0%B9%88%E0%B8%B2%E0%B8%A7%E0%B8%97%E0%B8%94%E0%B8%AA%E0%B8%AD%E0%B8%9A-1/")
COOKIE="mod_stub=$(node -e 'console.log(encodeURIComponent(JSON.stringify({mcupr_anim_default:false,mcupr_consent_test:1})))'); mcupr_lang=th"
mkdir -p ../.lighthouse
status=0
for p in "${paths[@]}"; do
  out="../.lighthouse/$(echo "$p" | md5sum | cut -c1-8).json"
  CHROME_PATH="$CHROME" "$LH" "http://localhost:${FRONT_PORT}${p}" --quiet --output=json --output-path="$out" \
    --chrome-flags="--headless=new --no-sandbox" \
    --only-categories=performance,accessibility,best-practices,seo \
    --extra-headers="{\"Cookie\":\"${COOKIE}\"}" >/dev/null 2>&1 || { echo "lighthouse failed for $p"; status=1; continue; }
  node -e '
    const r=require(process.argv[1]); const c=r.categories, a=r.audits;
    const f=(k)=>Math.round(c[k].score*100);
    console.log(decodeURIComponent(process.argv[2]).padEnd(24), "perf",f("performance"),"a11y",f("accessibility"),"bp",f("best-practices"),"seo",f("seo"),"| FCP",a["first-contentful-paint"].displayValue,"LCP",a["largest-contentful-paint"].displayValue,"TBT",a["total-blocking-time"].displayValue,"CLS",a["cumulative-layout-shift"].displayValue,"SI",a["speed-index"].displayValue);
    for (const k of ["performance","accessibility","best-practices","seo"]) if (c[k].score<1) for (const ref of c[k].auditRefs) { const x=a[ref.id]; if (x.score!==null && x.score<1 && ref.weight>0) console.log("   -",k,":",x.id,"("+x.title+")", x.displayValue||""); }
  ' "$out" "$p"
done
exit $status
