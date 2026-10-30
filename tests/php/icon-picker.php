<?php
/** The icon picker: what the script is given, the lists it dresses, the first screen of the Customizer.   php php/icon-picker.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$m = new WP_Customize_Manager(); $GLOBALS['wp_customize'] = $m; do_action( 'customize_register', $m );
$d = mcupr_icon_picker_data();
t( count( $d['paths'] ) >= 40 && isset( $d['paths']['home'] ) && 0 === strpos( $d['paths']['home'], '<' ), 'the script is given the drawing of every icon of the theme (' . count( $d['paths'] ) . ')' );
t( count( $d['controls'] ) >= 4 && ! array_diff( $d['controls'], array_keys( $m->controls() ) ), 'the Customizer icon lists to dress all exist (' . count( $d['controls'] ) . ')' );
$bad = array(); foreach ( $d['controls'] as $id ) { if ( 'select' !== $m->get_control( $id )->type ) { $bad[] = $id; } }
t( ! $bad, 'and each of them is a select (the real field stays)' );
t( '' !== $d['t']['choose'] && '' !== $d['t']['search'] && '' !== $d['t']['nothing'], 'the words of the picker are there' );
ob_start(); mcupr_menu_item_fields( 0, (object) array( 'ID' => 0, 'classes' => array() ), 0 ); $h = ob_get_clean();
t( false !== strpos( $h, 'data-mcupr-icon-picker="1"' ) && false !== strpos( $h, 'name="menu-item-mcupr-icon[0]"' ) && false !== strpos( $h, '<option value="home"' ), 'the icon list of a menu item is marked for the picker and keeps its name and options' );
// the first screen of the Customizer: every section the theme adds has its own icon
$pane = mcupr_cz_pane_data(); $icons = array();
foreach ( $pane['groups'] as $g ) { foreach ( $g['items'] as $it ) { $icons[ $it['id'] ] = $it['icon']; } }
$miss = array(); foreach ( $m->sections() as $id => $s ) { if ( 0 === strpos( $id, 'mcupr_' ) && ! $s->panel && ! isset( $icons[ $id ] ) ) { $miss[] = $id; } }
foreach ( $m->panels() as $id => $s ) { if ( 0 === strpos( $id, 'mcupr_' ) && ! isset( $icons[ $id ] ) ) { $miss[] = $id; } }
t( ! $miss, 'first screen: every section and panel of the theme is in a group with an icon, none falls to "Other"' . ( $miss ? ' (' . implode( ', ', $miss ) . ')' : '' ) );
foreach ( array( 'mcupr_footer', 'mcupr_loading', 'mcupr_cookies', 'mcupr_credit' ) as $id ) { t( isset( $icons[ $id ] ) && 'dots' !== $icons[ $id ], "first screen: $id has an icon of its own (" . ( isset( $icons[ $id ] ) ? $icons[ $id ] : 'none' ) . ')' ); }
$js = file_get_contents( __DIR__ . '/../../mcupr/assets/js/customizer-pane.js' );
$noshape = array(); foreach ( array_unique( $icons ) as $ic ) { if ( 'dots' !== $ic && false === strpos( $js, "\t\t" . $ic . ": '<" ) && false === strpos( $js, "\t\t'" . $ic . "': '<" ) ) { $noshape[] = $ic; } }
t( ! $noshape, 'first screen: the script draws every icon the list names' . ( $noshape ? ' (' . implode( ', ', $noshape ) . ')' : '' ) );
// the animated icons (Lordicon) are gone: no function, a plain icon, and the old settings are cleared once
t( ! function_exists( 'mcupr_lord_map' ) && ! function_exists( 'mcupr_lord_wrap' ) && ! file_exists( __DIR__ . '/../../mcupr/inc/lordicon.php' ) && ! is_dir( __DIR__ . '/../../mcupr/assets/vendor/lordicon' ), 'animated icons: the code and the player are gone' );
t( 0 === strpos( mcupr_icon( 'home', 20 ), '<svg' ), 'animated icons: an icon is always a plain SVG' );
set_theme_mod( 'mcupr_lord_on', true ); set_theme_mod( 'mcupr_lord_map', 'news = abc123456' ); delete_option( 'mcupr_lord_cleaned' );
do_action( 'admin_init' );
t( null === get_theme_mod( 'mcupr_lord_map', null ) && null === get_theme_mod( 'mcupr_lord_on', null ) && get_option( 'mcupr_lord_cleaned' ), 'animated icons: the saved settings are removed from the database, once' );
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
