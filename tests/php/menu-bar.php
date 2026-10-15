<?php
/** Menu layouts: the default and the one-capsule "bar" mode.   php php/menu-bar.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$keep = get_theme_mod( 'mcupr_menu_layout', null );
foreach ( array( 'top' => 'top', 'bar' => 'bar', 'side' => 'side', 'hybrid' => 'side', 'evil' => 'top', '' => 'top' ) as $in => $out ) {
	set_theme_mod( 'mcupr_menu_layout', $in );
	t( $out === mcupr_menu_default(), "default: '$in' gives '$out'" );
}
require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
global $wp_customize; $wp_customize = new WP_Customize_Manager(); do_action( 'customize_register', $wp_customize );
$st = $wp_customize->get_setting( 'mcupr_menu_layout' );
t( 'bar' === $st->sanitize( 'bar' ) && 'top' === $st->sanitize( 'x' ) && 'side' === $st->sanitize( 'hybrid' ), 'customizer: the setting accepts bar, keeps the old hybrid as side, refuses anything else' );
t( array( 'top', 'bar', 'side' ) === array_keys( $wp_customize->get_control( 'mcupr_menu_layout' )->choices ), 'customizer: three choices: top, bar, side' );
t( array_key_exists( 'layout-bar', mcupr_icon_paths() ) && '' !== mcupr_t( 'menu_bar' ) && 'menu_bar' !== mcupr_t( 'menu_bar' ), 'icon and label exist' );
foreach ( array( 'th', 'en', 'zh', 'ja' ) as $l ) { t( 'menu_bar' !== mcupr_t( 'menu_bar', $l ) && '' !== mcupr_t( 'menu_bar', $l ), "label in $l" ); }
set_theme_mod( 'mcupr_menu_layout', 'bar' );
ob_start(); mcupr_head_prefs(); $h = ob_get_clean();
t( false !== strpos( $h, "data-menubar" ) && false !== strpos( $h, "'bar'" ), 'head script: the saved choice and the site default "bar" turn into data-menu=top + data-menubar before the first paint' );
if ( null === $keep ) { remove_theme_mod( 'mcupr_menu_layout' ); } else { set_theme_mod( 'mcupr_menu_layout', $keep ); }
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
