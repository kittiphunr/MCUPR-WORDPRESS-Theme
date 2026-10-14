#!/usr/bin/env bash
# Starts PHP's built-in server on the bootstrapped site and runs the e2e tests.
#   bash run.sh               # all suites
#   bash run.sh functional    # one suite: functional | responsive
# Env: PORT (8080), CHROMIUM_PATH (use an installed Chromium instead of Playwright's)
set -uo pipefail
cd "$(dirname "$0")"
PORT="${PORT:-8080}"
export BASE_URL="http://localhost:${PORT}"

[[ -d node_modules ]] || npm install --silent
[[ -f .site/database/wp.sqlite ]] || PORT="$PORT" bash bootstrap.sh
if [[ -z "${CHROMIUM_PATH:-}" ]]; then npx playwright install chromium >/dev/null 2>&1 || true; fi

php -S "localhost:${PORT}" -t .site >.server.log 2>&1 &
echo $! > .server.pid
trap 'kill "$(cat .server.pid)" 2>/dev/null; rm -f .server.pid' EXIT
for _ in $(seq 1 30); do curl -fs "$BASE_URL/" >/dev/null && break; sleep 0.5; done

suites=("${@:-functional responsive}")
[[ $# -eq 0 ]] && suites=(functional layout content effects responsive)
status=0
for s in "${suites[@]}"; do
  echo "=== $s ==="
  node "e2e/$s.js" || status=1
done
exit $status
