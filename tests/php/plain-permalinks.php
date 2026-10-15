<?php
/** Student portal and office section links still work with plain permalinks.   php php/plain-permalinks.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$home = home_url( '/' );
add_filter( 'pre_option_permalink_structure', static function () { return ''; } );
t( mcupr_student_portal_url() === $home . '?mcupr_student=1', 'plain: the portal link uses the query variable (' . mcupr_student_portal_url() . ')' );
t( false !== strpos( mcupr_student_portal_url( array( 'x' => 'y' ) ), 'mcupr_student=1' ) && false !== strpos( mcupr_student_portal_url( array( 'x' => 'y' ) ), 'x=y' ), 'plain: extra arguments are kept' );
$id = wp_insert_post( array( 'post_type' => 'mcupr_office', 'post_title' => 'Plain test', 'post_status' => 'publish' ) );
$u = mcupr_office_url( $id, 'news', 2 );
t( false !== strpos( $u, 'mcupr_osec=news' ) && false !== strpos( $u, 'mcupr_opage=2' ) && false === strpos( $u, '/news/' ), 'plain: the office section link uses query variables (' . $u . ')' );
t( false === strpos( mcupr_office_url( $id, 'news' ), 'mcupr_opage' ), 'plain: page 1 adds no page argument' );
remove_all_filters( 'pre_option_permalink_structure' );
add_filter( 'pre_option_permalink_structure', static function () { return '/%postname%/'; } );
t( mcupr_student_portal_url() === home_url( '/student/' ), 'pretty: the portal link is /student/' );
t( false !== strpos( mcupr_office_url( $id, 'news', 2 ), '/news/page/2/' ), 'pretty: the office section link is a path' );
wp_delete_post( $id, true );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
