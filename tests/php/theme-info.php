<?php
/** Theme name and screenshot.   php php/theme-info.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$th = wp_get_theme( 'mcupr' );
t( 'MCUPR-S.A.T.U.R.N' === $th->get( 'Name' ), 'theme: the name shown in Appearance → Themes is MCUPR-S.A.T.U.R.N' );
t( 'mcupr' === $th->get_stylesheet() && get_stylesheet() === 'mcupr', 'theme: the folder (and so the active theme setting) is still "mcupr" – existing sites keep working' );
t( 'mcupr' === $th->get( 'TextDomain' ), 'theme: text domain unchanged' );
$shot = $th->get_screenshot();
t( is_string( $shot ) && preg_match( '/screenshot\.(png|jpg)$/', $shot ), 'theme: a screenshot is found by WordPress (' . basename( (string) $shot ) . ')' );
$f = get_theme_file_path( 'screenshot.jpg' );
$sz = is_readable( $f ) ? getimagesize( $f ) : false;
t( $sz && 1200 === $sz[0] && 900 === $sz[1], 'theme: the screenshot is 1200 × 900 (4:3, as WordPress asks)' );
t( is_readable( $f ) && filesize( $f ) < 400 * 1024, 'theme: the screenshot is small enough (' . ( is_readable( $f ) ? round( filesize( $f ) / 1024 ) : 0 ) . ' KB)' );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
