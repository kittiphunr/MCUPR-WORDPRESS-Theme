<?php
/**
 * News inside an office: the news tab is "here" on an article of the office, the article's head and trail, the list as the same window
 * as the news of the whole site.   php php/office-news.php [site]
 */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
wp_set_current_user( 1 );
$o  = wp_insert_post( array( 'post_type' => 'mcupr_office', 'post_title' => 'Office News Test', 'post_status' => 'publish', 'post_name' => 'on-test' ) );
$dr = wp_insert_post( array( 'post_type' => 'mcupr_office', 'post_title' => 'Draft Office', 'post_status' => 'draft', 'post_name' => 'on-draft' ) );
$p1 = wp_insert_post( array( 'post_type' => 'post', 'post_title' => 'On news one', 'post_status' => 'publish', 'post_content' => 'x' ) );
$p2 = wp_insert_post( array( 'post_type' => 'post', 'post_title' => 'On news two', 'post_status' => 'publish', 'post_content' => 'x' ) );
$p3 = wp_insert_post( array( 'post_type' => 'post', 'post_title' => 'Elsewhere', 'post_status' => 'publish', 'post_content' => 'x' ) );
mcupr_office_assign( $p1, $o ); mcupr_office_assign( $p2, $o ); mcupr_office_assign( $p3, $dr );

// ---- the tabs on an article of the office
$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = new WP_Query( array( 'p' => $p1 ) );
$GLOBALS['wp_query']->the_post();
t( is_singular( 'post' ) && mcupr_office_shown() === $o, 'article: the office of the post is found and shown' );
$tabs = mcupr_office_tabs( $o );
$cur  = array();
foreach ( $tabs as $tb ) { if ( $tb[3] ) { $cur[] = $tb[0]; } }
t( array( 'news' ) === $cur, 'article: only the News tab is marked "here" (' . implode( ',', $cur ) . ')' );
// ---- the head
ob_start(); get_template_part( 'template-parts/office-article-head' ); $h = ob_get_clean();
t( false !== strpos( $h, 'of-article-head' ) && false !== strpos( $h, '--of:' ) && false !== strpos( $h, 'On news one' ) && false !== strpos( $h, '<h1' ), 'head: in the office\'s colour, with the title as the one h1' );
preg_match_all( '/<li><a href="([^"]+)">/', $h, $m );
t( count( $m[1] ) >= 2 && $m[1][0] === mcupr_office_url( $o ) && $m[1][1] === mcupr_office_url( $o, 'news' ), 'head: the way back goes office > news (' . count( $m[1] ) . ' steps)' );
t( false !== strpos( $h, 'ocat=' ), 'head: the category step links to the office news filtered by it' );
wp_reset_postdata();
// ---- a post of a draft office: no chrome for the public
wp_set_current_user( 0 );
$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = new WP_Query( array( 'p' => $p3 ) );
$GLOBALS['wp_query']->the_post();
t( 0 === mcupr_office_shown(), 'draft office: its header and article head are not shown to the public' );
wp_reset_postdata();
wp_set_current_user( 1 );
$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = new WP_Query( array( 'p' => $p3 ) );
$GLOBALS['wp_query']->the_post();
t( $dr === mcupr_office_shown(), 'draft office: the editor who may edit it still sees the header' );
wp_reset_postdata();

// ---- the list
$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = new WP_Query( array( 'post_type' => 'mcupr_office', 'p' => $o ) );
set_query_var( 'mcupr_osec', 'news' );
ob_start(); mcupr_office_render( $o, 'news' ); $l = ob_get_clean();
t( false !== strpos( $l, 'class="fb fb-solo" data-fb' ) && false !== strpos( $l, 'data-fb-items' ) && false !== strpos( $l, 'data-fb-view="gallery"' ), 'list: the same window as the site news – view switcher and items for the shared script' );
t( 2 === preg_match_all( '/<article/', $l ), 'list: only this office\'s two news are listed (' . preg_match_all( '/<article/', $l ) . ')' );
t( false === strpos( $l, 'Elsewhere' ), 'list: the news of another office is not here' );
t( false === strpos( $l, 'fb-side' ) && false === strpos( $l, 'fb-drawer' ), 'list: no side list (it is the office\'s own page)' );
t( false !== strpos( $l, 'localStorage.getItem("mcupr_view")' ), 'list: the remembered view is restored before it is drawn' );

foreach ( array( $p1, $p2, $p3 ) as $id ) { wp_delete_post( $id, true ); }
wp_delete_post( $o, true ); wp_delete_post( $dr, true );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
