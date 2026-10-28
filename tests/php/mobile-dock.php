<?php
/** The bottom bar of a phone: when it is on, what it holds, how it is switched off.   php php/mobile-dock.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$out  = static function () { ob_start(); mcupr_dock_markup(); return ob_get_clean(); };
$keep = get_theme_mod( 'mcupr_dock', null );
remove_theme_mod( 'mcupr_dock' );

t( mcupr_dock_enabled(), 'default: the bar is on' );
$h = $out();
t( 1 === substr_count( $h, 'id="m-dock"' ) && false !== strpos( $h, 'aria-label="' . esc_attr( mcupr_t( 'dock_nav' ) ) . '"' ), 'markup: one navigation landmark with a name' );
t( 1 === substr_count( $h, 'data-dock-menu' ) && false !== strpos( $h, 'aria-controls="site-nav"' ) && false !== strpos( $h, 'aria-expanded="false"' ), 'markup: the round button opens the same drawer as the header button (aria-controls, aria-expanded)' );
t( preg_match_all( '/class="dock-i"/', $h ) >= 3 && false !== strpos( $h, 'data-dock="home"' ) && false !== strpos( $h, 'data-dock="news"' ) && false !== strpos( $h, 'data-dock="search"' ), 'markup: home, news and search are beside the round button' );
$pos = array( strpos( $h, 'data-dock="home"' ), strpos( $h, 'data-dock-menu' ), strpos( $h, 'data-dock="search"' ) );
t( $pos[0] < $pos[1] && $pos[1] < $pos[2], 'markup: the round button sits between the places' );
t( false === strpos( $h, 'aria-current' ) || 1 === substr_count( $h, 'aria-current="page"' ), 'markup: at most one place is marked as the current one' );

foreach ( array( 'th', 'en', 'zh', 'ja' ) as $l ) {
	foreach ( array( 'dock_news', 'dock_events', 'dock_nav' ) as $k ) { $ok = isset( ( require MCUPR_DIR . '/inc/strings.php' )[ $k ] ) && '' !== mcupr_t( $k, $l ) && $k !== mcupr_t( $k, $l ); if ( ! $ok ) { t( false, "language $l: $k has a text" ); } }
}
t( true, 'languages: every label of the bar has a text in Thai, English, Chinese and Japanese' );

add_filter( 'mcupr_dock_items', static function ( $i ) { $i[] = array( 'key' => 'x', 'label' => 'A <b>"x"</b>', 'url' => 'https://example.org/?a=1&b=2', 'icon' => 'star', 'current' => true ); return $i; } );
$h2 = $out();
t( false !== strpos( $h2, 'data-dock="x"' ) && false === strpos( $h2, '<b>"x"' ) && false !== strpos( $h2, 'a=1&#038;b=2' ), 'filter: a place can be added; its text and address are escaped' );
remove_all_filters( 'mcupr_dock_items' );

set_theme_mod( 'mcupr_dock', false );
t( ! mcupr_dock_enabled() && '' === $out(), 'off: the Customizer switch removes the bar completely' );
$attr = apply_filters( 'language_attributes', 'lang="th"' );
t( false === strpos( $attr, 'data-dock' ), 'off: the page is not marked, so it keeps no room for the bar' );
set_theme_mod( 'mcupr_dock', true );
t( false !== strpos( apply_filters( 'language_attributes', 'lang="th"' ), 'data-dock="1"' ), 'on: the page is marked so the stylesheet keeps room for the bar' );

require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
$GLOBALS['wp_customize'] = new WP_Customize_Manager();
do_action( 'customize_register', $GLOBALS['wp_customize'] );
$c = $GLOBALS['wp_customize']->get_control( 'mcupr_dock' );
t( $c && 'mcupr_display' === $c->section && 'checkbox' === $c->type, 'customizer: a checkbox in the display section' );

$css = preg_replace( '/\s+/', '', (string) file_get_contents( get_theme_file_path( 'assets/css/tailwind.css' ) ) );
foreach ( array( '.m-dock', '.dock-main', '.dock-i', '--dock-h', 'html[data-dock]body{padding-bottom' ) as $needle ) {
	t( false !== strpos( $css, str_replace( ' ', '', $needle ) ), "css: $needle is in the stylesheet" );
}


// the home hero of a phone: a picture in the corner and a search bar with a choice of what to search in
$sc = mcupr_hero_search_scopes();
t( isset( $sc[''] ) && isset( $sc['post'] ) && isset( $sc['page'] ) && array_key_first( $sc ) === '', 'search scopes: everything first, then news and pages' );
ob_start(); mcupr_hero_search(); $hs = ob_get_clean();
t( 1 === substr_count( $hs, 'role="search"' ) && 1 === substr_count( $hs, 'name="s"' ) && false !== strpos( $hs, 'type="search"' ), 'hero search: one search form with a search field' );
t( preg_match( '/<label for="(hs-\d+)" class="sr-only">/', $hs, $m ) && false !== strpos( $hs, 'id="' . $m[1] . '"' ), 'hero search: the field has a label (hidden, for screen readers)' );
t( false !== strpos( $hs, 'data-hs-filter' ) && false !== strpos( $hs, 'aria-haspopup="true"' ) && false !== strpos( $hs, 'aria-expanded="false"' ) && preg_match( '/aria-controls="(hs-\d+-scope)"/', $hs, $m2 ) && false !== strpos( $hs, 'id="' . $m2[1] . '"' ), 'hero search: the filter button names the panel it opens' );
t( substr_count( $hs, 'name="post_type"' ) === count( $sc ) && 1 === substr_count( $hs, ' checked' ) && false !== strpos( $hs, '<fieldset class="hs-scope"' ) && false !== strpos( $hs, '<legend>' ), 'hero search: the choices are radio buttons in a fieldset with a legend, "everything" is on' );
$_GET['lang'] = 'en'; ob_start(); mcupr_hero_search(); $hl = ob_get_clean(); unset( $_GET['lang'] );
t( false !== strpos( $hl, 'name="lang"' ), 'hero search: the chosen language is kept' );
$av = mcupr_hero_avatar();
t( false !== strpos( $av, 'class="hero-avatar"' ) && false !== strpos( $av, 'aria-hidden="true"' ) && false !== strpos( $av, '<img' ), 'hero avatar: a round picture, decoration only (hidden from screen readers)' );
foreach ( array( 'hero_search_ph', 'scope_t', 'scope_all', 'scope_pages' ) as $k ) { foreach ( array( 'th', 'en', 'zh', 'ja' ) as $l ) { if ( mcupr_t( $k, $l ) === $k || '' === mcupr_t( $k, $l ) ) { t( false, "language $l: $k" ); } } }
t( true, 'languages: the texts of the hero search are in all four languages' );
$hero = (string) file_get_contents( get_theme_file_path( 'template-parts/hero.php' ) );
t( false !== strpos( $hero, 'mcupr_hero_search()' ) && strpos( $hero, 'mcupr_hero_search()' ) < strpos( $hero, 'hero-quick' ), 'hero template: the search bar is above the quick links' );
$css2 = preg_replace( '/\s+/', '', (string) file_get_contents( get_theme_file_path( 'assets/css/tailwind.css' ) ) );
foreach ( array( '.hero-avatar', '.hs-filter', '.hs-scope', '.hero.hero-search' ) as $needle ) { t( false !== strpos( $css2, $needle ), "css: $needle is in the stylesheet" ); }

null === $keep ? remove_theme_mod( 'mcupr_dock' ) : set_theme_mod( 'mcupr_dock', $keep );
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
