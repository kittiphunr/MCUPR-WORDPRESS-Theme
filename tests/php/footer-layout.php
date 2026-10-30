<?php
/** Where the parts of the footer sit: the first arrangement, moving a part, hiding, wrong values, empty columns.   php php/footer-layout.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$all = array( 'contact', 'map', 'links', 'stats' );
foreach ( $all as $b ) { remove_theme_mod( 'mcupr_footer_col_' . $b ); }
t( array( array( 'contact' ), array( 'map', 'links' ), array( 'stats' ) ) === mcupr_footer_layout( $all ), 'the first arrangement balances the heights: contact | map and links | statistics' );
t( array( array( 'contact' ), array( 'map', 'links' ) ) === mcupr_footer_layout( array( 'contact', 'map', 'links' ) ), 'a part with nothing to show (statistics off) is left out, and so is its column' );
t( array( array( 'contact' ), array( 'links' ), array( 'stats' ) ) === mcupr_footer_layout( array( 'contact', 'links', 'stats' ) ), 'without the map the links take the middle column alone' );
t( array( array( 'contact' ), array( 'stats' ) ) === mcupr_footer_layout( array( 'contact', 'stats' ) ), 'a column with nothing in it is left out (no map, no links: two columns)' );
t( array( array( 'contact' ) ) === mcupr_footer_layout( array( 'contact' ) ), 'only the contact details: one column' );
set_theme_mod( 'mcupr_footer_col_stats', 1 );
t( array( array( 'contact', 'stats' ), array( 'map', 'links' ) ) === mcupr_footer_layout( $all ), 'statistics moved to column 1: under the contact details, and the empty third column is left out' );
set_theme_mod( 'mcupr_footer_col_map', 3 ); set_theme_mod( 'mcupr_footer_col_links', 3 );
t( array( array( 'contact', 'stats' ), array( 'map', 'links' ) ) === mcupr_footer_layout( $all ), 'parts in one column keep the order contact, map, links, statistics; column 2 is empty so there are two columns' );
set_theme_mod( 'mcupr_footer_col_contact', 0 );
t( array( array( 'stats' ), array( 'map', 'links' ) ) === mcupr_footer_layout( $all ), 'the contact details can be hidden' );
set_theme_mod( 'mcupr_footer_col_map', 0 );
t( 2 === mcupr_footer_col( 'map' ), 'the map cannot be "hidden" this way (it has its own switch): a wrong value gives its place' );
set_theme_mod( 'mcupr_footer_col_stats', 9 ); t( 3 === mcupr_footer_col( 'stats' ), 'a column that does not exist gives the first place of that part' );
foreach ( $all as $b ) { remove_theme_mod( 'mcupr_footer_col_' . $b ); }
// the footer as the page prints it
ob_start();
foreach ( mcupr_footer_layout( array( 'contact', 'links', 'stats' ) ) as $col ) { foreach ( $col as $b ) { mcupr_footer_block( $b, array( array( home_url( '/x/' ), 'privacy' ) ) ); } }
$h = ob_get_clean();
t( false !== strpos( $h, 'flinks' ) && false !== strpos( $h, 'fstat' ) && false !== strpos( $h, home_url( '/x/' ) ), 'the parts print: contact, the links with their address, the statistics card' );
// the Customizer has the four choices, in the footer section
require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
$m = new WP_Customize_Manager(); $GLOBALS['wp_customize'] = $m; do_action( 'customize_register', $m );
$ok = true; foreach ( $all as $b ) { $c = $m->get_control( 'mcupr_footer_col_' . $b ); if ( ! $c || 'mcupr_footer' !== $c->section ) { $ok = false; } }
t( $ok, 'the four choices are in the footer section of the Customizer' );
t( isset( $m->get_control( 'mcupr_footer_col_contact' )->choices[0] ) && ! isset( $m->get_control( 'mcupr_footer_col_map' )->choices[0] ), '"Hide" is offered for the contact details and the links, not for the map and the statistics' );
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
