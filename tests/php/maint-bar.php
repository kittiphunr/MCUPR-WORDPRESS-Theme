<?php
/** The maintenance reminder bar: who sees it, and the body class that makes room for it.   php php/maint-bar.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$keep = get_option( 'mcupr_status', null );
$set = function ( $on ) { $s = mcupr_status(); $s['maint']['on'] = $on ? 1 : 0; update_option( 'mcupr_status', $s ); };
wp_set_current_user( 0 );
$set( false );
t( ! mcupr_maint_bar_shown() && ! in_array( 'has-maint', get_body_class(), true ), 'off: no bar and no body class' );
$set( true );
t( ! mcupr_maint_bar_shown(), 'on, visitor: the bar (an editors’ reminder) is not shown to visitors' );
wp_set_current_user( 1 );
t( mcupr_maint_bar_shown() && in_array( 'has-maint', get_body_class(), true ), 'on, administrator: the bar is shown and the page gets the class that makes room for it' );
ob_start(); do_action( 'wp_body_open' ); $h = ob_get_clean();
t( false !== strpos( $h, 'class="maint-bar"' ) && false !== strpos( $h, 'role="status"' ) && false !== strpos( $h, 'mcupr-status' ), 'on, administrator: the bar is printed right after <body> with a link to the settings' );
$set( false );
ob_start(); do_action( 'wp_body_open' ); t( false === strpos( ob_get_clean(), 'maint-bar' ), 'off again: nothing printed' );
$css = file_get_contents( get_theme_file_path( 'assets/css/tailwind.css' ) );
t( preg_match( '/\.maint-bar\{[^}]*position:fixed[^}]*top:0[^}]*left:0[^}]*right:0/', $css ) && false !== strpos( $css, 'body.has-maint{padding-top:var(--maint-h' ), 'css: the bar is fixed to the top over the full width and the body makes room for it' );
if ( null === $keep ) { delete_option( 'mcupr_status' ); } else { update_option( 'mcupr_status', $keep ); }
wp_set_current_user( 0 );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
