<?php
/**
 * Fewer database queries on a page (4B), without changing what a page shows:
 *
 *  - the few theme options that are read on every page but saved without "autoload" come in one query
 *    (WordPress 6.4+: wp_prime_option_caches), instead of one query each;
 *  - "how many news / staff / events …" for the figures strip is counted once for all types and kept in one autoloaded option,
 *    which is dropped when a post is published, unpublished or deleted – instead of one COUNT query per figure per page;
 *  - the pictures (featured images) of the posts a list query returned are loaded together, instead of one query per picture.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** Options read on (nearly) every page that are not autoloaded. Filterable. */
function mcupr_diet_options() {
	return apply_filters( 'mcupr_diet_options', array( 'mcupr_staff', 'mcupr_ics_feeds', 'mcupr_daily_views', 'site_logo' ) );
}
add_action(
	'after_setup_theme',
	static function () {
		if ( function_exists( 'wp_prime_option_caches' ) && ! is_admin() ) {
			wp_prime_option_caches( mcupr_diet_options() );
		}
	},
	1
);

/* ------------------------------------------------------------------ published posts per type */

/** array( post type => published posts ), from one saved option; counted again after any post changes. */
function mcupr_published_counts() {
	$c = get_option( 'mcupr_pubcounts', null );
	if ( is_array( $c ) ) {
		return $c;
	}
	global $wpdb;
	$c = array();
	foreach ( (array) $wpdb->get_results( "SELECT post_type, COUNT(*) AS n FROM {$wpdb->posts} WHERE post_status = 'publish' GROUP BY post_type" ) as $r ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$c[ $r->post_type ] = (int) $r->n;
	}
	update_option( 'mcupr_pubcounts', $c, true );
	return $c;
}
function mcupr_published_count( $type ) {
	$c = mcupr_published_counts();
	return isset( $c[ $type ] ) ? (int) $c[ $type ] : 0;
}
add_action(
	'transition_post_status',
	static function ( $new, $old ) {
		if ( $new !== $old && ( 'publish' === $new || 'publish' === $old ) ) {
			delete_option( 'mcupr_pubcounts' );
		}
	},
	10,
	2
);
add_action(
	'deleted_post',
	static function () {
		delete_option( 'mcupr_pubcounts' );
	}
);

/* ------------------------------------------------------------------ the pictures of a list */

/** After a list query: load the featured images of its posts with two queries (posts + their data) instead of two per picture. */
add_filter(
	'the_posts',
	static function ( $posts, $q ) {
		if ( is_admin() || ! is_array( $posts ) || count( $posts ) < 2 || false === $q->get( 'update_post_meta_cache' ) ) {
			return $posts;
		}
		$ids = array();
		foreach ( $posts as $p ) {
			if ( $p instanceof WP_Post && 'attachment' !== $p->post_type && false !== wp_cache_get( $p->ID, 'post_meta' ) ) {
				$t = (int) get_post_meta( $p->ID, '_thumbnail_id', true );
				if ( $t ) {
					$ids[ $t ] = $t;
				}
			}
		}
		if ( $ids ) {
			_prime_post_caches( array_slice( array_values( $ids ), 0, 100 ), false, true );
		}
		return $posts;
	},
	20,
	2
);
