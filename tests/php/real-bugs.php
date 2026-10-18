<?php
/** Bugs found in the code review: cookies on old PHP, office news paging with Plain permalinks, scripts old browsers cannot parse, contrast of the hero in the high-contrast modes.   php php/real-bugs.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$root = realpath( __DIR__ . '/../../mcupr' );

// raw setcookie() with an options array (PHP 7.3+) anywhere but the compat helper
$raw = array();
foreach ( glob( $root . '/inc/*.php' ) as $f ) {
	if ( 'compat.php' !== basename( $f ) && preg_match( '/\bsetcookie\s*\([^;]*\barray\s*\(/', file_get_contents( $f ) ) ) {
		$raw[] = basename( $f );
	}
}
t( ! $raw, 'cookies: no setcookie() with an options array outside mcupr_set_cookie()' . ( $raw ? ' (' . implode( ', ', $raw ) . ')' : '' ) );
t( 5 === ( new ReflectionFunction( 'mcupr_set_cookie' ) )->getNumberOfParameters(), 'cookies: the helper takes a path (the site may live in a folder)' );

// regex lookbehind stops a whole script from loading on Safari before 16.4 and on old TV browsers
$lb = array();
foreach ( glob( $root . '/assets/js/*.js' ) as $f ) {
	if ( preg_match( '/\(\?<[=!]/', file_get_contents( $f ) ) ) {
		$lb[] = basename( $f );
	}
}
t( ! $lb, 'scripts: no regex lookbehind in the theme scripts' . ( $lb ? ' (' . implode( ', ', $lb ) . ')' : '' ) );

// office news paging in Plain permalinks
add_filter( 'pre_option_permalink_structure', '__return_empty_string' );
$o = wp_insert_post( array( 'post_type' => 'mcupr_office', 'post_status' => 'publish', 'post_title' => 'Pager office' ) );
$h = mcupr_office_pager( $o, 'news', 2, 5 );
t( false !== strpos( $h, 'mcupr_opage=3' ) && false === strpos( $h, '/page/' ) && false === strpos( $h, 'mcupr_opage=1' ), 'office news: Plain permalinks page with ?mcupr_opage= (page 1 without it)' );
t( false !== strpos( $h, 'mcupr_osec=news' ) && false === strpos( $h, 'news/page' ), 'office news: the section stays in the address' );
remove_all_filters( 'pre_option_permalink_structure' );
wp_delete_post( $o, true );

// the hero without a picture: every contrast mode gives it a second colour that keeps the text readable
$css = file_get_contents( __DIR__ . '/../../src/input.css' );
foreach ( array( 'wb', 'yb', 'by' ) as $m ) {
	t( (bool) preg_match( '/:root\[data-contrast="' . $m . '"\] \{[^}]*--brand-2:/', $css ), "contrast $m: the hero's second colour is set" );
}
t( false !== strpos( $css, 'html[data-contrast] .opt input:checked + .opt-b' ) && false !== strpos( $css, 'html[data-contrast] .poll-o:has(input:checked)' ), 'contrast: a chosen tile or poll answer is drawn stronger than an unchosen one' );

echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
