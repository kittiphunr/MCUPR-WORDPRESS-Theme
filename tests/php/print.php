<?php
/**
 * A4 print letterhead: options, markup, per-post switch (starter site, after starter-fixtures.php).
 *   php php/print.php [site]
 */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
function mods( $a ) { foreach ( $a as $k => $v ) { null === $v ? remove_theme_mod( $k ) : set_theme_mod( $k, $v ); } }
wp_set_current_user( 0 );
$saved = array();
foreach ( array( 'mcupr_print_head_h', 'mcupr_print_foot_h', 'mcupr_print_side', 'mcupr_print_head_img', 'mcupr_print_foot_img', 'mcupr_print_bg_img', 'mcupr_print_source', 'mcupr_print_text' ) as $k ) { $saved[ $k ] = get_theme_mod( $k, null ); }

$long = get_page_by_path( 'print-long', OBJECT, 'post' );
t( (bool) $long, 'fixture: the long post exists' );
$GLOBALS['wp_query'] = new WP_Query( array( 'p' => $long->ID ) );
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
$GLOBALS['post'] = $long; setup_postdata( $long );

mods( array( 'mcupr_print_head_h' => null, 'mcupr_print_foot_h' => null, 'mcupr_print_side' => null ) );
$o = mcupr_print_options();
t( 36 === $o['head_h'] && 20 === $o['foot_h'] && 18 === $o['side'], 'defaults: 36 / 20 / 18 mm' );
mods( array( 'mcupr_print_head_h' => 999, 'mcupr_print_foot_h' => 1, 'mcupr_print_side' => 7 ) );
$o = mcupr_print_options();
t( 80 === $o['head_h'] && 8 === $o['foot_h'] && 8 === $o['side'], 'sizes are clamped to what fits an A4 sheet' );
mods( array( 'mcupr_print_head_h' => 40, 'mcupr_print_foot_h' => 25, 'mcupr_print_side' => 15 ) );
ob_start(); mcupr_print_css(); $css = ob_get_clean();
t( strpos( $css, '@page{size:A4 portrait;margin:0}' ) !== false && strpos( $css, 'padding:40mm 15mm 25mm 15mm' ) !== false && strpos( $css, 'height:40mm' ) !== false && strpos( $css, 'height:25mm' ) !== false, 'css: A4 page, padding and the two fixed bands use the chosen millimetres' );

mods( array( 'mcupr_print_head_img' => null, 'mcupr_print_foot_img' => null ) );
ob_start(); mcupr_print_markup(); $h = ob_get_clean();
t( strpos( $h, 'print-lh' ) !== false && strpos( $h, 'print-rule' ) !== false && strpos( $h, 'aria-hidden="true"' ) !== false, 'no images: text letterhead (logo, name, rule) and it is hidden from assistive technology' );
mods( array( 'mcupr_print_text' => false ) );
ob_start(); mcupr_print_markup(); $h = ob_get_clean();
t( strpos( $h, 'print-lh' ) === false, 'text letterhead can be switched off' );
mods( array( 'mcupr_print_text' => null ) );

$head = (int) $saved['mcupr_print_head_img']; $foot = (int) $saved['mcupr_print_foot_img'];
mods( array( 'mcupr_print_head_img' => $head, 'mcupr_print_foot_img' => $foot ) );
ob_start(); mcupr_print_markup(); $h = ob_get_clean();
t( $head && $foot && substr_count( $h, 'class="print-fill"' ) === 2 && strpos( $h, 'print-lh' ) === false, 'images: the header and footer graphics replace the text letterhead' );
t( strpos( $h, 'print-bg' ) === false, 'no background unless one is set' );
mods( array( 'mcupr_print_bg_img' => $head ) );
ob_start(); mcupr_print_markup(); $h = ob_get_clean();
t( strpos( $h, 'print-bg-img' ) !== false, 'a background image is printed behind the text' );
mods( array( 'mcupr_print_bg_img' => null, 'mcupr_print_source' => false ) );
ob_start(); mcupr_print_markup(); $h = ob_get_clean();
t( strpos( $h, 'print-src' ) === false, 'the source line can be switched off' );
mods( array( 'mcupr_print_source' => null ) );
ob_start(); mcupr_print_markup(); $h = ob_get_clean();
t( strpos( $h, 'print-src' ) !== false && strpos( $h, 'print-long' ) !== false, 'the source line shows the address of the post' );

update_post_meta( $long->ID, '_mcupr_print', 'off' );
ob_start(); mcupr_print_markup(); mcupr_print_css(); $h = ob_get_clean();
t( '' === $h && ! mcupr_print_enabled(), 'a post can switch the letterhead off' );
delete_post_meta( $long->ID, '_mcupr_print' );
t( mcupr_print_enabled(), 'and on again' );
$GLOBALS['wp_query'] = new WP_Query( array( 'post_type' => 'post' ) ); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
t( ! mcupr_print_enabled(), 'listings do not get a letterhead' );

foreach ( $saved as $k => $v ) { null === $v ? remove_theme_mod( $k ) : set_theme_mod( $k, $v ); }
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
