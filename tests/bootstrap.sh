#!/usr/bin/env bash
# Builds a throw-away WordPress site in tests/.site (core + SQLite, no MySQL
# needed), links the theme, installs it and seeds fixture content.
#
#   bash bootstrap.sh          # create if missing
#   bash bootstrap.sh --fresh  # delete and recreate
#
# Requires: php (>=7.4, with sqlite3/gd/mbstring), composer, node/npm.
set -euo pipefail
cd "$(dirname "$0")"
SITE="$PWD/.site"
PORT="${PORT:-8080}"

if [[ "${1:-}" == "--fresh" ]]; then rm -rf "$SITE"; fi
if [[ -f "$SITE/database/wp.sqlite" ]]; then echo "Site already bootstrapped ($SITE). Use --fresh to rebuild."; exit 0; fi

rm -rf "$SITE" .composer && mkdir -p .composer
# WordPress core and the SQLite drop-in come from Packagist, so no wordpress.org access is needed.
( cd .composer && composer init -n --name=mcupr/tests -q \
  && composer require johnpbloch/wordpress-core aaemnnosttv/wp-sqlite-db --no-interaction -q )
cp -r .composer/vendor/johnpbloch/wordpress-core "$SITE"
mkdir -p "$SITE/database" "$SITE/wp-content/mu-plugins" "$SITE/wp-content/themes"
cp .composer/vendor/aaemnnosttv/wp-sqlite-db/src/db.php "$SITE/wp-content/db.php"
cp fixtures/wp-config.php "$SITE/wp-config.php"
sed -i "s#__PORT__#${PORT}#g" "$SITE/wp-config.php"
cp fixtures/seed.php "$SITE/seed.php"
cp fixtures/mu-plugins/*.php "$SITE/wp-content/mu-plugins/"
ln -s "$(cd .. && pwd)/mcupr" "$SITE/wp-content/themes/mcupr"

( cd "$SITE" && php seed.php )
echo "Bootstrapped WordPress in $SITE"
