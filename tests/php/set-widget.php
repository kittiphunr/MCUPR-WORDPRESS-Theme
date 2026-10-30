<?php
/** For the browser tests: put a text widget into a widget area, or take it out.   php php/set-widget.php <site> <sidebar> <title> <text>|--remove */
require rtrim( $argv[1], '/' ) . '/wp-load.php';
$sw = (array) get_option( 'sidebars_widgets' ); $wt = get_option( 'widget_text' ); $wt = is_array( $wt ) ? $wt : array( '_multiwidget' => 1 );
if ( '--remove' === $argv[4] ) {
	$sw[ $argv[2] ] = array_values( array_diff( isset( $sw[ $argv[2] ] ) ? (array) $sw[ $argv[2] ] : array(), array( 'text-99' ) ) );
	unset( $wt[99] );
} else {
	$wt[99] = array( 'title' => $argv[3], 'text' => $argv[4], 'filter' => false ); $sw[ $argv[2] ] = array_merge( array( 'text-99' ), array_diff( isset( $sw[ $argv[2] ] ) ? (array) $sw[ $argv[2] ] : array(), array( 'text-99' ) ) );
}
update_option( 'widget_text', $wt ); update_option( 'sidebars_widgets', $sw );
echo "ok\n";
