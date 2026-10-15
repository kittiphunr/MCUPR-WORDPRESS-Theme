<?php
/** Test helper: php php/set-status.php <site> '<json of mcupr_status parts>'  (or "reset"). The e2e suite flips the status with it. */
$site = $argv[1];
require rtrim( $site, '/' ) . '/wp-load.php';
if ( 'reset' === $argv[2] ) {
	delete_option( 'mcupr_status' );
	echo "reset\n";
	exit( 0 );
}
$cur = mcupr_status();
foreach ( json_decode( $argv[2], true ) as $k => $v ) {
	$cur[ $k ] = array_merge( $cur[ $k ], $v );
}
if ( empty( $cur['maint']['key'] ) ) {
	$cur['maint']['key'] = wp_generate_password( 16, false );
}
update_option( 'mcupr_status', $cur, false );
echo $cur['maint']['key'] . "\n";
