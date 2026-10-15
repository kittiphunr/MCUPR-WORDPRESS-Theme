<?php
/** "In numbers": cleaning, the sum, the block, the widget and the shortcode.   php php/numbers.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$keys = array( 'students', 'teachers', 'faculties', 'bachelor', 'master', 'doctoral', 'asof' );
$keep = array(); foreach ( $keys as $k ) { $keep[ $k ] = get_theme_mod( 'mcupr_num_' . $k, null ); remove_theme_mod( 'mcupr_num_' . $k ); }

t( '12500' === mcupr_numbers_clean( '12,500' ) && '12500' === mcupr_numbers_clean( ' 12 500 บาท' ) && '' === mcupr_numbers_clean( 'abc' ) && '' === mcupr_numbers_clean( '' ), 'clean: only digits are kept, no digits = blank' );
t( '99999999' === mcupr_numbers_clean( '99999999999999' ), 'clean: capped' );
t( '0' === mcupr_numbers_clean( '0' ), 'clean: zero is a number' );

t( ! mcupr_numbers_has() && '' === mcupr_numbers_html(), 'empty: nothing is shown when no figure is filled in' );
set_theme_mod( 'mcupr_num_bachelor', '18,000' ); set_theme_mod( 'mcupr_num_master', '3200' ); set_theme_mod( 'mcupr_num_doctoral', '650' );
$n = mcupr_numbers();
t( 21850 === $n['students'], 'total: blank students is the sum of the three levels' );
set_theme_mod( 'mcupr_num_students', '25,000' ); set_theme_mod( 'mcupr_num_teachers', '1,250' ); set_theme_mod( 'mcupr_num_faculties', '12' ); set_theme_mod( 'mcupr_num_asof', 'ข้อมูล ณ <b>2568</b>' );
$n = mcupr_numbers();
t( 25000 === $n['students'] && 1250 === $n['teachers'] && 12 === $n['faculties'], 'total: a typed total wins' );
$h = mcupr_numbers_html();
t( false !== strpos( $h, '25,000' ) && false !== strpos( $h, '1,250' ) && false !== strpos( $h, '18,000' ) && 6 === substr_count( $h, 'nm-tile' ), 'block: six tiles with separators' );
t( false !== strpos( $h, '(82%)' ) && false !== strpos( $h, '(15%)' ) && false !== strpos( $h, '(3.0%)' ), 'block: each level shows its share of the three levels' );
t( false === strpos( $h, '<b>2568' ) && false !== strpos( $h, 'ข้อมูล ณ' ), 'block: the note is escaped' );
t( false !== strpos( mcupr_numbers_html( true ), 'nm-compact' ), 'block: a compact version for sidebars' );
t( $h === do_shortcode( '[mcupr_numbers]' ), 'shortcode: [mcupr_numbers] prints the block' );
remove_theme_mod( 'mcupr_num_bachelor' ); remove_theme_mod( 'mcupr_num_master' ); remove_theme_mod( 'mcupr_num_doctoral' );
$h2 = mcupr_numbers_html();
t( 3 === substr_count( $h2, 'nm-tile' ) && false === strpos( $h2, 'nm-sub' ), 'block: no levels, no level heading' );
t( class_exists( 'MCUPR_Numbers_Widget' ), 'widget: registered class' );
ob_start();
the_widget( 'MCUPR_Numbers_Widget', array( 'title' => 'ตัวเลข' ), array( 'before_widget' => '<section>', 'after_widget' => '</section>', 'before_title' => '<h2>', 'after_title' => '</h2>' ) );
$w = ob_get_clean();
t( false !== strpos( $w, '<h2>ตัวเลข</h2>' ) && false !== strpos( $w, 'nm-compact' ), 'widget: title and compact figures' );
foreach ( $keys as $k ) { remove_theme_mod( 'mcupr_num_' . $k ); }
ob_start(); the_widget( 'MCUPR_Numbers_Widget', array( 'title' => 'x' ) ); t( '' === trim( ob_get_clean() ), 'widget: prints nothing when empty' );
foreach ( $keep as $k => $v ) { if ( null !== $v ) { set_theme_mod( 'mcupr_num_' . $k, $v ); } }
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
