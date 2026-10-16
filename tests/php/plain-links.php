<?php
/** Plain permalinks: links into the website with a pretty path become plain ones.   php php/plain-links.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$orig = get_option( 'permalink_structure' );
$set  = function ( $v ) { update_option( 'permalink_structure', $v ); $GLOBALS['wp_rewrite']->init(); };
wp_set_current_user( 1 );
$par   = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'plt parent', 'post_name' => 'plt-parent' ) );
$child = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'plt child', 'post_name' => 'plt-child', 'post_parent' => $par ) );
$work  = wp_insert_post( array( 'post_type' => 'mcupr_work', 'post_status' => 'publish', 'post_title' => 'plt work', 'post_name' => 'plt-work' ) );
$term  = wp_insert_term( 'plt cat', 'mcupr_work_cat', array( 'slug' => 'plt-cat' ) );
$H     = untrailingslashit( home_url( '/' ) );

$set( '/%postname%/' );
t( ! mcupr_plain_mode(), 'pretty permalinks: the plain mode is off (nothing is rewritten)' );
$set( '' );
t( mcupr_plain_mode(), 'plain permalinks: the plain mode is on' );
$u = mcupr_plain_url( $H . '/plt-parent/plt-child/' );
t( $u === get_permalink( $child ) && false !== strpos( $u, 'page_id=' . $child ), 'a page with a parent (…/plt-parent/plt-child/) → ?page_id=' . $child );
$u = mcupr_plain_url( $H . '/works/' );
t( false !== strpos( $u, 'post_type=mcupr_work' ) && false === strpos( $u, '/works/' ), 'the works archive (…/works/) → ?post_type=mcupr_work' );
$u = mcupr_plain_url( $H . '/works/plt-work/' );
t( $u === get_permalink( $work ) && false === strpos( $u, '/works/' ), 'one work (…/works/plt-work/) → its plain link (' . $u . ')' );
$u = mcupr_plain_url( $H . '/work-category/plt-cat/' );
t( false !== strpos( $u, 'mcupr_work_cat=plt-cat' ), 'a category of works (…/work-category/plt-cat/) → ?mcupr_work_cat=plt-cat' );
$u = mcupr_plain_url( $H . '/student/' );
t( false !== strpos( $u, 'mcupr_student=1' ), 'the student portal (…/student/) → ?mcupr_student=1' );
$u = mcupr_plain_url( $H . '/works/?lang=en#top' );
t( false !== strpos( $u, 'lang=en' ) && false !== strpos( $u, 'post_type=mcupr_work' ) && '#top' === substr( $u, -4 ), 'the language and the #anchor of a link are kept' );
$u = mcupr_plain_url( $H . '/plt-child/' );
t( $u === get_permalink( $child ), 'a short address (…/plt-child/ for …/plt-parent/plt-child/) finds the page, like WordPress does for a pretty link' );
foreach ( array( 'https://example.org/works/' => 'another website', $H . '/' => 'the home page', $H . '/?p=5' => 'a link that is plain already', $H . '/wp-content/uploads/a.pdf' => 'a file', $H . '/nothing-here-xyz/' => 'a path nothing answers to', '#top' => 'an anchor', 'mailto:a@example.org' => 'an e-mail link' ) as $in => $what ) {
	t( mcupr_plain_url( $in ) === $in, 'left alone: ' . $what );
}
$set( $orig );
wp_delete_post( $work, true ); wp_delete_post( $child, true ); wp_delete_post( $par, true );
if ( ! is_wp_error( $term ) ) { wp_delete_term( $term['term_id'], 'mcupr_work_cat' ); }
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
