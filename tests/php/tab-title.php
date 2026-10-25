<?php
/** The title on the browser tab: what the site can choose, what reaches the script, and when the script is not loaded.   php php/tab-title.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }

$keep = array();
foreach ( array( 'mcupr_tab_mode', 'mcupr_tab_speed', 'mcupr_tab_width' ) as $k ) {
	$keep[ $k ] = get_theme_mod( $k, null );
	remove_theme_mod( $k );
}

// 1. the defaults: type it, scroll it when it is too long, at a speed a person can read
$o = mcupr_tab_title_opts();
t( 'scroll' === $o['mode'] && 'normal' === $o['speed'] && 24 === $o['width'], 'defaults: type and scroll, normal speed, 24 letters to a tab' );
t( $o['type'] > 0 && $o['step'] > $o['type'] && $o['rest'] > 0, 'defaults: the scroll is slower than the typing, and rests between them' );

// 2. what a site can choose is cleaned
set_theme_mod( 'mcupr_tab_mode', 'nonsense' );
set_theme_mod( 'mcupr_tab_speed', 'warp' );
set_theme_mod( 'mcupr_tab_width', 9999 );
$o = mcupr_tab_title_opts();
t( 'scroll' === $o['mode'] && 'normal' === $o['speed'] && 60 === $o['width'], 'choices: an unknown mode or speed falls back, and the width is held at 60' );
set_theme_mod( 'mcupr_tab_width', -5 );
t( 8 === mcupr_tab_title_opts()['width'], 'choices: a width below the smallest the script works with is raised to 8' );
set_theme_mod( 'mcupr_tab_width', '30abc' );
t( 30 === mcupr_tab_title_opts()['width'], 'choices: a width typed with other characters is read as its number' );
foreach ( array( 'slow', 'normal', 'fast' ) as $sp ) {
	set_theme_mod( 'mcupr_tab_speed', $sp );
	$o = mcupr_tab_title_opts();
	t( $sp === $o['speed'] && array( $o['type'], $o['step'] ) === mcupr_tab_speeds()[ $sp ], 'speed: "' . $sp . '" gives its own pair of delays' );
}
set_theme_mod( 'mcupr_tab_speed', 'slow' );
$slow = mcupr_tab_title_opts();
set_theme_mod( 'mcupr_tab_speed', 'fast' );
$fast = mcupr_tab_title_opts();
t( $slow['type'] > $fast['type'] && $slow['step'] > $fast['step'], 'speed: slow really is slower than fast, for typing and for scrolling' );

// 3. the Customizer offers the three choices, in the section the other effects are in
require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
$GLOBALS['wp_customize'] = new WP_Customize_Manager();
do_action( 'customize_register', $GLOBALS['wp_customize'] );
$cz = $GLOBALS['wp_customize'];
$in = true;
foreach ( array( 'mcupr_tab_mode', 'mcupr_tab_speed', 'mcupr_tab_width' ) as $id ) {
	$c  = $cz->get_control( $id );
	$in = $in && $c && 'mcupr_display' === $c->section && $cz->get_setting( $id );
}
t( $in, 'customizer: all three controls are there, in the Animation section' );
$choices = $cz->get_control( 'mcupr_tab_mode' ) ? $cz->get_control( 'mcupr_tab_mode' )->choices : array();
t( array( 'scroll', 'type', 'off' ) === array_keys( $choices ), 'customizer: the mode offers type-and-scroll, only type, and none' );

// 4. the script is loaded when the effect is on, with its settings, and not when it is off
$loaded = static function () {
	wp_dequeue_script( 'mcupr-tab-title' );
	wp_deregister_script( 'mcupr-tab-title' );
	do_action( 'wp_enqueue_scripts' );
	return wp_script_is( 'mcupr-tab-title', 'enqueued' );
};
set_theme_mod( 'mcupr_tab_mode', 'scroll' );
set_theme_mod( 'mcupr_tab_speed', 'normal' );
set_theme_mod( 'mcupr_tab_width', 20 );
t( $loaded(), 'loading: with the effect on, the script is on the page' );
// WordPress hands numbers over as text ("width":"20"); the script reads them with parseInt, so compare them as numbers
$data = (string) wp_scripts()->get_data( 'mcupr-tab-title', 'data' );
$got  = preg_match( '/var mcuprTab = (\{.*\});/s', $data, $m ) ? json_decode( $m[1], true ) : array();
t( is_array( $got ) && 'scroll' === ( $got['mode'] ?? '' ) && 20 === (int) ( $got['width'] ?? 0 ) && 90 === (int) ( $got['type'] ?? 0 ) && 220 === (int) ( $got['step'] ?? 0 ), 'loading: it is handed the site\'s choices (mode, width, and the two delays)' );
set_theme_mod( 'mcupr_tab_mode', 'type' );
t( $loaded() && false !== strpos( (string) wp_scripts()->get_data( 'mcupr-tab-title', 'data' ), '"mode":"type"' ), 'loading: "only type" reaches the script as it is' );
set_theme_mod( 'mcupr_tab_mode', 'off' );
t( ! $loaded(), 'loading: with the effect off, the script is not on the page at all' );

// 5. the script file is there and speaks the contract the page gives it
$js = (string) file_get_contents( get_theme_file_path( 'assets/js/tab-title.js' ) );
t( false !== strpos( $js, 'window.mcuprTab' ) && false !== strpos( $js, 'mcuprFullTitle' ), 'script: it reads the settings the theme hands over and keeps the whole title' );
t( false !== strpos( $js, 'prefers-reduced-motion' ) && false !== strpos( $js, 'data-motion' ), 'script: it stands down for the browser setting and for the theme\'s own switch' );
t( false !== strpos( $js, 'beforeprint' ) && false !== strpos( $js, 'visibilitychange' ) && false !== strpos( $js, 'pagehide' ), 'script: it puts the whole title back before printing, in the background, and on leaving' );
// whatever else reads the title for a file name or a heading must use the whole one
$readers = '';
foreach ( array( 'print-doc.js', 'pdf-export.js' ) as $f ) {
	$src      = (string) file_get_contents( get_theme_file_path( 'assets/js/' . $f ) );
	$readers .= ( false !== strpos( $src, 'mcuprFullTitle' ) ? '' : $f . ' ' );
}
t( '' === $readers, 'readers: the print page and the PDF export take the whole title, never a half-typed one' . ( '' !== $readers ? ' (not: ' . $readers . ')' : '' ) );
$all = '';
foreach ( glob( get_theme_file_path( 'assets/js/*.js' ) ) as $f ) {
	if ( preg_match( '/document\.title\s*=[^=]/', (string) file_get_contents( $f ) ) && 'tab-title.js' !== basename( $f ) ) {
		$all .= basename( $f ) . ' ';
	}
}
t( '' === $all, 'readers: nothing else in the theme writes the title and would fight the effect' . ( '' !== $all ? ' (' . $all . ')' : '' ) );

foreach ( $keep as $k => $v ) {
	null === $v ? remove_theme_mod( $k ) : set_theme_mod( $k, $v );
}
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
