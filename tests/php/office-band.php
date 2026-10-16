<?php
/** The office band reaches the top of the page, with the gradient and the seal.   php php/office-band.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$css = file_get_contents( get_theme_file_path( 'assets/css/tailwind.css' ) );
$pos = static function ( $needle ) use ( $css ) { return strpos( $css, $needle ); };
t( preg_match( '/\.office-chrome\{margin-top:calc\(-1 \* var\(--hdr-h,5\.5rem\)\)\}/', $css ), 'css: the office header is pulled up under the floating header' );
t( preg_match( '/\.office-chrome\[data-variant\] \.of-band\{[^}]*padding-top:calc\(var\(--hdr-h,5\.5rem\) \+ 1\.25rem\)/', $css ), 'css: the band keeps its text clear of the header' );
t( preg_match( '/\.office-chrome \.of-band:before\{[^}]*mask:var\(--seal\)/', $css ) || preg_match( '/\.office-chrome \.of-band::before\{[^}]*mask:var\(--seal\)/', $css ), 'css: the university seal is drawn on the band' );
t( preg_match( '/html\[data-contrast\] \.office-chrome \.of-band(::before|:before)\{display:none\}/', $css ), 'css: high contrast has no seal' );
t( preg_match( '/\.office-chrome\[data-variant=full\] \.of-band\{padding:1\.75rem 0 3\.4rem\}|\.office-chrome\[data-variant="full"\] \.of-band\{padding:1\.75rem 0 3\.4rem\}/', $css ) && $pos( 'padding-top:calc(var(--hdr-h,5.5rem) + 1.25rem)' ) > $pos( 'padding:1.75rem 0 3.4rem' ), 'css: the new padding comes after the old one (it wins)' );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
