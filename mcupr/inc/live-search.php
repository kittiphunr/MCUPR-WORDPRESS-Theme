<?php
/**
 * Search without leaving the page: the search bar of the home hero asks this small endpoint for the first results and
 * shows them under the bar, so there is no page to load. The endpoint is the normal search of the site (the same main
 * query, so what the audience and office rules hide from the search page is hidden here too) answered as JSON instead of
 * a page: /?s=…&mcupr_live=1 [&post_type=…] [&final=1].
 *
 * The word is written to the search statistics (Redirects & insights) only when the person really searched (Enter, the
 * button: final=1), not at every pause of the typing.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

define( 'MCUPR_LIVE_N', 6 );

function mcupr_live_requested() {
	return ! is_admin() && isset( $_GET['mcupr_live'] ) && isset( $_GET['s'] ); // phpcs:ignore WordPress.Security.NonceVerification
}

/** A short list is enough (and quick): the first results of the same query. */
add_action(
	'pre_get_posts',
	static function ( $q ) {
		if ( $q->is_main_query() && $q->is_search() && mcupr_live_requested() ) {
			$q->set( 'posts_per_page', MCUPR_LIVE_N );
			$q->set( 'paged', 1 );
		}
	},
	99
);

/** What kind of page this is, in words: news, event, page, or the name of the post type. */
function mcupr_live_kind( $post ) {
	switch ( $post->post_type ) {
		case 'post':
			return mcupr_t( 'dock_news' );
		case 'mcupr_event':
			return mcupr_t( 'dock_events' );
		case 'page':
			return mcupr_t( 'scope_pages' );
	}
	$o = get_post_type_object( $post->post_type );
	return $o && ! empty( $o->labels->singular_name ) ? $o->labels->singular_name : '';
}

add_action(
	'template_redirect',
	static function () {
		if ( ! is_search() || ! mcupr_live_requested() ) {
			return;
		}
		global $wp_query;
		$q     = get_search_query( false );
		$items = array();
		if ( '' !== trim( $q ) && mb_strlen( $q ) <= 80 ) {
			foreach ( $wp_query->posts as $p ) {
				$thumb   = get_the_post_thumbnail_url( $p, 'thumbnail' );
				$items[] = array(
					't' => html_entity_decode( wp_strip_all_tags( get_the_title( $p ) ), ENT_QUOTES, 'UTF-8' ),
					'u' => get_permalink( $p ),
					'k' => mcupr_live_kind( $p ),
					'd' => mcupr_date_text( get_post_timestamp( $p ) ),
					'i' => $thumb ? $thumb : '',
				);
			}
		}
		$total = '' === trim( $q ) ? 0 : (int) $wp_query->found_posts;
		if ( ! empty( $_GET['final'] ) && '' !== trim( $q ) && function_exists( 'mcupr_ins_log_search' ) && function_exists( 'mcupr_ins_opts' ) && ! empty( mcupr_ins_opts()['logsearch'] ) && ! mcupr_is_bot() ) { // phpcs:ignore WordPress.Security.NonceVerification
			mcupr_ins_log_search( $q, $total );
		}
		$all = remove_query_arg( array( 'mcupr_live', 'final' ) );
		nocache_headers();
		header( 'Cache-Control: private, max-age=20' );
		header( 'X-Robots-Tag: noindex' );
		wp_send_json(
			array(
				'q'     => $q,
				'total' => $total,
				'items' => $items,
				'all'   => esc_url_raw( add_query_arg( array_filter( array( 's' => $q, 'post_type' => isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '', 'lang' => isset( $_GET['lang'] ) ? sanitize_key( wp_unslash( $_GET['lang'] ) ) : '' ) ), mcupr_home_url() ) ), // phpcs:ignore WordPress.Security.NonceVerification
			)
		);
	},
	2
);
