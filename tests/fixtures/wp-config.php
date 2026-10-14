<?php
define( 'DB_NAME', 'wp' ); define( 'DB_USER', '' ); define( 'DB_PASSWORD', '' ); define( 'DB_HOST', '' );
define( 'DB_CHARSET', 'utf8' ); define( 'DB_COLLATE', '' );
define( 'DB_DIR', __DIR__ . '/database/' ); define( 'DB_FILE', 'wp.sqlite' );
foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as $k ) { define( $k, 'test' ); }
$table_prefix = 'wp_';
define( 'WP_DEBUG', true ); define( "WP_DEBUG_DISPLAY", getenv("WP_DEBUG_DISPLAY") !== "0" ); define( 'WP_DEBUG_LOG', false );
define( 'AUTOMATIC_UPDATER_DISABLED', true ); define( 'WP_HTTP_BLOCK_EXTERNAL', true ); // no outbound calls from tests
define( 'WP_HOME', 'http://localhost:__PORT__' ); define( 'WP_SITEURL', 'http://localhost:__PORT__' );
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
require_once ABSPATH . 'wp-settings.php';
