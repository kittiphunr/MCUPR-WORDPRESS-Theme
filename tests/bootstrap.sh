#!/usr/bin/env bash
# Builds a throw-away WordPress site in tests/.site (core + SQLite, no MySQL
# needed), links the theme, installs it and seeds fixture content.
#
#   bash bootstrap.sh          # create if missing
#   bash bootstrap.sh --fresh  # delete and recreate
#
# Test another WordPress release:  WP_VERSION=6.2.2 SITE_DIR=.site-6.2 bash bootstrap.sh
#   (then: SITE_DIR=.site-6.2 bash run.sh)
# Requires: php (>=7.4, with sqlite3/gd/mbstring), composer, node/npm.
set -euo pipefail
cd "$(dirname "$0")"
SITE="$PWD/${SITE_DIR:-.site}"
WP_REQ="johnpbloch/wordpress-core${WP_VERSION:+:$WP_VERSION}"
PORT="${PORT:-8080}"

if [[ "${1:-}" == "--fresh" ]]; then rm -rf "$SITE"; fi
if [[ -f "$SITE/database/wp.sqlite" ]]; then echo "Site already bootstrapped ($SITE). Use --fresh to rebuild."; exit 0; fi

COMPOSER_DIR=".composer${WP_VERSION:+-$WP_VERSION}"
rm -rf "$SITE" "$COMPOSER_DIR" && mkdir -p "$COMPOSER_DIR"
# WordPress core and the SQLite drop-in come from Packagist, so no wordpress.org access is needed.
( cd "$COMPOSER_DIR" && composer init -n --name=mcupr/tests -q \
  && composer require "$WP_REQ" aaemnnosttv/wp-sqlite-db --no-interaction -q )
cp -r "$COMPOSER_DIR/vendor/johnpbloch/wordpress-core" "$SITE"
mkdir -p "$SITE/database" "$SITE/wp-content/mu-plugins" "$SITE/wp-content/themes"
cp "$COMPOSER_DIR/vendor/aaemnnosttv/wp-sqlite-db/src/db.php" "$SITE/wp-content/db.php"
cp fixtures/wp-config.php "$SITE/wp-config.php"
sed -i "s#__PORT__#${PORT}#g" "$SITE/wp-config.php"
cp fixtures/seed.php "$SITE/seed.php"
cp fixtures/mu-plugins/*.php "$SITE/wp-content/mu-plugins/"
ln -s "$(cd .. && pwd)/mcupr" "$SITE/wp-content/themes/mcupr"

( cd "$SITE" && php seed.php )
echo "Bootstrapped WordPress in $SITE"
