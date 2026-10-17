<?php
/** Database queries per page (a guard against pages getting heavier), and the things that were made cheaper.   php php/query-budget.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
$site = rtrim( $site, '/' );
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
/** Run one page request in its own process: array( queries, status ). */
function page_cost( $site, $uri ) {
	$code = '$_SERVER["HTTP_HOST"]="localhost:8090"; $_SERVER["REQUEST_URI"]=' . var_export( $uri, true ) . '; $_SERVER["REQUEST_METHOD"]="GET"; $_SERVER["HTTP_USER_AGENT"]="Mozilla/5.0 Chrome/120"; parse_str( (string) parse_url( ' . var_export( $uri, true ) . ', PHP_URL_QUERY ), $_GET ); define( "WP_USE_THEMES", true ); ob_start(); require ' . var_export( $site . '/wp-blog-header.php', true ) . '; ob_end_clean(); global $wpdb; echo "RESULT " . (int) $wpdb->num_queries . " " . (int) http_response_code();';
	$out = (string) shell_exec( 'php -d display_errors=0 -r ' . escapeshellarg( $code ) . ' 2>/dev/null' );
	return preg_match( '/RESULT (\d+) (\d+)/', $out, $m ) ? array( (int) $m[1], (int) $m[2] ) : array( 0, 0 );
}
require $site . '/wp-load.php';
// the second request of a page is the usual one (options and indexes are saved by the first)
foreach ( array( '/' => 100, '/offices/' => 60, '/student/' => 80 ) as $uri => $budget ) {
	page_cost( $site, $uri );
	list( $n, $status ) = page_cost( $site, $uri );
	t( 200 === $status && $n > 5 && $n <= $budget, 'page ' . $uri . ': ' . $n . ' queries (budget ' . $budget . ')' );
}
// one lookup for the pages with the usual slugs, however often the footer, cookie notice and dialogs ask
$before = $wpdb->num_queries;
for ( $i = 0; $i < 12; $i++ ) { foreach ( array( 'privacy', 'cookie', 'a11y', 'sitemap', 'contact' ) as $k ) { mcupr_policy_url( $k ); } }
t( $wpdb->num_queries - $before <= 8, 'policy page addresses: 60 questions cost ' . ( $wpdb->num_queries - $before ) . ' queries' );
// the office switch and the student-only index cost nothing on a page once they are saved
mcupr_office_hidden_terms(); mcupr_aud_index();
$before = $wpdb->num_queries;
$GLOBALS['mcupr_office_hidden'] = null; unset( $GLOBALS['mcupr_office_hidden'], $GLOBALS['mcupr_aud_cache'] );
mcupr_office_hidden_terms(); mcupr_aud_index();
t( $wpdb->num_queries - $before <= 1, 'the hidden-offices list and the student-only index come from one saved option each (' . ( $wpdb->num_queries - $before ) . ' extra queries)' );
// changing an office's switch refreshes the list
$offices = mcupr_offices( 'any' );
if ( $offices ) {
	$id = $offices[0]->ID;
	$was = get_post_meta( $id, '_mcupr_of_main', true );
	update_post_meta( $id, '_mcupr_of_main', '1' );
	$with = mcupr_office_hidden_terms();
	$term = (int) mcupr_office_term_id( $id );
	t( ! in_array( $term, $with, true ), 'switching an office to "also show on the main site" updates the saved list at once' );
	'' === $was ? delete_post_meta( $id, '_mcupr_of_main' ) : update_post_meta( $id, '_mcupr_of_main', $was );
	t( in_array( $term, mcupr_office_hidden_terms(), true ), 'and switching it back does too' );
} else {
	t( true, 'no offices on this site: the switch check is skipped' );
}
// "how many news / staff / events": one saved option for all types, refreshed when a post is published or removed
delete_option( 'mcupr_pubcounts' );
mcupr_published_counts();
$before = $wpdb->num_queries;
for ( $i = 0; $i < 20; $i++ ) { foreach ( array( 'post', 'mcupr_person', 'mcupr_unit', 'mcupr_event' ) as $ty ) { mcupr_published_count( $ty ); } }
t( $wpdb->num_queries - $before <= 1, 'figure counts: 80 questions cost ' . ( $wpdb->num_queries - $before ) . ' queries' );
$n0 = mcupr_published_count( 'post' );
$tmp = wp_insert_post( array( 'post_title' => 'budget-count', 'post_status' => 'draft' ) );
t( mcupr_published_count( 'post' ) === $n0, 'figure counts: a draft is not counted' );
wp_update_post( array( 'ID' => $tmp, 'post_status' => 'publish' ) );
t( mcupr_published_count( 'post' ) === $n0 + 1, 'figure counts: publishing a post raises the number at once' );
wp_update_post( array( 'ID' => $tmp, 'post_status' => 'draft' ) );
t( mcupr_published_count( 'post' ) === $n0, 'figure counts: unpublishing lowers it' );
wp_update_post( array( 'ID' => $tmp, 'post_status' => 'publish' ) );
wp_delete_post( $tmp, true );
t( mcupr_published_count( 'post' ) === $n0, 'figure counts: deleting a published post lowers it' );
t( mcupr_published_count( 'post' ) === (int) wp_count_posts( 'post' )->publish, 'figure counts: the same number WordPress itself gives' );
// the featured images of a list come together
$ids = array();
$img = wp_insert_attachment( array( 'post_title' => 'budget-img', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), '' );
for ( $i = 0; $i < 6; $i++ ) { $ids[ $i ] = wp_insert_post( array( 'post_title' => 'budget-post-' . $i, 'post_status' => 'publish' ) ); $a = wp_insert_attachment( array( 'post_title' => 'budget-a-' . $i, 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), '' ); set_post_thumbnail( $ids[ $i ], $a ); $ids[ 'a' . $i ] = $a; }
wp_cache_flush();
$q = new WP_Query( array( 'post__in' => array_slice( $ids, 0, 6 ), 'posts_per_page' => 6, 'no_found_rows' => true, 'orderby' => 'post__in' ) );
$before = $wpdb->num_queries;
foreach ( $q->posts as $po ) { wp_get_attachment_image_src( (int) get_post_thumbnail_id( $po->ID ), 'thumbnail' ); wp_attachment_is_image( (int) get_post_thumbnail_id( $po->ID ) ); }
t( $wpdb->num_queries - $before <= 1, 'list pictures: 6 featured images cost ' . ( $wpdb->num_queries - $before ) . ' extra queries after the list (not 12)' );
foreach ( $ids as $k => $v ) { wp_delete_post( $v, true ); }
wp_delete_post( $img, true );
// the options read on every page come in one query (WordPress 6.4+; older versions work as before)
t( in_array( 'mcupr_staff', mcupr_diet_options(), true ) && in_array( 'mcupr_ics_feeds', mcupr_diet_options(), true ), 'options: the staff and calendar-feed options are in the list' );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
