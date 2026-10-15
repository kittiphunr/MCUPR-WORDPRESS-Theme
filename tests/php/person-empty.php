<?php
/** A person without a biography: the page says so, and the office band is not covered.   php php/person-empty.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$css = file_get_contents( get_theme_file_path( 'assets/css/tailwind.css' ) );
t( preg_match( '/#content>\.office-chrome~article>\.mcu-wheel:first-child\{[^}]*margin-top:0/', $css ), 'css: the page band is not pulled up under the office header' );
t( false !== strpos( $css, '.profile-none' ) && false !== strpos( $css, '.profile-mates' ), 'css: the empty-profile note and the "more people" grid are styled' );
foreach ( array( 'st_empty_t', 'st_empty', 'st_mates' ) as $k ) {
	$ok = true;
	foreach ( array( 'th', 'en', 'zh', 'ja' ) as $l ) {
		$ok = $ok && '' !== trim( (string) mcupr_t( $k, $l ) );
	}
	t( $ok, 'strings: ' . $k . ' in the four languages' );
}
$src = file_get_contents( get_theme_file_path( 'single-mcupr_person.php' ) );
t( false !== strpos( $src, "mcupr_office_url( \$oid, 'people' )" ), 'person page: "all staff" goes back to the office people list inside an office' );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
