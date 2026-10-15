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
foreach ( array( '/' => 125, '/offices/' => 80, '/student/' => 80 ) as $uri => $budget ) {
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
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
